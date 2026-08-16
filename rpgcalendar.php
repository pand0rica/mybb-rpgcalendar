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
    . 'rpgcalendar_weekrow,rpgcalendar_daycell,rpgcalendar_daycell_empty,rpgcalendar_daycell_more,rpgcalendar_eventbit,'
    . 'rpgcalendar_eventbit_plain,rpgcalendar_birthdaybit,rpgcalendar_monthtext,'
    . 'rpgcalendar_eventtable,rpgcalendar_eventtable_row,rpgcalendar_eventtable_empty,rpgcalendar_event,'
    . 'rpgcalendar_day,rpgcalendar_day_row,rpgcalendar_day_empty,rpgcalendar_day_addbutton,'
    . 'rpgcalendar_form,rpgcalendar_form_calendar,'
    . 'rpgcalendar_event_invites,rpgcalendar_event_invite,rpgcalendar_event_response,rpgcalendar_event_manage,'
    . 'rpgcalendar_current_monthtexts,rpgcalendar_current_monthtext_bit,'
    . 'rpgcalendar_calendars,rpgcalendar_calendar_row,rpgcalendar_calendar_addform,rpgcalendar_calendar_delete,'
    . 'rpgcalendar_calendar_share,rpgcalendar_calendar_leave,rpgcalendar_calendar_invites,rpgcalendar_calendar_invite_row,'
    . 'rpgcalendar_calendar_joinable,rpgcalendar_calendar_joinable_row,'
    . 'rpgcalendar_share,rpgcalendar_share_member,rpgcalendar_share_empty,rpgcalendar_autocomplete,'
    . 'rpgcalendar_hint';

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

/** Localized label for a weather condition slug (falls back to the raw slug). */
function rpgcalendar_weather_label(string $slug): string
{
    global $lang;

    $key = 'rpgcalendar_wcond_' . preg_replace('/[^a-z0-9_]/', '', $slug);

    return (isset($lang->$key) && $lang->$key !== '') ? $lang->$key : $slug;
}

/**
 * All image URLs for a weather condition, keyed by slug. A condition may have
 * several variant images: the base name (e.g. rain.png) plus dash-suffixed
 * variants (rain-1.png, rain-2.png, rain-night.png, …). The image directory is
 * scanned once per request. @return array slug => string[] (sorted URLs)
 */
function rpgcalendar_weather_images(): array
{
    global $mybb;
    static $variants = null;

    if ($variants !== null) {
        return $variants;
    }

    $variants = [];
    $dir = isset($mybb->settings['rpgcalendar_weather_imagedir'])
        ? trim((string)$mybb->settings['rpgcalendar_weather_imagedir']) : '';
    $dir = trim($dir, '/');
    $abs = MYBB_ROOT . $dir;
    if ($dir === '' || !is_dir($abs)) {
        return $variants;
    }

    $exts = ['png', 'gif', 'jpg', 'jpeg', 'svg', 'webp'];
    foreach ((array)scandir($abs) as $file) {
        // base "slug" up to an optional "-variant" suffix, then the extension
        if (!preg_match('/^([a-z0-9_]+)(?:-[^.]+)?\.([a-z0-9]+)$/i', $file, $m)) {
            continue;
        }
        if (!in_array(strtolower($m[2]), $exts, true)) {
            continue;
        }
        $variants[strtolower($m[1])][] = $mybb->settings['bburl'] . '/' . $dir . '/' . $file;
    }
    foreach ($variants as &$list) {
        sort($list); // stable order so the seed picks the same file every time
    }
    unset($list);

    return $variants;
}

/**
 * <img> markup for a weather condition. If several variant images exist for the
 * slug, one is picked deterministically from $seed (so the same entry always
 * shows the same image). Empty string if no image exists (caller shows text).
 * Includes a trailing space.
 */
function rpgcalendar_weather_symbol(string $slug, string $label, int $seed = 0): string
{
    $slug = preg_replace('/[^a-z0-9_]/', '', strtolower($slug));
    if ($slug === '') {
        return '';
    }
    $images = rpgcalendar_weather_images();
    if (empty($images[$slug])) {
        return '';
    }

    $list = $images[$slug];
    $src  = htmlspecialchars_uni($list[abs($seed) % count($list)]);
    $alt  = htmlspecialchars_uni($label);

    return '<img src="' . $src . '" alt="' . $alt . '" title="' . $alt . '" class="rpgcalendar_wicon" /> ';
}

/** Markup for the single global holiday symbol (image URL or emoji/text). */
function rpgcalendar_holiday_symbol(): string
{
    global $mybb, $lang;
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $sym = isset($mybb->settings['rpgcalendar_holiday_symbol'])
        ? trim((string)$mybb->settings['rpgcalendar_holiday_symbol']) : '';
    if ($sym === '') {
        $cache = '';
    } elseif (strpos($sym, '/') !== false || preg_match('/\.(png|gif|jpe?g|svg|webp)$/i', $sym)) {
        // Give the image a non-empty alt/title, mirroring the weather <img> markup
        // exactly – an empty alt="" makes some image filters treat it as a bare/unnamed
        // image and strip it.
        $label = (isset($lang->rpgcalendar_cal_holiday) && (string)$lang->rpgcalendar_cal_holiday !== '')
            ? htmlspecialchars_uni((string)$lang->rpgcalendar_cal_holiday) : 'Holiday';
        $cache = '<img src="' . htmlspecialchars_uni($sym) . '" alt="' . $label . '" title="' . $label . '" class="rpgcalendar_wicon" /> ';
    } else {
        $cache = htmlspecialchars_uni($sym) . ' ';
    }

    return $cache;
}

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

// When enabled, event titles (subjects) are rendered as HTML in visible text.
// Tooltips, the page <title> and the breadcrumb always stay plain (see below).
$rpgcalendar_html_titles = (int)($mybb->settings['rpgcalendar_html_titles'] ?? 0) === 1;

require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/frontend.php';

$rpgcalendar_action = $mybb->get_input('action');

/**
 * Renders the add-event form page. Also used to redisplay the form with errors
 * after a failed submit, so the user does not lose their input.
 */
