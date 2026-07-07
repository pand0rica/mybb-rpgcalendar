<?php
/**
 * RPG Calendar – modern calendar replacement for RPG forums.
 *
 * Merges native MyBB events, extended events with arbitrary years, plots from
 * provider plugins (hook 'rpgcalendar_collect_events') and birthdays into one
 * month view. The native calendar.php is never modified.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

// Link rewrite runs at BOTH hooks with priority -10: some plugins (header
// tables) eval link templates already at global_start; MyBB's own
// header_menu_calendar is (re)cached between the two hooks and eval'd after
// global_intermediate. The handler is idempotent, so running twice is safe.
$plugins->add_hook('global_start',               'rpgcalendar_rewrite_calendar_links', -10);
$plugins->add_hook('global_intermediate',        'rpgcalendar_rewrite_calendar_links', -10);
$plugins->add_hook('admin_tools_menu',           'rpgcalendar_admin_menu');
$plugins->add_hook('admin_tools_action_handler', 'rpgcalendar_admin_action');

function rpgcalendar_info(): array
{
    return [
        'name'          => 'RPG Calendar',
        'description'   => 'Modern calendar replacement: native events, extended events in any year, plots via provider hook, monthly free texts and important-event tables.',
        'website'       => 'https://github.com/pand0rica/rpgcalendar',
        'author'        => 'pand0rica',
        'authorsite'    => 'https://github.com/pand0rica',
        'version'       => '1.0.0',
        'guid'          => '',
        'codename'      => 'rpgcalendar',
        'compatibility' => '18*',
    ];
}

function rpgcalendar_install(): void
{
    require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/installer.php';
    Rpgcalendar_Installer::createTables();
}

function rpgcalendar_is_installed(): bool
{
    global $db;

    return $db->table_exists('rpgcalendar_events');
}

function rpgcalendar_activate(): void
{
    require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/installer.php';
    Rpgcalendar_Installer::installSettings();
    Rpgcalendar_Installer::installTemplates();
    Rpgcalendar_Installer::installStylesheet();
}

function rpgcalendar_deactivate(): void
{
    require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/installer.php';
    Rpgcalendar_Installer::removeTemplates();
    Rpgcalendar_Installer::removeSettings();
    Rpgcalendar_Installer::removeStylesheet();
}

function rpgcalendar_uninstall(): void
{
    require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/installer.php';
    Rpgcalendar_Installer::dropTables();
}

/**
 * Rewrites calendar.php links to rpgcalendar.php at runtime in the template
 * cache (no template edit, fully reversible). Which templates are rewritten
 * is configurable (setting rpgcalendar_headerlink_templates), so custom
 * themes/header plugins are covered too. Stale query strings are dropped on
 * purpose. Idempotent – safe to run at multiple hooks.
 */
function rpgcalendar_rewrite_calendar_links(): void
{
    global $mybb, $templates;

    if (!isset($mybb->settings['rpgcalendar_enabled']) || (int)$mybb->settings['rpgcalendar_enabled'] !== 1) {
        return;
    }

    if ((int)$mybb->settings['rpgcalendar_headerlink'] !== 1 || !is_object($templates)) {
        return;
    }

    global $db;

    $setting = isset($mybb->settings['rpgcalendar_headerlink_templates'])
        ? (string)$mybb->settings['rpgcalendar_headerlink_templates'] : 'header_menu_calendar';
    foreach (explode(',', $setting) as $name) {
        $name = trim($name);
        if ($name === '') {
            continue;
        }
        if (!isset($templates->cache[$name])) {
            $templates->cache($db->escape_string($name));
        }
        if (isset($templates->cache[$name])
            && strpos($templates->cache[$name], 'rpgcalendar.php') === false
        ) {
            $templates->cache[$name] = preg_replace(
                '/calendar\.php[^"\'{]*/',
                'rpgcalendar.php',
                $templates->cache[$name]
            );
        }
    }
}

function rpgcalendar_admin_menu(array &$sub_menu): void
{
    global $lang;

    $lang->load('rpgcalendar');

    $sub_menu[] = [
        'id'    => 'rpgcalendar',
        'title' => $lang->rpgcalendar,
        'link'  => 'index.php?module=tools-rpgcalendar',
    ];
}

function rpgcalendar_admin_action(array &$actions): void
{
    $actions['rpgcalendar'] = [
        'active' => 'rpgcalendar',
        'file'   => 'rpgcalendar.php',
    ];
}
