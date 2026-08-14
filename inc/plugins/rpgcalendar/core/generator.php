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

    /** Climate model used for the long-range fallback. */
    const CLIMATE_MODEL = 'MRI_AGCM3_2_S';

    /** @var Rpgcalendar_Database */
    private $database;

    /** @var string[] Human-readable errors collected during a generate run. */
    private $errors = [];

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

    /** All condition slugs the generator can emit (for docs/UI). @return string[] */
    public static function conditionSlugs(): array
    {
        return ['clear', 'cloudy', 'fog', 'rain', 'snow', 'thunder'];
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
            . '&daily=weathercode,temperature_2m_max,temperature_2m_min&timezone=UTC';

        $data = $this->getJson($url);
        if (!is_array($data) || empty($data['daily']['time'])) {
            $this->errors[] = 'weather-fetch-failed';

            return [];
        }

        $daily = $data['daily'];
        $codes = isset($daily['weathercode']) && is_array($daily['weathercode']) ? $daily['weathercode'] : [];
        $tmax  = isset($daily['temperature_2m_max']) && is_array($daily['temperature_2m_max']) ? $daily['temperature_2m_max'] : [];
        $tmin  = isset($daily['temperature_2m_min']) && is_array($daily['temperature_2m_min']) ? $daily['temperature_2m_min'] : [];

        $out = [];
        foreach ($daily['time'] as $i => $iso) {
            $ymd = self::parseIsoDate((string)$iso);
            if ($ymd === null) {
                continue;
            }
            $code = isset($codes[$i]) ? (int)$codes[$i] : 0;
            $out[] = [
                'y'    => $ymd['y'], 'm' => $ymd['m'], 'd' => $ymd['d'],
                'slug' => self::weatherSlug($code),
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

    /** Coarse condition from a daily mean temperature and precipitation sum. */
    private static function climateSlug(?float $mean, float $precip): string
    {
        if ($precip >= 5.0) {
            if ($mean !== null && $mean <= 0.5) {
                return 'snow';
            }

            return 'rain';
        }
        if ($precip >= 0.5) {
            return 'cloudy';
        }

        return 'clear';
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
     * @return array[] each ['y','m','d','title','description']
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

        $out = [];
        foreach ($data as $entry) {
            if (!is_array($entry) || empty($entry['date'])) {
                continue;
            }
            $ymd = self::parseIsoDate((string)$entry['date']);
            if ($ymd === null) {
                continue;
            }
            // Prefer localName (the holiday's name in the country's own language,
            // e.g. German for DE); the English "name" is intentionally not stored
            // so generated holidays stay in the local/MyBB language.
            $local = isset($entry['localName']) ? trim((string)$entry['localName']) : '';
            $name  = isset($entry['name']) ? trim((string)$entry['name']) : '';
            $title = ($local !== '') ? $local : $name;
            if ($title === '') {
                continue;
            }
            $out[] = [
                'y' => $ymd['y'], 'm' => $ymd['m'], 'd' => $ymd['d'],
                'title' => my_substr($title, 0, 200),
                'description' => '',
            ];
        }

        return $out;
    }

    // -------------------------------------------------------------------
    // HTTP + date helpers
    // -------------------------------------------------------------------

    /** GET a URL via MyBB's fetch_remote_file() and JSON-decode it. */
    private function getJson(string $url)
    {
        if (!function_exists('fetch_remote_file')) {
            require_once MYBB_ROOT . 'inc/functions.php';
        }

        $body = @fetch_remote_file($url);
        if ($body === false || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
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