function rpgcalendar_output_event_form(array $values, array $errors, string $invite_raw, int $eid = 0): void
{
    global $mybb, $lang, $templates, $theme, $headerinclude, $header, $footer;

    $is_edit    = $eid > 0;
    $form_title = $is_edit ? $lang->rpgcalendar_edit_title : $lang->rpgcalendar_add_title;

    $nav_year  = (int)$values['nav_year'];
    $nav_month = (int)$values['nav_month'];
    $nav_day   = (int)$values['nav_day'];

    $rpgcalendar_pagetitle = $lang->rpgcalendar_title . ' - ' . $form_title;
    $form_errors = $errors ? inline_error($errors) : '';

    $form_backurl = 'rpgcalendar.php?action=day&amp;year=' . $nav_year . '&amp;month=' . $nav_month . '&amp;day=' . $nav_day;
    if ($is_edit) {
        $form_action = 'rpgcalendar.php?action=do_edit&amp;year=' . $nav_year . '&amp;month=' . $nav_month . '&amp;day=' . $nav_day;
        $eid_field   = '<input type="hidden" name="eid" value="' . (int)$eid . '" />';
    } else {
        $form_action = 'rpgcalendar.php?action=do_add&amp;year=' . $nav_year . '&amp;month=' . $nav_month . '&amp;day=' . $nav_day;
        $eid_field   = '';
    }
    $post_code = $mybb->post_code;

    $title_value       = htmlspecialchars_uni((string)$values['title']);
    $description_value = htmlspecialchars_uni((string)$values['description']);
    $invite_value      = htmlspecialchars_uni($invite_raw);

    $start_inputs = Rpgcalendar_Frontend::dateInputs('start', (int)$values['start_day'], (int)$values['start_month'], (int)$values['start_year']);
    $end_inputs   = Rpgcalendar_Frontend::dateInputs('end', (int)$values['end_day'], (int)$values['end_month'], (int)$values['end_year']);
    $until_inputs = Rpgcalendar_Frontend::dateInputs('repeat_until', (int)$values['repeat_until_day'], (int)$values['repeat_until_month'], (int)$values['repeat_until_year'], true);
    $repeat_options        = Rpgcalendar_Frontend::repeatOptions((int)$values['repeat_type']);
    $repeat_interval_value = max(1, (int)($values['repeat_interval'] ?? 1));

    $multiday_checked  = !empty($values['multiday']) ? ' checked="checked"' : '';
    $important_checked = !empty($values['important']) ? ' checked="checked"' : '';

    // Calendar selector (replaces the old forum-wide checkbox): the chosen
    // sub-calendar decides visibility — a global calendar is forum-wide/public.
    require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/database.php';
    $cal_db = new Rpgcalendar_Database();
    $cal_db->ensurePersonalCalendar((int)$mybb->user['uid']); // guarantee ≥1 option
    $writable = $cal_db->getWritableCalendars((int)$mybb->user['uid'], Rpgcalendar_Frontend::isTeam());

    $selected_cal = (int)($values['calendar_id'] ?? 0);
    // In edit mode the event's current calendar must always be offered (and
    // preselected), even if the editor could not normally write to it — so it
    // is preserved by default and the dropdown never silently moves the event.
    if ($is_edit && $selected_cal > 0) {
        $in_list = false;
        foreach ($writable as $c) {
            if ((int)$c['cid'] === $selected_cal) {
                $in_list = true;
                break;
            }
        }
        if (!$in_list) {
            $current = $cal_db->getCalendar($selected_cal);
            if ($current) {
                array_unshift($writable, $current);
            }
        }
    }
    if ($selected_cal === 0 && $writable) {
        $selected_cal = (int)$writable[0]['cid']; // default: first (personal) calendar
    }
    $calendar_options = '';
    foreach ($writable as $cal) {
        $sel   = ((int)$cal['cid'] === $selected_cal) ? ' selected="selected"' : '';
        $label = htmlspecialchars_uni((string)$cal['name']);
        if ((int)$cal['type'] === 1) {
            $label .= ' (' . htmlspecialchars_uni($lang->rpgcalendar_cal_global) . ')';
        }
        $calendar_options .= '<option value="' . (int)$cal['cid'] . '"' . $sel . '>' . $label . '</option>';
    }
    eval('$calendar_row = "' . $templates->get('rpgcalendar_form_calendar') . '";');

    // Username autocomplete (Select2 + xmlhttp get_users) on the invite field
    $ac_target   = '#rpgcalendar_invites';
    $ac_multiple = 'true';
    eval('$rpgcalendar_autocomplete = "' . $templates->get('rpgcalendar_autocomplete') . '";');

    add_breadcrumb($form_title);
    eval('$page = "' . $templates->get('rpgcalendar_form') . '";');
    output_page($page);
}

/**
 * Sends an invite notification PM from the event author to the invited members.
 *
 * Sends it the same way private.php does (PMDataHandler + private_do_send_end
 * hook) instead of send_pm(), so notification plugins that listen on that hook
 * (Account Switcher unread-PM notice, MyAlerts, ...) react exactly as they do
 * for a normally sent PM. $pm/$pmhandler/$pminfo are made global because those
 * hook listeners read them.
 */
function rpgcalendar_send_invite_pms(int $eid, array $uids, array $data): void
{
    global $mybb, $lang, $plugins, $session, $pm, $pmhandler, $pminfo;

    if ((int)($mybb->settings['enablepms'] ?? 1) !== 1 || !$uids) {
        return;
    }

    // The private.php send flow works on usernames, so resolve the uids
    $usernames = [];
    foreach ($uids as $uid) {
        $user = get_user((int)$uid);
        if (!empty($user['uid'])) {
            $usernames[] = (string)$user['username'];
        }
    }
    if (!$usernames) {
        return;
    }

    $date_str = (int)$data['start_day'] . '.' . (int)$data['start_month'] . '.' . (int)$data['start_year'];
    if (Rpgcalendar_Engine::dateKey((int)$data['start_year'], (int)$data['start_month'], (int)$data['start_day'])
        !== Rpgcalendar_Engine::dateKey((int)$data['end_year'], (int)$data['end_month'], (int)$data['end_day'])
    ) {
        $date_str .= ' - ' . (int)$data['end_day'] . '.' . (int)$data['end_month'] . '.' . (int)$data['end_year'];
    }

    $link = $mybb->settings['bburl'] . '/rpgcalendar.php?action=event&source=extended&eid=' . (int)$eid
        . '&year=' . (int)$data['start_year'] . '&month=' . (int)$data['start_month'] . '&day=' . (int)$data['start_day'];

    rpgcalendar_native_pm(
        $usernames,
        $lang->sprintf($lang->rpgcalendar_pm_subject, strip_tags((string)$data['title'])),
        $lang->sprintf($lang->rpgcalendar_pm_body, strip_tags((string)$data['title']), $date_str, $link)
    );
}

/**
 * Sends a PM from the current user to the given usernames exactly the way
 * private.php does (PMDataHandler + private_do_send_end), so notification
 * plugins react as for a normal PM. $pm/$pmhandler/$pminfo are made global
 * because the hook listeners read them.
 */
function rpgcalendar_native_pm(array $usernames, string $subject, string $message): void
{
    global $mybb, $plugins, $session, $pm, $pmhandler, $pminfo;

    if ((int)($mybb->settings['enablepms'] ?? 1) !== 1 || !$usernames) {
        return;
    }

    require_once MYBB_ROOT . 'inc/datahandlers/pm.php';

    $pm = [
        'subject'     => $subject,
        'message'     => $message,
        'icon'        => -1,
        'fromid'      => (int)$mybb->user['uid'],
        'do'          => '',
        'pmid'        => 0,
        'to'          => array_values($usernames),
        'bcc'         => [],
        'options'     => [
            'signature'      => 0,
            'disablesmilies' => 0,
            'savecopy'       => 0,
            'readreceipt'    => 0,
        ],
        'saveasdraft' => 0,
    ];
    if (is_object($session)) {
        $pm['ipaddress'] = $session->packedip;
    }

    $pmhandler = new PMDataHandler();
    $pmhandler->admin_override = true; // not blocked by the sender's own limits
    $pmhandler->set_data($pm);
    if (!$pmhandler->validate_pm()) {
        return;
    }
    $pminfo = $pmhandler->insert_pm();
    $plugins->run_hooks('private_do_send_end');
}

