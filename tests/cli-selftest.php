<?php
/**
 * rpgcalendar – CLI self test.
 *
 * Run on the (test) server from the MyBB root:
 *   php tests/cli-selftest.php
 *
 * Bootstraps inc/init.php (DB + settings, no session), then verifies the
 * calendar math, occurrence expansion, provider sanitizing and – if the
 * plugin tables exist – a database round trip. Not part of a release zip.
 */

if (PHP_SAPI !== 'cli') {
    die('CLI only.');
}

define('IN_MYBB', 1);
chdir(dirname(__DIR__));
require_once './inc/init.php';

// Minimal user context so my_date() works without a session
if (!isset($mybb->user) || !is_array($mybb->user) || empty($mybb->user)) {
    $mybb->user = [];
}
$mybb->user += ['uid' => 0, 'timezone' => $mybb->settings['timezoneoffset'] ?? 0, 'dst' => 0, 'dateformat' => '', 'timeformat' => ''];

require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/engine.php';
require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/database.php';
require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/generator.php';

$pass = 0;
$fail = 0;

function check(string $name, $expected, $actual): void
{
    global $pass, $fail;

    if ($expected === $actual) {
        $pass++;
        echo "  ok   {$name}\n";
    } else {
        $fail++;
        echo "  FAIL {$name}: expected " . var_export($expected, true)
            . ", got " . var_export($actual, true) . "\n";
    }
}

/** Fake database: feeds fixture rows into the engine, no real queries. */
class Rpgcalendar_TestDatabase
{
    public $rows = [];
    public $weather = [];
    public $holidays = [];
    public $locations = [];
    public $prefs = [];

    public function getEventsForMonthView(int $s, int $e, int $u): array
    {
        return $this->rows; // engine re-filters via occurrence expansion
    }

    public function getMonthText(int $y, int $m): ?array
    {
        return null;
    }

    public function getCalendarColors(): array
    {
        return [];
    }

    public function getUserPrefs(int $uid): array
    {
        return $this->prefs;
    }

    public function listLocations(bool $visible_only = false): array
    {
        return $this->locations;
    }

    public function getWeatherForMonthView(int $s, int $e, array $locids): array
    {
        return self::filterByLocid($this->weather, $locids);
    }

    public function getHolidayForMonthView(int $s, int $e, array $locids): array
    {
        return self::filterByLocid($this->holidays, $locids);
    }

    /** Mirrors the real "WHERE locid IN (...)" (empty list => nothing). */
    private static function filterByLocid(array $rows, array $locids): array
    {
        return array_values(array_filter($rows, function (array $r) use ($locids) {
            return in_array((int)$r['locid'], $locids, true);
        }));
    }
}

function make_row(int $eid, int $sy, int $sm, int $sd, int $ey, int $em, int $ed, int $repeat = 0, int $until_key = 0): array
{
    return [
        'eid' => $eid, 'title' => 'T' . $eid, 'description' => '',
        'start_year' => $sy, 'start_month' => $sm, 'start_day' => $sd,
        'end_year' => $ey, 'end_month' => $em, 'end_day' => $ed,
        'start_key' => Rpgcalendar_Engine::dateKey($sy, $sm, $sd),
        'end_key'   => Rpgcalendar_Engine::dateKey($ey, $em, $ed),
        'repeat_type' => $repeat, 'repeat_until_key' => $until_key,
        'important' => 1, 'visible' => 1, 'source' => 'manual', 'private' => 0, 'uid' => 0,
        'calendar_id' => 0,
    ];
}

/** Days of the viewed month on which event $id appears. @return int[] */
function days_with_event(array $data, string $id): array
{
    $days = [];
    foreach ($data['grid'] as $week) {
        foreach ($week as $cell) {
            if ($cell === null) {
                continue;
            }
            foreach ($cell['events'] as $ev) {
                if ($ev['id'] === $id) {
                    $days[] = (int)$cell['day'];
                }
            }
        }
    }
    sort($days);

    return $days;
}

function month_data(array $rows, int $year, int $month, array $extra = []): array
{
    $db = new Rpgcalendar_TestDatabase();
    $db->rows      = $rows;
    $db->weather   = $extra['weather']   ?? [];
    $db->holidays  = $extra['holidays']  ?? [];
    $db->locations = $extra['locations'] ?? [];
    $db->prefs     = $extra['prefs']     ?? [];
    $engine = new Rpgcalendar_Engine($db, null);

    return $engine->getMonthData($year, $month, 1, 0);
}

/** Sources of the events on a given day of the month, in render order. */
function sources_on_day(array $data, int $day): array
{
    foreach ($data['grid'] as $week) {
        foreach ($week as $cell) {
            if ($cell !== null && (int)$cell['day'] === $day) {
                return array_map(function (array $ev) {
                    return $ev['source'] ?? '';
                }, $cell['events']);
            }
        }
    }

    return [];
}

