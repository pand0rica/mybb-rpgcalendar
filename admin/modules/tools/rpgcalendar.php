<?php
/**
 * rpgcalendar – ACP module (Tools & Maintenance)
 *
 * Manages extended events and month texts. Native forum events are managed
 * through the normal MyBB calendar and are intentionally not editable here.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

$lang->load('rpgcalendar');

require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/engine.php';
require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/database.php';

$page->add_breadcrumb_item($lang->rpgcalendar, 'index.php?module=tools-rpgcalendar');

$rpgcalendar_db = new Rpgcalendar_Database();
$action = $mybb->get_input('action');

if ($mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
}

// ---------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------

function rpgcalendar_acp_tabs(string $active): void
{
    global $page, $lang;

    $tabs = [
        'events' => [
            'title'       => $lang->rpgcalendar_tab_events,
            'link'        => 'index.php?module=tools-rpgcalendar',
            'description' => $lang->rpgcalendar_tab_desc_events,
        ],
        'add_event' => [
            'title'       => $lang->rpgcalendar_tab_add_event,
            'link'        => 'index.php?module=tools-rpgcalendar&amp;action=add_event',
            'description' => $lang->rpgcalendar_tab_desc_add_event,
        ],
        'monthtexts' => [
            'title'       => $lang->rpgcalendar_tab_monthtexts,
            'link'        => 'index.php?module=tools-rpgcalendar&amp;action=monthtexts',
            'description' => $lang->rpgcalendar_tab_desc_monthtexts,
        ],
    ];

    if ($active === 'edit_event') {
        $tabs['edit_event'] = [
            'title'       => $lang->rpgcalendar_tab_edit_event,
            'link'        => '#',
            'description' => $lang->rpgcalendar_tab_desc_edit_event,
        ];
    }

    $page->output_nav_tabs($tabs, $active);
}

/** @return int[] [min_year, max_year] from the plugin settings */
function rpgcalendar_acp_year_bounds(): array
{
    global $mybb;

    $min = isset($mybb->settings['rpgcalendar_min_year']) ? (int)$mybb->settings['rpgcalendar_min_year'] : -2000;
    $max = isset($mybb->settings['rpgcalendar_max_year']) ? (int)$mybb->settings['rpgcalendar_max_year'] : 5000;

    return [max(-32768, $min), min(32767, $max)];
}

/**
 * Reads and validates the event form. Returns [data, errors].
 */
