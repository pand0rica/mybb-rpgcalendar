<?php
/**
 * rpgcalendar – index mini calendar renderer.
 *
 * Renders every current in-game month (setting 'current_inplay', there can be
 * several) as a compact Mon-Sun grid. Days are highlighted by priority
 * birthday < event/plot < scene; the full moon gets a glow; a click on a day
 * opens a popover (assets/rpgcalendar-mini.js) listing what is on that day.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

class Rpgcalendar_Mini
{
    /**
     * @param array $settings Per-account settings (Rpgcalendar_Database::getUserSettings())
     * @param int   $uid      Viewer uid (> 0)
     * @return string Rendered HTML ('' when there is nothing to render)
     */
    public static function render(array $settings, int $uid): string
    {
        global $mybb, $lang, $templates;

        require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/engine.php';
        require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/database.php';
        require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/native_adapter.php';

        $lang->load('rpgcalendar');

        $database = new Rpgcalendar_Database();
        $native   = new Rpgcalendar_NativeAdapter();
        $engine   = new Rpgcalendar_Engine($database, $native);

        $months = Rpgcalendar_Engine::currentIngameMonths(
            (string)($mybb->settings['rpgcalendar_current_inplay'] ?? ''),
            (int)($mybb->settings['rpgcalendar_default_year'] ?? 0),
            (int)($mybb->settings['rpgcalendar_default_month'] ?? 0)
        );

        $startofweek = $native->getStartOfWeek();

        $mini_weekdays = '';
        for ($i = 0; $i < 7; $i++) {
            $wd = ($startofweek + $i) % 7;
            $mini_weekdays .= '<th>' . htmlspecialchars_uni($lang->{'rpgcalendar_wd_' . $wd}) . '</th>';
        }

        $json_flags  = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
        $type_labels = [
            'birthday' => $lang->rpgcalendar_mini_type_birthday,
            'event'    => $lang->rpgcalendar_mini_type_event,
            'scene'    => $lang->rpgcalendar_mini_type_scene,
        ];

        $mini_months = '';
        foreach ($months as $m) {
            $year  = (int)$m['year'];
            $month = (int)$m['month'];

            $data = $engine->getMiniMonthData($year, $month, $startofweek, $uid, $settings);

            $mini_title = htmlspecialchars_uni($lang->{'rpgcalendar_month_' . $month} . ' ' . $year);
            $mini_url   = 'rpgcalendar.php?year=' . $year . '&amp;month=' . $month;

            $mini_rows = '';
            foreach ($data['weeks'] as $week) {
                $mini_rows .= '<tr>';
                foreach ($week as $cell) {
                    if ($cell === null) {
                        $mini_rows .= '<td class="rpgcalendar_mini_empty"></td>';
                        continue;
                    }

                    $mini_day   = (int)$cell['day'];
                    $mini_class = 'rpgcalendar_mini_cell';
                    if ($cell['kind'] !== '') {
                        $mini_class .= ' rpgcalendar_mini_cell--' . $cell['kind'];
                    }
                    if ($cell['moon'] === 'full') {
                        $mini_class .= ' rpgcalendar_mini_cell--fullmoon';
                    }
                    if ($mini_day === (int)$data['today_day']) {
                        $mini_class .= ' rpgcalendar_mini_cell--today';
                    }

                    $mini_data = '';
                    if ($cell['items'] || $cell['moon'] === 'full') {
                        $items = [];
                        foreach ($cell['items'] as $item) {
                            $items[] = [
                                'type'  => $item['type'],
                                'label' => $type_labels[$item['type']] ?? '',
                                'title' => $item['title'],
                                'url'   => $item['url'],
                            ];
                        }
                        $payload = [
                            'date'  => $mini_day . '. ' . $lang->{'rpgcalendar_month_' . $month} . ' ' . $year,
                            'day'   => 'rpgcalendar.php?action=day&year=' . $year . '&month=' . $month . '&day=' . $mini_day,
                            'moon'  => ($cell['moon'] === 'full') ? $lang->rpgcalendar_mini_fullmoon : '',
                            'items' => $items,
                        ];
                        $mini_data = htmlspecialchars_uni((string)json_encode($payload, $json_flags));
                    }

                    eval('$mini_rows .= "' . $templates->get('rpgcalendar_mini_day') . '";');
                }
                $mini_rows .= '</tr>';
            }

            eval('$mini_months .= "' . $templates->get('rpgcalendar_mini_month') . '";');
        }

        if ($mini_months === '') {
            return '';
        }

        $mini_legend_moon = !empty($settings['show_moon'])
            ? '<span class="rpgcalendar_mini_legend_item"><i class="rpgcalendar_mini_swatch rpgcalendar_mini_swatch--fullmoon"></i>'
              . htmlspecialchars_uni($lang->rpgcalendar_mini_fullmoon) . '</span>'
            : '';
        $mini_position = ($settings['mini_position'] === 'header') ? 'header' : 'inplay';
        $mini_asset    = htmlspecialchars_uni(rtrim((string)$mybb->settings['bburl'], '/'))
            . '/inc/plugins/rpgcalendar/assets/rpgcalendar-mini.js';

        $mini_html = '';
        eval('$mini_html = "' . $templates->get('rpgcalendar_mini') . '";');

        return $mini_html;
    }
}
