<?php
/**
 * rpgcalendar – Generator
 *
 * The ONLY place that performs outbound HTTP requests. Fetches real weather
 * (Open-Meteo) and real public holidays (Nager.Date) for a real-world location
 * and materialises them as editable "auto" rows in the plugin tables.
 *
 * All requests go through MyBB's fetch_remote_file() and are defensive: a
 * failed/empty/invalid response never breaks the caller, it just yields fewer
 * rows and an error string. Nothing here runs on a normal page view – only on
 * an explicit ACP "generate" action.
 *
 * Time mapping (confirmed design): the in-game calendar date equals the real
 * date. Past days come from the archive API, the near window (today .. +16d)
 * from the forecast API, everything further out from climate normals.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

class Rpgcalendar_Generator
{
    const GEOCODE_URL  = 'https://geocoding-api.open-meteo.com/v1/search';
    const ARCHIVE_URL  = 'https://archive-api.open-meteo.com/v1/archive';
    const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';
    const CLIMATE_URL  = 'https://climate-api.open-meteo.com/v1/climate';
    const HOLIDAY_URL  = 'https://date.nager.at/api/v3/PublicHolidays';
    const WIKI_API     = 'https://%s.wikipedia.org/w/api.php';

    /** Descriptive User-Agent required by the Wikimedia APIs (T400119). */
    const USER_AGENT   = 'rpgcalendar-MyBB/1.2 (+https://github.com/pand0rica/rpgcalendar)';

    /** Climate model used for the long-range fallback. */
    const CLIMATE_MODEL = 'MRI_AGCM3_2_S';

    /** @var Rpgcalendar_Database */
    private $database;

    /** @var string[] Human-readable errors collected during a generate run. */
    private $errors = [];

    /** @var array Wikipedia lookups cached per generate run ("lang|name" => result|null). */
    private $wikiCache = [];

    public function __construct($database)
    {
        $this->database = $database;
    }

    /** @return string[] */
    public function getErrors(): array
    {
        return $this->errors;
    }

    // -------------------------------------------------------------------
    // WMO weather code → condition slug (image base name)
    // -------------------------------------------------------------------

    public static function weatherSlug(int $code): string
    {
        if ($code === 0) {
            return 'clear';
        }
        if ($code >= 1 && $code <= 3) {
            return 'cloudy';
        }
        if ($code === 45 || $code === 48) {
            return 'fog';
        }
        if (($code >= 51 && $code <= 67) || ($code >= 80 && $code <= 82)) {
            return 'rain';
        }
        if (($code >= 71 && $code <= 77) || $code === 85 || $code === 86) {
            return 'snow';
        }
        if ($code >= 95 && $code <= 99) {
            return 'thunder';
        }

        return 'cloudy';
    }

    /** The WMO weather interpretation codes Open-Meteo emits. @return int[] */
    public static function weatherCodes(): array
    {
        return [
            0, 1, 2, 3, 45, 48, 51, 53, 55, 56, 57, 61, 63, 65, 66, 67,
            71, 73, 75, 77, 80, 81, 82, 85, 86, 95, 96, 99,
        ];
    }

    /**
     * All condition slugs the generator can emit and that the UI offers: the raw
     * WMO codes (one image per code, e.g. "61") plus the derived "windy".
     *
     * @return string[]
     */
    public static function conditionSlugs(): array
    {
        $slugs = array_map('strval', self::weatherCodes());
        $slugs[] = 'windy';

        return $slugs;
    }

    /** Windy override threshold (km/h) from the setting, default 40. */
    private static function windyThreshold(): float
    {
        global $mybb;

        $t = (float)($mybb->settings['rpgcalendar_windy_threshold'] ?? 40);

        return $t > 0 ? $t : 40.0;
    }

    // -------------------------------------------------------------------
    // Public entry point
    // -------------------------------------------------------------------

    /**
     * (Re)generates weather + holidays for one real location within a key range.
     * Replaces existing 'auto' rows in that range, leaving 'manual' rows intact.
     *
     * @return array ['weather' => int, 'holidays' => int, 'errors' => string[]]
     */
    public function generateForLocation(int $locid, int $from_key, int $to_key): array
    {
        $this->errors = [];

        $location = $this->database->getLocation($locid);
        if (!$location) {
            $this->errors[] = 'location-not-found';

            return ['weather' => 0, 'holidays' => 0, 'errors' => $this->errors];
        }
        if ((int)$location['type'] !== 1) {
            $this->errors[] = 'location-not-real';

            return ['weather' => 0, 'holidays' => 0, 'errors' => $this->errors];
        }
        if ($from_key > $to_key) {
            list($from_key, $to_key) = [$to_key, $from_key];
        }

        // Ensure coordinates (geocode from the city name if missing).
        $lat = (float)$location['lat'];
        $lon = (float)$location['lon'];
        if ($lat === 0.0 && $lon === 0.0) {
            $geo = $this->geocode((string)$location['city']);
            if ($geo === null) {
                $this->errors[] = 'geocode-failed';
            } else {
                $lat = $geo['lat'];
                $lon = $geo['lon'];
                $this->database->saveLocation(['lat' => $lat, 'lon' => $lon], $locid, true);
            }
        }

        $weather_count = 0;
        if ($lat !== 0.0 || $lon !== 0.0) {
            $days = $this->fetchWeather($lat, $lon, $from_key, $to_key);
            $this->database->deleteAutoWeatherInRange($locid, $from_key, $to_key);
            foreach ($days as $d) {
                $this->database->saveWeather([
                    'locid'       => $locid,
                    'source'      => 'auto',
                    'wcondition'  => $d['slug'],
                    'start_year'  => $d['y'], 'start_month' => $d['m'], 'start_day' => $d['d'],
                    'end_year'    => $d['y'], 'end_month'   => $d['m'], 'end_day'   => $d['d'],
                    'yearly'      => 0,
                    'note'        => $d['note'],
                    'visible'     => 1,
                ]);
                $weather_count++;
            }
        }

        $holiday_count = 0;
        $country = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$location['country']));
        if (strlen($country) === 2) {
            $year_from = intdiv($from_key, 10000);
            $year_to   = intdiv($to_key, 10000);
            $this->database->deleteAutoHolidayInRange($locid, $from_key, $to_key);
            for ($y = $year_from; $y <= $year_to; $y++) {
                foreach ($this->fetchHolidays($country, $y) as $h) {
                    $key = Rpgcalendar_Engine::dateKey($h['y'], $h['m'], $h['d']);
                    if ($key < $from_key || $key > $to_key) {
                        continue;
                    }
                    $this->database->saveHoliday([
                        'locid'       => $locid,
                        'source'      => 'auto',
                        'title'       => $h['title'],
                        'description' => $h['description'],
                        'url'         => $h['url'] ?? '',
                        'start_year'  => $h['y'], 'start_month' => $h['m'], 'start_day' => $h['d'],
                        'end_year'    => $h['y'], 'end_month'   => $h['m'], 'end_day'   => $h['d'],
                        'yearly'      => 0,
                        'visible'     => 1,
                    ]);
                    $holiday_count++;
                }
            }
        } else {
            $this->errors[] = 'no-country';
        }

        return ['weather' => $weather_count, 'holidays' => $holiday_count, 'errors' => $this->errors];
    }

    // -------------------------------------------------------------------
    // Geocoding
    // -------------------------------------------------------------------

    /** @return array|null ['lat' => float, 'lon' => float] */
    public function geocode(string $city): ?array
    {
        $city = trim($city);
        if ($city === '') {
            return null;
        }

        $url  = self::GEOCODE_URL . '?name=' . rawurlencode($city) . '&count=1&format=json';
        $data = $this->getJson($url);
        if (!is_array($data) || empty($data['results'][0])) {
            return null;
        }

        $first = $data['results'][0];
        if (!isset($first['latitude'], $first['longitude'])) {
            return null;
        }

        return ['lat' => (float)$first['latitude'], 'lon' => (float)$first['longitude']];
    }

    // -------------------------------------------------------------------
    // Weather
    // -------------------------------------------------------------------

    /**
     * Fetches per-day weather for a key range, picking archive/forecast/climate
     * per contiguous sub-window relative to the real today.
     *
     * @return array[] each ['y','m','d','slug','note']
     */
    public function fetchWeather(float $lat, float $lon, int $from_key, int $to_key): array
    {
        $today_n = Rpgcalendar_Engine::dayNumber((int)my_date('Y'), (int)my_date('n'), (int)my_date('j'));

        $from = self::keyToDayNumber($from_key);
        $to   = self::keyToDayNumber($to_key);
        if ($from === null || $to === null) {
            $this->errors[] = 'weather-range-invalid';

            return [];
        }

        // Sub-window boundaries (day numbers). Archive lags a few days, so the
        // forecast window absorbs the last ~5 days plus the +16d outlook.
        $near_start = $today_n - 5;
        $near_end   = $today_n + 16;

        $result = [];

        // Archive: everything before the near window.
        $a_from = $from;
        $a_to   = min($to, $near_start - 1);
        if ($a_from <= $a_to) {
            $result = array_merge($result, $this->fetchDailyRange(self::ARCHIVE_URL, $lat, $lon, $a_from, $a_to));
        }

        // Forecast: the near window.
        $f_from = max($from, $near_start);
        $f_to   = min($to, $near_end);
        if ($f_from <= $f_to) {
            $result = array_merge($result, $this->fetchDailyRange(self::FORECAST_URL, $lat, $lon, $f_from, $f_to));
        }

        // Climate normals: everything beyond the near window.
        $c_from = max($from, $near_end + 1);
        $c_to   = $to;
        if ($c_from <= $c_to) {
            $result = array_merge($result, $this->fetchClimateRange($lat, $lon, $c_from, $c_to));
        }

        return $result;
    }

    /** Archive/forecast share the same daily=weathercode,temp shape. */
    private function fetchDailyRange(string $base, float $lat, float $lon, int $from_n, int $to_n): array
    {
        $start = self::dayNumberToDate($from_n);
        $end   = self::dayNumberToDate($to_n);
        if ($start === null || $end === null) {
            return [];
        }

        $url = $base . '?latitude=' . rawurlencode((string)$lat)
            . '&longitude=' . rawurlencode((string)$lon)
            . '&start_date=' . $start . '&end_date=' . $end
            . '&daily=weathercode,temperature_2m_max,temperature_2m_min,windspeed_10m_max&timezone=UTC';

        $data = $this->getJson($url);
        if (!is_array($data) || empty($data['daily']['time'])) {
            $this->errors[] = 'weather-fetch-failed';

            return [];
        }

        $daily = $data['daily'];
        $codes = isset($daily['weathercode']) && is_array($daily['weathercode']) ? $daily['weathercode'] : [];
        $tmax  = isset($daily['temperature_2m_max']) && is_array($daily['temperature_2m_max']) ? $daily['temperature_2m_max'] : [];
        $tmin  = isset($daily['temperature_2m_min']) && is_array($daily['temperature_2m_min']) ? $daily['temperature_2m_min'] : [];
        $wind  = isset($daily['windspeed_10m_max']) && is_array($daily['windspeed_10m_max']) ? $daily['windspeed_10m_max'] : [];
        $windy = self::windyThreshold();

        $out = [];
        foreach ($daily['time'] as $i => $iso) {
            $ymd = self::parseIsoDate((string)$iso);
            if ($ymd === null) {
                continue;
            }
            // One slug per WMO code (image "<code>.svg"); strong wind overrides it.
            $slug = (string)(isset($codes[$i]) ? (int)$codes[$i] : 0);
            if (isset($wind[$i]) && is_numeric($wind[$i]) && (float)$wind[$i] >= $windy) {
                $slug = 'windy';
            }
            $out[] = [
                'y'    => $ymd['y'], 'm' => $ymd['m'], 'd' => $ymd['d'],
                'slug' => $slug,
                'note' => self::formatTempNote($tmin[$i] ?? null, $tmax[$i] ?? null),
            ];
        }

        return $out;
    }

    /**
     * Long-range fallback: monthly climate normals mapped to a coarse condition
     * that is applied to every day of the covered months.
     */
    private function fetchClimateRange(float $lat, float $lon, int $from_n, int $to_n): array
    {
        $start = self::dayNumberToDate($from_n);
        $end   = self::dayNumberToDate($to_n);
        if ($start === null || $end === null) {
            return [];
        }

        $url = self::CLIMATE_URL . '?latitude=' . rawurlencode((string)$lat)
            . '&longitude=' . rawurlencode((string)$lon)
            . '&start_date=' . $start . '&end_date=' . $end
            . '&models=' . self::CLIMATE_MODEL
            . '&daily=temperature_2m_mean,precipitation_sum&timezone=UTC';

        $data = $this->getJson($url);
        if (!is_array($data) || empty($data['daily']['time'])) {
            $this->errors[] = 'weather-climate-failed';

            return [];
        }

        $daily  = $data['daily'];
        $means  = isset($daily['temperature_2m_mean']) && is_array($daily['temperature_2m_mean']) ? $daily['temperature_2m_mean'] : [];
        $precip = isset($daily['precipitation_sum']) && is_array($daily['precipitation_sum']) ? $daily['precipitation_sum'] : [];

        $out = [];
        foreach ($daily['time'] as $i => $iso) {
            $ymd = self::parseIsoDate((string)$iso);
            if ($ymd === null) {
                continue;
            }
            $mean = isset($means[$i]) ? (float)$means[$i] : null;
            $rain = isset($precip[$i]) ? (float)$precip[$i] : 0.0;
            $out[] = [
                'y'    => $ymd['y'], 'm' => $ymd['m'], 'd' => $ymd['d'],
                'slug' => self::climateSlug($mean, $rain),
                'note' => ($mean !== null) ? round($mean) . '°' : '',
            ];
        }

        return $out;
    }

    /** Coarse WMO code from a daily mean temperature and precipitation sum. */
    private static function climateSlug(?float $mean, float $precip): string
    {
        if ($precip >= 5.0) {
            if ($mean !== null && $mean <= 0.5) {
                return '73'; // moderate snow
            }

            return '63'; // moderate rain
        }
        if ($precip >= 0.5) {
            return '3'; // overcast
        }

        return '0'; // clear sky
    }

    private static function formatTempNote($min, $max): string
    {
        $has_min = ($min !== null && is_numeric($min));
        $has_max = ($max !== null && is_numeric($max));
        if ($has_min && $has_max) {
            return round((float)$min) . '° / ' . round((float)$max) . '°';
        }
        if ($has_max) {
            return round((float)$max) . '°';
        }
        if ($has_min) {
            return round((float)$min) . '°';
        }

        return '';
    }

    // -------------------------------------------------------------------
    // Holidays (Nager.Date)
    // -------------------------------------------------------------------

    /**
     * Public holidays for one country and year.
     *
     * @return array[] each ['y','m','d','title','description','url']
     */
    public function fetchHolidays(string $country, int $year): array
    {
        $country = strtoupper(preg_replace('/[^A-Za-z]/', '', $country));
        if (strlen($country) !== 2 || $year < 1 || $year > 9999) {
            return [];
        }

        $url  = self::HOLIDAY_URL . '/' . (int)$year . '/' . $country;
        $data = $this->getJson($url);
        if (!is_array($data)) {
            $this->errors[] = 'holiday-fetch-failed';

            return [];
        }

        $lang    = $this->wikiLang();
        $wiki_on = $this->wikiEnabled();

        $out = [];
        foreach ($data as $entry) {
            if (!is_array($entry) || empty($entry['date'])) {
                continue;
            }
            $ymd = self::parseIsoDate((string)$entry['date']);
            if ($ymd === null) {
                continue;
            }
            $local = isset($entry['localName']) ? trim((string)$entry['localName']) : '';
            $name  = isset($entry['name']) ? trim((string)$entry['name']) : '';
            $title = ($local !== '') ? $local : $name;
            if ($title === '') {
                continue;
            }

            // Translate the name and fetch a short explanation + article link from
            // Wikipedia in the board's language (unless disabled by setting). The
            // English name is the best search query.
            $desc = '';
            $link = '';
            if ($wiki_on) {
                $query = ($name !== '') ? $name : $title;
                $wiki  = $this->wikiLookup($lang, $query);
                if ($wiki !== null) {
                    if ($wiki['title'] !== '') {
                        $title = $wiki['title'];
                    }
                    if ($wiki['extract'] !== '') {
                        $desc = self::trimExtract($wiki['extract']);
                    }
                    $link = (string)($wiki['url'] ?? '');
                }
            }

            // Public = statutory day off (from Nager's "types").
            $types  = isset($entry['types']) && is_array($entry['types']) ? $entry['types'] : [];
            $public = in_array('Public', $types, true);
            $line   = self::workfreeLine($lang, $public);
            $desc   = trim($desc . ($desc !== '' ? "\n\n" : '') . $line);

            $out[] = [
                'y' => $ymd['y'], 'm' => $ymd['m'], 'd' => $ymd['d'],
                'title' => my_substr($title, 0, 200),
                'description' => $desc,
                'url' => my_substr($link, 0, 255),
            ];
        }

        return $out;
    }

    // -------------------------------------------------------------------
    // Wikipedia enrichment (localized name + short description)
    // -------------------------------------------------------------------

    /** Wikipedia language edition derived from the board's default language. */
    private function wikiLang(): string
    {
        global $mybb;

        return self::wikiLangFromBoard((string)($mybb->settings['bblanguage'] ?? 'english'));
    }

    /** Whether generated holidays should be enriched from Wikipedia (ACP setting). */
    private function wikiEnabled(): bool
    {
        global $mybb;

        // Default on when the setting is not present yet (fresh upgrade).
        return !isset($mybb->settings['rpgcalendar_holiday_wiki'])
            || (int)$mybb->settings['rpgcalendar_holiday_wiki'] === 1;
    }

    /** Maps a MyBB language pack name (e.g. "deutsch_du") to a Wikipedia code. */
    public static function wikiLangFromBoard(string $bblanguage): string
    {
        $b = strtolower(trim($bblanguage));

        static $map = [
            'deutsch'    => 'de', 'english'  => 'en', 'francais' => 'fr',
            'espanol'    => 'es', 'italiano' => 'it', 'nederlands' => 'nl',
            'portugues'  => 'pt', 'polski'   => 'pl', 'russian'  => 'ru',
            'svenska'    => 'sv', 'suomi'    => 'fi', 'turkce'   => 'tr',
            'cesky'      => 'cs', 'dansk'    => 'da', 'norsk'    => 'no',
        ];
        foreach ($map as $prefix => $code) {
            if (strpos($b, $prefix) === 0) {
                return $code;
            }
        }

        $b = preg_replace('/[^a-z]/', '', $b);

        return (strlen($b) >= 2) ? substr($b, 0, 2) : 'en';
    }

    /**
     * Resolves a holiday name to a localized title + intro extract. Cached per
     * run. For non-English targets it searches the ENGLISH Wikipedia (where the
     * English holiday name matches cleanly), follows the langlink to the target
     * language and fetches that page's extract – far more accurate than searching
     * the target Wikipedia with an English query. Returns null if no target-
     * language article exists (caller then keeps the original name).
     *
     * @return array|null ['title','extract','url']
     */
    private function wikiLookup(string $lang, string $query): ?array
    {
        $query = trim($query);
        if ($query === '' || !preg_match('/^[a-z]{2,3}$/', $lang)) {
            return null;
        }
        $key = $lang . '|' . strtolower($query);
        if (array_key_exists($key, $this->wikiCache)) {
            return $this->wikiCache[$key];
        }

        $res = ($lang === 'en')
            ? $this->wikiExtractBySearch('en', $query)
            : $this->wikiViaEnglish($lang, $query);

        $this->wikiCache[$key] = $res;

        return $res;
    }

    /** Top search match on a Wikipedia edition + its intro extract. @return array|null */
    private function wikiExtractBySearch(string $lang, string $query): ?array
    {
        $url = sprintf(self::WIKI_API, $lang)
            . '?action=query&format=json&redirects=1&prop=extracts|info&inprop=url&exintro=1&explaintext=1'
            . '&generator=search&gsrnamespace=0&gsrlimit=1&gsrsearch=' . rawurlencode($query);

        $data = $this->getJson($url);
        if (!is_array($data) || empty($data['query']['pages']) || !is_array($data['query']['pages'])) {
            return null;
        }
        $page = reset($data['query']['pages']);
        if (!is_array($page) || !isset($page['title'])) {
            return null;
        }

        return [
            'title'   => trim((string)$page['title']),
            'extract' => isset($page['extract']) ? trim((string)$page['extract']) : '',
            'url'     => isset($page['fullurl']) ? trim((string)$page['fullurl']) : '',
        ];
    }

    /** English article (by search) -> target-language langlink -> target extract. @return array|null */
    private function wikiViaEnglish(string $lang, string $query): ?array
    {
        $url = 'https://en.wikipedia.org/w/api.php'
            . '?action=query&format=json&redirects=1&generator=search&gsrnamespace=0&gsrlimit=1'
            . '&gsrsearch=' . rawurlencode($query)
            . '&prop=langlinks&lllang=' . $lang . '&lllimit=1';

        $data = $this->getJson($url);
        if (!is_array($data) || empty($data['query']['pages']) || !is_array($data['query']['pages'])) {
            return null;
        }
        $page = reset($data['query']['pages']);
        if (!is_array($page) || empty($page['langlinks'][0]['*'])) {
            return null; // no article in the target language
        }
        $target_title = trim((string)$page['langlinks'][0]['*']);
        if ($target_title === '') {
            return null;
        }

        $url2 = sprintf(self::WIKI_API, $lang)
            . '?action=query&format=json&redirects=1&prop=extracts|info&inprop=url&exintro=1&explaintext=1'
            . '&titles=' . rawurlencode($target_title);
        $data2   = $this->getJson($url2);
        $extract = '';
        $link    = '';
        if (is_array($data2) && !empty($data2['query']['pages']) && is_array($data2['query']['pages'])) {
            $p2 = reset($data2['query']['pages']);
            if (is_array($p2)) {
                $extract = isset($p2['extract']) ? trim((string)$p2['extract']) : '';
                $link    = isset($p2['fullurl']) ? trim((string)$p2['fullurl']) : '';
            }
        }

        return ['title' => $target_title, 'extract' => $extract, 'url' => $link];
    }

    /** Shortens a Wikipedia extract to a couple of sentences (~600 chars). */
    private static function trimExtract(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        if (my_strlen($s) <= 600) {
            return $s;
        }
        $cut = my_substr($s, 0, 600);
        $dot = strrpos($cut, '. ');
        if ($dot !== false && $dot > 200) {
            $cut = substr($cut, 0, $dot + 1);
        }

        return trim($cut) . ' …';
    }

    /** One-line "is it a day off" note in the target language (falls back to English). */
    private static function workfreeLine(string $lang, bool $public): string
    {
        static $t = [
            'de' => ['Gesetzlicher Feiertag (arbeitsfrei).', 'Kein gesetzlicher Feiertag (regulärer Arbeitstag).'],
            'en' => ['Public holiday (a day off).', 'Not a public holiday (a regular working day).'],
            'fr' => ['Jour férié (chômé).', 'Pas un jour férié (jour ouvrable).'],
        ];
        $m = $t[$lang] ?? $t['en'];

        return $public ? $m[0] : $m[1];
    }

    // -------------------------------------------------------------------
    // HTTP + date helpers
    // -------------------------------------------------------------------

    /** GET a URL body and JSON-decode it. */
    private function getJson(string $url)
    {
        $body = $this->httpGet($url);
        if ($body === null || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }

    /**
     * Fetches a URL body. Prefers cURL so we can send a descriptive
     * User-Agent: the Wikimedia APIs reject requests without one
     * (see phabricator.wikimedia.org/T400119), and MyBB's fetch_remote_file()
     * does not let us set request headers. Falls back to fetch_remote_file()
     * when cURL is unavailable (fine for Open-Meteo / Nager.Date).
     */
    private function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body !== false && $body !== '' && $code >= 200 && $code < 300) {
                return (string)$body;
            }
            // fall through and try fetch_remote_file() on any failure
        }

        if (!function_exists('fetch_remote_file')) {
            require_once MYBB_ROOT . 'inc/functions.php';
        }
        $body = @fetch_remote_file($url);

        return ($body === false || $body === '') ? null : (string)$body;
    }

    /** @return int|null day number for a positive-year date key */
    private static function keyToDayNumber(int $key): ?int
    {
        $y = intdiv($key, 10000);
        $rest = $key - $y * 10000;
        $m = intdiv($rest, 100);
        $d = $rest - $m * 100;
        if ($y < 1 || $m < 1 || $m > 12 || $d < 1 || $d > Rpgcalendar_Engine::daysInMonth($y, $m)) {
            return null;
        }

        return Rpgcalendar_Engine::dayNumber($y, $m, $d);
    }

    /** @return string|null 'YYYY-MM-DD' (positive years only) */
    private static function dayNumberToDate(int $n): ?string
    {
        $ymd = Rpgcalendar_Engine::fromDayNumber($n);
        if ($ymd['y'] < 1 || $ymd['y'] > 9999) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $ymd['y'], $ymd['m'], $ymd['d']);
    }

    /** @return array|null ['y','m','d'] from an ISO 'YYYY-MM-DD' prefix */
    private static function parseIsoDate(string $iso): ?array
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $mm)) {
            return null;
        }

        return ['y' => (int)$mm[1], 'm' => (int)$mm[2], 'd' => (int)$mm[3]];
    }
}
