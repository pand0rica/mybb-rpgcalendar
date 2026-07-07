<?php
/**
 * rpgcalendar – frontend month view.
 *
 * Merges extended events (any year), native MyBB events, provider events
 * (hook 'rpgcalendar_collect_events') and birthdays into one month grid,
 * plus the month free text and the important-events table.
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'rpgcalendar.php');

$templatelist = 'rpgcalendar,rpgcalendar_nav,rpgcalendar_nav_link,rpgcalendar_weekdayheader_cell,'
    . 'rpgcalendar_weekrow,rpgcalendar_daycell,rpgcalendar_daycell_empty,rpgcalendar_eventbit,'
    . 'rpgcalendar_eventbit_plain,rpgcalendar_birthdaybit,rpgcalendar_monthtext,'
    . 'rpgcalendar_eventtable,rpgcalendar_eventtable_row,rpgcalendar_eventtable_empty';

require_once './global.php';

$lang->load('rpgcalendar');

if (!isset($mybb->settings['rpgcalendar_enabled']) || (int)$mybb->settings['rpgcalendar_enabled'] !== 1) {
    error($lang->rpgcalendar_disabled);
}

$allowed_groups = isset($mybb->settings['rpgcalendar_allowed_groups'])
    ? (string)$mybb->settings['rpgcalendar_allowed_groups'] : '';
if ($allowed_groups !== '' && $allowed_groups !== '-1' && !is_member($allowed_groups)) {
    error_no_permission();
}

require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/engine.php';
require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/database.php';
require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/native_adapter.php';

add_breadcrumb($lang->rpgcalendar_title, 'rpgcalendar.php');

// ---------------------------------------------------------------------
// Resolve requested year/month (year 0 is a valid astronomical year, so
// "parameter missing" is detected on the raw string, not on the int cast)
// ---------------------------------------------------------------------
$min_year = isset($mybb->settings['rpgcalendar_min_year']) ? (int)$mybb->settings['rpgcalendar_min_year'] : -2000;
$max_year = isset($mybb->settings['rpgcalendar_max_year']) ? (int)$mybb->settings['rpgcalendar_max_year'] : 5000;
$min_year = max(-32768, $min_year);
$max_year = min(32767, $max_year);
if ($min_year > $max_year) {
    $min_year = $max_year;
}

$default_year = (int)($mybb->settings['rpgcalendar_default_year'] ?? 0);
if ($default_year === 0) {
    $default_year = (int)my_date('Y');
}
$default_month = (int)($mybb->settings['rpgcalendar_default_month'] ?? 0);
if ($default_month < 1 || $default_month > 12) {
    $default_month = (int)my_date('n');
}

$year_raw = $mybb->get_input('year');
$year = ($year_raw === '' || !is_numeric($year_raw)) ? $default_year : (int)$year_raw;
$year = max($min_year, min($max_year, $year));

$month = $mybb->get_input('month', MyBB::INPUT_INT);
if ($month < 1 || $month > 12) {
    $month = $default_month;
}

// ---------------------------------------------------------------------
// Collect month data
// ---------------------------------------------------------------------
$rpgcalendar_database = new Rpgcalendar_Database();
$rpgcalendar_native   = new Rpgcalendar_NativeAdapter();
$rpgcalendar_engine   = new Rpgcalendar_Engine($rpgcalendar_database, $rpgcalendar_native);

$startofweek = $rpgcalendar_native->getStartOfWeek();
$month_data  = $rpgcalendar_engine->getMonthData($year, $month, $startofweek, (int)$mybb->user['uid']);

$rpgcalendar_monthname  = $lang->{'rpgcalendar_month_' . $month};
$rpgcalendar_monthtitle = $rpgcalendar_monthname . ' ' . $year;
$rpgcalendar_pagetitle  = $lang->rpgcalendar_title . ' - ' . $rpgcalendar_monthtitle;

// ---------------------------------------------------------------------
// Navigation (prev/next clamped to the configured year range)
// ---------------------------------------------------------------------
$nav_prev = $nav_next = '';
list($prev_year, $prev_month) = Rpgcalendar_Engine::shiftMonth($year, $month, -1);
if ($prev_year >= $min_year) {
    $nav_url   = 'rpgcalendar.php?year=' . $prev_year . '&amp;month=' . $prev_month;
    $nav_label = '&laquo; ' . htmlspecialchars_uni($lang->{'rpgcalendar_month_' . $prev_month}) . ' ' . $prev_year;
    eval('$nav_prev = "' . $templates->get('rpgcalendar_nav_link') . '";');
}
list($next_year, $next_month) = Rpgcalendar_Engine::shiftMonth($year, $month, 1);
if ($next_year <= $max_year) {
    $nav_url   = 'rpgcalendar.php?year=' . $next_year . '&amp;month=' . $next_month;
    $nav_label = htmlspecialchars_uni($lang->{'rpgcalendar_month_' . $next_month}) . ' ' . $next_year . ' &raquo;';
    eval('$nav_next = "' . $templates->get('rpgcalendar_nav_link') . '";');
}

$month_options = '';
for ($m = 1; $m <= 12; $m++) {
    $selected = ($m === $month) ? ' selected="selected"' : '';
    $month_options .= '<option value="' . $m . '"' . $selected . '>'
        . htmlspecialchars_uni($lang->{'rpgcalendar_month_' . $m}) . '</option>';
}

eval('$rpgcalendar_nav = "' . $templates->get('rpgcalendar_nav') . '";');

// ---------------------------------------------------------------------
// Month free text (parsed with MyCode/smilies, no HTML)
// ---------------------------------------------------------------------
$rpgcalendar_monthtext = '';
if (is_array($month_data['monthtext']) && trim($month_data['monthtext']['content']) !== '') {
    require_once MYBB_ROOT . 'inc/class_parser.php';
    $parser = new postParser();
    $monthtext_content = $parser->parse_message($month_data['monthtext']['content'], [
        'allow_html'      => 0,
        'allow_mycode'    => 1,
        'allow_smilies'   => 1,
        'allow_imgcode'   => 1,
        'allow_videocode' => 0,
        'nl2br'           => 1,
        'filter_badwords' => 1,
    ]);
    eval('$rpgcalendar_monthtext = "' . $templates->get('rpgcalendar_monthtext') . '";');
}

// ---------------------------------------------------------------------
// Weekday header (rotated by start of week)
// ---------------------------------------------------------------------
$weekdayheader = '';
for ($i = 0; $i < 7; $i++) {
    $wd = ($startofweek + $i) % 7;
    $wd_name = htmlspecialchars_uni($lang->{'rpgcalendar_wd_' . $wd});
    eval('$weekdayheader .= "' . $templates->get('rpgcalendar_weekdayheader_cell') . '";');
}

// ---------------------------------------------------------------------
// Week rows
// ---------------------------------------------------------------------
$weekrows = '';
foreach ($month_data['grid'] as $week) {
    $day_cells = '';
    foreach ($week as $cell) {
        if ($cell === null) {
            eval('$day_cells .= "' . $templates->get('rpgcalendar_daycell_empty') . '";');
            continue;
        }

        $day       = (int)$cell['day'];
        $day_class = ($month_data['today_day'] === $day) ? 'trow_sep' : 'trow1';

        $day_birthdays = '';
        foreach ($cell['birthdays'] as $entry) {
            $birthday_profilelink = get_profile_link((int)$entry['uid']);
            $birthday_name = format_name(
                htmlspecialchars_uni($entry['username']),
                (int)$entry['usergroup'],
                (int)$entry['displaygroup']
            );
            $birthday_age = ($entry['age'] !== null) ? ' (' . (int)$entry['age'] . ')' : '';
            eval('$day_birthdays .= "' . $templates->get('rpgcalendar_birthdaybit') . '";');
        }

        $day_events = '';
        foreach ($cell['events'] as $event) {
            $event_badge = htmlspecialchars_uni($event['badge']);
            $event_title = htmlspecialchars_uni($event['title']);
            if ($event['url'] !== '') {
                $event_url = htmlspecialchars_uni($event['url']);
                eval('$day_events .= "' . $templates->get('rpgcalendar_eventbit') . '";');
            } else {
                eval('$day_events .= "' . $templates->get('rpgcalendar_eventbit_plain') . '";');
            }
        }

        eval('$day_cells .= "' . $templates->get('rpgcalendar_daycell') . '";');
    }
    eval('$weekrows .= "' . $templates->get('rpgcalendar_weekrow') . '";');
}

// ---------------------------------------------------------------------
// Important-events table
// ---------------------------------------------------------------------
$eventtable_rows = '';
$altbg = 'trow1';
foreach ($month_data['important'] as $event) {
    $row_badge = htmlspecialchars_uni($event['badge']);

    $source_key = 'rpgcalendar_source_' . $event['source'];
    $row_source_label = isset($lang->$source_key)
        ? htmlspecialchars_uni($lang->$source_key)
        : htmlspecialchars_uni($event['source']);

    $row_date = $event['start']['d'] . '.' . $event['start']['m'] . '.' . $event['start']['y'];
    if ($event['start'] !== $event['end']) {
        $row_date .= ' &ndash; ' . $event['end']['d'] . '.' . $event['end']['m'] . '.' . $event['end']['y'];
    }

    $row_title = htmlspecialchars_uni($event['title']);
    if ($event['url'] !== '') {
        $row_title = '<a href="' . htmlspecialchars_uni($event['url']) . '">' . $row_title . '</a>';
    }

    $row_description = '';
    if (trim($event['description']) !== '') {
        $excerpt = trim($event['description']);
        if (my_strlen($excerpt) > 160) {
            $excerpt = my_substr($excerpt, 0, 160) . '&hellip;';
        }
        $row_description = '<div class="smalltext">' . htmlspecialchars_uni($excerpt) . '</div>';
    }

    eval('$eventtable_rows .= "' . $templates->get('rpgcalendar_eventtable_row') . '";');
    $altbg = ($altbg === 'trow1') ? 'trow2' : 'trow1';
}
if ($eventtable_rows === '') {
    eval('$eventtable_rows = "' . $templates->get('rpgcalendar_eventtable_empty') . '";');
}
eval('$rpgcalendar_eventtable = "' . $templates->get('rpgcalendar_eventtable') . '";');

// ---------------------------------------------------------------------
// Optional output hook for extensions below the event table
// ---------------------------------------------------------------------
$rpgcalendar_footer_html = '';
$footer_args = [
    'year'   => $year,
    'month'  => $month,
    'output' => &$rpgcalendar_footer_html,
];
$plugins->run_hooks('rpgcalendar_monthview_end', $footer_args);

eval('$page = "' . $templates->get('rpgcalendar') . '";');
output_page($page);