// ---------------------------------------------------------------------
// Day view: all events of a single day + an "add event" button
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'day') {
    $day = $mybb->get_input('day', MyBB::INPUT_INT);
    if ($day < 1 || $day > Rpgcalendar_Engine::daysInMonth($year, $month)) {
        error($lang->rpgcalendar_error_invalid_date);
    }

    $startofweek = $rpgcalendar_native->getStartOfWeek();
    $month_data  = $rpgcalendar_engine->getMonthData($year, $month, $startofweek, (int)$mybb->user['uid']);

    // Pull the requested day's cell out of the month grid
    $day_events = $day_birthdays = [];
    foreach ($month_data['grid'] as $week) {
        foreach ($week as $cell) {
            if ($cell !== null && (int)$cell['day'] === $day) {
                $day_events    = $cell['events'];
                $day_birthdays = $cell['birthdays'];
                break 2;
            }
        }
    }

    $rpgcalendar_daydate   = $day . '. ' . htmlspecialchars_uni($lang->{'rpgcalendar_month_' . $month}) . ' ' . $year;
    $rpgcalendar_pagetitle = $lang->rpgcalendar_title . ' - ' . $rpgcalendar_daydate;

    // Events first (weather on top, then holidays, then the rest), birthdays below.
    $day_rows = '';
    foreach ($day_events as $event) {
        $row_badge  = htmlspecialchars_uni($event['badge']);
        $source_key = 'rpgcalendar_source_' . $event['source'];
        $row_type   = isset($lang->$source_key) ? htmlspecialchars_uni($lang->$source_key) : htmlspecialchars_uni($event['source']);

        $title_text = $rpgcalendar_html_titles ? $event['title'] : htmlspecialchars_uni($event['title']);
        if (($event['source'] ?? '') === 'weather') {
            $wlabel = rpgcalendar_weather_label((string)$event['condition']);
            $wnote  = (string)($event['note'] ?? '');
            $title_text = rpgcalendar_weather_symbol((string)$event['condition'], $wlabel, (int)($event['wid'] ?? 0))
                . htmlspecialchars_uni(trim($wlabel . ($wnote !== '' ? ', ' . $wnote : '')));
        } elseif (($event['source'] ?? '') === 'holiday') {
            $title_text = rpgcalendar_holiday_symbol() . $title_text;
        }
        // Detail link: native events, extended events (set by the engine) and
        // providers all carry a raw-'&' url which we escape exactly once here.
        $detail_url = (string)$event['url'];
        if ($detail_url !== '') {
            $row_title = '<a href="' . htmlspecialchars_uni($detail_url) . '">' . $title_text . '</a>';
        } else {
            $row_title = $title_text;
        }
        eval('$day_rows .= "' . $templates->get('rpgcalendar_day_row') . '";');
    }
    foreach ($day_birthdays as $entry) {
        $row_badge = 'birthday';
        $row_type  = htmlspecialchars_uni($lang->rpgcalendar_birthday);
        $row_title = build_profile_link(
            format_name(htmlspecialchars_uni($entry['username']), (int)$entry['usergroup'], (int)$entry['displaygroup']),
            (int)$entry['uid']
        );
        if ($entry['age'] !== null) {
            $row_title .= ' (' . (int)$entry['age'] . ')';
        }
        eval('$day_rows .= "' . $templates->get('rpgcalendar_day_row') . '";');
    }
    if ($day_rows === '') {
        eval('$day_rows = "' . $templates->get('rpgcalendar_day_empty') . '";');
    }

    $rpgcalendar_addbutton = '';
    if (Rpgcalendar_Frontend::canAddEvents()) {
        $add_url = 'rpgcalendar.php?action=add&amp;year=' . $year . '&amp;month=' . $month . '&amp;day=' . $day;
        eval('$rpgcalendar_addbutton = "' . $templates->get('rpgcalendar_day_addbutton') . '";');
    }

    $day_backurl = 'rpgcalendar.php?year=' . $year . '&amp;month=' . $month;

    add_breadcrumb($rpgcalendar_daydate);
    eval('$page = "' . $templates->get('rpgcalendar_day') . '";');
    output_page($page);
    exit;
}

// ---------------------------------------------------------------------
// Add-event form
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'add') {
    if (!Rpgcalendar_Frontend::canAddEvents()) {
        error_no_permission();
    }

    $day = $mybb->get_input('day', MyBB::INPUT_INT);
    if ($day < 1 || $day > Rpgcalendar_Engine::daysInMonth($year, $month)) {
        $day = 1;
    }

    $values = [
        'title'              => '',
        'description'        => '',
        'start_day'          => $day,
        'start_month'        => $month,
        'start_year'         => $year,
        'end_day'            => $day,
        'end_month'          => $month,
        'end_year'           => $year,
        'multiday'           => 0,
        'repeat_type'        => 0,
        'repeat_interval'    => 1,
        'repeat_until_day'   => 0,
        'repeat_until_month' => 0,
        'repeat_until_year'  => 0,
        'important'          => 0,
        'calendar_id'        => 0,
        'nav_year'           => $year,
        'nav_month'          => $month,
        'nav_day'            => $day,
    ];
    rpgcalendar_output_event_form($values, [], '');
    exit;
}

// ---------------------------------------------------------------------
// Save a new event (POST)
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'do_add') {
    if (!Rpgcalendar_Frontend::canAddEvents()) {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));

    list($data, $errors) = Rpgcalendar_Frontend::readEventInput();
    list($invite_uids, $invite_errors, $invite_names) = Rpgcalendar_Frontend::resolveInvites(
        $mybb->get_input('invites'), (int)$mybb->user['uid']
    );
    $errors = array_merge($errors, $invite_errors);

    // Resolve + authorize the chosen sub-calendar
    $calendar = $rpgcalendar_database->getCalendar((int)$data['calendar_id']);
    if ($calendar === null || !Rpgcalendar_Frontend::canWriteCalendar($calendar)) {
        $errors[] = $lang->rpgcalendar_error_invalid_calendar;
    }

    if ($errors) {
        $values = array_merge($data, [
            'multiday'    => ($mybb->get_input('multiday', MyBB::INPUT_INT) === 1) ? 1 : 0,
            'calendar_id' => (int)$data['calendar_id'],
            'nav_year'    => $year,
            'nav_month'   => $month,
            'nav_day'     => $mybb->get_input('nav_day', MyBB::INPUT_INT) ?: $data['start_day'],
        ]);
        rpgcalendar_output_event_form($values, $errors, $mybb->get_input('invites'));
        exit;
    }

    // A global calendar => public event; a personal calendar => private to author
    $is_global = ((int)$calendar['type'] === 1);
    $data['calendar_id'] = (int)$calendar['cid'];
    $data['uid']         = (int)$mybb->user['uid'];
    $data['visible']     = 1;                    // frontend events are immediately visible
    $data['private']     = $is_global ? 0 : 1;
    $data['source']      = 'manual';

    $new_eid = $rpgcalendar_database->saveEvent($data, 0);

    if ($invite_uids) {
        $rpgcalendar_database->addInvites($new_eid, $invite_uids, (int)$mybb->user['uid']);
        rpgcalendar_send_invite_pms($new_eid, $invite_uids, $data);
    }

    redirect(
        'rpgcalendar.php?action=day&year=' . (int)$data['start_year'] . '&month=' . (int)$data['start_month'] . '&day=' . (int)$data['start_day'],
        $lang->rpgcalendar_event_saved
    );
}

// ---------------------------------------------------------------------
// Respond to an invite (accept / decline, POST)
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'invite_respond') {
    if ((int)$mybb->user['uid'] <= 0) {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));

    $eid   = $mybb->get_input('eid', MyBB::INPUT_INT);
    $reply = $mybb->get_input('reply');
    $invite = $rpgcalendar_database->getInvite($eid, (int)$mybb->user['uid']);
    if ($invite === null) {
        error_no_permission();
    }

    $status = ($reply === 'accept')
        ? Rpgcalendar_Frontend::STATUS_ACCEPTED
        : Rpgcalendar_Frontend::STATUS_DECLINED;
    $rpgcalendar_database->setInviteStatus($eid, (int)$mybb->user['uid'], $status);

    $back_year  = $mybb->get_input('year', MyBB::INPUT_INT) ?: $year;
    $back_month = $mybb->get_input('month', MyBB::INPUT_INT) ?: $month;
    $back_day   = $mybb->get_input('day', MyBB::INPUT_INT) ?: 1;
    redirect(
        'rpgcalendar.php?action=event&source=extended&eid=' . $eid . '&year=' . $back_year . '&month=' . $back_month . '&day=' . $back_day,
        $lang->rpgcalendar_invite_responded
    );
}