function make_weather(int $wid, int $locid, int $y, int $m, int $d, string $cond = 'clear', int $yearly = 0): array
{
    return [
        'wid' => $wid, 'locid' => $locid, 'source' => 'auto', 'wcondition' => $cond,
        'start_year' => $y, 'start_month' => $m, 'start_day' => $d,
        'end_year' => $y, 'end_month' => $m, 'end_day' => $d,
        'start_key' => Rpgcalendar_Engine::dateKey($y, $m, $d),
        'end_key'   => Rpgcalendar_Engine::dateKey($y, $m, $d),
        'yearly' => $yearly, 'note' => '', 'visible' => 1,
    ];
}

function make_holiday(int $hid, int $locid, int $y, int $m, int $d, int $yearly = 0): array
{
    return [
        'hid' => $hid, 'locid' => $locid, 'source' => 'auto', 'title' => 'H' . $hid, 'description' => '',
        'start_year' => $y, 'start_month' => $m, 'start_day' => $d,
        'end_year' => $y, 'end_month' => $m, 'end_day' => $d,
        'start_key' => Rpgcalendar_Engine::dateKey($y, $m, $d),
        'end_key'   => Rpgcalendar_Engine::dateKey($y, $m, $d),
        'yearly' => $yearly, 'visible' => 1,
    ];
}

function make_location(int $locid, int $type = 0): array
{
    return ['locid' => $locid, 'name' => 'L' . $locid, 'type' => $type, 'visible' => 1];
}

echo "== Calendar math ==\n";
check('1970-01-01 is Thursday (4)', 4, Rpgcalendar_Engine::weekday(1970, 1, 1));
check('2000-01-01 is Saturday (6)', 6, Rpgcalendar_Engine::weekday(2000, 1, 1));
check('dayNumber(1970,1,1) == 0', 0, Rpgcalendar_Engine::dayNumber(1970, 1, 1));
check('daysInMonth 1900-02 (no leap)', 28, Rpgcalendar_Engine::daysInMonth(1900, 2));
check('daysInMonth 2000-02 (leap)', 29, Rpgcalendar_Engine::daysInMonth(2000, 2));
check('daysInMonth 2024-02 (leap)', 29, Rpgcalendar_Engine::daysInMonth(2024, 2));
check('daysInMonth -4-02 (astronomical leap)', 29, Rpgcalendar_Engine::daysInMonth(-4, 2));
check('daysInMonth 0-02 (year 0 is leap)', 29, Rpgcalendar_Engine::daysInMonth(0, 2));

$roundtrips = [[1247, 3, 12], [-50, 12, 31], [2024, 2, 29], [1, 1, 1], [0, 2, 29], [3021, 6, 15], [-2000, 1, 1]];
foreach ($roundtrips as $rt) {
    $n = Rpgcalendar_Engine::dayNumber($rt[0], $rt[1], $rt[2]);
    $back = Rpgcalendar_Engine::fromDayNumber($n);
    check("roundtrip {$rt[0]}-{$rt[1]}-{$rt[2]}", ['y' => $rt[0], 'm' => $rt[1], 'd' => $rt[2]], $back);
}

check('dateKey monotonic across negative year border', true,
    Rpgcalendar_Engine::dateKey(-50, 12, 31) < Rpgcalendar_Engine::dateKey(-49, 1, 1));
check('shiftMonth Jan -1 => Dec prev year', [1246, 12], Rpgcalendar_Engine::shiftMonth(1247, 1, -1));
check('shiftMonth Dec +1 => Jan next year', [1248, 1], Rpgcalendar_Engine::shiftMonth(1247, 12, 1));

echo "== Grid ==\n";
$data = month_data([], 1247, 3);
$ok = true;
foreach ($data['grid'] as $week) {
    if (count($week) !== 7) {
        $ok = false;
    }
}
check('every grid week has 7 cells', true, $ok);
$cell_count = 0;
foreach ($data['grid'] as $week) {
    foreach ($week as $cell) {
        if ($cell !== null) {
            $cell_count++;
        }
    }
}
check('grid holds all days of March', 31, $cell_count);

echo "== One-time / multi-day events ==\n";
$row = make_row(1, 1247, 3, 28, 1247, 4, 2);
check('multi-day event, March part', [28, 29, 30, 31], days_with_event(month_data([$row], 1247, 3), 'ext-1'));
check('multi-day event, April part', [1, 2], days_with_event(month_data([$row], 1247, 4), 'ext-1'));
check('multi-day event, absent in May', [], days_with_event(month_data([$row], 1247, 5), 'ext-1'));