function rpgcalendar_acp_read_event_input(): array
{
    global $mybb, $lang;

    $errors = [];
    list($min_year, $max_year) = rpgcalendar_acp_year_bounds();

    $data = [
        'title'       => trim($mybb->get_input('title')),
        'description' => trim($mybb->get_input('description')),
        'start_year'  => $mybb->get_input('start_year', MyBB::INPUT_INT),
        'start_month' => $mybb->get_input('start_month', MyBB::INPUT_INT),
        'start_day'   => $mybb->get_input('start_day', MyBB::INPUT_INT),
        'repeat_type' => $mybb->get_input('repeat_type', MyBB::INPUT_INT),
        'important'   => $mybb->get_input('important', MyBB::INPUT_INT),
        'visible'     => $mybb->get_input('visible', MyBB::INPUT_INT),
    ];

    if ($data['title'] === '') {
        $errors[] = $lang->rpgcalendar_error_invalid_title;
    } elseif (my_strlen($data['title']) > 200) {
        $data['title'] = my_substr($data['title'], 0, 200);
    }

    // Start date
    if ($data['start_month'] < 1 || $data['start_month'] > 12
        || $data['start_day'] < 1
        || $data['start_day'] > Rpgcalendar_Engine::daysInMonth($data['start_year'], $data['start_month'])
    ) {
        $errors[] = $lang->rpgcalendar_error_invalid_date;
    }
    if ($data['start_year'] < $min_year || $data['start_year'] > $max_year) {
        $errors[] = $lang->rpgcalendar_error_year_range;
    }

    // End date (multi-day only, otherwise = start)
    if ($mybb->get_input('multiday', MyBB::INPUT_INT) === 1) {
        $data['end_year']  = $mybb->get_input('end_year', MyBB::INPUT_INT);
        $data['end_month'] = $mybb->get_input('end_month', MyBB::INPUT_INT);
        $data['end_day']   = $mybb->get_input('end_day', MyBB::INPUT_INT);

        if ($data['end_month'] < 1 || $data['end_month'] > 12
            || $data['end_day'] < 1
            || $data['end_day'] > Rpgcalendar_Engine::daysInMonth($data['end_year'], $data['end_month'])
        ) {
            $errors[] = $lang->rpgcalendar_error_invalid_date;
        } elseif ($data['end_year'] < $min_year || $data['end_year'] > $max_year) {
            $errors[] = $lang->rpgcalendar_error_year_range;
        } elseif (Rpgcalendar_Engine::dateKey($data['end_year'], $data['end_month'], $data['end_day'])
            < Rpgcalendar_Engine::dateKey($data['start_year'], $data['start_month'], $data['start_day'])
        ) {
            $errors[] = $lang->rpgcalendar_error_end_before_start;
        }
    } else {
        $data['end_year']  = $data['start_year'];
        $data['end_month'] = $data['start_month'];
        $data['end_day']   = $data['start_day'];
    }

    // Repeat
    if ($data['repeat_type'] < 0 || $data['repeat_type'] > 3) {
        $data['repeat_type'] = 0;
    }
    $data['repeat_until_year']  = $mybb->get_input('repeat_until_year', MyBB::INPUT_INT);
    $data['repeat_until_month'] = $mybb->get_input('repeat_until_month', MyBB::INPUT_INT);
    $data['repeat_until_day']   = $mybb->get_input('repeat_until_day', MyBB::INPUT_INT);
    $has_until = ($data['repeat_until_year'] !== 0 || $data['repeat_until_month'] !== 0 || $data['repeat_until_day'] !== 0);
    if ($data['repeat_type'] === 0 || !$has_until) {
        $data['repeat_until_year'] = $data['repeat_until_month'] = $data['repeat_until_day'] = 0;
    } else {
        if ($data['repeat_until_month'] < 1 || $data['repeat_until_month'] > 12
            || $data['repeat_until_day'] < 1
            || $data['repeat_until_day'] > Rpgcalendar_Engine::daysInMonth($data['repeat_until_year'], $data['repeat_until_month'])
            || Rpgcalendar_Engine::dateKey($data['repeat_until_year'], $data['repeat_until_month'], $data['repeat_until_day'])
               < Rpgcalendar_Engine::dateKey($data['start_year'], $data['start_month'], $data['start_day'])
        ) {
            $errors[] = $lang->rpgcalendar_error_invalid_repeat_until;
        }
    }

    return [$data, $errors];
}

/** Three inline day/month/year inputs for a date. */
function rpgcalendar_acp_date_inputs(Form $form, string $prefix, int $day, int $month, int $year, bool $allow_zero = false): string
{
    global $lang;

    $min_day = $allow_zero ? 0 : 1;
    list($min_year, $max_year) = rpgcalendar_acp_year_bounds();
    if ($allow_zero && $min_year > 0) {
        $min_year = 0;
    }

    $months = [];
    if ($allow_zero) {
        $months[0] = '-';
    }
    for ($m = 1; $m <= 12; $m++) {
        $months[$m] = $lang->{'rpgcalendar_month_' . $m};
    }

    return $form->generate_numeric_field($prefix . '_day', $day, ['id' => $prefix . '_day', 'min' => $min_day, 'max' => 31, 'style' => 'width: 60px'])
        . ' ' . $form->generate_select_box($prefix . '_month', $months, [$month], ['id' => $prefix . '_month'])
        . ' ' . $form->generate_numeric_field($prefix . '_year', $year, ['id' => $prefix . '_year', 'min' => $min_year, 'max' => $max_year, 'style' => 'width: 90px'])
        . ' <span class="smalltext">(' . $lang->rpgcalendar_day . ' / ' . $lang->rpgcalendar_month . ' / ' . $lang->rpgcalendar_year . ')</span>';
}