// ---------------------------------------------------------------------
// Edit an existing (extended) event
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'edit') {
    if (!Rpgcalendar_Frontend::canAddEvents()) {
        error_no_permission();
    }
    $eid   = $mybb->get_input('eid', MyBB::INPUT_INT);
    $event = $rpgcalendar_database->getEvent($eid);
    if ($event === null) {
        error($lang->rpgcalendar_event_notfound);
    }
    if (!Rpgcalendar_Frontend::canManageEvent($event)) {
        error_no_permission();
    }

    // Split repeat_until_key back into day/month/year for the form
    $until = ['day' => 0, 'month' => 0, 'year' => 0];
    if ((int)$event['repeat_until_key'] > 0) {
        $key = (int)$event['repeat_until_key'];
        $until['year']  = intdiv($key, 10000);
        $rest           = $key - $until['year'] * 10000;
        $until['month'] = intdiv($rest, 100);
        $until['day']   = $rest - $until['month'] * 100;
    }

    // Existing invites as a comma-separated username list for the invite field
    $invite_names = [];
    foreach ($rpgcalendar_database->getInvites($eid) as $inv) {
        $invite_names[] = (string)$inv['username'];
    }
    $invite_raw = implode(', ', $invite_names);

    $values = [
        'title'              => (string)$event['title'],
        'description'        => (string)$event['description'],
        'start_day'          => (int)$event['start_day'],
        'start_month'        => (int)$event['start_month'],
        'start_year'         => (int)$event['start_year'],
        'end_day'            => (int)$event['end_day'],
        'end_month'          => (int)$event['end_month'],
        'end_year'           => (int)$event['end_year'],
        'multiday'           => ((int)$event['start_key'] !== (int)$event['end_key']) ? 1 : 0,
        'repeat_type'        => (int)$event['repeat_type'],
        'repeat_interval'    => (int)$event['repeat_interval'],
        'repeat_until_day'   => $until['day'],
        'repeat_until_month' => $until['month'],
        'repeat_until_year'  => $until['year'],
        'important'          => (int)$event['important'],
        'calendar_id'        => (int)$event['calendar_id'],
        'nav_year'           => (int)$event['start_year'],
        'nav_month'          => (int)$event['start_month'],
        'nav_day'            => (int)$event['start_day'],
    ];
    rpgcalendar_output_event_form($values, [], $invite_raw, $eid);
    exit;
}

// ---------------------------------------------------------------------
// Save an edited event (POST)
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'do_edit') {
    if (!Rpgcalendar_Frontend::canAddEvents()) {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));

    $eid   = $mybb->get_input('eid', MyBB::INPUT_INT);
    $event = $rpgcalendar_database->getEvent($eid);
    if ($event === null) {
        error($lang->rpgcalendar_event_notfound);
    }
    if (!Rpgcalendar_Frontend::canManageEvent($event)) {
        error_no_permission();
    }

    list($data, $errors) = Rpgcalendar_Frontend::readEventInput();
    list($invite_uids, $invite_errors, $invite_names) = Rpgcalendar_Frontend::resolveInvites(
        $mybb->get_input('invites'), (int)$event['uid']
    );
    $errors = array_merge($errors, $invite_errors);

    // Resolve + authorize the chosen sub-calendar. Keeping the event's current
    // calendar is always allowed; write permission is only required to MOVE it
    // to a different one.
    $calendar = $rpgcalendar_database->getCalendar((int)$data['calendar_id']);
    $keeping_current = ($calendar !== null && (int)$calendar['cid'] === (int)$event['calendar_id']);
    if ($calendar === null || (!$keeping_current && !Rpgcalendar_Frontend::canWriteCalendar($calendar))) {
        $errors[] = $lang->rpgcalendar_error_invalid_calendar;
    }

    if ($errors) {
        $values = array_merge($data, [
            'multiday'    => ($mybb->get_input('multiday', MyBB::INPUT_INT) === 1) ? 1 : 0,
            'calendar_id' => (int)$data['calendar_id'],
            'nav_year'    => $year,
            'nav_month'   => $month,
            'nav_day'     => $mybb->get_input('nav_day', MyBB::INPUT_INT) ?: $data['start_day'],
        ]);
        rpgcalendar_output_event_form($values, $errors, $mybb->get_input('invites'), $eid);
        exit;
    }

    $is_global = ((int)$calendar['type'] === 1);
    $data['calendar_id'] = (int)$calendar['cid'];
    $data['visible']     = (int)$event['visible'];   // keep current visibility
    $data['private']     = $is_global ? 0 : 1;
    $rpgcalendar_database->saveEvent($data, $eid);

    // Invite diff: add new (with PM), remove dropped, keep the rest and their status
    $current_uids = $rpgcalendar_database->getInviteUids($eid);
    $to_add    = array_values(array_diff($invite_uids, $current_uids));
    $to_remove = array_values(array_diff($current_uids, $invite_uids));
    foreach ($to_remove as $ruid) {
        $rpgcalendar_database->deleteInvite($eid, (int)$ruid);
    }
    if ($to_add) {
        $rpgcalendar_database->addInvites($eid, $to_add, (int)$event['uid']);
        rpgcalendar_send_invite_pms($eid, $to_add, $data);
    }

    redirect(
        'rpgcalendar.php?action=event&source=extended&eid=' . $eid . '&year=' . (int)$data['start_year'] . '&month=' . (int)$data['start_month'] . '&day=' . (int)$data['start_day'],
        $lang->rpgcalendar_event_updated
    );
}

// ---------------------------------------------------------------------
// Delete an (extended) event (POST)
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'delete') {
    if ((int)$mybb->user['uid'] <= 0) {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));

    $eid   = $mybb->get_input('eid', MyBB::INPUT_INT);
    $event = $rpgcalendar_database->getEvent($eid);
    if ($event === null) {
        error($lang->rpgcalendar_event_notfound);
    }
    if (!Rpgcalendar_Frontend::canManageEvent($event)) {
        error_no_permission();
    }

    $back_year  = (int)$event['start_year'];
    $back_month = (int)$event['start_month'];
    $back_day   = (int)$event['start_day'];
    $rpgcalendar_database->deleteEvent($eid);   // also removes its invites

    redirect(
        'rpgcalendar.php?action=day&year=' . $back_year . '&month=' . $back_month . '&day=' . $back_day,
        $lang->rpgcalendar_event_deleted
    );
}

// ---------------------------------------------------------------------
// Save colour/visibility (and, for own personal calendars, name) of one row
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'do_calendar_row') {
    if ((int)$mybb->user['uid'] <= 0) {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));

    $viewer_uid = (int)$mybb->user['uid'];
    // calkey is the calendar key: real 'cal-{cid}' or a virtual one
    // ('plotcat-{cid}', 'birthdays', 'feed-{fid}').
    $calkey = trim($mybb->get_input('calkey'));
    if (!preg_match('/^(cal-\d+|plotcat-\d+|feed-\d+|birthdays)$/', $calkey)) {
        error($lang->rpgcalendar_error_invalid_calendar);
    }

    $color   = Rpgcalendar_Frontend::sanitizeColor($mybb->get_input('color'));
    $visible = ($mybb->get_input('visible', MyBB::INPUT_INT) === 1) ? 1 : 0;

    // Colour/visibility are per-viewer preferences and safe for any key.
    $rpgcalendar_database->setPref($viewer_uid, $calkey, $color !== '' ? $color : null, $visible);

    // For an own personal real calendar, also allow rename + default colour.
    if (preg_match('/^cal-(\d+)$/', $calkey, $m)) {
        $cid = (int)$m[1];
        $cal = $rpgcalendar_database->getCalendar($cid);
        if ($cal !== null && (int)$cal['type'] === 0 && (int)$cal['owner_uid'] === $viewer_uid) {
            $upd  = [];
            $name = trim($mybb->get_input('name'));
            if ($name !== '') {
                $upd['name'] = my_substr($name, 0, 120);
            }
            if ($color !== '') {
                $upd['default_color'] = $color;
            }
            if ($upd) {
                $rpgcalendar_database->updateCalendar($cid, $upd);
            }
        }
    }

    redirect('rpgcalendar.php?action=calendars&year=' . $year . '&month=' . $month,
        $lang->rpgcalendar_calendar_saved);
}

// ---------------------------------------------------------------------
// Create a new personal calendar
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'do_calendar_add') {
    if ((int)$mybb->user['uid'] <= 0) {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));

    $name = trim($mybb->get_input('name'));
    if ($name === '') {
        error($lang->rpgcalendar_error_calendar_name);
    }
    $color = Rpgcalendar_Frontend::sanitizeColor($mybb->get_input('color'));

    $rpgcalendar_database->createCalendar([
        'name'          => my_substr($name, 0, 120),
        'owner_uid'     => (int)$mybb->user['uid'],
        'type'          => 0,
        'default_color' => $color !== '' ? $color : '#3b82f6',
    ]);

    redirect('rpgcalendar.php?action=calendars&year=' . $year . '&month=' . $month,
        $lang->rpgcalendar_calendar_saved);
}

