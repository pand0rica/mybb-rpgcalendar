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
require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/generator.php';

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
        'calendars' => [
            'title'       => $lang->rpgcalendar_tab_calendars,
            'link'        => 'index.php?module=tools-rpgcalendar&amp;action=calendars',
            'description' => $lang->rpgcalendar_tab_desc_calendars,
        ],
        'locations' => [
            'title'       => $lang->rpgcalendar_tab_locations,
            'link'        => 'index.php?module=tools-rpgcalendar&amp;action=locations',
            'description' => $lang->rpgcalendar_tab_desc_locations,
        ],
        'weather' => [
            'title'       => $lang->rpgcalendar_tab_weather,
            'link'        => 'index.php?module=tools-rpgcalendar&amp;action=weather',
            'description' => $lang->rpgcalendar_tab_desc_weather,
        ],
        'holidays' => [
            'title'       => $lang->rpgcalendar_tab_holidays,
            'link'        => 'index.php?module=tools-rpgcalendar&amp;action=holidays',
            'description' => $lang->rpgcalendar_tab_desc_holidays,
        ],
    ];

    $edit_tabs = [
        'edit_event'    => 'rpgcalendar_tab_edit_event',
        'add_location'  => 'rpgcalendar_tab_add_location',
        'edit_location' => 'rpgcalendar_tab_edit_location',
        'add_weather'   => 'rpgcalendar_tab_add_weather',
        'edit_weather'  => 'rpgcalendar_tab_edit_weather',
        'add_holiday'   => 'rpgcalendar_tab_add_holiday',
        'edit_holiday'  => 'rpgcalendar_tab_edit_holiday',
    ];
    if (isset($edit_tabs[$active])) {
        $desc_key = ($active === 'edit_event')
            ? 'rpgcalendar_tab_desc_edit_event'
            : $edit_tabs[$active] . '_desc';
        $tabs[$active] = [
            'title'       => $lang->{$edit_tabs[$active]},
            'link'        => '#',
            'description' => $lang->{$desc_key},
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
        'repeat_type'     => $mybb->get_input('repeat_type', MyBB::INPUT_INT),
        'repeat_interval' => max(1, $mybb->get_input('repeat_interval', MyBB::INPUT_INT)),
        'important'       => $mybb->get_input('important', MyBB::INPUT_INT),
        'visible'         => $mybb->get_input('visible', MyBB::INPUT_INT),
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

    $form_container->output_row(
        $lang->rpgcalendar_field_interval,
        $lang->rpgcalendar_field_interval_desc,
        $form->generate_numeric_field('repeat_interval', max(1, (int)($values['repeat_interval'] ?? 1)), ['id' => 'repeat_interval', 'min' => 1, 'max' => 999, 'style' => 'width: 70px']),
        'repeat_interval'
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
        'repeat_interval'  => 1,
        'repeat_until_key' => 0,
        'important'        => 1,
        'visible'          => 1,
    ];
}

// ---------------------------------------------------------------------
// Locations / weather / holidays helpers
// ---------------------------------------------------------------------

/** @return array locid => name (0 => dash when $with_empty). */
function rpgcalendar_acp_location_options(Rpgcalendar_Database $db, bool $with_empty = false, bool $real_only = false): array
{
    $options = [];
    if ($with_empty) {
        $options[0] = '—';
    }
    foreach ($db->listLocations() as $loc) {
        if ($real_only && (int)$loc['type'] !== 1) {
            continue;
        }
        $options[(int)$loc['locid']] = $loc['name'];
    }

    return $options;
}

/** Localized label for a weather condition slug (falls back to the raw slug). */
function rpgcalendar_acp_wcond_label(string $slug): string
{
    global $lang;

    $key = 'rpgcalendar_wcond_' . preg_replace('/[^a-z0-9_]/', '', $slug);

    return (isset($lang->$key) && $lang->$key !== '') ? $lang->$key : $slug;
}

/** Select box of the standard weather conditions (value = slug, label localized). */
function rpgcalendar_acp_wcond_select(Form $form, string $current): string
{
    $options = [];
    foreach (Rpgcalendar_Generator::conditionSlugs() as $slug) {
        $options[$slug] = rpgcalendar_acp_wcond_label($slug);
    }
    if ($current !== '' && !isset($options[$current])) {
        $options[$current] = $current; // preserve a custom condition on edit
    }

    return $form->generate_select_box('wcondition', $options, [$current], ['id' => 'wcondition']);
}

/** Reads/validates the location form. @return array [data, errors] */
function rpgcalendar_acp_read_location_input(): array
{
    global $mybb, $lang;

    $errors = [];
    $data = [
        'name'      => trim($mybb->get_input('name')),
        'type'      => ($mybb->get_input('type', MyBB::INPUT_INT) === 1) ? 1 : 0,
        'country'   => strtoupper(preg_replace('/[^A-Za-z]/', '', $mybb->get_input('country'))),
        'city'      => trim($mybb->get_input('city')),
        'lat'       => (float)$mybb->get_input('lat'),
        'lon'       => (float)$mybb->get_input('lon'),
        'disporder' => $mybb->get_input('disporder', MyBB::INPUT_INT),
        'visible'   => $mybb->get_input('visible', MyBB::INPUT_INT),
    ];
    if (strlen($data['country']) > 2) {
        $data['country'] = substr($data['country'], 0, 2);
    }
    if ($data['name'] === '') {
        $errors[] = $lang->rpgcalendar_error_invalid_name;
    }
    if ($data['type'] === 1) {
        if (strlen($data['country']) !== 2) {
            $errors[] = $lang->rpgcalendar_error_country;
        }
        if ($data['city'] === '' && $data['lat'] == 0.0 && $data['lon'] == 0.0) {
            $errors[] = $lang->rpgcalendar_error_city_or_coords;
        }
    }

    return [$data, $errors];
}

/** Shared date reader for weather/holiday forms. @return array [data, errors] */
function rpgcalendar_acp_read_dated_input(): array
{
    global $mybb, $lang;

    $errors = [];
    list($min_year, $max_year) = rpgcalendar_acp_year_bounds();

    $d = [
        'start_year'  => $mybb->get_input('start_year', MyBB::INPUT_INT),
        'start_month' => $mybb->get_input('start_month', MyBB::INPUT_INT),
        'start_day'   => $mybb->get_input('start_day', MyBB::INPUT_INT),
        'yearly'      => ($mybb->get_input('yearly', MyBB::INPUT_INT) === 1) ? 1 : 0,
        'visible'     => $mybb->get_input('visible', MyBB::INPUT_INT),
    ];

    if ($d['start_month'] < 1 || $d['start_month'] > 12
        || $d['start_day'] < 1
        || $d['start_day'] > Rpgcalendar_Engine::daysInMonth($d['start_year'], $d['start_month'])
    ) {
        $errors[] = $lang->rpgcalendar_error_invalid_date;
    }
    if ($d['start_year'] < $min_year || $d['start_year'] > $max_year) {
        $errors[] = $lang->rpgcalendar_error_year_range;
    }

    if ($mybb->get_input('multiday', MyBB::INPUT_INT) === 1) {
        $d['end_year']  = $mybb->get_input('end_year', MyBB::INPUT_INT);
        $d['end_month'] = $mybb->get_input('end_month', MyBB::INPUT_INT);
        $d['end_day']   = $mybb->get_input('end_day', MyBB::INPUT_INT);
        if ($d['end_month'] < 1 || $d['end_month'] > 12
            || $d['end_day'] < 1
            || $d['end_day'] > Rpgcalendar_Engine::daysInMonth($d['end_year'], $d['end_month'])
        ) {
            $errors[] = $lang->rpgcalendar_error_invalid_date;
        } elseif (Rpgcalendar_Engine::dateKey($d['end_year'], $d['end_month'], $d['end_day'])
            < Rpgcalendar_Engine::dateKey($d['start_year'], $d['start_month'], $d['start_day'])
        ) {
            $errors[] = $lang->rpgcalendar_error_end_before_start;
        }
    } else {
        $d['end_year']  = $d['start_year'];
        $d['end_month'] = $d['start_month'];
        $d['end_day']   = $d['start_day'];
    }

    return [$d, $errors];
}

/** Default date part for weather/holiday add forms (today, single day). */
function rpgcalendar_acp_dated_defaults(): array
{
    return [
        'start_year'  => (int)my_date('Y'), 'start_month' => (int)my_date('n'), 'start_day' => (int)my_date('j'),
        'end_year'    => (int)my_date('Y'), 'end_month'   => (int)my_date('n'), 'end_day'   => (int)my_date('j'),
        'start_key'   => 0, 'end_key' => 0, 'yearly' => 0, 'visible' => 1,
    ];
}

/** Multi-day checkbox + end-date rows, shared by weather/holiday forms. */
function rpgcalendar_acp_dated_rows(FormContainer $fc, Form $form, array $values): void
{
    global $lang;

    $fc->output_row(
        $lang->rpgcalendar_field_start . ' <em>*</em>',
        $lang->rpgcalendar_field_start_desc,
        rpgcalendar_acp_date_inputs($form, 'start', (int)$values['start_day'], (int)$values['start_month'], (int)$values['start_year']),
        'start_day'
    );
    $multiday = ((int)$values['start_key'] !== (int)$values['end_key']) ? 1 : 0;
    $fc->output_row(
        $lang->rpgcalendar_field_multiday,
        '',
        $form->generate_check_box('multiday', 1, $lang->rpgcalendar_field_multiday, ['checked' => $multiday, 'id' => 'multiday']),
        'multiday'
    );
    $fc->output_row(
        $lang->rpgcalendar_field_end,
        $lang->rpgcalendar_field_end_desc,
        rpgcalendar_acp_date_inputs($form, 'end', (int)$values['end_day'], (int)$values['end_month'], (int)$values['end_year']),
        'end_day'
    );
    $fc->output_row(
        $lang->rpgcalendar_field_yearly,
        $lang->rpgcalendar_field_yearly_desc,
        $form->generate_yes_no_radio('yearly', (int)$values['yearly']),
        'yearly'
    );
    $fc->output_row(
        $lang->rpgcalendar_field_visible,
        $lang->rpgcalendar_field_visible_desc,
        $form->generate_yes_no_radio('visible', (int)$values['visible']),
        'visible'
    );
}

/** Renders the location add/edit form. */
function rpgcalendar_acp_location_form(array $values, array $errors, string $form_action, string $tab): void
{
    global $page, $lang;

    $page->output_header($lang->rpgcalendar);
    rpgcalendar_acp_tabs($tab);
    if ($errors) {
        $page->output_inline_error($errors);
    }

    $form = new Form('index.php?module=tools-rpgcalendar&amp;action=' . $form_action, 'post');
    if (!empty($values['locid'])) {
        echo $form->generate_hidden_field('locid', (int)$values['locid']);
    }

    $fc = new FormContainer(($tab === 'edit_location') ? $lang->rpgcalendar_tab_edit_location : $lang->rpgcalendar_tab_add_location);
    $fc->output_row(
        $lang->rpgcalendar_field_locname . ' <em>*</em>',
        $lang->rpgcalendar_field_locname_desc,
        $form->generate_text_box('name', $values['name'], ['id' => 'name']),
        'name'
    );
    $fc->output_row(
        $lang->rpgcalendar_field_loctype,
        $lang->rpgcalendar_field_loctype_desc,
        $form->generate_select_box('type', [0 => $lang->rpgcalendar_loctype_fictional, 1 => $lang->rpgcalendar_loctype_real], [(int)$values['type']], ['id' => 'type']),
        'type'
    );
    $fc->output_row(
        $lang->rpgcalendar_field_country,
        $lang->rpgcalendar_field_country_desc,
        $form->generate_text_box('country', $values['country'], ['id' => 'country', 'style' => 'width: 60px']),
        'country'
    );
    $fc->output_row(
        $lang->rpgcalendar_field_city,
        $lang->rpgcalendar_field_city_desc,
        $form->generate_text_box('city', $values['city'], ['id' => 'city']),
        'city'
    );
    $fc->output_row(
        $lang->rpgcalendar_field_coords,
        $lang->rpgcalendar_field_coords_desc,
        $form->generate_text_box('lat', $values['lat'], ['id' => 'lat', 'style' => 'width: 90px'])
        . ' ' . $form->generate_text_box('lon', $values['lon'], ['id' => 'lon', 'style' => 'width: 90px']),
        'lat'
    );
    $fc->output_row(
        $lang->rpgcalendar_field_disporder,
        '',
        $form->generate_numeric_field('disporder', (int)$values['disporder'], ['id' => 'disporder', 'style' => 'width: 70px']),
        'disporder'
    );
    $fc->output_row(
        $lang->rpgcalendar_field_visible,
        $lang->rpgcalendar_field_visible_desc,
        $form->generate_yes_no_radio('visible', (int)$values['visible']),
        'visible'
    );
    $fc->end();
    $form->output_submit_wrapper([$form->generate_submit_button($lang->rpgcalendar_save)]);
    $form->end();

    $page->output_footer();
}

/** Renders the weather add/edit form. */
function rpgcalendar_acp_weather_form(Rpgcalendar_Database $db, array $values, array $errors, string $form_action, string $tab): void
{
    global $page, $lang;

    $page->output_header($lang->rpgcalendar);
    rpgcalendar_acp_tabs($tab);
    if ($errors) {
        $page->output_inline_error($errors);
    }

    $form = new Form('index.php?module=tools-rpgcalendar&amp;action=' . $form_action, 'post');
    if (!empty($values['wid'])) {
        echo $form->generate_hidden_field('wid', (int)$values['wid']);
    }

    $fc = new FormContainer(($tab === 'edit_weather') ? $lang->rpgcalendar_tab_edit_weather : $lang->rpgcalendar_tab_add_weather);
    $fc->output_row(
        $lang->rpgcalendar_field_location . ' <em>*</em>',
        $lang->rpgcalendar_field_location_desc,
        $form->generate_select_box('locid', rpgcalendar_acp_location_options($db), [(int)$values['locid']], ['id' => 'locid']),
        'locid'
    );
    $fc->output_row(
        $lang->rpgcalendar_field_condition . ' <em>*</em>',
        $lang->rpgcalendar_field_condition_desc,
        rpgcalendar_acp_wcond_select($form, (string)$values['wcondition']),
        'wcondition'
    );
    rpgcalendar_acp_dated_rows($fc, $form, $values);
    $fc->output_row(
        $lang->rpgcalendar_field_note,
        $lang->rpgcalendar_field_note_desc,
        $form->generate_text_box('note', $values['note'], ['id' => 'note']),
        'note'
    );
    $fc->end();
    $form->output_submit_wrapper([$form->generate_submit_button($lang->rpgcalendar_save)]);
    $form->end();

    $page->output_footer();
}

/** Renders the holiday add/edit form. */
function rpgcalendar_acp_holiday_form(Rpgcalendar_Database $db, array $values, array $errors, string $form_action, string $tab): void
{
    global $page, $lang;

    $page->output_header($lang->rpgcalendar);
    rpgcalendar_acp_tabs($tab);
    if ($errors) {
        $page->output_inline_error($errors);
    }

    $form = new Form('index.php?module=tools-rpgcalendar&amp;action=' . $form_action, 'post');
    if (!empty($values['hid'])) {
        echo $form->generate_hidden_field('hid', (int)$values['hid']);
    }

    $fc = new FormContainer(($tab === 'edit_holiday') ? $lang->rpgcalendar_tab_edit_holiday : $lang->rpgcalendar_tab_add_holiday);
    $fc->output_row(
        $lang->rpgcalendar_field_location . ' <em>*</em>',
        $lang->rpgcalendar_field_location_desc,
        $form->generate_select_box('locid', rpgcalendar_acp_location_options($db), [(int)$values['locid']], ['id' => 'locid']),
        'locid'
    );
    $fc->output_row(
        $lang->rpgcalendar_field_title . ' <em>*</em>',
        $lang->rpgcalendar_field_title_desc,
        $form->generate_text_box('title', $values['title'], ['id' => 'title']),
        'title'
    );
    $fc->output_row(
        $lang->rpgcalendar_field_description,
        $lang->rpgcalendar_field_description_desc,
        $form->generate_text_box('description', $values['description'], ['id' => 'description']),
        'description'
    );
    rpgcalendar_acp_dated_rows($fc, $form, $values);
    $fc->end();
    $form->output_submit_wrapper([$form->generate_submit_button($lang->rpgcalendar_save)]);
    $form->end();

    $page->output_footer();
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
    // New ACP events are forum-wide -> assign the global Forum calendar so they
    // are visible under the calendar-based access model.
    if ($eid === 0) {
        require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/installer.php';
        $data['calendar_id'] = Rpgcalendar_Installer::ensureGlobalForumCalendar();
    } else {
        $existing = $rpgcalendar_db->getEvent($eid);
        $data['calendar_id'] = (int)($existing['calendar_id'] ?? 0);
    }
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

// Create or rename/recolour a global calendar
if ($action === 'do_calendar' && $mybb->request_method === 'post') {
    require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/frontend.php';
    $cid   = $mybb->get_input('cid', MyBB::INPUT_INT);
    $name  = trim($mybb->get_input('name'));
    $color = Rpgcalendar_Frontend::sanitizeColor($mybb->get_input('default_color'));
    if ($color === '') {
        $color = '#64748b';
    }

    $joinable = ($mybb->get_input('joinable', MyBB::INPUT_INT) === 1) ? 1 : 0;

    if ($name === '') {
        flash_message($lang->rpgcalendar_error_calendar_name, 'error');
    } elseif ($cid > 0) {
        $rpgcalendar_db->updateCalendar($cid, [
            'name' => my_substr($name, 0, 120), 'default_color' => $color, 'joinable' => $joinable,
        ]);
        flash_message($lang->rpgcalendar_calendar_saved, 'success');
    } else {
        $rpgcalendar_db->createCalendar([
            'name' => my_substr($name, 0, 120), 'owner_uid' => 0, 'type' => 1,
            'default_color' => $color, 'joinable' => $joinable,
        ]);
        flash_message($lang->rpgcalendar_calendar_saved, 'success');
    }
    admin_redirect('index.php?module=tools-rpgcalendar&action=calendars');
}

if ($action === 'delete_calendar' && $mybb->request_method === 'post') {
    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
    $cal = $rpgcalendar_db->getCalendar($cid);
    if (!$cal || (int)$cal['type'] !== 1) {
        flash_message($lang->rpgcalendar_error_not_found, 'error');
    } else {
        // Move events to another global calendar (the Forum), else leave 0
        require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/installer.php';
        $forum_cid = Rpgcalendar_Installer::ensureGlobalForumCalendar();
        $fallback  = ($forum_cid === $cid) ? 0 : $forum_cid;
        $rpgcalendar_db->deleteCalendar($cid, $fallback);
        flash_message($lang->rpgcalendar_calendar_deleted, 'success');
    }
    admin_redirect('index.php?module=tools-rpgcalendar&action=calendars');
}

// --- Locations ---

if ($action === 'do_add_location' || $action === 'do_edit_location') {
    $locid = ($action === 'do_edit_location') ? $mybb->get_input('locid', MyBB::INPUT_INT) : 0;
    if ($action === 'do_edit_location' && !$rpgcalendar_db->getLocation($locid)) {
        flash_message($lang->rpgcalendar_error_not_found, 'error');
        admin_redirect('index.php?module=tools-rpgcalendar&action=locations');
    }

    list($data, $errors) = rpgcalendar_acp_read_location_input();
    if ($errors) {
        $values = array_merge(['locid' => $locid], $data);
        rpgcalendar_acp_location_form(
            $values,
            $errors,
            ($locid > 0) ? 'do_edit_location' : 'do_add_location',
            ($locid > 0) ? 'edit_location' : 'add_location'
        );
        exit;
    }

    $rpgcalendar_db->saveLocation($data, $locid);
    flash_message($lang->rpgcalendar_saved, 'success');
    admin_redirect('index.php?module=tools-rpgcalendar&action=locations');
}

if ($action === 'delete_location' && $mybb->request_method === 'post') {
    $locid = $mybb->get_input('locid', MyBB::INPUT_INT);
    if (!$rpgcalendar_db->getLocation($locid)) {
        flash_message($lang->rpgcalendar_error_not_found, 'error');
    } else {
        $rpgcalendar_db->deleteLocation($locid);
        flash_message($lang->rpgcalendar_deleted, 'success');
    }
    admin_redirect('index.php?module=tools-rpgcalendar&action=locations');
}

if ($action === 'do_generate' && $mybb->request_method === 'post') {
    list($min_year, $max_year) = rpgcalendar_acp_year_bounds();
    $locid     = $mybb->get_input('locid', MyBB::INPUT_INT);
    $from_year = $mybb->get_input('from_year', MyBB::INPUT_INT);
    $to_year   = $mybb->get_input('to_year', MyBB::INPUT_INT);
    $location  = $rpgcalendar_db->getLocation($locid);

    if (!$location || (int)$location['type'] !== 1) {
        flash_message($lang->rpgcalendar_error_not_real, 'error');
        admin_redirect('index.php?module=tools-rpgcalendar&action=locations');
    }
    if ($from_year < $min_year || $to_year > $max_year || $from_year > $to_year) {
        flash_message($lang->rpgcalendar_error_year_range, 'error');
        admin_redirect('index.php?module=tools-rpgcalendar&action=locations');
    }

    $from_key  = Rpgcalendar_Engine::dateKey($from_year, 1, 1);
    $to_key    = Rpgcalendar_Engine::dateKey($to_year, 12, 31);
    $generator = new Rpgcalendar_Generator($rpgcalendar_db);
    $result    = $generator->generateForLocation($locid, $from_key, $to_key);

    if ($result['weather'] === 0 && $result['holidays'] === 0) {
        flash_message($lang->sprintf($lang->rpgcalendar_generate_failed, implode(', ', $result['errors'])), 'error');
    } else {
        flash_message($lang->sprintf($lang->rpgcalendar_generate_done, $result['weather'], $result['holidays']), 'success');
    }
    admin_redirect('index.php?module=tools-rpgcalendar&action=locations');
}

// --- Weather ---

if ($action === 'do_add_weather' || $action === 'do_edit_weather') {
    $wid = ($action === 'do_edit_weather') ? $mybb->get_input('wid', MyBB::INPUT_INT) : 0;
    $existing = ($wid > 0) ? $rpgcalendar_db->getWeather($wid) : null;
    if ($action === 'do_edit_weather' && !$existing) {
        flash_message($lang->rpgcalendar_error_not_found, 'error');
        admin_redirect('index.php?module=tools-rpgcalendar&action=weather');
    }

    list($dates, $errors) = rpgcalendar_acp_read_dated_input();
    $data = array_merge($dates, [
        'locid'      => $mybb->get_input('locid', MyBB::INPUT_INT),
        'wcondition' => trim($mybb->get_input('wcondition')),
        'note'       => trim($mybb->get_input('note')),
        // Editing an auto row turns it into a manual one so a re-generate keeps it.
        'source'     => 'manual',
    ]);
    if ($data['locid'] <= 0 || !$rpgcalendar_db->getLocation($data['locid'])) {
        $errors[] = $lang->rpgcalendar_error_location;
    }
    if ($data['wcondition'] === '') {
        $errors[] = $lang->rpgcalendar_error_condition;
    }

    if ($errors) {
        $values = array_merge(rpgcalendar_acp_dated_defaults(), $data, [
            'wid'       => $wid,
            'start_key' => Rpgcalendar_Engine::dateKey((int)$data['start_year'], (int)$data['start_month'], (int)$data['start_day']),
            'end_key'   => Rpgcalendar_Engine::dateKey((int)$data['end_year'], (int)$data['end_month'], (int)$data['end_day']),
        ]);
        rpgcalendar_acp_weather_form($rpgcalendar_db, $values, $errors, ($wid > 0) ? 'do_edit_weather' : 'do_add_weather', ($wid > 0) ? 'edit_weather' : 'add_weather');
        exit;
    }

    $rpgcalendar_db->saveWeather($data, $wid);
    flash_message($lang->rpgcalendar_saved, 'success');
    admin_redirect('index.php?module=tools-rpgcalendar&action=weather');
}

if ($action === 'delete_weather' && $mybb->request_method === 'post') {
    $wid = $mybb->get_input('wid', MyBB::INPUT_INT);
    if (!$rpgcalendar_db->getWeather($wid)) {
        flash_message($lang->rpgcalendar_error_not_found, 'error');
    } else {
        $rpgcalendar_db->deleteWeather($wid);
        flash_message($lang->rpgcalendar_deleted, 'success');
    }
    admin_redirect('index.php?module=tools-rpgcalendar&action=weather');
}

if ($action === 'delete_auto_weather' && $mybb->request_method === 'post') {
    $loc = $mybb->get_input('filter_loc', MyBB::INPUT_INT);
    $rpgcalendar_db->deleteAutoWeather($loc);
    flash_message($lang->rpgcalendar_delete_auto_done, 'success');
    admin_redirect('index.php?module=tools-rpgcalendar&action=weather&filter_loc=' . $loc);
}

// --- Holidays ---

if ($action === 'do_add_holiday' || $action === 'do_edit_holiday') {
    $hid = ($action === 'do_edit_holiday') ? $mybb->get_input('hid', MyBB::INPUT_INT) : 0;
    $existing = ($hid > 0) ? $rpgcalendar_db->getHoliday($hid) : null;
    if ($action === 'do_edit_holiday' && !$existing) {
        flash_message($lang->rpgcalendar_error_not_found, 'error');
        admin_redirect('index.php?module=tools-rpgcalendar&action=holidays');
    }

    list($dates, $errors) = rpgcalendar_acp_read_dated_input();
    $data = array_merge($dates, [
        'locid'       => $mybb->get_input('locid', MyBB::INPUT_INT),
        'title'       => trim($mybb->get_input('title')),
        'description' => trim($mybb->get_input('description')),
        'source'      => 'manual',
    ]);
    if ($data['locid'] <= 0 || !$rpgcalendar_db->getLocation($data['locid'])) {
        $errors[] = $lang->rpgcalendar_error_location;
    }
    if ($data['title'] === '') {
        $errors[] = $lang->rpgcalendar_error_invalid_title;
    } elseif (my_strlen($data['title']) > 200) {
        $data['title'] = my_substr($data['title'], 0, 200);
    }

    if ($errors) {
        $values = array_merge(rpgcalendar_acp_dated_defaults(), $data, [
            'hid'       => $hid,
            'start_key' => Rpgcalendar_Engine::dateKey((int)$data['start_year'], (int)$data['start_month'], (int)$data['start_day']),
            'end_key'   => Rpgcalendar_Engine::dateKey((int)$data['end_year'], (int)$data['end_month'], (int)$data['end_day']),
        ]);
        rpgcalendar_acp_holiday_form($rpgcalendar_db, $values, $errors, ($hid > 0) ? 'do_edit_holiday' : 'do_add_holiday', ($hid > 0) ? 'edit_holiday' : 'add_holiday');
        exit;
    }

    $rpgcalendar_db->saveHoliday($data, $hid);
    flash_message($lang->rpgcalendar_saved, 'success');
    admin_redirect('index.php?module=tools-rpgcalendar&action=holidays');
}

if ($action === 'delete_holiday' && $mybb->request_method === 'post') {
    $hid = $mybb->get_input('hid', MyBB::INPUT_INT);
    if (!$rpgcalendar_db->getHoliday($hid)) {
        flash_message($lang->rpgcalendar_error_not_found, 'error');
    } else {
        $rpgcalendar_db->deleteHoliday($hid);
        flash_message($lang->rpgcalendar_deleted, 'success');
    }
    admin_redirect('index.php?module=tools-rpgcalendar&action=holidays');
}

if ($action === 'delete_auto_holiday' && $mybb->request_method === 'post') {
    $loc = $mybb->get_input('filter_loc', MyBB::INPUT_INT);
    $rpgcalendar_db->deleteAutoHoliday($loc);
    flash_message($lang->rpgcalendar_delete_auto_done, 'success');
    admin_redirect('index.php?module=tools-rpgcalendar&action=holidays&filter_loc=' . $loc);
}

/** Builds a suggested month text from a location's weather + holidays. */
function rpgcalendar_acp_build_monthtext(Rpgcalendar_Database $db, int $locid, int $year, int $month): string
{
    global $lang;

    if ($locid <= 0 || $month < 1 || $month > 12) {
        return '';
    }
    $days = Rpgcalendar_Engine::daysInMonth($year, $month);
    if ($days < 1) {
        return '';
    }
    $start_key = Rpgcalendar_Engine::dateKey($year, $month, 1);
    $end_key   = Rpgcalendar_Engine::dateKey($year, $month, $days);

    $lines = [];

    $weather = $db->getWeatherForMonthView($start_key, $end_key, [$locid]);
    if ($weather) {
        $counts = [];
        foreach ($weather as $w) {
            $slug = (string)$w['wcondition'];
            $counts[$slug] = ($counts[$slug] ?? 0) + 1;
        }
        arsort($counts);
        $parts = [];
        foreach ($counts as $slug => $n) {
            $key   = 'rpgcalendar_wcond_' . preg_replace('/[^a-z0-9_]/', '', $slug);
            $label = (isset($lang->$key) && $lang->$key !== '') ? $lang->$key : $slug;
            $parts[] = $label . ' (' . (int)$n . ')';
        }
        $lines[] = $lang->rpgcalendar_monthtext_weather_prefix . ' ' . implode(', ', $parts);
    }

    $holidays = $db->getHolidayForMonthView($start_key, $end_key, [$locid]);
    if ($holidays) {
        usort($holidays, function (array $a, array $b): int {
            return (int)$a['start_key'] <=> (int)$b['start_key'];
        });
        $parts = [];
        foreach ($holidays as $h) {
            $parts[] = (int)$h['start_day'] . '.' . (int)$h['start_month'] . '. ' . $h['title'];
        }
        $lines[] = $lang->rpgcalendar_monthtext_holiday_prefix . ' ' . implode(', ', $parts);
    }

    return implode("\n", $lines);
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

if ($action === 'calendars') {
    require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/frontend.php';
    $page->output_header($lang->rpgcalendar);
    rpgcalendar_acp_tabs('calendars');

    $edit_cid = $mybb->get_input('cid', MyBB::INPUT_INT);
    $edit_row = null;

    $table = new Table();
    $table->construct_header($lang->rpgcalendar_cal_name);
    $table->construct_header($lang->rpgcalendar_cal_color, ['width' => '18%']);
    $table->construct_header($lang->rpgcalendar_cal_joinable, ['width' => '14%', 'class' => 'align_center']);
    $table->construct_header($lang->rpgcalendar_th_controls, ['width' => '20%', 'class' => 'align_center']);

    foreach ($rpgcalendar_db->getGlobalCalendars() as $cal) {
        if ($edit_cid > 0 && (int)$cal['cid'] === $edit_cid) {
            $edit_row = $cal;
        }
        $color = Rpgcalendar_Frontend::sanitizeColor((string)$cal['default_color']) ?: '#64748b';
        $delete_form = '<form action="index.php" method="post" style="display:inline">'
            . '<input type="hidden" name="module" value="tools-rpgcalendar" />'
            . '<input type="hidden" name="action" value="delete_calendar" />'
            . '<input type="hidden" name="cid" value="' . (int)$cal['cid'] . '" />'
            . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
            . '<input type="submit" class="submit_button" value="' . $lang->rpgcalendar_delete . '" />'
            . '</form>';

        $table->construct_cell(htmlspecialchars_uni((string)$cal['name']));
        $table->construct_cell('<span style="display:inline-block;width:14px;height:14px;border-radius:3px;vertical-align:middle;background:'
            . $color . '"></span> ' . htmlspecialchars_uni($color));
        $table->construct_cell(((int)$cal['joinable'] === 1) ? $lang->yes : $lang->no, ['class' => 'align_center']);
        $table->construct_cell(
            '<a href="index.php?module=tools-rpgcalendar&amp;action=calendars&amp;cid=' . (int)$cal['cid'] . '">'
            . $lang->rpgcalendar_edit . '</a> &nbsp; ' . $delete_form,
            ['class' => 'align_center']
        );
        $table->construct_row();
    }
    $table->output($lang->rpgcalendar_tab_calendars);

    $form_name  = $edit_row ? (string)$edit_row['name'] : '';
    $form_color = $edit_row ? Rpgcalendar_Frontend::sanitizeColor((string)$edit_row['default_color']) : '#64748b';
    if ($form_color === '') {
        $form_color = '#64748b';
    }

    $form = new Form('index.php?module=tools-rpgcalendar&amp;action=do_calendar', 'post');
    if ($edit_row) {
        echo $form->generate_hidden_field('cid', (int)$edit_row['cid']);
    }
    $form_container = new FormContainer($edit_row ? $lang->rpgcalendar_edit : $lang->rpgcalendar_cal_add);
    $form_container->output_row(
        $lang->rpgcalendar_cal_name . ' <em>*</em>', '',
        $form->generate_text_box('name', $form_name, ['id' => 'name']), 'name'
    );
    $form_container->output_row(
        $lang->rpgcalendar_cal_color, $lang->rpgcalendar_cal_color_desc,
        '<input type="color" name="default_color" value="' . htmlspecialchars_uni($form_color) . '" />', 'default_color'
    );
    $form_container->output_row(
        $lang->rpgcalendar_cal_joinable, $lang->rpgcalendar_cal_joinable_desc,
        $form->generate_yes_no_radio('joinable', $edit_row ? (int)$edit_row['joinable'] : 0), 'joinable'
    );
    $form_container->end();
    $form->output_submit_wrapper([$form->generate_submit_button($lang->rpgcalendar_save)]);
    $form->end();

    $page->output_footer();
    exit;
}

if ($action === 'monthtexts' || $action === 'prefill_monthtext') {
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
        // Escape first, then append the ellipsis entity, so htmlspecialchars_uni
        // does not turn &hellip; into the literal text "&hellip;".
        $truncated = (my_strlen($text['content']) > 120);
        $excerpt = htmlspecialchars_uni($truncated ? my_substr($text['content'], 0, 120) : $text['content']);
        if ($truncated) {
            $excerpt .= '&hellip;';
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
        $table->construct_cell($excerpt);
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

    list($min_year, $max_year) = rpgcalendar_acp_year_bounds();
    $months = [];
    for ($m = 1; $m <= 12; $m++) {
        $months[$m] = $lang->{'rpgcalendar_month_' . $m};
    }

    // Default form values (prefilled when ?mtid= given)
    $form_year    = $edit_row ? (int)$edit_row['year'] : (int)my_date('Y');
    $form_month   = $edit_row ? (int)$edit_row['month'] : (int)my_date('n');
    $form_content = $edit_row ? $edit_row['content'] : '';

    // Pre-fill request: generate the content from a location's weather/holidays.
    // The location comes from the pre-fill form's dropdown (first location by default).
    $prefill_locid = $mybb->get_input('prefill_locid', MyBB::INPUT_INT);
    if ($action === 'prefill_monthtext') {
        $form_year  = $mybb->get_input('year', MyBB::INPUT_INT);
        $form_month = $mybb->get_input('month', MyBB::INPUT_INT);
        if ($form_month < 1 || $form_month > 12) {
            $form_month = (int)my_date('n');
        }
        $generated = rpgcalendar_acp_build_monthtext($rpgcalendar_db, $prefill_locid, $form_year, $form_month);
        if ($generated !== '') {
            $form_content = $generated;
            echo '<div class="notice">' . $lang->rpgcalendar_monthtext_prefilled . '</div>';
        } else {
            echo '<div class="notice">' . $lang->rpgcalendar_monthtext_prefill_empty . '</div>';
        }
    }

    // Pre-fill helper form (choose location, month, year -> suggest content).
    $loc_options = rpgcalendar_acp_location_options($rpgcalendar_db);
    if ($loc_options) {
        $pf  = new Form('index.php?module=tools-rpgcalendar&amp;action=prefill_monthtext', 'post');
        $pfc = new FormContainer($lang->rpgcalendar_monthtext_prefill);
        $pfc->output_row(
            $lang->rpgcalendar_field_location,
            $lang->rpgcalendar_monthtext_prefill_desc,
            $pf->generate_select_box('prefill_locid', $loc_options, [$prefill_locid], ['id' => 'prefill_locid'])
            . ' ' . $pf->generate_select_box('month', $months, [$form_month])
            . ' ' . $pf->generate_numeric_field('year', $form_year, ['min' => $min_year, 'max' => $max_year, 'style' => 'width: 90px']),
            'prefill_locid'
        );
        $pfc->end();
        $pf->output_submit_wrapper([$pf->generate_submit_button($lang->rpgcalendar_monthtext_prefill_btn)]);
        $pf->end();
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

// --- Location views ---

if ($action === 'add_location') {
    rpgcalendar_acp_location_form([
        'locid' => 0, 'name' => '', 'type' => 0, 'country' => '', 'city' => '',
        'lat' => 0, 'lon' => 0, 'disporder' => 0, 'visible' => 1,
    ], [], 'do_add_location', 'add_location');
    exit;
}

if ($action === 'edit_location') {
    $locid = $mybb->get_input('locid', MyBB::INPUT_INT);
    $location = $rpgcalendar_db->getLocation($locid);
    if (!$location) {
        flash_message($lang->rpgcalendar_error_not_found, 'error');
        admin_redirect('index.php?module=tools-rpgcalendar&action=locations');
    }
    rpgcalendar_acp_location_form($location, [], 'do_edit_location', 'edit_location');
    exit;
}

if ($action === 'locations') {
    $page->output_header($lang->rpgcalendar);
    rpgcalendar_acp_tabs('locations');

    echo '<p><a href="index.php?module=tools-rpgcalendar&amp;action=add_location">&#43; '
        . $lang->rpgcalendar_tab_add_location . '</a></p>';

    $table = new Table();
    $table->construct_header($lang->rpgcalendar_th_locname);
    $table->construct_header($lang->rpgcalendar_th_type, ['width' => '12%']);
    $table->construct_header($lang->rpgcalendar_th_place, ['width' => '25%']);
    $table->construct_header($lang->rpgcalendar_th_visible, ['width' => '8%', 'class' => 'align_center']);
    $table->construct_header($lang->rpgcalendar_th_controls, ['width' => '20%', 'class' => 'align_center']);

    $locations = $rpgcalendar_db->listLocations();
    foreach ($locations as $loc) {
        $locid = (int)$loc['locid'];
        $delete_form = '<form action="index.php" method="post" style="display:inline">'
            . '<input type="hidden" name="module" value="tools-rpgcalendar" />'
            . '<input type="hidden" name="action" value="delete_location" />'
            . '<input type="hidden" name="locid" value="' . $locid . '" />'
            . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
            . '<input type="submit" class="submit_button" value="' . $lang->rpgcalendar_delete
            . '" onclick="return confirm(\'' . $lang->rpgcalendar_confirm_delete_location . '\');" />'
            . '</form>';

        $place = ((int)$loc['type'] === 1)
            ? trim(htmlspecialchars_uni($loc['city']) . ' (' . htmlspecialchars_uni($loc['country']) . ')', ' ')
            : '&ndash;';

        $table->construct_cell(htmlspecialchars_uni($loc['name']));
        $table->construct_cell(((int)$loc['type'] === 1) ? $lang->rpgcalendar_loctype_real : $lang->rpgcalendar_loctype_fictional);
        $table->construct_cell($place);
        $table->construct_cell(((int)$loc['visible'] === 1) ? $lang->yes : $lang->no, ['class' => 'align_center']);
        $table->construct_cell(
            '<a href="index.php?module=tools-rpgcalendar&amp;action=edit_location&amp;locid=' . $locid . '">'
            . $lang->rpgcalendar_edit . '</a> &nbsp; ' . $delete_form,
            ['class' => 'align_center']
        );
        $table->construct_row();
    }
    if (!$locations) {
        $table->construct_cell($lang->rpgcalendar_no_locations, ['colspan' => 5, 'class' => 'align_center']);
        $table->construct_row();
    }
    $table->output($lang->rpgcalendar_tab_locations);

    // Generate form for real locations (weather + holidays from real sources).
    $real_options = rpgcalendar_acp_location_options($rpgcalendar_db, false, true);
    if ($real_options) {
        list($min_year, $max_year) = rpgcalendar_acp_year_bounds();
        $cur = (int)my_date('Y');
        $cur = max($min_year, min($max_year, $cur));

        $gform = new Form('index.php?module=tools-rpgcalendar&amp;action=do_generate', 'post');
        $gc = new FormContainer($lang->rpgcalendar_generate_title);
        $gc->output_row(
            $lang->rpgcalendar_field_location,
            $lang->rpgcalendar_generate_desc,
            $gform->generate_select_box('locid', $real_options, [0], ['id' => 'gen_locid'])
            . ' ' . $gform->generate_numeric_field('from_year', $cur, ['min' => $min_year, 'max' => $max_year, 'style' => 'width: 90px'])
            . ' &ndash; ' . $gform->generate_numeric_field('to_year', $cur, ['min' => $min_year, 'max' => $max_year, 'style' => 'width: 90px']),
            'gen_locid'
        );
        $gc->end();
        $gform->output_submit_wrapper([$gform->generate_submit_button($lang->rpgcalendar_generate_btn)]);
        $gform->end();
    }

    $page->output_footer();
    exit;
}

// --- Weather views ---

if ($action === 'add_weather') {
    rpgcalendar_acp_weather_form($rpgcalendar_db, array_merge(rpgcalendar_acp_dated_defaults(), [
        'wid' => 0, 'locid' => 0, 'wcondition' => '', 'note' => '',
    ]), [], 'do_add_weather', 'add_weather');
    exit;
}

if ($action === 'edit_weather') {
    $wid = $mybb->get_input('wid', MyBB::INPUT_INT);
    $row = $rpgcalendar_db->getWeather($wid);
    if (!$row) {
        flash_message($lang->rpgcalendar_error_not_found, 'error');
        admin_redirect('index.php?module=tools-rpgcalendar&action=weather');
    }
    rpgcalendar_acp_weather_form($rpgcalendar_db, $row, [], 'do_edit_weather', 'edit_weather');
    exit;
}

if ($action === 'weather') {
    $page->output_header($lang->rpgcalendar);
    rpgcalendar_acp_tabs('weather');

    echo '<p><a href="index.php?module=tools-rpgcalendar&amp;action=add_weather">&#43; '
        . $lang->rpgcalendar_tab_add_weather . '</a></p>';

    $flt = $mybb->get_input('filter_loc', MyBB::INPUT_INT);
    echo '<form action="index.php" method="post" style="margin: 0 0 10px;">'
        . '<input type="hidden" name="module" value="tools-rpgcalendar" />'
        . '<input type="hidden" name="action" value="delete_auto_weather" />'
        . '<input type="hidden" name="filter_loc" value="' . $flt . '" />'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . '<input type="submit" class="submit_button" value="' . $lang->rpgcalendar_delete_auto
        . '" onclick="return confirm(\'' . $lang->rpgcalendar_confirm_delete_auto . '\');" /></form>';

    $names = [];
    foreach ($rpgcalendar_db->listLocations() as $loc) {
        $names[(int)$loc['locid']] = $loc['name'];
    }

    $filter_loc = $mybb->get_input('filter_loc', MyBB::INPUT_INT);
    if ($names) {
        echo '<form action="index.php" method="get" style="margin-bottom: 8px;">'
            . '<input type="hidden" name="module" value="tools-rpgcalendar" />'
            . '<input type="hidden" name="action" value="weather" />'
            . '<select name="filter_loc"><option value="0">' . $lang->rpgcalendar_filter_all_locations . '</option>';
        foreach ($names as $lid => $lname) {
            $sel = ($lid === $filter_loc) ? ' selected="selected"' : '';
            echo '<option value="' . $lid . '"' . $sel . '>' . htmlspecialchars_uni($lname) . '</option>';
        }
        echo '</select> <input type="submit" class="submit_button" value="' . $lang->rpgcalendar_filter . '" /></form>';
    }

    $perpage  = 30;
    $page_num = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $total    = $rpgcalendar_db->countWeather($filter_loc);
    $offset   = ($page_num - 1) * $perpage;

    $table = new Table();
    $table->construct_header($lang->rpgcalendar_th_date, ['width' => '18%']);
    $table->construct_header($lang->rpgcalendar_th_location, ['width' => '18%']);
    $table->construct_header($lang->rpgcalendar_th_condition);
    $table->construct_header($lang->rpgcalendar_th_source, ['width' => '10%', 'class' => 'align_center']);
    $table->construct_header($lang->rpgcalendar_th_controls, ['width' => '18%', 'class' => 'align_center']);

    foreach ($rpgcalendar_db->listWeather($filter_loc, $offset, $perpage) as $row) {
        $wid = (int)$row['wid'];
        $delete_form = '<form action="index.php" method="post" style="display:inline">'
            . '<input type="hidden" name="module" value="tools-rpgcalendar" />'
            . '<input type="hidden" name="action" value="delete_weather" />'
            . '<input type="hidden" name="wid" value="' . $wid . '" />'
            . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
            . '<input type="submit" class="submit_button" value="' . $lang->rpgcalendar_delete . '" />'
            . '</form>';

        $date = rpgcalendar_acp_format_date($row);
        if ((int)$row['yearly'] === 1) {
            $date .= ' <span class="smalltext">(' . $lang->rpgcalendar_yearly_short . ')</span>';
        }

        $table->construct_cell($date);
        $table->construct_cell(htmlspecialchars_uni($names[(int)$row['locid']] ?? ('#' . (int)$row['locid'])));
        $table->construct_cell(htmlspecialchars_uni(rpgcalendar_acp_wcond_label((string)$row['wcondition'])) . ($row['note'] !== '' ? ' <span class="smalltext">' . htmlspecialchars_uni($row['note']) . '</span>' : ''));
        $table->construct_cell(htmlspecialchars_uni($row['source']), ['class' => 'align_center']);
        $table->construct_cell(
            '<a href="index.php?module=tools-rpgcalendar&amp;action=edit_weather&amp;wid=' . $wid . '">'
            . $lang->rpgcalendar_edit . '</a> &nbsp; ' . $delete_form,
            ['class' => 'align_center']
        );
        $table->construct_row();
    }
    if (!$total) {
        $table->construct_cell($lang->rpgcalendar_no_weather, ['colspan' => 5, 'class' => 'align_center']);
        $table->construct_row();
    }
    $table->output($lang->rpgcalendar_tab_weather);

    if ($total > $perpage) {
        echo draw_admin_pagination($page_num, $perpage, $total,
            'index.php?module=tools-rpgcalendar&amp;action=weather&amp;filter_loc=' . $filter_loc . '&amp;page={page}');
    }

    $page->output_footer();
    exit;
}

// --- Holiday views ---

if ($action === 'add_holiday') {
    rpgcalendar_acp_holiday_form($rpgcalendar_db, array_merge(rpgcalendar_acp_dated_defaults(), [
        'hid' => 0, 'locid' => 0, 'title' => '', 'description' => '',
    ]), [], 'do_add_holiday', 'add_holiday');
    exit;
}

if ($action === 'edit_holiday') {
    $hid = $mybb->get_input('hid', MyBB::INPUT_INT);
    $row = $rpgcalendar_db->getHoliday($hid);
    if (!$row) {
        flash_message($lang->rpgcalendar_error_not_found, 'error');
        admin_redirect('index.php?module=tools-rpgcalendar&action=holidays');
    }
    rpgcalendar_acp_holiday_form($rpgcalendar_db, $row, [], 'do_edit_holiday', 'edit_holiday');
    exit;
}

if ($action === 'holidays') {
    $page->output_header($lang->rpgcalendar);
    rpgcalendar_acp_tabs('holidays');

    echo '<p><a href="index.php?module=tools-rpgcalendar&amp;action=add_holiday">&#43; '
        . $lang->rpgcalendar_tab_add_holiday . '</a></p>';

    $flt = $mybb->get_input('filter_loc', MyBB::INPUT_INT);
    echo '<form action="index.php" method="post" style="margin: 0 0 10px;">'
        . '<input type="hidden" name="module" value="tools-rpgcalendar" />'
        . '<input type="hidden" name="action" value="delete_auto_holiday" />'
        . '<input type="hidden" name="filter_loc" value="' . $flt . '" />'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . '<input type="submit" class="submit_button" value="' . $lang->rpgcalendar_delete_auto
        . '" onclick="return confirm(\'' . $lang->rpgcalendar_confirm_delete_auto . '\');" /></form>';

    $names = [];
    foreach ($rpgcalendar_db->listLocations() as $loc) {
        $names[(int)$loc['locid']] = $loc['name'];
    }

    $filter_loc = $mybb->get_input('filter_loc', MyBB::INPUT_INT);
    if ($names) {
        echo '<form action="index.php" method="get" style="margin-bottom: 8px;">'
            . '<input type="hidden" name="module" value="tools-rpgcalendar" />'
            . '<input type="hidden" name="action" value="holidays" />'
            . '<select name="filter_loc"><option value="0">' . $lang->rpgcalendar_filter_all_locations . '</option>';
        foreach ($names as $lid => $lname) {
            $sel = ($lid === $filter_loc) ? ' selected="selected"' : '';
            echo '<option value="' . $lid . '"' . $sel . '>' . htmlspecialchars_uni($lname) . '</option>';
        }
        echo '</select> <input type="submit" class="submit_button" value="' . $lang->rpgcalendar_filter . '" /></form>';
    }

    $perpage  = 30;
    $page_num = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $total    = $rpgcalendar_db->countHolidays($filter_loc);
    $offset   = ($page_num - 1) * $perpage;

    $table = new Table();
    $table->construct_header($lang->rpgcalendar_th_date, ['width' => '18%']);
    $table->construct_header($lang->rpgcalendar_th_location, ['width' => '18%']);
    $table->construct_header($lang->rpgcalendar_th_title);
    $table->construct_header($lang->rpgcalendar_th_source, ['width' => '10%', 'class' => 'align_center']);
    $table->construct_header($lang->rpgcalendar_th_controls, ['width' => '18%', 'class' => 'align_center']);

    foreach ($rpgcalendar_db->listHolidays($filter_loc, $offset, $perpage) as $row) {
        $hid = (int)$row['hid'];
        $delete_form = '<form action="index.php" method="post" style="display:inline">'
            . '<input type="hidden" name="module" value="tools-rpgcalendar" />'
            . '<input type="hidden" name="action" value="delete_holiday" />'
            . '<input type="hidden" name="hid" value="' . $hid . '" />'
            . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
            . '<input type="submit" class="submit_button" value="' . $lang->rpgcalendar_delete . '" />'
            . '</form>';

        $date = rpgcalendar_acp_format_date($row);
        if ((int)$row['yearly'] === 1) {
            $date .= ' <span class="smalltext">(' . $lang->rpgcalendar_yearly_short . ')</span>';
        }

        $table->construct_cell($date);
        $table->construct_cell(htmlspecialchars_uni($names[(int)$row['locid']] ?? ('#' . (int)$row['locid'])));
        $table->construct_cell(htmlspecialchars_uni($row['title']));
        $table->construct_cell(htmlspecialchars_uni($row['source']), ['class' => 'align_center']);
        $table->construct_cell(
            '<a href="index.php?module=tools-rpgcalendar&amp;action=edit_holiday&amp;hid=' . $hid . '">'
            . $lang->rpgcalendar_edit . '</a> &nbsp; ' . $delete_form,
            ['class' => 'align_center']
        );
        $table->construct_row();
    }
    if (!$total) {
        $table->construct_cell($lang->rpgcalendar_no_holidays, ['colspan' => 5, 'class' => 'align_center']);
        $table->construct_row();
    }
    $table->output($lang->rpgcalendar_tab_holidays);

    if ($total > $perpage) {
        echo draw_admin_pagination($page_num, $perpage, $total,
            'index.php?module=tools-rpgcalendar&amp;action=holidays&amp;filter_loc=' . $filter_loc . '&amp;page={page}');
    }

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