/** "12.3.1247" or "12.3.1247 – 2.4.1247" for a table row. */
function rpgcalendar_acp_format_date(array $row): string
{
    $out = (int)$row['start_day'] . '.' . (int)$row['start_month'] . '.' . (int)$row['start_year'];
    if ((int)$row['start_key'] !== (int)$row['end_key']) {
        $out .= ' &ndash; ' . (int)$row['end_day'] . '.' . (int)$row['end_month'] . '.' . (int)$row['end_year'];
    }

    return $out;
}

/** Renders the add/edit event form. */
function rpgcalendar_acp_event_form(array $values, array $errors, string $form_action, string $tab): void
{
    global $page, $lang;

    $page->output_header($lang->rpgcalendar);
    rpgcalendar_acp_tabs($tab);

    if ($errors) {
        $page->output_inline_error($errors);
    }

    $form = new Form('index.php?module=tools-rpgcalendar&amp;action=' . $form_action, 'post');
    if (!empty($values['eid'])) {
        echo $form->generate_hidden_field('eid', (int)$values['eid']);
    }

    $form_container = new FormContainer(($tab === 'edit_event') ? $lang->rpgcalendar_tab_edit_event : $lang->rpgcalendar_tab_add_event);

    $form_container->output_row(
        $lang->rpgcalendar_field_title . ' <em>*</em>',
        $lang->rpgcalendar_field_title_desc,
        $form->generate_text_box('title', $values['title'], ['id' => 'title']),
        'title'
    );
    $form_container->output_row(
        $lang->rpgcalendar_field_description,
        $lang->rpgcalendar_field_description_desc,
        $form->generate_text_area('description', $values['description'], ['id' => 'description', 'rows' => 4]),
        'description'
    );
    $form_container->output_row(
        $lang->rpgcalendar_field_start . ' <em>*</em>',
        $lang->rpgcalendar_field_start_desc,
        rpgcalendar_acp_date_inputs($form, 'start', (int)$values['start_day'], (int)$values['start_month'], (int)$values['start_year']),
        'start_day'
    );

    $multiday = ((int)$values['start_key'] !== (int)$values['end_key']) ? 1 : 0;
    $form_container->output_row(
        $lang->rpgcalendar_field_multiday,
        '',
        $form->generate_check_box('multiday', 1, $lang->rpgcalendar_field_multiday, ['checked' => $multiday, 'id' => 'multiday']),
        'multiday'
    );
    $form_container->output_row(
        $lang->rpgcalendar_field_end,
        $lang->rpgcalendar_field_end_desc,
        rpgcalendar_acp_date_inputs($form, 'end', (int)$values['end_day'], (int)$values['end_month'], (int)$values['end_year']),
        'end_day'
    );

    $repeat_options = [];
    for ($r = 0; $r <= 3; $r++) {
        $repeat_options[$r] = $lang->{'rpgcalendar_repeat_' . $r};
    }
    $form_container->output_row(
        $lang->rpgcalendar_field_repeat,
        $lang->rpgcalendar_field_repeat_desc,
        $form->generate_select_box('repeat_type', $repeat_options, [(int)$values['repeat_type']], ['id' => 'repeat_type']),
        'repeat_type'
    );

    $until = ['day' => 0, 'month' => 0, 'year' => 0];
    if ((int)$values['repeat_until_key'] > 0) {
        $key = (int)$values['repeat_until_key'];
        $until['year']  = intdiv($key, 10000);
        $rest = $key - $until['year'] * 10000;
        $until['month'] = intdiv($rest, 100);
        $until['day']   = $rest - $until['month'] * 100;
    }
    $form_container->output_row(
        $lang->rpgcalendar_field_repeat_until,
        $lang->rpgcalendar_field_repeat_until_desc,
        rpgcalendar_acp_date_inputs($form, 'repeat_until', $until['day'], $until['month'], $until['year'], true),
        'repeat_until_day'
    );

    $form_container->output_row(
        $lang->rpgcalendar_field_important,
        $lang->rpgcalendar_field_important_desc,
        $form->generate_yes_no_radio('important', (int)$values['important']),
        'important'
    );
    $form_container->output_row(
        $lang->rpgcalendar_field_visible,
        $lang->rpgcalendar_field_visible_desc,
        $form->generate_yes_no_radio('visible', (int)$values['visible']),
        'visible'
    );

    $form_container->end();
    $form->output_submit_wrapper([$form->generate_submit_button($lang->rpgcalendar_save)]);
    $form->end();

    $page->output_footer();
}

