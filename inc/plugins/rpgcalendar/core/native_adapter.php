<?php
/**
 * rpgcalendar – Native adapter
 *
 * The ONLY place that reads native MyBB calendar data (mybb_events via
 * get_events()) and birthdays (native profile birthday or a custom profile
 * field). Native data is strictly read-only here.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

class Rpgcalendar_NativeAdapter
{
    /** Native events only exist inside the unix-timestamp era. */
    const NATIVE_MIN_YEAR = 1970;
    const NATIVE_MAX_YEAR = 2037;

    /** @var DB_Base */
    private $db;

    /** @var array|null Native calendar row (mybb_calendars). */
    private $calendar = null;

    public function __construct()
    {
        global $db, $mybb;

        $this->db = $db;

        $cid = (int)($mybb->settings['rpgcalendar_native_cid'] ?? 0);
        if ($cid > 0) {
            $row = $db->fetch_array($db->simple_select('calendars', '*', 'cid = ' . $cid));
            if ($row) {
                $this->calendar = $row;
            }
        }
    }

    /** 0 = Sunday ... 6 = Saturday; falls back to Monday. */
    public function getStartOfWeek(): int
    {
        if (is_array($this->calendar) && isset($this->calendar['startofweek'])) {
            return (int)$this->calendar['startofweek'] % 7;
        }

        return 1;
    }

    /**
     * Native events of the given month, already expanded to days by MyBB's
     * get_events() (which also resolves repeating events).
     *
     * @return array day => list of normalized events
     */
    public function getEventsForMonth(int $year, int $month): array
    {
        global $mybb;

        if ((int)($mybb->settings['rpgcalendar_shownative'] ?? 0) !== 1
            || !is_array($this->calendar)
            || $year < self::NATIVE_MIN_YEAR || $year > self::NATIVE_MAX_YEAR
        ) {
            return [];
        }

        require_once MYBB_ROOT . 'inc/functions_calendar.php';

        $permissions = get_calendar_permissions((int)$this->calendar['cid']);
        if (empty($permissions['canviewcalendar'])) {
            return [];
        }

        // Month window with a one-day buffer on both sides: get_events()
        // shifts by user timezones, the strict filter below re-trims.
        list($next_y, $next_m) = Rpgcalendar_Engine::shiftMonth($year, $month, 1);
        $start = gmmktime(0, 0, 0, $month, 1, $year) - 86400;
        $end   = gmmktime(0, 0, 0, $next_m, 1, $next_y) + 86400;

        $unapproved   = empty($permissions['canmoderateevents']) ? 0 : 1;
        $events_cache = get_events($this->calendar, $start, $end, $unapproved, 1);

        $days = [];
        if (!is_array($events_cache)) {
            return $days;
        }
        foreach ($events_cache as $key => $events) {
            $parts = explode('-', (string)$key);
            if (count($parts) !== 3) {
                continue;
            }
            $d = (int)$parts[0];
            if ((int)$parts[1] !== $month || (int)$parts[2] !== $year || $d < 1) {
                continue;
            }
            foreach ($events as $event) {
                $days[$d][] = [
                    'id'          => 'native-' . (int)$event['eid'],
                    'source'      => 'native',
                    'title'       => (string)$event['name'],
                    'description' => '',
                    // Own detail view instead of the native calendar.php page
                    'url'         => $mybb->settings['bburl'] . '/rpgcalendar.php?action=event&eid=' . (int)$event['eid']
                                     . '&year=' . $year . '&month=' . $month . '&day=' . $d,
                    'start'       => ['y' => $year, 'm' => $month, 'd' => $d],
                    'end'         => ['y' => $year, 'm' => $month, 'd' => $d],
                    'important'   => false,
                    'badge'       => 'native',
                ];
            }
        }

        return $days;
    }

    /**
     * Loads a single native event by eid for the detail view. Applies the same
     * calendar / visibility / privacy guards as the month merge so nothing
     * becomes visible here that would be hidden in the native calendar.
     *
     * @return array|null ['eid','name','description','uid'] or null if the event
     *                     is missing, from another calendar, or not viewable.
     */
    public function getNativeEvent(int $eid): ?array
    {
        global $mybb;

        if ((int)($mybb->settings['rpgcalendar_shownative'] ?? 0) !== 1
            || !is_array($this->calendar)
            || $eid <= 0
        ) {
            return null;
        }

        require_once MYBB_ROOT . 'inc/functions_calendar.php';

        $permissions = get_calendar_permissions((int)$this->calendar['cid']);
        if (empty($permissions['canviewcalendar'])) {
            return null;
        }

        $event = $this->db->fetch_array($this->db->simple_select(
            'events', '*',
            'eid = ' . $eid . ' AND cid = ' . (int)$this->calendar['cid']
        ));
        if (!$event) {
            return null;
        }

        // Unapproved events are only visible to moderators
        if ((int)$event['visible'] !== 1 && empty($permissions['canmoderateevents'])) {
            return null;
        }

        // Private events only for their author (mirrors native behaviour)
        if ((int)$event['private'] === 1
            && (int)$event['uid'] !== (int)$mybb->user['uid']
            && empty($permissions['canmoderateevents'])
        ) {
            return null;
        }

        return [
            'eid'         => (int)$event['eid'],
            'name'        => (string)$event['name'],
            'description' => (string)$event['description'],
            'uid'         => (int)$event['uid'],
        ];
    }

    /**
     * Birthdays of the given month. Source depends on the ACP setting
     * rpgcalendar_birthday_source: 0 = native profile birthday (only near the
     * real current year), >0 = custom profile field fid (shown in every year).
     *
     * @return array day => list of ['uid','username','usergroup','displaygroup','age']
     */
    public function getBirthdaysForMonth(int $year, int $month): array
    {
        global $mybb;

        if ((int)($mybb->settings['rpgcalendar_showbirthdays'] ?? 0) !== 1) {
            return [];
        }

        $source_fid = (int)($mybb->settings['rpgcalendar_birthday_source'] ?? 0);
        if ($source_fid > 0) {
            return $this->birthdaysFromProfileField($source_fid, $year, $month);
        }

        return $this->birthdaysFromNativeProfile($year, $month);
    }

    private function birthdaysFromNativeProfile(int $year, int $month): array
    {
        global $mybb;

        $range = max(0, (int)($mybb->settings['rpgcalendar_birthday_year_range'] ?? 1));
        $real_year = (int)my_date('Y');
        if (abs($year - $real_year) > $range) {
            return [];
        }

        $month = (int)$month;
        $query = $this->db->query("
            SELECT uid, username, usergroup, displaygroup, birthday
            FROM " . TABLE_PREFIX . "users
            WHERE birthday != ''
              AND birthdayprivacy = 'all'
              AND (birthday LIKE '%-{$month}-%' OR birthday LIKE '%-{$month}')
        ");

        $days = [];
        while ($row = $this->db->fetch_array($query)) {
            $parsed = self::parseBirthday((string)$row['birthday'], $month);
            if ($parsed === null) {
                continue;
            }
            $age = null;
            if ($parsed['year'] !== null && $year === $real_year && $year - $parsed['year'] > 0) {
                $age = $year - $parsed['year'];
            }
            $days[$parsed['day']][] = [
                'uid'          => (int)$row['uid'],
                'username'     => (string)$row['username'],
                'usergroup'    => (int)$row['usergroup'],
                'displaygroup' => (int)$row['displaygroup'],
                'age'          => $age,
            ];
        }

        return $days;
    }

    private function birthdaysFromProfileField(int $fid, int $year, int $month): array
    {
        $column = 'fid' . (int)$fid;
        if (!$this->db->field_exists($column, 'userfields')) {
            return [];
        }

        $month = (int)$month;
        $query = $this->db->query("
            SELECT u.uid, u.username, u.usergroup, u.displaygroup, f.{$column} AS icbirthday
            FROM " . TABLE_PREFIX . "userfields f
            INNER JOIN " . TABLE_PREFIX . "users u ON u.uid = f.ufid
            WHERE f.{$column} != ''
        ");

        $days = [];
        while ($row = $this->db->fetch_array($query)) {
            $parsed = self::parseBirthday((string)$row['icbirthday'], $month);
            if ($parsed === null) {
                continue;
            }
            // no birthdays before the (IC) birth year
            if ($parsed['year'] !== null && $year < $parsed['year']) {
                continue;
            }
            $age = null;
            if ($parsed['year'] !== null && $year - $parsed['year'] > 0) {
                $age = $year - $parsed['year'];
            }
            $days[$parsed['day']][] = [
                'uid'          => (int)$row['uid'],
                'username'     => (string)$row['username'],
                'usergroup'    => (int)$row['usergroup'],
                'displaygroup' => (int)$row['displaygroup'],
                'age'          => $age,
            ];
        }

        return $days;
    }

    /**
     * Parses "day-month-year" or "day-month" (MyBB birthday format, e.g.
     * "12-3-1200"). Returns ['day', 'year'|null] if the month matches,
     * null for non-matching or invalid values.
     */
    public static function parseBirthday(string $value, int $month): ?array
    {
        $parts = explode('-', trim($value));
        if (count($parts) < 2 || count($parts) > 3) {
            return null;
        }
        if (!is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return null;
        }
        $day = (int)$parts[0];
        $m   = (int)$parts[1];
        if ($m !== $month || $day < 1 || $day > 31) {
            return null;
        }

        $year = null;
        if (count($parts) === 3 && is_numeric($parts[2])) {
            $year = (int)$parts[2];
        }

        return ['day' => $day, 'year' => $year];
    }
}