// ---------------------------------------------------------------------
// Delete a personal calendar (events move to another personal calendar)
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'do_calendar_delete') {
    if ((int)$mybb->user['uid'] <= 0) {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));

    $viewer_uid = (int)$mybb->user['uid'];
    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
    $cal = $rpgcalendar_database->getCalendar($cid);
    if ($cal === null || (int)$cal['type'] !== 0 || (int)$cal['owner_uid'] !== $viewer_uid) {
        error_no_permission();
    }
    if ($rpgcalendar_database->countPersonalCalendars($viewer_uid) <= 1) {
        error($lang->rpgcalendar_error_last_calendar);
    }

    // Fallback = another personal calendar of this user
    $fallback = 0;
    foreach ($rpgcalendar_database->getCalendarsForUser($viewer_uid) as $c) {
        if ((int)$c['type'] === 0 && (int)$c['owner_uid'] === $viewer_uid && (int)$c['cid'] !== $cid) {
            $fallback = (int)$c['cid'];
            break;
        }
    }
    $rpgcalendar_database->deleteCalendar($cid, $fallback);

    redirect('rpgcalendar.php?action=calendars&year=' . $year . '&month=' . $month,
        $lang->rpgcalendar_calendar_deleted);
}

// ---------------------------------------------------------------------
// Share a personal calendar: add a member (invite, with PM)
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'do_share_add') {
    if ((int)$mybb->user['uid'] <= 0) {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));

    $viewer_uid = (int)$mybb->user['uid'];
    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
    $cal = $rpgcalendar_database->getCalendar($cid);
    if ($cal === null || (int)$cal['type'] !== 0 || (int)$cal['owner_uid'] !== $viewer_uid) {
        error_no_permission();
    }

    require_once MYBB_ROOT . 'inc/functions_user.php';
    $name = trim($mybb->get_input('username'));
    $user = ($name !== '') ? get_user_by_username($name) : null;
    if (!$user || empty($user['uid'])) {
        error($lang->sprintf($lang->rpgcalendar_error_unknown_user, htmlspecialchars_uni($name)));
    }
    $muid = (int)$user['uid'];
    if ($muid === $viewer_uid) {
        error($lang->rpgcalendar_error_share_self);
    }

    $role = ($mybb->get_input('role') === 'editor') ? 1 : 0;
    $rpgcalendar_database->addMember($cid, $muid, $role, 0); // status = invited

    // get_user_by_username() only returns the uid, so fetch the full row to get
    // the username the PM datahandler needs as a recipient.
    $recipient = get_user($muid);
    $link = $mybb->settings['bburl'] . '/rpgcalendar.php?action=calendars';
    rpgcalendar_native_pm(
        [(string)($recipient['username'] ?? '')],
        $lang->sprintf($lang->rpgcalendar_share_pm_subject, strip_tags((string)$cal['name'])),
        $lang->sprintf($lang->rpgcalendar_share_pm_body, strip_tags((string)$cal['name']), $link)
    );

    redirect('rpgcalendar.php?action=share&cid=' . $cid, $lang->rpgcalendar_share_saved);
}

// ---------------------------------------------------------------------
// Share a personal calendar: remove a member
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'do_share_remove') {
    if ((int)$mybb->user['uid'] <= 0) {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));

    $viewer_uid = (int)$mybb->user['uid'];
    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
    $cal = $rpgcalendar_database->getCalendar($cid);
    if ($cal === null || (int)$cal['type'] !== 0 || (int)$cal['owner_uid'] !== $viewer_uid) {
        error_no_permission();
    }
    $rpgcalendar_database->removeMember($cid, $mybb->get_input('uid', MyBB::INPUT_INT));

    redirect('rpgcalendar.php?action=share&cid=' . $cid, $lang->rpgcalendar_share_removed);
}

// ---------------------------------------------------------------------
// Respond to a calendar share invitation (accept / decline)
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'do_share_respond') {
    if ((int)$mybb->user['uid'] <= 0) {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));

    $viewer_uid = (int)$mybb->user['uid'];
    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
    $member = $rpgcalendar_database->getMember($cid, $viewer_uid);
    if ($member === null || (int)$member['status'] !== 0) {
        error_no_permission();
    }
    if ($mybb->get_input('reply') === 'accept') {
        $rpgcalendar_database->setMemberStatus($cid, $viewer_uid, 1);
    } else {
        $rpgcalendar_database->removeMember($cid, $viewer_uid);
    }

    redirect('rpgcalendar.php?action=calendars&year=' . $year . '&month=' . $month,
        $lang->rpgcalendar_invite_responded);
}

// ---------------------------------------------------------------------
// Self-subscribe to a joinable global calendar
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'do_calendar_join') {
    if ((int)$mybb->user['uid'] <= 0) {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));

    $viewer_uid = (int)$mybb->user['uid'];
    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
    $cal = $rpgcalendar_database->getCalendar($cid);
    if ($cal === null || (int)$cal['type'] !== 1 || (int)$cal['joinable'] !== 1) {
        error_no_permission();
    }
    // Participant: accepted editor so they can contribute events
    $rpgcalendar_database->addMember($cid, $viewer_uid, 1, 1);

    redirect('rpgcalendar.php?action=calendars&year=' . $year . '&month=' . $month,
        $lang->rpgcalendar_joined);
}

// ---------------------------------------------------------------------
// Leave a shared or joined calendar (removes own membership)
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'do_calendar_leave') {
    if ((int)$mybb->user['uid'] <= 0) {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));

    $viewer_uid = (int)$mybb->user['uid'];
    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
    if ($rpgcalendar_database->getMember($cid, $viewer_uid) === null) {
        error_no_permission();
    }
    $rpgcalendar_database->removeMember($cid, $viewer_uid);

    redirect('rpgcalendar.php?action=calendars&year=' . $year . '&month=' . $month,
        $lang->rpgcalendar_left);
}

// ---------------------------------------------------------------------
// Manage sharing of one own personal calendar (member list + add form)
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'share') {
    if ((int)$mybb->user['uid'] <= 0) {
        error_no_permission();
    }
    $viewer_uid = (int)$mybb->user['uid'];
    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
    $cal = $rpgcalendar_database->getCalendar($cid);
    if ($cal === null || (int)$cal['type'] !== 0 || (int)$cal['owner_uid'] !== $viewer_uid) {
        error_no_permission();
    }

    $post_code       = $mybb->post_code;
    $share_cal_name  = htmlspecialchars_uni((string)$cal['name']);
    $share_backurl   = 'rpgcalendar.php?action=calendars&amp;year=' . $year . '&amp;month=' . $month;
    $rpgcalendar_pagetitle = $lang->rpgcalendar_title . ' - ' . $lang->rpgcalendar_share_title;

    $member_rows = '';
    foreach ($rpgcalendar_database->getCalendarMembers($cid) as $m) {
        $member_link = build_profile_link(
            format_name(htmlspecialchars_uni($m['username']), (int)$m['usergroup'], (int)$m['displaygroup']),
            (int)$m['uid']
        );
        $role_label   = ((int)$m['role'] === 1) ? $lang->rpgcalendar_role_editor : $lang->rpgcalendar_role_viewer;
        $status_label = ((int)$m['status'] === 1) ? $lang->rpgcalendar_share_accepted : $lang->rpgcalendar_share_pending;
        $member_uid   = (int)$m['uid'];
        eval('$member_rows .= "' . $templates->get('rpgcalendar_share_member') . '";');
    }
    if ($member_rows === '') {
        eval('$member_rows = "' . $templates->get('rpgcalendar_share_empty') . '";');
    }

    // Username autocomplete on the "add member" field (single recipient)
    $ac_target   = '#rpgcalendar_shareuser';
    $ac_multiple = 'false';
    eval('$rpgcalendar_autocomplete = "' . $templates->get('rpgcalendar_autocomplete') . '";');

    add_breadcrumb($lang->rpgcalendar_share_title);
    eval('$page = "' . $templates->get('rpgcalendar_share') . '";');
    output_page($page);
    exit;
}