/** Default values for the add form. */
function rpgcalendar_acp_event_defaults(): array
{
    return [
        'eid'              => 0,
        'title'            => '',
        'description'      => '',
        'start_year'       => (int)my_date('Y'),
        'start_month'      => (int)my_date('n'),
        'start_day'        => (int)my_date('j'),
        'end_year'         => (int)my_date('Y'),
        'end_month'        => (int)my_date('n'),
        'end_day'          => (int)my_date('j'),
        'start_key'        => 0,
        'end_key'          => 0,
        'repeat_type'      => 0,
        'repeat_until_key' => 0,
        'important'        => 1,
        'visible'          => 1,
    ];
}

// ---------------------------------------------------------------------
// POST actions
// ---------------------------------------------------------------------

if ($action === 'do_add_event' || $action === 'do_edit_event') {
    $eid = ($action === 'do_edit_event') ? $mybb->get_input('eid', MyBB::INPUT_INT) : 0;
    if ($action === 'do_edit_event' && !$rpgcalendar_db->getEvent($eid)) {
        flash_message($lang->rpgcalendar_error_not_found, 'error');
        admin_redirect('index.php?module=tools-rpgcalendar');
    }

    list($data, $errors) = rpgcalendar_acp_read_event_input();
    if ($errors) {
        $values = array_merge(rpgcalendar_acp_event_defaults(), $data, [
            'eid'              => $eid,
            'start_key'        => Rpgcalendar_Engine::dateKey((int)$data['start_year'], (int)$data['start_month'], (int)$data['start_day']),
            'end_key'          => Rpgcalendar_Engine::dateKey((int)$data['end_year'], (int)$data['end_month'], (int)$data['end_day']),
            'repeat_until_key' => 0,
        ]);
        rpgcalendar_acp_event_form(
            $values,
            $errors,
            ($eid > 0) ? 'do_edit_event' : 'do_add_event',
            ($eid > 0) ? 'edit_event' : 'add_event'
        );
        exit;
    }

    $data['uid'] = (int)$mybb->user['uid'];
    $rpgcalendar_db->saveEvent($data, $eid);

    flash_message($lang->rpgcalendar_saved, 'success');
    admin_redirect('index.php?module=tools-rpgcalendar');
}

if ($action === 'delete_event' && $mybb->request_method === 'post') {
    $eid = $mybb->get_input('eid', MyBB::INPUT_INT);
    if (!$rpgcalendar_db->getEvent($eid)) {
        flash_message($lang->rpgcalendar_error_not_found, 'error');
    } else {
        $rpgcalendar_db->deleteEvent($eid);
        flash_message($lang->rpgcalendar_deleted, 'success');
    }
    admin_redirect('index.php?module=tools-rpgcalendar');
}

if ($action === 'do_monthtext' && $mybb->request_method === 'post') {
    list($min_year, $max_year) = rpgcalendar_acp_year_bounds();
    $year    = $mybb->get_input('year', MyBB::INPUT_INT);
    $month   = $mybb->get_input('month', MyBB::INPUT_INT);
    $content = trim($mybb->get_input('content'));

    if ($month < 1 || $month > 12) {
        flash_message($lang->rpgcalendar_error_invalid_date, 'error');
    } elseif ($year < $min_year || $year > $max_year) {
        flash_message($lang->rpgcalendar_error_year_range, 'error');
    } elseif ($content === '') {
        flash_message($lang->rpgcalendar_error_empty_text, 'error');
    } else {
        $rpgcalendar_db->saveMonthText($year, $month, $content, (int)$mybb->user['uid']);
        flash_message($lang->rpgcalendar_monthtext_saved, 'success');
    }
    admin_redirect('index.php?module=tools-rpgcalendar&action=monthtexts');
}

if ($action === 'delete_monthtext' && $mybb->request_method === 'post') {
    $mtid = $mybb->get_input('mtid', MyBB::INPUT_INT);
    $rpgcalendar_db->deleteMonthText($mtid);
    flash_message($lang->rpgcalendar_monthtext_deleted, 'success');
    admin_redirect('index.php?module=tools-rpgcalendar&action=monthtexts');
}