echo "== Weekly repeat ==\n";
$row = make_row(2, 1247, 1, 5, 1247, 1, 6, Rpgcalendar_Engine::REPEAT_WEEKLY);
// Jan 5 1247 + 7k, two days each. Expected in February 1247:
$expected = [];
$jan5 = Rpgcalendar_Engine::dayNumber(1247, 1, 5);
$feb1 = Rpgcalendar_Engine::dayNumber(1247, 2, 1);
$febdays = Rpgcalendar_Engine::daysInMonth(1247, 2);
for ($k = 0; $k < 20; $k++) {
    foreach ([0, 1] as $off) {
        $n = $jan5 + 7 * $k + $off;
        $d = $n - $feb1 + 1;
        if ($d >= 1 && $d <= $febdays) {
            $expected[] = $d;
        }
    }
}
sort($expected);
check('weekly 2-day event in February', $expected, days_with_event(month_data([$row], 1247, 2), 'ext-2'));

$until = Rpgcalendar_Engine::dateKey(2024, 1, 15);
$row = make_row(3, 2024, 1, 1, 2024, 1, 1, Rpgcalendar_Engine::REPEAT_WEEKLY, $until);
check('weekly repeat honours repeat_until', [1, 8, 15], days_with_event(month_data([$row], 2024, 1), 'ext-3'));
check('weekly repeat stops after repeat_until', [], days_with_event(month_data([$row], 2024, 2), 'ext-3'));

echo "== Monthly repeat ==\n";
$row = make_row(4, 2024, 1, 31, 2024, 1, 31, Rpgcalendar_Engine::REPEAT_MONTHLY);
check('monthly day-31 skips February', [], days_with_event(month_data([$row], 2024, 2), 'ext-4'));
check('monthly day-31 hits March', [31], days_with_event(month_data([$row], 2024, 3), 'ext-4'));
check('monthly repeat absent before start', [], days_with_event(month_data([$row], 2023, 12), 'ext-4'));

echo "== Yearly repeat ==\n";
$row = make_row(5, 2024, 2, 29, 2024, 2, 29, Rpgcalendar_Engine::REPEAT_YEARLY);
check('yearly Feb-29 skips non-leap 2025', [], days_with_event(month_data([$row], 2025, 2), 'ext-5'));
check('yearly Feb-29 hits leap 2028', [29], days_with_event(month_data([$row], 2028, 2), 'ext-5'));

$row = make_row(6, 1200, 3, 12, 1200, 3, 12, Rpgcalendar_Engine::REPEAT_YEARLY);
check('yearly repeat over decades (1200 -> 1247)', [12], days_with_event(month_data([$row], 1247, 3), 'ext-6'));

$row = make_row(7, 1246, 12, 30, 1247, 1, 4, Rpgcalendar_Engine::REPEAT_YEARLY);
check('yearly multi-day spills into January', [1, 2, 3, 4], days_with_event(month_data([$row], 1248, 1), 'ext-7'));

echo "== Important table ==\n";
$row = make_row(8, 1247, 3, 12, 1247, 3, 12);
$data = month_data([$row], 1247, 3);
check('important table holds the event', 1, count($data['important']));
check('important table entry id', 'ext-8', $data['important'][0]['id']);

// The helper may over-deliver (a look-back window); the caller filters by month
// overlap. So test the contract, not exact sets.
echo "== Dated occurrence helper ==\n";
check('non-yearly => single occurrence', [Rpgcalendar_Engine::dayNumber(1247, 3, 12)],
    Rpgcalendar_Engine::datedOccurrenceStarts(1247, 3, 12, 0, false, 1247));
check('yearly includes the view-year occurrence', true,
    in_array(Rpgcalendar_Engine::dayNumber(1247, 3, 12),
        Rpgcalendar_Engine::datedOccurrenceStarts(1200, 3, 12, 0, true, 1247), true));
$feb29_starts = Rpgcalendar_Engine::datedOccurrenceStarts(2024, 2, 29, 0, true, 2025);
$feb29_in_2025 = false;
foreach ($feb29_starts as $n) {
    if (Rpgcalendar_Engine::fromDayNumber($n)['y'] === 2025) {
        $feb29_in_2025 = true;
    }
}
check('yearly Feb-29 has no occurrence in non-leap 2025', false, $feb29_in_2025);

echo "== Weather condition slugs ==\n";
check('weatherCodes has the 28 WMO codes', 28, count(Rpgcalendar_Generator::weatherCodes()));
$slugs = Rpgcalendar_Generator::conditionSlugs();
check('conditionSlugs = 28 codes + windy', 29, count($slugs));
check('conditionSlugs contains code 61', true, in_array('61', $slugs, true));
check('conditionSlugs contains code 99', true, in_array('99', $slugs, true));
check('conditionSlugs contains windy', true, in_array('windy', $slugs, true));

