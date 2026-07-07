<?php
/**
 * rpgcalendar – Installer
 *
 * All install/activate/deactivate/uninstall database work lives here so the
 * lifecycle functions in inc/plugins/rpgcalendar.php stay thin.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

class Rpgcalendar_Installer
{
    /**
     * Creates all plugin tables. rpgcalendar_feeds is unused until v1.1 but is
     * created here so _install() stays the single source of the schema.
     */
    public static function createTables(): void
    {
        global $db;

        $collation = $db->build_create_table_collation();

        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgcalendar_events` (
            `eid`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title`            VARCHAR(200) NOT NULL DEFAULT '',
            `description`      TEXT NOT NULL,
            `start_year`       SMALLINT NOT NULL DEFAULT 0,
            `start_month`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `start_day`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `end_year`         SMALLINT NOT NULL DEFAULT 0,
            `end_month`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `end_day`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `start_key`        INT NOT NULL DEFAULT 0,
            `end_key`          INT NOT NULL DEFAULT 0,
            `repeat_type`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `repeat_until_key` INT NOT NULL DEFAULT 0,
            `important`        TINYINT(1) NOT NULL DEFAULT 0,
            `visible`          TINYINT(1) NOT NULL DEFAULT 1,
            `source`           VARCHAR(20) NOT NULL DEFAULT 'manual',
            `source_uid`       VARCHAR(191) NOT NULL DEFAULT '',
            `feed_id`          INT UNSIGNED NOT NULL DEFAULT 0,
            `uid`              INT UNSIGNED NOT NULL DEFAULT 0,
            `private`          TINYINT(1) NOT NULL DEFAULT 0,
            `dateline`         INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`eid`),
            KEY `overlap` (`end_key`, `start_key`),
            KEY `feed_uid` (`feed_id`, `source_uid`)
        ) ENGINE=InnoDB{$collation};");

        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgcalendar_monthtexts` (
            `mtid`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `year`     SMALLINT NOT NULL DEFAULT 0,
            `month`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `content`  TEXT NOT NULL,
            `uid`      INT UNSIGNED NOT NULL DEFAULT 0,
            `dateline` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`mtid`),
            UNIQUE KEY `yearmonth` (`year`, `month`)
        ) ENGINE=InnoDB{$collation};");

        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgcalendar_feeds` (
            `fid`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title`      VARCHAR(120) NOT NULL DEFAULT '',
            `url`        VARCHAR(500) NOT NULL DEFAULT '',
            `uid`        INT UNSIGNED NOT NULL DEFAULT 0,
            `enabled`    TINYINT(1) NOT NULL DEFAULT 1,
            `lastfetch`  INT UNSIGNED NOT NULL DEFAULT 0,
            `laststatus` VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (`fid`),
            KEY `uid` (`uid`)
        ) ENGINE=InnoDB{$collation};");
    }

    public static function dropTables(): void
    {
        global $db;

        $db->drop_table('rpgcalendar_events');
        $db->drop_table('rpgcalendar_monthtexts');
        $db->drop_table('rpgcalendar_feeds');
    }

    /**
     * Setting definitions: name (without prefix) => [title, description, optionscode, default].
     * Single source for install and for the upgrade path.
     */
    private static function settingDefinitions(): array
    {
        return [
            'enabled' => [
                'Enable RPG Calendar',
                'Master switch for the rpgcalendar frontend page.',
                'onoff', '1',
            ],
            'headerlink' => [
                'Rewrite header calendar link',
                'Points the calendar link in the forum header to rpgcalendar.php instead of calendar.php (runtime rewrite, fully reversible).',
                'onoff', '1',
            ],
            'headerlink_templates' => [
                'Link rewrite templates',
                'Comma-separated template names in which calendar.php links are rewritten at runtime. The MyBB default is header_menu_calendar; themes with custom headers can add their own template names here (no template edit needed).',
                'text', 'header_menu_calendar',
            ],
            'allowed_groups' => [
                'Allowed user groups',
                'User groups that may view the RPG calendar page. Select none to allow all groups.',
                'groupselect', '',
            ],
            'native_cid' => [
                'Native calendar ID',
                'ID (cid) of the native MyBB calendar whose events are merged into the month view. Its "start of week" setting is reused.',
                'numeric', '1',
            ],
            'shownative' => [
                'Show native events',
                'Merge events from the native MyBB calendar into the month view (years 1970-2037 only, a technical limit of the native calendar).',
                'onoff', '1',
            ],
            'showbirthdays' => [
                'Show birthdays',
                'Show member birthdays in the month view.',
                'onoff', '1',
            ],
            'birthday_source' => [
                'Birthday source',
                '0 = native profile birthday. Any other number = ID (fid) of a custom profile field containing an IC birthday in the format day-month-year (e.g. 12-3-1200) or day-month.',
                'numeric', '0',
            ],
            'birthday_year_range' => [
                'Birthday year range (native source only)',
                'Native birthdays are only shown if the displayed year is at most this many years away from the real current year. Profile field birthdays ignore this and appear in every year.',
                'numeric', '1',
            ],
            'min_year' => [
                'Minimum year',
                'Lowest year reachable via navigation and event input.',
                'numeric', '-2000',
            ],
            'max_year' => [
                'Maximum year',
                'Highest year reachable via navigation and event input.',
                'numeric', '5000',
            ],
            'default_year' => [
                'Default year',
                'Year shown when the page is opened without parameters. 0 = the real current year.',
                'numeric', '0',
            ],
            'default_month' => [
                'Default month',
                'Month (1-12) shown when the page is opened without parameters. 0 = the real current month.',
                'numeric', '0',
            ],
        ];
    }

    /**
     * Idempotent: creates the setting group if missing, otherwise adds only the
     * individual settings that do not exist yet (upgrade path).
     */
    public static function installSettings(): void
    {
        global $db;

        $definitions = self::settingDefinitions();

        $existing = $db->fetch_array($db->simple_select('settinggroups', 'gid', "name = 'rpgcalendar'"));
        if ($existing) {
            $gid = (int)$existing['gid'];
        } else {
            $gid = (int)$db->insert_query('settinggroups', [
                'name'        => 'rpgcalendar',
                'title'       => 'RPG Calendar',
                'description' => 'Settings for the RPG Calendar plugin.',
                'disporder'   => 1,
                'isdefault'   => 0,
            ]);
        }

        $disporder = 1;
        foreach ($definitions as $suffix => $def) {
            $name = 'rpgcalendar_' . $suffix;
            $row = $db->fetch_array($db->simple_select('settings', 'sid',
                "name = '" . $db->escape_string($name) . "'"));
            if (!$row) {
                $db->insert_query('settings', [
                    'name'        => $name,
                    'title'       => $db->escape_string($def[0]),
                    'description' => $db->escape_string($def[1]),
                    'optionscode' => $def[2],
                    'value'       => $def[3],
                    'disporder'   => $disporder,
                    'gid'         => $gid,
                ]);
            }
            $disporder++;
        }

        rebuild_settings();
    }

    public static function removeSettings(): void
    {
        global $db;

        $db->delete_query('settings', "name LIKE 'rpgcalendar\_%'");
        $db->delete_query('settinggroups', "name = 'rpgcalendar'");
        rebuild_settings();
    }

    /**
     * Loads every .tpl file from the plugin template directory into mybb_templates
     * (filename without extension = template title). Existing rows are replaced
     * so re-activation picks up template changes.
     */
    public static function installTemplates(): void
    {
        global $db;

        $template_dir = MYBB_ROOT . 'inc/plugins/rpgcalendar/templates/';
        if (!is_dir($template_dir)) {
            return;
        }

        self::removeTemplates();

        $handle = opendir($template_dir);
        if (!$handle) {
            return;
        }
        while (($file = readdir($handle)) !== false) {
            if (substr($file, -4) !== '.tpl') {
                continue;
            }
            $title   = substr($file, 0, -4);
            $content = (string)file_get_contents($template_dir . $file);
            $db->insert_query('templates', [
                'title'    => $db->escape_string($title),
                'template' => $db->escape_string($content),
                'sid'      => -1,
                'version'  => '',
                'dateline' => TIME_NOW,
            ]);
        }
        closedir($handle);
    }

    public static function removeTemplates(): void
    {
        global $db;

        $db->delete_query('templates', "title LIKE 'rpgcalendar%'");
    }

    /**
     * Registers the plugin stylesheet in every theme, attached to rpgcalendar.php only.
     */
    public static function installStylesheet(): void
    {
        global $db;

        require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
        require_once MYBB_ADMIN_DIR . 'inc/functions_themes.php';

        $css_path = MYBB_ROOT . 'inc/plugins/rpgcalendar/assets/rpgcalendar.css';
        if (!file_exists($css_path)) {
            return;
        }

        $css = $db->escape_string((string)file_get_contents($css_path));
        $themes = $db->simple_select('themes', 'tid');
        while ($theme = $db->fetch_array($themes)) {
            $tid = (int)$theme['tid'];
            $existing = $db->fetch_array($db->simple_select(
                'themestylesheets', 'sid',
                "name = 'rpgcalendar.css' AND tid = '" . $tid . "'"
            ));
            if ($existing) {
                $db->update_query('themestylesheets', [
                    'stylesheet'   => $css,
                    'lastmodified' => TIME_NOW,
                ], "sid = '" . (int)$existing['sid'] . "'", 1);
            } else {
                $sid = $db->insert_query('themestylesheets', [
                    'name'         => 'rpgcalendar.css',
                    'tid'          => $tid,
                    'attachedto'   => 'rpgcalendar.php',
                    'stylesheet'   => $css,
                    'lastmodified' => TIME_NOW,
                    'cachefile'    => 'rpgcalendar.css',
                ]);
                $db->update_query('themestylesheets',
                    ['cachefile' => "css.php?stylesheet={$sid}"], "sid = '{$sid}'", 1);
            }
            update_theme_stylesheet_list($tid);
        }
    }

    public static function removeStylesheet(): void
    {
        global $db;

        require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
        require_once MYBB_ADMIN_DIR . 'inc/functions_themes.php';

        $db->delete_query('themestylesheets', "name = 'rpgcalendar.css'");
        $themes = $db->simple_select('themes', 'tid');
        while ($theme = $db->fetch_array($themes)) {
            update_theme_stylesheet_list((int)$theme['tid']);
        }
    }
}