// ---------------------------------------------------------------------
// Views
// ---------------------------------------------------------------------

if ($action === 'add_event') {
    rpgcalendar_acp_event_form(rpgcalendar_acp_event_defaults(), [], 'do_add_event', 'add_event');
    exit;
}

if ($action === 'edit_event') {
    $eid = $mybb->get_input('eid', MyBB::INPUT_INT);
    $event = $rpgcalendar_db->getEvent($eid);
    if (!$event) {
        flash_message($lang->rpgcalendar_error_not_found, 'error');
        admin_redirect('index.php?module=tools-rpgcalendar');
    }
    rpgcalendar_acp_event_form($event, [], 'do_edit_event', 'edit_event');
    exit;
}

if ($action === 'monthtexts') {
    $page->output_header($lang->rpgcalendar);
    rpgcalendar_acp_tabs('monthtexts');

    // List
    $table = new Table();
    $table->construct_header($lang->rpgcalendar_th_year, ['width' => '10%']);
    $table->construct_header($lang->rpgcalendar_th_month, ['width' => '15%']);
    $table->construct_header($lang->rpgcalendar_th_text);
    $table->construct_header($lang->rpgcalendar_th_controls, ['width' => '20%', 'class' => 'align_center']);

    $edit_row = null;
    $edit_mtid = $mybb->get_input('mtid', MyBB::INPUT_INT);

    $texts = $rpgcalendar_db->listMonthTexts();
    foreach ($texts as $text) {
        if ($edit_mtid > 0 && (int)$text['mtid'] === $edit_mtid) {
            $edit_row = $text;
        }
        $excerpt = $text['content'];
        if (my_strlen($excerpt) > 120) {
            $excerpt = my_substr($excerpt, 0, 120) . '&hellip;';
        }

        $delete_form = '<form action="index.php" method="post" style="display:inline">'
            . '<input type="hidden" name="module" value="tools-rpgcalendar" />'
            . '<input type="hidden" name="action" value="delete_monthtext" />'
            . '<input type="hidden" name="mtid" value="' . (int)$text['mtid'] . '" />'
            . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
            . '<input type="submit" class="submit_button" value="' . $lang->rpgcalendar_delete . '" />'
            . '</form>';

        $table->construct_cell((int)$text['year']);
        $table->construct_cell(htmlspecialchars_uni($lang->{'rpgcalendar_month_' . (int)$text['month']}));
        $table->construct_cell(htmlspecialchars_uni($excerpt));
        $table->construct_cell(
            '<a href="index.php?module=tools-rpgcalendar&amp;action=monthtexts&amp;mtid=' . (int)$text['mtid'] . '">'
            . $lang->rpgcalendar_edit . '</a> &nbsp; ' . $delete_form,
            ['class' => 'align_center']
        );
        $table->construct_row();
    }
    if (!$texts) {
        $table->construct_cell($lang->rpgcalendar_no_monthtexts, ['colspan' => 4, 'class' => 'align_center']);
        $table->construct_row();
    }
    $table->output($lang->rpgcalendar_tab_monthtexts);

    // Add/edit form (prefilled when ?mtid= given)
    $form_year    = $edit_row ? (int)$edit_row['year'] : (int)my_date('Y');
    $form_month   = $edit_row ? (int)$edit_row['month'] : (int)my_date('n');
    $form_content = $edit_row ? $edit_row['content'] : '';

    list($min_year, $max_year) = rpgcalendar_acp_year_bounds();
    $months = [];
    for ($m = 1; $m <= 12; $m++) {
        $months[$m] = $lang->{'rpgcalendar_month_' . $m};
    }

    $form = new Form('index.php?module=tools-rpgcalendar&amp;action=do_monthtext', 'post');
    $form_container = new FormContainer($lang->rpgcalendar_monthtext_add);
    $form_container->output_row(
        $lang->rpgcalendar_th_month . ' / ' . $lang->rpgcalendar_th_year . ' <em>*</em>',
        $lang->rpgcalendar_monthtext_add_desc,
        $form->generate_select_box('month', $months, [$form_month], ['id' => 'month'])
        . ' ' . $form->generate_numeric_field('year', $form_year, ['id' => 'year', 'min' => $min_year, 'max' => $max_year, 'style' => 'width: 90px']),
        'month'
    );
    $form_container->output_row(
        $lang->rpgcalendar_field_content . ' <em>*</em>',
        $lang->rpgcalendar_field_content_desc,
        $form->generate_text_area('content', $form_content, ['id' => 'content', 'rows' => 6]),
        'content'
    );
    $form_container->end();
    $form->output_submit_wrapper([$form->generate_submit_button($lang->rpgcalendar_save_monthtext)]);
    $form->end();

    $page->output_footer();
    exit;
}