// ---------------------------------------------------------------------
// "My calendars": per-user colour + visibility, personal calendar CRUD
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'calendars') {
    if ((int)$mybb->user['uid'] <= 0) {
        error_no_permission();
    }
    $viewer_uid = (int)$mybb->user['uid'];
    $rpgcalendar_database->ensurePersonalCalendar($viewer_uid);

    $calendars      = $rpgcalendar_database->getCalendarsForUser($viewer_uid);
    $prefs          = $rpgcalendar_database->getUserPrefs($viewer_uid);
    $personal_count = $rpgcalendar_database->countPersonalCalendars($viewer_uid);
    $post_code      = $mybb->post_code;

    $calendar_rows = '';
    foreach ($calendars as $cal) {
        $cid     = (int)$cal['cid'];
        $key     = 'cal-' . $cid;
        $cal_key = $key;
        $pref    = $prefs[$key] ?? null;

        $eff_color = ($pref !== null && $pref['color'] !== '') ? $pref['color'] : (string)$cal['default_color'];
        $eff_color = Rpgcalendar_Frontend::sanitizeColor($eff_color) ?: '#3b82f6';
        $vis_checked = ($pref === null || (int)$pref['visible'] === 1) ? ' checked="checked"' : '';

        $is_own_personal = ((int)$cal['type'] === 0 && (int)$cal['owner_uid'] === $viewer_uid);
        $cal_type_label  = ((int)$cal['type'] === 1)
            ? htmlspecialchars_uni($lang->rpgcalendar_cal_global)
            : htmlspecialchars_uni($lang->rpgcalendar_cal_personal);

        // Editable name for own personal calendars, else a static label
        if ($is_own_personal) {
            $cal_name_field = '<input type="text" name="name" value="' . htmlspecialchars_uni((string)$cal['name'])
                . '" maxlength="120" class="textbox" />';
        } else {
            $cal_name_field = htmlspecialchars_uni((string)$cal['name']);
        }

        // Per-row controls: share (own personal), delete (own personal, not the
        // last one), leave (shared/joined calendars the viewer is a member of).
        $cal_controls = '';
        if ($is_own_personal) {
            $share_url = 'rpgcalendar.php?action=share&amp;cid=' . $cid;
            eval('$cal_controls .= "' . $templates->get('rpgcalendar_calendar_share') . '";');
            if ($personal_count > 1) {
                eval('$cal_controls .= "' . $templates->get('rpgcalendar_calendar_delete') . '";');
            }
        } elseif ((int)$cal['owner_uid'] !== $viewer_uid
            && $rpgcalendar_database->getMember($cid, $viewer_uid) !== null) {
            // shared personal or joined joinable global -> allow leaving
            eval('$cal_controls .= "' . $templates->get('rpgcalendar_calendar_leave') . '";');
        }

        eval('$calendar_rows .= "' . $templates->get('rpgcalendar_calendar_row') . '";');
    }

    // Virtual calendars (plot categories, birthdays): colour + visibility only,
    // no rename/delete/share/leave. Keyed by their virtual calendar key.
    foreach (Rpgcalendar_Engine::collectVirtualCalendars() as $vc) {
        $cal_key = (string)$vc['key'];
        $pref    = $prefs[$cal_key] ?? null;

        $eff_color = ($pref !== null && $pref['color'] !== '') ? $pref['color'] : (string)$vc['default_color'];
        $eff_color = Rpgcalendar_Frontend::sanitizeColor($eff_color) ?: '#3b82f6';
        $vis_checked = ($pref === null || (int)$pref['visible'] === 1) ? ' checked="checked"' : '';

        $cal_name_field = htmlspecialchars_uni((string)$vc['name']);
        $cal_type_label = htmlspecialchars_uni($lang->rpgcalendar_cal_system);
        $cal_controls   = '';

        eval('$calendar_rows .= "' . $templates->get('rpgcalendar_calendar_row') . '";');
    }

    eval('$calendar_addform = "' . $templates->get('rpgcalendar_calendar_addform') . '";');

    // Pending share invitations (accept / decline)
    $calendar_invites = '';
    foreach ($rpgcalendar_database->getPendingCalendarInvites($viewer_uid) as $inv) {
        $inv_cid   = (int)$inv['cid'];
        $inv_name  = htmlspecialchars_uni((string)$inv['name']);
        $inv_owner_user = get_user((int)$inv['owner_uid']);
        $inv_owner = build_profile_link(
            htmlspecialchars_uni((string)($inv_owner_user['username'] ?? '')),
            (int)$inv['owner_uid']
        );
        $inv_role  = ((int)$inv['role'] === 1) ? $lang->rpgcalendar_role_editor : $lang->rpgcalendar_role_viewer;
        eval('$calendar_invites .= "' . $templates->get('rpgcalendar_calendar_invite_row') . '";');
    }
    $calendar_invites_block = '';
    if ($calendar_invites !== '') {
        eval('$calendar_invites_block = "' . $templates->get('rpgcalendar_calendar_invites') . '";');
    }

    // Global calendars the viewer may join (self-subscribe)
    $calendar_joinable = '';
    foreach ($rpgcalendar_database->getJoinableCalendars($viewer_uid) as $jc) {
        $join_cid  = (int)$jc['cid'];
        $join_name = htmlspecialchars_uni((string)$jc['name']);
        $join_desc = htmlspecialchars_uni((string)$jc['description']);
        eval('$calendar_joinable .= "' . $templates->get('rpgcalendar_calendar_joinable_row') . '";');
    }
    $calendar_joinable_block = '';
    if ($calendar_joinable !== '') {
        eval('$calendar_joinable_block = "' . $templates->get('rpgcalendar_calendar_joinable') . '";');
    }

    $rpgcalendar_pagetitle = $lang->rpgcalendar_title . ' - ' . $lang->rpgcalendar_calendars_title;
    $calendars_backurl = 'rpgcalendar.php?year=' . $year . '&amp;month=' . $month;

    add_breadcrumb($lang->rpgcalendar_calendars_title);
    eval('$page = "' . $templates->get('rpgcalendar_calendars') . '";');
    output_page($page);
    exit;
}