echo "== Weather / holiday placement ==\n";
$extra = [
    'locations' => [make_location(1, 0)],
    'weather'   => [make_weather(1, 1, 1247, 3, 12, 'rain')],
    'holidays'  => [make_holiday(1, 1, 1247, 3, 12)],
];
$data = month_data([], 1247, 3, $extra);
$srcs = sources_on_day($data, 12);
check('weather and holiday present on day 12', true, in_array('weather', $srcs, true) && in_array('holiday', $srcs, true));
check('weather is ordered above holiday on the same day', true,
    array_search('weather', $srcs, true) < array_search('holiday', $srcs, true));
check('holiday is important, weather is not', 1, count($data['important']));
check('no hint when a fictional location has data', [], $data['hints']);

check('fictional location without data yields a hint', 1,
    count(month_data([], 1247, 3, ['locations' => [make_location(2, 0)]])['hints']));
check('real location without data yields no hint', [],
    month_data([], 1247, 3, ['locations' => [make_location(3, 1)]])['hints']);

// Visibility via per-user calendar prefs (calendar_key visible=0 hides it)
$hidden = month_data([], 1247, 3, [
    'locations' => [make_location(1, 0)],
    'weather'   => [make_weather(1, 1, 1247, 3, 12)],
    'holidays'  => [make_holiday(1, 1, 1247, 3, 12)],
    'prefs'     => [
        'weather-1' => ['color' => '', 'visible' => 0],
        'holiday-1' => ['color' => '', 'visible' => 0],
    ],
]);
check('hidden weather/holiday calendars are not placed', [], sources_on_day($hidden, 12));

check('yearly weather recurs in later years', ['weather'],
    sources_on_day(month_data([], 1248, 12, [
        'locations' => [make_location(1, 0)],
        'weather'   => [make_weather(1, 1, 1247, 12, 25, 'snow', 1)],
    ]), 25));

echo "== Provider sanitizing ==\n";
$valid = Rpgcalendar_Engine::sanitizeProvidedEvent([
    'id' => 'plot-5', 'source' => 'plot', 'title' => 'Test Plot',
    'url' => 'plotcp.php?action=view&pid=5',
    'start' => ['y' => 1247, 'm' => 3, 'd' => 12],
    'end' => ['y' => 1247, 'm' => 4, 'd' => 2],
]);
check('valid provider event accepted', 'plot-5', is_array($valid) ? $valid['id'] : null);
check('missing title rejected', null,
    Rpgcalendar_Engine::sanitizeProvidedEvent(['start' => ['y' => 1, 'm' => 1, 'd' => 1]]));
check('invalid date (Feb 31) rejected', null,
    Rpgcalendar_Engine::sanitizeProvidedEvent(['title' => 'x', 'start' => ['y' => 2024, 'm' => 2, 'd' => 31]]));
$js = Rpgcalendar_Engine::sanitizeProvidedEvent([
    'title' => 'x', 'url' => 'javascript:alert(1)',
    'start' => ['y' => 2024, 'm' => 1, 'd' => 1],
]);
check('javascript: url stripped', '', is_array($js) ? $js['url'] : null);

echo "== Database round trip ==\n";
if ($db->table_exists('rpgcalendar_events')) {
    $store = new Rpgcalendar_Database();
    $eid = $store->saveEvent([
        'title' => 'SELFTEST ' . TIME_NOW, 'description' => 'selftest row',
        'start_year' => 1247, 'start_month' => 3, 'start_day' => 12,
        'end_year' => 1247, 'end_month' => 3, 'end_day' => 14,
        'repeat_type' => 0, 'important' => 1, 'visible' => 1, 'uid' => 0,
    ]);
    check('saveEvent returns id', true, $eid > 0);

    $found = false;
    $rows = $store->getEventsForMonthView(
        Rpgcalendar_Engine::dateKey(1247, 3, 1),
        Rpgcalendar_Engine::dateKey(1247, 3, 31),
        0
    );
    foreach ($rows as $r) {
        if ((int)$r['eid'] === $eid) {
            $found = true;
        }
    }
    check('overlap query finds the row', true, $found);

    $rows = $store->getEventsForMonthView(
        Rpgcalendar_Engine::dateKey(1247, 5, 1),
        Rpgcalendar_Engine::dateKey(1247, 5, 31),
        0
    );
    $found = false;
    foreach ($rows as $r) {
        if ((int)$r['eid'] === $eid) {
            $found = true;
        }
    }
    check('overlap query excludes other months', false, $found);

    $store->deleteEvent($eid);
    check('row deleted', null, $store->getEvent($eid));
} else {
    echo "  skip (table rpgcalendar_events does not exist - plugin not installed)\n";
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