// Default view: event list
$page->output_header($lang->rpgcalendar);
rpgcalendar_acp_tabs('events');

// Year filter
$filter_year = $mybb->get_input('filter_year', MyBB::INPUT_INT);
$years = $rpgcalendar_db->listEventYears();

if ($years) {
    echo '<form action="index.php" method="get" style="margin-bottom: 8px;">'
        . '<input type="hidden" name="module" value="tools-rpgcalendar" />'
        . '<select name="filter_year">'
        . '<option value="0">' . $lang->rpgcalendar_filter_all_years . '</option>';
    foreach ($years as $y) {
        $selected = ($y === $filter_year) ? ' selected="selected"' : '';
        echo '<option value="' . $y . '"' . $selected . '>' . $y . '</option>';
    }
    echo '</select> <input type="submit" class="submit_button" value="' . $lang->rpgcalendar_filter . '" /></form>';
}

$perpage  = 30;
$page_num = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
$total    = $rpgcalendar_db->countEvents($filter_year);
$offset   = ($page_num - 1) * $perpage;

$table = new Table();
$table->construct_header($lang->rpgcalendar_th_date, ['width' => '18%']);
$table->construct_header($lang->rpgcalendar_th_title);
$table->construct_header($lang->rpgcalendar_th_repeat, ['width' => '12%']);
$table->construct_header($lang->rpgcalendar_th_important, ['width' => '8%', 'class' => 'align_center']);
$table->construct_header($lang->rpgcalendar_th_visible, ['width' => '8%', 'class' => 'align_center']);
$table->construct_header($lang->rpgcalendar_th_controls, ['width' => '18%', 'class' => 'align_center']);

$events = $rpgcalendar_db->listEvents($filter_year, $offset, $perpage);
foreach ($events as $event) {
    $delete_form = '<form action="index.php" method="post" style="display:inline">'
        . '<input type="hidden" name="module" value="tools-rpgcalendar" />'
        . '<input type="hidden" name="action" value="delete_event" />'
        . '<input type="hidden" name="eid" value="' . (int)$event['eid'] . '" />'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . '<input type="submit" class="submit_button" value="' . $lang->rpgcalendar_delete . '" />'
        . '</form>';

    $table->construct_cell(rpgcalendar_acp_format_date($event));
    $table->construct_cell(htmlspecialchars_uni($event['title']));
    $table->construct_cell(htmlspecialchars_uni($lang->{'rpgcalendar_repeat_' . (int)$event['repeat_type']}));
    $table->construct_cell(((int)$event['important'] === 1) ? $lang->yes : $lang->no, ['class' => 'align_center']);
    $table->construct_cell(((int)$event['visible'] === 1) ? $lang->yes : $lang->no, ['class' => 'align_center']);
    $table->construct_cell(
        '<a href="index.php?module=tools-rpgcalendar&amp;action=edit_event&amp;eid=' . (int)$event['eid'] . '">'
        . $lang->rpgcalendar_edit . '</a> &nbsp; ' . $delete_form,
        ['class' => 'align_center']
    );
    $table->construct_row();
}
if (!$events) {
    $table->construct_cell($lang->rpgcalendar_no_events, ['colspan' => 6, 'class' => 'align_center']);
    $table->construct_row();
}
$table->output($lang->rpgcalendar_tab_events);

if ($total > $perpage) {
    echo draw_admin_pagination($page_num, $perpage, $total,
        'index.php?module=tools-rpgcalendar&amp;filter_year=' . $filter_year . '&amp;page={page}');
}

$page->output_footer();