// ---------------------------------------------------------------------
// Single event detail view: keeps native events inside rpgcalendar.php
// instead of following the old calendar.php link.
// ---------------------------------------------------------------------
if ($rpgcalendar_action === 'event') {
    $event_source = $mybb->get_input('source');
    $eid          = $mybb->get_input('eid', MyBB::INPUT_INT);
    $viewer_uid   = (int)$mybb->user['uid'];
    $day          = $mybb->get_input('day', MyBB::INPUT_INT);

    // Normalize both sources into one detail record.
    $detail      = null;
    $is_extended = false;
    $detail_date = '';

    if ($event_source === 'extended') {
        $ext = $rpgcalendar_database->getEvent($eid);
        if ($ext !== null && (int)$ext['visible'] === 1) {
            // Calendar-based access (matches the month-grid query), plus legacy
            // per-event invites and team override.
            $access_cids = $rpgcalendar_database->getViewerAccessCids($viewer_uid);
            $can_see = in_array((int)$ext['calendar_id'], $access_cids, true)
                || Rpgcalendar_Frontend::isTeam()
                || ($viewer_uid > 0 && $rpgcalendar_database->getInvite($eid, $viewer_uid) !== null);
            if ($can_see) {
                $is_extended = true;
                $detail = [
                    'name'        => (string)$ext['title'],
                    'description' => (string)$ext['description'],
                    'uid'         => (int)$ext['uid'],
                ];
                $detail_date = (int)$ext['start_day'] . '.' . (int)$ext['start_month'] . '.' . (int)$ext['start_year'];
                if ((int)$ext['start_key'] !== (int)$ext['end_key']) {
                    $detail_date .= ' &ndash; ' . (int)$ext['end_day'] . '.' . (int)$ext['end_month'] . '.' . (int)$ext['end_year'];
                }
            }
        }
    } else {
        $native_event = $rpgcalendar_native->getNativeEvent($eid);
        if ($native_event !== null) {
            $detail = [
                'name'        => (string)$native_event['name'],
                'description' => (string)$native_event['description'],
                'uid'         => (int)$native_event['uid'],
            ];
            if ($day >= 1 && $day <= Rpgcalendar_Engine::daysInMonth($year, $month)) {
                $detail_date = $day . '. ' . htmlspecialchars_uni($lang->{'rpgcalendar_month_' . $month}) . ' ' . $year;
            }
        }
    }

    if ($detail === null) {
        error($lang->rpgcalendar_event_notfound);
    }

    $event_date = $detail_date;

    // Description parsed like the native calendar (MyCode + smilies, no HTML)
    $event_description = '';
    if (trim($detail['description']) !== '') {
        require_once MYBB_ROOT . 'inc/class_parser.php';
        $parser = new postParser();
        $event_description = $parser->parse_message($detail['description'], [
            'allow_html'      => 0,
            'allow_mycode'    => 1,
            'allow_smilies'   => 1,
            'allow_imgcode'   => 1,
            'allow_videocode' => 1,
            'nl2br'           => 1,
            'filter_badwords' => 1,
        ]);
    }
    if (trim($event_description) === '') {
        $event_description = $lang->rpgcalendar_event_nodescription;
    }

    $event_title = $rpgcalendar_html_titles
        ? $detail['name']
        : htmlspecialchars_uni($detail['name']);
    // Always plain for the page <title> and breadcrumb (no markup allowed there)
    $event_title_plain = htmlspecialchars_uni(strip_tags($detail['name']));

    $event_author_line = '';
    if ($detail['uid'] > 0) {
        $author = get_user($detail['uid']);
        if (!empty($author['uid'])) {
            $author_link = build_profile_link(
                format_name(htmlspecialchars_uni($author['username']), (int)$author['usergroup'], (int)$author['displaygroup']),
                (int)$author['uid']
            );
            $event_author_line = '<span class="rpgcalendar_eventview_author">'
                . $lang->sprintf($lang->rpgcalendar_event_by, $author_link) . '</span>';
        }
    }

    // Invite list, the viewer's accept/decline form, and manage buttons
    // (extended events only).
    $event_invites       = '';
    $event_response_form = '';
    $event_manage        = '';
    if ($is_extended) {
        if (Rpgcalendar_Frontend::canManageEvent($ext)) {
            $manage_day = ($day >= 1) ? $day : 1;
            $edit_url   = 'rpgcalendar.php?action=edit&amp;eid=' . $eid
                . '&amp;year=' . $year . '&amp;month=' . $month . '&amp;day=' . $manage_day;
            $post_code  = $mybb->post_code;
            eval('$event_manage = "' . $templates->get('rpgcalendar_event_manage') . '";');
        }

        $invites = $rpgcalendar_database->getInvites($eid);
        if ($invites) {
            $invite_items = '';
            foreach ($invites as $inv) {
                $invite_link = build_profile_link(
                    format_name(htmlspecialchars_uni($inv['username']), (int)$inv['usergroup'], (int)$inv['displaygroup']),
                    (int)$inv['uid']
                );
                $status_class = 'pending';
                if ((int)$inv['status'] === Rpgcalendar_Frontend::STATUS_ACCEPTED) {
                    $status_class = 'accepted';
                } elseif ((int)$inv['status'] === Rpgcalendar_Frontend::STATUS_DECLINED) {
                    $status_class = 'declined';
                }
                $invite_status = htmlspecialchars_uni($lang->{'rpgcalendar_invite_status_' . $status_class});
                eval('$invite_items .= "' . $templates->get('rpgcalendar_event_invite') . '";');
            }
            eval('$event_invites = "' . $templates->get('rpgcalendar_event_invites') . '";');
        }

        if ($viewer_uid > 0) {
            $my_invite = $rpgcalendar_database->getInvite($eid, $viewer_uid);
            if ($my_invite !== null) {
                $post_code  = $mybb->post_code;
                $resp_day   = ($day >= 1) ? $day : 1;
                $my_status  = (int)$my_invite['status'];
                $my_status_class = 'pending';
                if ($my_status === Rpgcalendar_Frontend::STATUS_ACCEPTED) {
                    $my_status_class = 'accepted';
                } elseif ($my_status === Rpgcalendar_Frontend::STATUS_DECLINED) {
                    $my_status_class = 'declined';
                }
                $my_status_label = htmlspecialchars_uni($lang->{'rpgcalendar_invite_status_' . $my_status_class});
                eval('$event_response_form = "' . $templates->get('rpgcalendar_event_response') . '";');
            }
        }
    }

    if ($day >= 1 && $day <= Rpgcalendar_Engine::daysInMonth($year, $month)) {
        $event_backurl = 'rpgcalendar.php?action=day&amp;year=' . $year . '&amp;month=' . $month . '&amp;day=' . $day;
    } else {
        $event_backurl = 'rpgcalendar.php?year=' . $year . '&amp;month=' . $month;
    }
    $event_pagetitle = $lang->rpgcalendar_title . ' - ' . $event_title_plain;

    add_breadcrumb(strip_tags($detail['name']));

    eval('$page = "' . $templates->get('rpgcalendar_event') . '";');
    output_page($page);
    exit;
}

