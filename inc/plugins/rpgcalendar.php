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

// Global variable {$rpgcalendar_current_monthtexts} + forum/category description
// injection. global_intermediate (not global_start): the template system is not
// ready at global_start, so $templates->get() would fail there.
$plugins->add_hook('global_intermediate',   'rpgcalendar_set_current_monthtexts');
$plugins->add_hook('build_forumbits_forum', 'rpgcalendar_forumbits_monthtexts');
$plugins->add_hook('pre_output_page',       'rpgcalendar_pre_output_monthtexts');

function rpgcalendar_info(): array
{
    return [
        'name'          => 'RPG Calendar',
        'description'   => 'Modern calendar replacement: native events, extended events in any year, member event creation with invitations, personal & shared sub-calendars, per-location weather &amp; holidays (auto-generated for real places), plots via provider hook, monthly free texts and important-event tables.',
        'website'       => 'https://github.com/pand0rica/rpgcalendar',
        'author'        => 'pand0rica',
        'authorsite'    => 'https://github.com/pand0rica',
        'version'       => '1.2.0',
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
    // createTables() uses CREATE TABLE IF NOT EXISTS, so this doubles as the
    // upgrade path: tables added in a later version appear on re-activation
    // without touching existing data.
    Rpgcalendar_Installer::createTables();
    Rpgcalendar_Installer::upgradeSchema();
    Rpgcalendar_Installer::installSettings();
    // Re-apply values kept from a previous deactivation (no-op on first install)
    Rpgcalendar_Installer::restoreSettingValues();
    Rpgcalendar_Installer::installTemplates();
    Rpgcalendar_Installer::installStylesheet();
}

function rpgcalendar_deactivate(): void
{
    require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/installer.php';
    Rpgcalendar_Installer::removeTemplates();
    Rpgcalendar_Installer::removeStylesheet();
    // Keep the admin's setting values: snapshot them, then hide the group.
    // They are restored on the next activation and only dropped on uninstall.
    Rpgcalendar_Installer::backupSettingValues();
    Rpgcalendar_Installer::removeSettings();
}

function rpgcalendar_uninstall(): void
{
    require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/installer.php';
    // In case uninstall is run without a prior deactivate, remove settings too.
    Rpgcalendar_Installer::removeSettings();
    Rpgcalendar_Installer::clearSettingBackup(); // now the values are gone for good
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

/**
 * Builds the global template variable {$rpgcalendar_current_monthtexts}: the
 * rendered month texts of every month listed in rpgcalendar_current_inplay.
 * Runs at global_intermediate so it is available in header/index and every
 * top-level template. Empty setting => '' and early return (no query overhead).
 */
function rpgcalendar_set_current_monthtexts(): void
{
    global $mybb, $lang, $templates, $theme, $rpgcalendar_current_monthtexts;

    $rpgcalendar_current_monthtexts = '';

    $raw = isset($mybb->settings['rpgcalendar_current_inplay'])
        ? (string)$mybb->settings['rpgcalendar_current_inplay'] : '';
    if (trim($raw) === '') {
        return;
    }

    require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/database.php';
    $months = Rpgcalendar_Database::currentInplayMonths($raw);
    if (!$months) {
        return;
    }

    $rpgcalendar_db = new Rpgcalendar_Database();
    $texts = $rpgcalendar_db->getMonthTexts($months);
    if (!$texts) {
        return;
    }

    $lang->load('rpgcalendar');
    require_once MYBB_ROOT . 'inc/class_parser.php';
    $parser = new postParser();

    // Same parse options as the calendar's own month-text rendering, so the
    // texts look identical everywhere.
    $parse_options = [
        'allow_html'      => 0,
        'allow_mycode'    => 1,
        'allow_smilies'   => 1,
        'allow_imgcode'   => 1,
        'allow_videocode' => 0,
        'nl2br'           => 1,
        'filter_badwords' => 1,
    ];

    $monthtext_items = '';
    foreach ($months as $m) {
        $key = $m['year'] . '-' . $m['month'];
        if (!isset($texts[$key]) || trim((string)$texts[$key]['content']) === '') {
            continue;
        }
        $month_content = $parser->parse_message((string)$texts[$key]['content'], $parse_options);
        $month_title   = htmlspecialchars_uni($lang->{'rpgcalendar_month_' . $m['month']} . ' ' . $m['year']);
        eval('$monthtext_items .= "' . $templates->get('rpgcalendar_current_monthtext_bit') . '";');
    }

    if ($monthtext_items === '') {
        return;
    }

    eval('$rpgcalendar_current_monthtexts = "' . $templates->get('rpgcalendar_current_monthtexts') . '";');
}

/**
 * Forum/category descriptions are HTML-sanitized after build_forumbits_forum
 * (inc/functions_forumlist.php escapes bare & etc.), which would double-encode
 * our rendered HTML. So here we only swap the token for a sanitizer-safe HTML
 * comment sentinel (no &, survives both fixup regexes); the real value is put
 * in later at pre_output_page. Cheap strpos guard keeps it a no-op otherwise.
 */
function rpgcalendar_forumbits_monthtexts($forum)
{
    if (isset($forum['description'])
        && strpos($forum['description'], '{$rpgcalendar_current_monthtexts}') !== false
    ) {
        $forum['description'] = str_replace(
            '{$rpgcalendar_current_monthtexts}',
            '<!--RPGCALENDAR_CURRENT_MONTHTEXTS-->',
            $forum['description']
        );
    }

    return $forum;
}

/**
 * Replaces the sentinel with the real rendered value in the finished page,
 * i.e. after the description sanitizer ran, so entities stay intact. Only
 * touches pages whose descriptions actually carried the token.
 */
function rpgcalendar_pre_output_monthtexts($contents)
{
    global $rpgcalendar_current_monthtexts;

    if (strpos($contents, '<!--RPGCALENDAR_CURRENT_MONTHTEXTS-->') !== false) {
        $contents = str_replace(
            '<!--RPGCALENDAR_CURRENT_MONTHTEXTS-->',
            (string)$rpgcalendar_current_monthtexts,
            $contents
        );
    }

    return $contents;
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
