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
            `repeat_interval`  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            `repeat_until_key` INT NOT NULL DEFAULT 0,
            `important`        TINYINT(1) NOT NULL DEFAULT 0,
            `visible`          TINYINT(1) NOT NULL DEFAULT 1,
            `source`           VARCHAR(20) NOT NULL DEFAULT 'manual',
            `source_uid`       VARCHAR(191) NOT NULL DEFAULT '',
            `feed_id`          INT UNSIGNED NOT NULL DEFAULT 0,
            `uid`              INT UNSIGNED NOT NULL DEFAULT 0,
            `private`          TINYINT(1) NOT NULL DEFAULT 0,
            `calendar_id`      INT UNSIGNED NOT NULL DEFAULT 0,
            `dateline`         INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`eid`),
            KEY `overlap` (`end_key`, `start_key`),
            KEY `feed_uid` (`feed_id`, `source_uid`)
        ) ENGINE=InnoDB{$collation};");

        // Sub-calendars (Google-Calendar style). type: 0=personal, 1=global/forum.
        // owner_uid 0 = global. "shared" is layered on via members in a later stage.
        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgcalendar_calendars` (
            `cid`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name`          VARCHAR(120) NOT NULL DEFAULT '',
            `description`   VARCHAR(255) NOT NULL DEFAULT '',
            `owner_uid`     INT UNSIGNED NOT NULL DEFAULT 0,
            `type`          TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `joinable`      TINYINT(1) NOT NULL DEFAULT 0,
            `default_color` VARCHAR(7) NOT NULL DEFAULT '#3b82f6',
            `dateline`      INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`cid`),
            KEY `owner` (`owner_uid`)
        ) ENGINE=InnoDB{$collation};");

        // Calendar membership: shared personal calendars (owner invites) and
        // joinable global calendars (users self-subscribe). role 0=viewer,
        // 1=editor; status 0=invited (pending), 1=accepted.
        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgcalendar_calendar_members` (
            `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `cid`      INT UNSIGNED NOT NULL DEFAULT 0,
            `uid`      INT UNSIGNED NOT NULL DEFAULT 0,
            `role`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `status`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `dateline` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `cal_user` (`cid`, `uid`),
            KEY `uid` (`uid`)
        ) ENGINE=InnoDB{$collation};");

        // Per-user colour + visibility for any calendar key (real 'cal-{cid}' or,
        // later, virtual keys like 'plotcat-{cid}', 'birthdays', 'feed-{fid}').
        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgcalendar_calendar_prefs` (
            `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `uid`          INT UNSIGNED NOT NULL DEFAULT 0,
            `calendar_key` VARCHAR(60) NOT NULL DEFAULT '',
            `color`        VARCHAR(7) NOT NULL DEFAULT '',
            `visible`      TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_cal` (`uid`, `calendar_key`)
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

        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgcalendar_event_invites` (
            `invite_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `eid`       INT UNSIGNED NOT NULL DEFAULT 0,
            `uid`       INT UNSIGNED NOT NULL DEFAULT 0,
            `status`    TINYINT NOT NULL DEFAULT 0,
            `dateline`  INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`invite_id`),
            UNIQUE KEY `event_user` (`eid`, `uid`),
            KEY `uid` (`uid`)
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

        // v1.2 – play locations with per-location weather/holiday sub-calendars.
        // Each location exposes two virtual calendars 'weather-{locid}' and
        // 'holiday-{locid}' handled by the existing calendar_prefs visibility/colour.
        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgcalendar_locations` (
            `locid`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name`      VARCHAR(120) NOT NULL DEFAULT '',
            `type`      TINYINT(1) NOT NULL DEFAULT 0,
            `country`   VARCHAR(2) NOT NULL DEFAULT '',
            `city`      VARCHAR(120) NOT NULL DEFAULT '',
            `lat`       DECIMAL(8,4) NOT NULL DEFAULT 0,
            `lon`       DECIMAL(8,4) NOT NULL DEFAULT 0,
            `disporder` INT UNSIGNED NOT NULL DEFAULT 0,
            `visible`   TINYINT(1) NOT NULL DEFAULT 1,
            `dateline`  INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`locid`)
        ) ENGINE=InnoDB{$collation};");

        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgcalendar_weather` (
            `wid`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `locid`       INT UNSIGNED NOT NULL DEFAULT 0,
            `source`      VARCHAR(10) NOT NULL DEFAULT 'manual',
            `wcondition`  VARCHAR(40) NOT NULL DEFAULT '',
            `start_year`  SMALLINT NOT NULL DEFAULT 0,
            `start_month` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `start_day`   TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `end_year`    SMALLINT NOT NULL DEFAULT 0,
            `end_month`   TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `end_day`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `start_key`   INT NOT NULL DEFAULT 0,
            `end_key`     INT NOT NULL DEFAULT 0,
            `yearly`      TINYINT(1) NOT NULL DEFAULT 0,
            `note`        VARCHAR(255) NOT NULL DEFAULT '',
            `visible`     TINYINT(1) NOT NULL DEFAULT 1,
            `dateline`    INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`wid`),
            KEY `overlap` (`end_key`, `start_key`),
            KEY `loc` (`locid`)
        ) ENGINE=InnoDB{$collation};");

        $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "rpgcalendar_holidays` (
            `hid`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `locid`       INT UNSIGNED NOT NULL DEFAULT 0,
            `source`      VARCHAR(10) NOT NULL DEFAULT 'manual',
            `title`       VARCHAR(200) NOT NULL DEFAULT '',
            `description` TEXT NOT NULL,
            `url`         VARCHAR(255) NOT NULL DEFAULT '',
            `start_year`  SMALLINT NOT NULL DEFAULT 0,
            `start_month` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `start_day`   TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `end_year`    SMALLINT NOT NULL DEFAULT 0,
            `end_month`   TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `end_day`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `start_key`   INT NOT NULL DEFAULT 0,
            `end_key`     INT NOT NULL DEFAULT 0,
            `yearly`      TINYINT(1) NOT NULL DEFAULT 0,
            `visible`     TINYINT(1) NOT NULL DEFAULT 1,
            `dateline`    INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`hid`),
            KEY `overlap` (`end_key`, `start_key`),
            KEY `loc` (`locid`)
        ) ENGINE=InnoDB{$collation};");
    }

    /**
     * Adds columns introduced in later versions to an existing installation.
     * CREATE TABLE IF NOT EXISTS never alters existing tables, so column
     * upgrades live here. Idempotent: each column is only added if missing.
     */
    public static function upgradeSchema(): void
    {
        global $db;

        if (!$db->field_exists('repeat_interval', 'rpgcalendar_events')) {
            $db->add_column('rpgcalendar_events', 'repeat_interval',
                "SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER `repeat_type`");
        }

        if (!$db->field_exists('calendar_id', 'rpgcalendar_events')) {
            $db->add_column('rpgcalendar_events', 'calendar_id',
                "INT UNSIGNED NOT NULL DEFAULT 0 AFTER `private`");
        }

        if (!$db->field_exists('joinable', 'rpgcalendar_calendars')) {
            $db->add_column('rpgcalendar_calendars', 'joinable',
                "TINYINT(1) NOT NULL DEFAULT 0 AFTER `type`");
        }

        // v1.2: holiday descriptions hold a short Wikipedia explanation -> TEXT.
        if ($db->table_exists('rpgcalendar_holidays')) {
            $needs = true;
            foreach ($db->show_fields_from('rpgcalendar_holidays') as $field) {
                if (strtolower((string)$field['Field']) === 'description') {
                    $needs = (stripos((string)$field['Type'], 'text') === false);
                    break;
                }
            }
            if ($needs) {
                $db->modify_column('rpgcalendar_holidays', 'description', 'TEXT NOT NULL');
            }
        }

        // v1.2: link to the full Wikipedia article for a generated holiday.
        if ($db->table_exists('rpgcalendar_holidays')
            && !$db->field_exists('url', 'rpgcalendar_holidays')) {
            $db->add_column('rpgcalendar_holidays', 'url',
                "VARCHAR(255) NOT NULL DEFAULT '' AFTER `description`");
        }

        self::migrateEventsToCalendars();
    }

    /**
     * One-time, idempotent migration onto the sub-calendar model: ensures the
     * global "Forum" calendar and a "Persönlich" calendar per author exist, then
     * assigns every still-unassigned event (calendar_id = 0) to the right one
     * (private = 0 => Forum, private = 1 => that author's personal calendar).
     */
    public static function migrateEventsToCalendars(): void
    {
        global $db;

        // Nothing to do once every event carries a calendar_id
        $pending = $db->fetch_array($db->simple_select(
            'rpgcalendar_events', 'COUNT(*) AS num', 'calendar_id = 0'
        ));
        if ((int)($pending['num'] ?? 0) === 0) {
            return;
        }

        $forum_cid = self::ensureGlobalForumCalendar();

        // Forum-wide (public) events
        $db->update_query('rpgcalendar_events', ['calendar_id' => (int)$forum_cid],
            'calendar_id = 0 AND private = 0');

        // Personal events: one calendar per distinct author
        $query = $db->query("
            SELECT DISTINCT uid
            FROM " . TABLE_PREFIX . "rpgcalendar_events
            WHERE calendar_id = 0 AND private = 1 AND uid > 0
        ");
        while ($row = $db->fetch_array($query)) {
            $uid = (int)$row['uid'];
            $cid = self::ensurePersonalCalendarFor($uid);
            $db->update_query('rpgcalendar_events', ['calendar_id' => $cid],
                'calendar_id = 0 AND private = 1 AND uid = ' . $uid);
        }

        // Orphans (private with no author) fall back to the Forum calendar
        $db->update_query('rpgcalendar_events', ['calendar_id' => (int)$forum_cid],
            'calendar_id = 0');
    }

    /** Get-or-create the single global "Forum" calendar. Returns its cid. */
    public static function ensureGlobalForumCalendar(): int
    {
        global $db;

        $row = $db->fetch_array($db->simple_select(
            'rpgcalendar_calendars', 'cid', "type = 1 AND owner_uid = 0", ['limit' => 1]
        ));
        if ($row) {
            return (int)$row['cid'];
        }

        return (int)$db->insert_query('rpgcalendar_calendars', [
            'name'          => 'Forum',
            'description'   => $db->escape_string('Forum-wide events visible to everyone.'),
            'owner_uid'     => 0,
            'type'          => 1,
            'default_color' => '#64748b',
            'dateline'      => TIME_NOW,
        ]);
    }

    /** Get-or-create a user's default personal calendar. Returns its cid. */
    public static function ensurePersonalCalendarFor(int $uid): int
    {
        global $db;

        $uid = (int)$uid;
        $row = $db->fetch_array($db->simple_select(
            'rpgcalendar_calendars', 'cid',
            "type = 0 AND owner_uid = " . $uid, ['order_by' => 'cid', 'order_dir' => 'ASC', 'limit' => 1]
        ));
        if ($row) {
            return (int)$row['cid'];
        }

        return (int)$db->insert_query('rpgcalendar_calendars', [
            'name'          => 'Persönlich',
            'description'   => '',
            'owner_uid'     => $uid,
            'type'          => 0,
            'default_color' => '#3b82f6',
            'dateline'      => TIME_NOW,
        ]);
    }

    public static function dropTables(): void
    {
        global $db;

        $db->drop_table('rpgcalendar_events');
        $db->drop_table('rpgcalendar_monthtexts');
        $db->drop_table('rpgcalendar_event_invites');
        $db->drop_table('rpgcalendar_calendars');
        $db->drop_table('rpgcalendar_calendar_prefs');
        $db->drop_table('rpgcalendar_calendar_members');
        $db->drop_table('rpgcalendar_feeds');
        $db->drop_table('rpgcalendar_locations');
        $db->drop_table('rpgcalendar_weather');
        $db->drop_table('rpgcalendar_holidays');
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
            'team_groups' => [
                'Team groups',
                'User groups whose members may create forum-wide events (visible to '
                    . 'everyone) from the day view. All other logged-in members can only '
                    . 'create personal events for themselves and invite individual members. '
                    . 'Leave empty to let only admins create forum-wide events.',
                'groupselect', '',
            ],
            'current_inplay' => [
                'Current in-play months (global variable)',
                'Comma-separated year-month tokens (e.g. 1487-3, 1487-4) whose month texts '
                    . 'are collected into the global template variable {$rpgcalendar_current_monthtexts} '
                    . '(usable in templates and in forum/category descriptions). Empty disables the '
                    . 'variable (no query overhead). Counterpart to plotcp\'s plotcp_current_inplay '
                    . '(separate settings per plugin, same input format).',
                'text', '',
            ],
            'html_titles' => [
                'Allow HTML in event titles',
                'Render HTML in event titles (subjects) instead of escaping it. '
                    . 'Only enable this if you trust everyone who can create events – on public '
                    . 'calendars this allows those users to inject markup. Tooltips, the page title '
                    . 'and the breadcrumb always stay plain text.',
                'onoff', '0',
            ],
            'weather_imagedir' => [
                'Weather image directory',
                'Board-relative URL path to the folder with weather condition images '
                    . '(e.g. images/rpgcalendar/weather). The plugin looks for a file named '
                    . 'after the weather condition with a .png, .gif, .jpg, .jpeg, .svg or .webp '
                    . 'extension. The possible condition names are: clear (sunny), cloudy, fog, '
                    . 'rain, snow, thunder (thunderstorm) &ndash; e.g. rain.png. For more variety '
                    . 'you can add several images per condition using a dash suffix '
                    . '(rain.png, rain-1.png, rain-2.png, rain-night.png &hellip;); one is picked '
                    . 'per entry and stays the same on reload. If no matching image exists, the '
                    . 'localized condition text is shown instead. The plugin ships a default '
                    . 'icon set (one image per WMO weather code) in that folder.',
                'text', 'inc/plugins/rpgcalendar/assets/weather',
            ],
            'windy_threshold' => [
                'Windy threshold (km/h)',
                'When generating real weather, a day whose maximum wind speed reaches this value '
                    . '(km/h) is marked as "windy" instead of its weather code. 0 disables the '
                    . 'windy override.',
                'numeric', '40',
            ],
            'holiday_symbol' => [
                'Holiday symbol',
                'One symbol shown in front of every holiday. Either an emoji/short text or an image URL (a value containing a slash or ending in .png/.gif/.jpg/.svg is rendered as an image). '
                    . 'Defaults to the calendar-star icon shipped with the plugin, which matches the weather icon set.',
                'text', 'inc/plugins/rpgcalendar/assets/holiday/calendar-star.svg',
            ],
            'holiday_wiki' => [
                'Enrich generated holidays with Wikipedia',
                'When generating holidays for a real location, translate the holiday name into '
                    . 'the board language and add a short explanation plus a link to the full '
                    . 'article, fetched from Wikipedia. Turn this off to keep only the plain '
                    . 'holiday name and the "day off / working day" note. Existing entries are '
                    . 'not changed; re-generate to apply.',
                'onoff', '1',
            ],
            'weather_hint' => [
                'Show missing-data hint',
                'Show a hint for fictional locations that have no weather/holiday data for the displayed month, asking the team to add it manually.',
                'onoff', '1',
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

    /** Datacache key under which setting values survive a deactivation. */
    const SETTINGS_CACHE = 'rpgcalendar_settings';

    /**
     * Snapshots the current setting values into the datacache so a following
     * removeSettings() (on deactivation) does not lose the admin's choices.
     */
    public static function backupSettingValues(): void
    {
        global $db, $cache;

        $values = [];
        $query = $db->simple_select('settings', 'name, value', "name LIKE 'rpgcalendar\_%'");
        while ($row = $db->fetch_array($query)) {
            $values[(string)$row['name']] = (string)$row['value'];
        }
        if ($values) {
            $cache->update(self::SETTINGS_CACHE, $values);
        }
    }

    /**
     * Writes backed-up values back onto the freshly (re)created settings.
     * Called on activation after installSettings(); no-op on first install.
     */
    public static function restoreSettingValues(): void
    {
        global $db, $cache;

        $values = $cache->read(self::SETTINGS_CACHE);
        if (!is_array($values) || !$values) {
            return;
        }
        foreach ($values as $name => $value) {
            $db->update_query('settings',
                ['value' => $db->escape_string((string)$value)],
                "name = '" . $db->escape_string((string)$name) . "'"
            );
        }
        rebuild_settings();
    }

    /** Drops the value backup for good (only on uninstall). */
    public static function clearSettingBackup(): void
    {
        global $cache;

        $cache->delete(self::SETTINGS_CACHE);
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
