<?php
/**
 * rpgcalendar – Engine
 *
 * Calendar mathematics and month-view assembly. All date math is pure integer
 * arithmetic on a proleptic Gregorian calendar (astronomical year numbering,
 * year 0 exists), so any SMALLINT year works – including negative years.
 * No mktime/gmmktime/DateTime in this class.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

class Rpgcalendar_Engine
{
    const REPEAT_NONE    = 0;
    const REPEAT_WEEKLY  = 1;
    const REPEAT_MONTHLY = 2;
    const REPEAT_YEARLY  = 3;

    /** @var Rpgcalendar_Database */
    private $database;

    /** @var Rpgcalendar_NativeAdapter|null */
    private $native_adapter;

    public function __construct($database, $native_adapter = null)
    {
        $this->database       = $database;
        $this->native_adapter = $native_adapter;
    }

    // -------------------------------------------------------------------
    // Calendar math (static, pure integer)
    // -------------------------------------------------------------------

    /**
     * Sortable date key: y*10000 + m*100 + d. Strictly monotonic for all
     * years including negative ones.
     */
    public static function dateKey(int $y, int $m, int $d): int
    {
        return $y * 10000 + $m * 100 + $d;
    }

    public static function isLeapYear(int $y): bool
    {
        return ($y % 4 === 0 && ($y % 100 !== 0 || $y % 400 === 0));
    }

    public static function daysInMonth(int $y, int $m): int
    {
        static $days = [1 => 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        if ($m < 1 || $m > 12) {
            return 0;
        }
        if ($m === 2 && self::isLeapYear($y)) {
            return 29;
        }

        return $days[$m];
    }

    /**
     * Days since 1970-01-01 (negative for earlier dates). Days-from-civil
     * algorithm; valid for the entire SMALLINT year range.
     */
    public static function dayNumber(int $y, int $m, int $d): int
    {
        $y -= ($m <= 2) ? 1 : 0;
        $era = intdiv(($y >= 0) ? $y : $y - 399, 400);
        $yoe = $y - $era * 400;                                              // [0, 399]
        $doy = intdiv(153 * ($m + (($m > 2) ? -3 : 9)) + 2, 5) + $d - 1;     // [0, 365]
        $doe = $yoe * 365 + intdiv($yoe, 4) - intdiv($yoe, 100) + $doy;      // [0, 146096]

        return $era * 146097 + $doe - 719468;
    }

    /**
     * Inverse of dayNumber(). Returns ['y' => int, 'm' => int, 'd' => int].
     */
    public static function fromDayNumber(int $n): array
    {
        $n += 719468;
        $era = intdiv(($n >= 0) ? $n : $n - 146096, 146097);
        $doe = $n - $era * 146097;                                           // [0, 146096]
        $yoe = intdiv($doe - intdiv($doe, 1460) + intdiv($doe, 36524) - intdiv($doe, 146096), 365);
        $y   = $yoe + $era * 400;
        $doy = $doe - (365 * $yoe + intdiv($yoe, 4) - intdiv($yoe, 100));    // [0, 365]
        $mp  = intdiv(5 * $doy + 2, 153);                                    // [0, 11]
        $d   = $doy - intdiv(153 * $mp + 2, 5) + 1;                          // [1, 31]
        $m   = $mp + (($mp < 10) ? 3 : -9);                                  // [1, 12]

        return ['y' => $y + (($m <= 2) ? 1 : 0), 'm' => $m, 'd' => $d];
    }

    /**
     * Weekday: 0 = Sunday ... 6 = Saturday (1970-01-01 was a Thursday).
     */
    public static function weekday(int $y, int $m, int $d): int
    {
        $n = self::dayNumber($y, $m, $d);

        return (($n + 4) % 7 + 7) % 7;
    }

    /** Floor division that also works for negative numerators. */
    private static function floorDiv(int $a, int $b): int
    {
        $q = intdiv($a, $b);
        if (($a % $b) !== 0 && (($a < 0) !== ($b < 0))) {
            $q--;
        }

        return $q;
    }

    /** Shifts (year, month) by $delta months. Returns [year, month]. */
    public static function shiftMonth(int $y, int $m, int $delta): array
    {
        $idx = $y * 12 + ($m - 1) + $delta;
        $ny  = self::floorDiv($idx, 12);

        return [$ny, $idx - $ny * 12 + 1];
    }

    // -------------------------------------------------------------------
    // Month view assembly
    // -------------------------------------------------------------------

    /**
     * Builds all data for one month view.
     *
     * @param int $year        Any year within the configured range.
     * @param int $month       1-12.
     * @param int $startofweek 0 = Sunday ... 6 = Saturday.
     * @param int $viewer_uid  For private-event filtering.
     * @return array ['grid', 'monthtext', 'important', 'today_day']
     */
    public function getMonthData(int $year, int $month, int $startofweek = 1, int $viewer_uid = 0): array
    {
        global $plugins;

        $num_days       = self::daysInMonth($year, $month);
        $month_start_n  = self::dayNumber($year, $month, 1);
        $month_end_n    = $month_start_n + $num_days - 1;
        $month_start_key = self::dateKey($year, $month, 1);
        $month_end_key   = self::dateKey($year, $month, $num_days);

        $days = [];
        for ($d = 1; $d <= $num_days; $d++) {
            $days[$d] = ['events' => [], 'birthdays' => []];
        }
        $important = [];

        // 1) Extended events from rpgcalendar_events (incl. repeat expansion)
        $rows = $this->database->getEventsForMonthView($month_start_key, $month_end_key, $viewer_uid);
        foreach ($rows as $row) {
            $this->expandExtendedRow($row, $month_start_n, $month_end_n, $days, $important);
        }

        // 2) Native MyBB events + 3) birthdays (adapter guards year ranges itself)
        if (is_object($this->native_adapter)) {
            $native_days = $this->native_adapter->getEventsForMonth($year, $month);
            foreach ($native_days as $d => $events) {
                if (isset($days[$d])) {
                    foreach ($events as $event) {
                        $days[$d]['events'][] = $event;
                    }
                }
            }
            $birthday_days = $this->native_adapter->getBirthdaysForMonth($year, $month);
            foreach ($birthday_days as $d => $entries) {
                if (isset($days[$d])) {
                    foreach ($entries as $entry) {
                        $days[$d]['birthdays'][] = $entry;
                    }
                }
            }
        }

        // 4) Provider hook (Plot-CP etc.)
        if (is_object($plugins)) {
            $hook_args = [
                'year'   => $year,
                'month'  => $month,
                'events' => [],
            ];
            $plugins->run_hooks('rpgcalendar_collect_events', $hook_args);
            if (is_array($hook_args['events'])) {
                foreach ($hook_args['events'] as $provided) {
                    $event = self::sanitizeProvidedEvent($provided);
                    if ($event === null) {
                        continue; // a broken provider must never break the page
                    }
                    $this->placeEvent($event, $month_start_n, $month_end_n, $days, $important);
                }
            }
        }

        // 5) Important-events table, sorted by start date
        usort($important, function (array $a, array $b): int {
            $ka = self::dateKey($a['start']['y'], $a['start']['m'], $a['start']['d']);
            $kb = self::dateKey($b['start']['y'], $b['start']['m'], $b['start']['d']);
            if ($ka === $kb) {
                return strcmp($a['id'], $b['id']);
            }

            return ($ka < $kb) ? -1 : 1;
        });

        // Today marker only if the real current month is displayed
        $today_day = 0;
        if ($year === (int)my_date('Y') && $month === (int)my_date('n')) {
            $today_day = (int)my_date('j');
        }

        return [
            'grid'      => $this->buildGrid($year, $month, $days, $startofweek),
            'monthtext' => $this->database->getMonthText($year, $month),
            'important' => $important,
            'today_day' => $today_day,
        ];
    }

    /**
     * Normalizes an rpgcalendar_events row and adds every occurrence that
     * overlaps the viewed month. Repeat conventions (documented in README):
     * monthly repeats skip months without the start day (e.g. day 31 in
     * February), yearly repeats on Feb 29 skip non-leap years.
     */
    private function expandExtendedRow(array $row, int $month_start_n, int $month_end_n, array &$days, array &$important): void
    {
        $start_y = (int)$row['start_year'];
        $start_m = (int)$row['start_month'];
        $start_d = (int)$row['start_day'];
        $start_n = self::dayNumber($start_y, $start_m, $start_d);
        $end_n   = self::dayNumber((int)$row['end_year'], (int)$row['end_month'], (int)$row['end_day']);
        $span    = max(0, $end_n - $start_n);
        $until_key = (int)$row['repeat_until_key'];

        $view = self::fromDayNumber($month_start_n); // viewed year/month

        $occurrence_starts = [];
        switch ((int)$row['repeat_type']) {
            case self::REPEAT_WEEKLY:
                if ($start_n > $month_end_n) {
                    break;
                }
                $kmin = max(0, self::floorDiv($month_start_n - $span - $start_n + 6, 7)); // ceil()
                $kmax = self::floorDiv($month_end_n - $start_n, 7);
                for ($k = $kmin; $k <= $kmax; $k++) {
                    $occurrence_starts[] = $start_n + 7 * $k;
                }
                break;

            case self::REPEAT_MONTHLY:
                $back = intdiv($span, 28) + 1;
                for ($i = 0; $i <= $back; $i++) {
                    list($cy, $cm) = self::shiftMonth($view['y'], $view['m'], -$i);
                    if ($cy < $start_y || ($cy === $start_y && $cm < $start_m)) {
                        continue;
                    }
                    if ($start_d > self::daysInMonth($cy, $cm)) {
                        continue;
                    }
                    $occurrence_starts[] = self::dayNumber($cy, $cm, $start_d);
                }
                break;

            case self::REPEAT_YEARLY:
                $back = intdiv($span, 365) + 1;
                for ($i = 0; $i <= $back; $i++) {
                    $cy = $view['y'] - $i;
                    if ($cy < $start_y) {
                        continue;
                    }
                    if ($start_d > self::daysInMonth($cy, $start_m)) {
                        continue;
                    }
                    $occurrence_starts[] = self::dayNumber($cy, $start_m, $start_d);
                }
                break;

            case self::REPEAT_NONE:
            default:
                $occurrence_starts[] = $start_n;
                break;
        }

        foreach ($occurrence_starts as $occ_start_n) {
            if ($occ_start_n < $start_n) {
                continue; // never before the event itself
            }
            $occ_end_n = $occ_start_n + $span;
            if ($occ_end_n < $month_start_n || $occ_start_n > $month_end_n) {
                continue;
            }
            $occ_start = self::fromDayNumber($occ_start_n);
            if ($until_key > 0
                && self::dateKey($occ_start['y'], $occ_start['m'], $occ_start['d']) > $until_key
            ) {
                continue;
            }
            $occ_end = self::fromDayNumber($occ_end_n);

            $event = [
                'id'          => 'ext-' . (int)$row['eid'],
                'source'      => 'extended',
                'title'       => (string)$row['title'],
                'description' => (string)$row['description'],
                'url'         => '',
                'start'       => $occ_start,
                'end'         => $occ_end,
                'important'   => ((int)$row['important'] === 1),
                'badge'       => ((string)$row['source'] === 'ics') ? 'ics' : 'extended',
            ];
            $this->placeEvent($event, $month_start_n, $month_end_n, $days, $important);
        }
    }

    /**
     * Adds one normalized event occurrence to every day of the viewed month it
     * covers, and to the important pool if flagged.
     */
    private function placeEvent(array $event, int $month_start_n, int $month_end_n, array &$days, array &$important): void
    {
        $start_n = self::dayNumber($event['start']['y'], $event['start']['m'], $event['start']['d']);
        $end_n   = self::dayNumber($event['end']['y'], $event['end']['m'], $event['end']['d']);
        if ($end_n < $start_n) {
            $end_n = $start_n;
        }
        if ($end_n < $month_start_n || $start_n > $month_end_n) {
            return;
        }

        $first = max($start_n, $month_start_n);
        $last  = min($end_n, $month_end_n);
        for ($n = $first; $n <= $last; $n++) {
            $day = $n - $month_start_n + 1;
            if (isset($days[$day])) {
                $days[$day]['events'][] = $event;
            }
        }

        if (!empty($event['important'])) {
            $important[] = $event;
        }
    }

    /**
     * Validates an event supplied by a provider plugin via the
     * 'rpgcalendar_collect_events' hook. Returns the normalized event or null.
     */
    public static function sanitizeProvidedEvent($provided): ?array
    {
        if (!is_array($provided) || !isset($provided['title'], $provided['start'])) {
            return null;
        }

        $title = trim((string)$provided['title']);
        if ($title === '') {
            return null;
        }

        $start = self::sanitizeDatePart($provided['start']);
        if ($start === null) {
            return null;
        }
        $end = isset($provided['end']) ? self::sanitizeDatePart($provided['end']) : null;
        if ($end === null) {
            $end = $start;
        }
        if (self::dateKey($end['y'], $end['m'], $end['d']) < self::dateKey($start['y'], $start['m'], $start['d'])) {
            $end = $start;
        }

        $badge = isset($provided['badge']) ? (string)$provided['badge'] : 'plot';
        $badge = preg_replace('/[^a-z0-9_-]/', '', strtolower($badge));
        if ($badge === '') {
            $badge = 'plot';
        }

        // Only http(s) or relative URLs – blocks javascript: and friends
        $url = isset($provided['url']) ? trim((string)$provided['url']) : '';
        if ($url !== '' && strpos($url, ':') !== false && !preg_match('#^https?://#i', $url)) {
            $url = '';
        }

        return [
            'id'          => isset($provided['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$provided['id']) : 'provided-0',
            'source'      => isset($provided['source']) ? preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$provided['source'])) : 'plot',
            'title'       => $title,
            'description' => isset($provided['description']) ? (string)$provided['description'] : '',
            'url'         => $url,
            'start'       => $start,
            'end'         => $end,
            'important'   => !isset($provided['important']) || !empty($provided['important']),
            'badge'       => $badge,
        ];
    }

    /** @return array|null ['y','m','d'] with validated ranges */
    private static function sanitizeDatePart($part): ?array
    {
        if (!is_array($part) || !isset($part['y'], $part['m'], $part['d'])) {
            return null;
        }
        $y = (int)$part['y'];
        $m = (int)$part['m'];
        $d = (int)$part['d'];
        if ($y < -32768 || $y > 32767 || $m < 1 || $m > 12 || $d < 1 || $d > self::daysInMonth($y, $m)) {
            return null;
        }

        return ['y' => $y, 'm' => $m, 'd' => $d];
    }

    /**
     * Builds the week grid: array of weeks, each week an array of 7 cells.
     * A cell is either null (filler) or ['day', 'events', 'birthdays'].
     */
    private function buildGrid(int $year, int $month, array $days, int $startofweek): array
    {
        $startofweek = (($startofweek % 7) + 7) % 7;
        $offset = ((self::weekday($year, $month, 1) - $startofweek) % 7 + 7) % 7;
        $num_days = count($days);

        $cells = [];
        for ($i = 0; $i < $offset; $i++) {
            $cells[] = null;
        }
        for ($d = 1; $d <= $num_days; $d++) {
            $cells[] = [
                'day'       => $d,
                'events'    => $days[$d]['events'],
                'birthdays' => $days[$d]['birthdays'],
            ];
        }
        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return array_chunk($cells, 7);
    }
}