// Weather / holiday detail view (reuses the rpgcalendar_event template). Renders a
// short, localized description text for a single weather or holiday entry.
if ($rpgcalendar_action === 'weatherinfo' || $rpgcalendar_action === 'holidayinfo') {
    $is_weather = ($rpgcalendar_action === 'weatherinfo');
    $row = $is_weather
        ? $rpgcalendar_database->getWeather($mybb->get_input('wid', MyBB::INPUT_INT))
        : $rpgcalendar_database->getHoliday($mybb->get_input('hid', MyBB::INPUT_INT));
    if ($row === null || (int)$row['visible'] !== 1) {
        error($lang->rpgcalendar_detail_notfound);
    }

    $loc     = $rpgcalendar_database->getLocation((int)$row['locid']);
    $locname = $loc ? htmlspecialchars_uni($loc['name']) : '';

    // Occurrence date from the URL (falls back to the row's start date).
    $y = $mybb->get_input('year', MyBB::INPUT_INT);
    $m = $mybb->get_input('month', MyBB::INPUT_INT);
    $d = $mybb->get_input('day', MyBB::INPUT_INT);
    if ($m < 1 || $m > 12 || $d < 1 || $d > Rpgcalendar_Engine::daysInMonth($y, $m)) {
        $y = (int)$row['start_year'];
        $m = (int)$row['start_month'];
        $d = (int)$row['start_day'];
    }
    $event_date = $d . '.' . $m . '.' . $y;
    $span = Rpgcalendar_Engine::dayNumber((int)$row['end_year'], (int)$row['end_month'], (int)$row['end_day'])
        - Rpgcalendar_Engine::dayNumber((int)$row['start_year'], (int)$row['start_month'], (int)$row['start_day']);
    if ($span > 0) {
        $end = Rpgcalendar_Engine::fromDayNumber(Rpgcalendar_Engine::dayNumber($y, $m, $d) + $span);
        $event_date .= ' &ndash; ' . $end['d'] . '.' . $end['m'] . '.' . $end['y'];
    }

    $parts = [];
    if ($is_weather) {
        $label             = rpgcalendar_weather_label((string)$row['wcondition']);
        $event_title_plain = $label;
        $event_title       = rpgcalendar_weather_symbol((string)$row['wcondition'], $label, (int)$row['wid']) . htmlspecialchars_uni($label);
        $parts[] = $lang->rpgcalendar_detail_condition . ' ' . htmlspecialchars_uni($label);
        if ($locname !== '') {
            $parts[] = $lang->sprintf($lang->rpgcalendar_weather_in, $locname);
        }
        if ((string)$row['note'] !== '') {
            $parts[] = $lang->rpgcalendar_detail_temp . ' ' . htmlspecialchars_uni((string)$row['note']);
        }
    } else {
        $event_title_plain = (string)$row['title'];
        $event_title       = rpgcalendar_holiday_symbol() . htmlspecialchars_uni((string)$row['title']);

        // Link to the full Wikipedia article (replaces the trimmed "…" dead-end).
        $wiki_url  = trim((string)($row['url'] ?? ''));
        $link_html = '';
        if ($wiki_url !== '' && (strpos($wiki_url, 'https://') === 0 || strpos($wiki_url, 'http://') === 0)) {
            $link_html = '<a href="' . htmlspecialchars_uni($wiki_url) . '" target="_blank" rel="noopener noreferrer nofollow">'
                . $lang->rpgcalendar_wiki_more . '</a>';
        }

        // The description holds the Wikipedia extract followed by the day-off note
        // (blank-line separated). Put the article link right after the extract,
        // i.e. before that note.
        $desc = trim((string)$row['description']);
        if ($desc !== '') {
            $blocks  = preg_split('/\n\n+/', $desc);
            $parts[] = nl2br(htmlspecialchars_uni(array_shift($blocks)));
            if ($link_html !== '') {
                $parts[] = $link_html;
            }
            foreach ($blocks as $block) {
                $parts[] = nl2br(htmlspecialchars_uni($block));
            }
        } elseif ($link_html !== '') {
            $parts[] = $link_html;
        }

        if ($locname !== '') {
            $parts[] = $lang->sprintf($lang->rpgcalendar_holiday_in, $locname);
        }
    }
    $event_description   = $parts ? '<p>' . implode('</p><p>', $parts) . '</p>' : '';
    $event_author_line   = '';
    $event_invites       = '';
    $event_response_form = '';
    $event_manage        = '';

    if ($d >= 1 && $d <= Rpgcalendar_Engine::daysInMonth($y, $m)) {
        $event_backurl = 'rpgcalendar.php?action=day&amp;year=' . $y . '&amp;month=' . $m . '&amp;day=' . $d;
    } else {
        $event_backurl = 'rpgcalendar.php?year=' . $y . '&amp;month=' . $m;
    }
    $kind_label      = $is_weather ? $lang->rpgcalendar_cal_weather : $lang->rpgcalendar_cal_holiday;
    $event_pagetitle = $lang->rpgcalendar_title . ' - ' . $kind_label . ': ' . $event_title_plain;
    add_breadcrumb($event_title_plain);

    eval('$page = "' . $templates->get('rpgcalendar_event') . '";');
    output_page($page);
    exit;
}

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
// Missing-data hint for fictional locations (team must add data by hand)
// ---------------------------------------------------------------------
$rpgcalendar_hint = '';
if (!empty($month_data['hints']) && (int)($mybb->settings['rpgcalendar_weather_hint'] ?? 0) === 1) {
    $hint_items = '';
    foreach ($month_data['hints'] as $h) {
        $kinds = [];
        if (!empty($h['weather'])) {
            $kinds[] = $lang->rpgcalendar_cal_weather;
        }
        if (!empty($h['holiday'])) {
            $kinds[] = $lang->rpgcalendar_cal_holiday;
        }
        $line = $lang->sprintf(
            $lang->rpgcalendar_hint_line,
            htmlspecialchars_uni($h['name']),
            htmlspecialchars_uni($rpgcalendar_monthtitle),
            htmlspecialchars_uni(implode(' & ', $kinds))
        );
        $hint_items .= '<li>' . $line . '</li>';
    }
    eval('$rpgcalendar_hint = "' . $templates->get('rpgcalendar_hint') . '";');
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
        $day_url   = 'rpgcalendar.php?action=day&amp;year=' . $year . '&amp;month=' . $month . '&amp;day=' . $day;

        $birthday_style = Rpgcalendar_Frontend::eventStyle((string)($month_data['birthday_color'] ?? ''));
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

        // Cap chips per cell so crowded days do not blow up the grid
        $day_events = '';
        $shown = 0;
        foreach ($cell['events'] as $event) {
            if ($shown >= 4) {
                break;
            }
            $event_badge = htmlspecialchars_uni($event['badge']);
            // Sub-calendar colour (extended events) as inline style; empty for
            // native/plot/birthday, which keep their per-source badge colour.
            $event_style = (isset($event['color']) && $event['color'] !== '')
                ? Rpgcalendar_Frontend::eventStyle($event['color'])
                : '';
            $event_title = $rpgcalendar_html_titles
                ? $event['title']
                : htmlspecialchars_uni($event['title']);
            $event_title_attr = htmlspecialchars_uni(strip_tags($event['title']));

            // Weather/holiday chips carry a symbol (image or emoji) before the
            // text and build their own tooltip (location + condition).
            $event_symbol = '';
            $event_source = $event['source'] ?? '';
            if ($event_source === 'weather') {
                $wlabel = rpgcalendar_weather_label((string)$event['condition']);
                $wnote  = (string)($event['note'] ?? '');
                $event_symbol = rpgcalendar_weather_symbol((string)$event['condition'], $wlabel, (int)($event['wid'] ?? 0));
                $visible_text = ($wnote !== '') ? $wnote : (($event_symbol === '') ? $wlabel : '');
                $event_title  = htmlspecialchars_uni($visible_text);
                $tip = $wlabel . ($wnote !== '' ? ', ' . $wnote : '');
                if (($event['locname'] ?? '') !== '') {
                    $tip = $event['locname'] . ': ' . $tip;
                }
                $event_title_attr = htmlspecialchars_uni($tip);
            } elseif ($event_source === 'holiday') {
                $event_symbol = rpgcalendar_holiday_symbol();
                $tip = (string)$event['title'];
                if (($event['locname'] ?? '') !== '') {
                    $tip = $event['locname'] . ': ' . $tip;
                }
                $event_title_attr = htmlspecialchars_uni($tip);
            }

            if ($event['url'] !== '') {
                $event_url = htmlspecialchars_uni($event['url']);
                eval('$day_events .= "' . $templates->get('rpgcalendar_eventbit') . '";');
            } else {
                eval('$day_events .= "' . $templates->get('rpgcalendar_eventbit_plain') . '";');
            }
            $shown++;
        }
        if (count($cell['events']) > $shown) {
            $more_label = $lang->sprintf($lang->rpgcalendar_more, count($cell['events']) - $shown);
            eval('$day_events .= "' . $templates->get('rpgcalendar_daycell_more') . '";');
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

    $row_title = $rpgcalendar_html_titles
        ? $event['title']
        : htmlspecialchars_uni($event['title']);
    if ($event['url'] !== '') {
        $row_title = '<a href="' . htmlspecialchars_uni($event['url']) . '">' . $row_title . '</a>';
    }

    $row_description = '';
    if (trim($event['description']) !== '') {
        // Escape first, then append the ellipsis entity (see ACP month texts)
        $description = trim($event['description']);
        $truncated   = (my_strlen($description) > 160);
        $excerpt     = htmlspecialchars_uni($truncated ? my_substr($description, 0, 160) : $description);
        if ($truncated) {
            $excerpt .= '&hellip;';
        }
        $row_description = '<div class="smalltext">' . $excerpt . '</div>';
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

// "My calendars" link (logged-in users only)
$rpgcalendar_calendars_link = '';
if ((int)$mybb->user['uid'] > 0) {
    $rpgcalendar_calendars_link = '<div class="rpgcalendar_calendars_link"><a href="rpgcalendar.php?action=calendars&amp;year='
        . $year . '&amp;month=' . $month . '">' . htmlspecialchars_uni($lang->rpgcalendar_calendars_title) . '</a></div>';
}

eval('$page = "' . $templates->get('rpgcalendar') . '";');
output_page($page);
