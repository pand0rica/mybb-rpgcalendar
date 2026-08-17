<?php
/**
 * rpgcalendar – CLI plugin lifecycle helper (test server only).
 *
 * Usage from the MyBB root:
 *   php tests/cli-plugin.php install     # install + activate + mark active
 *   php tests/cli-plugin.php uninstall   # deactivate + uninstall + unmark
 *   php tests/cli-plugin.php status      # tables/settings/templates/stylesheet counts
 *
 * Mirrors what the ACP plugin manager does. Not part of a release zip.
 */

if (PHP_SAPI !== 'cli') {
    die('CLI only.');
}

define('IN_MYBB', 1);
chdir(dirname(__DIR__));
require_once './inc/init.php';

if (!defined('MYBB_ADMIN_DIR')) {
    $admin_dir = isset($config['admin_dir']) ? $config['admin_dir'] : 'admin';
    define('MYBB_ADMIN_DIR', MYBB_ROOT . $admin_dir . '/');
}

require_once MYBB_ROOT . 'inc/plugins/rpgcalendar.php';

function rpgcalendar_cli_set_active(bool $active): void
{
    global $cache;

    $plugins_cache = $cache->read('plugins');
    if (!is_array($plugins_cache)) {
        $plugins_cache = [];
    }
    if (!isset($plugins_cache['active']) || !is_array($plugins_cache['active'])) {
        $plugins_cache['active'] = [];
    }
    if ($active) {
        $plugins_cache['active']['rpgcalendar'] = 'rpgcalendar';
    } else {
        unset($plugins_cache['active']['rpgcalendar']);
    }
    $cache->update('plugins', $plugins_cache);
}

function rpgcalendar_cli_status(): void
{
    global $db, $cache;

    $tables = ['rpgcalendar_events', 'rpgcalendar_monthtexts'];
    foreach ($tables as $table) {
        echo "table {$table}: " . ($db->table_exists($table) ? 'exists' : 'MISSING') . "\n";
    }

    $row = $db->fetch_array($db->query("SELECT COUNT(*) AS num FROM " . TABLE_PREFIX . "settings WHERE name LIKE 'rpgcalendar\_%'"));
    echo 'settings: ' . (int)$row['num'] . "\n";

    $row = $db->fetch_array($db->query("SELECT COUNT(*) AS num FROM " . TABLE_PREFIX . "templates WHERE title LIKE 'rpgcalendar%'"));
    echo 'templates: ' . (int)$row['num'] . "\n";

    $row = $db->fetch_array($db->query("SELECT COUNT(*) AS num FROM " . TABLE_PREFIX . "themestylesheets WHERE name = 'rpgcalendar.css'"));
    echo 'stylesheets: ' . (int)$row['num'] . "\n";

    $plugins_cache = $cache->read('plugins');
    $active = isset($plugins_cache['active']['rpgcalendar']);
    echo 'plugin cache active: ' . ($active ? 'yes' : 'no') . "\n";
}

$cmd = isset($argv[1]) ? $argv[1] : 'status';
switch ($cmd) {
    case 'install':
        rpgcalendar_install();
        rpgcalendar_activate();
        rpgcalendar_cli_set_active(true);
        echo "installed + activated\n";
        rpgcalendar_cli_status();
        break;

    case 'uninstall':
        rpgcalendar_deactivate();
        rpgcalendar_uninstall();
        rpgcalendar_cli_set_active(false);
        echo "deactivated + uninstalled\n";
        rpgcalendar_cli_status();
        break;

    case 'status':
    default:
        rpgcalendar_cli_status();
        break;
}
