<?php
/**
 * rpgcalendar – Database layer
 *
 * All CRUD for the plugin tables. start_key/end_key/repeat_until_key are
 * computed here (never by the database) as y*10000 + m*100 + d.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

class Rpgcalendar_Database
{
    /** @var DB_Base */
    private $db;

    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    // -------------------------------------------------------------------
    // Extended events
    // -------------------------------------------------------------------

    /**
     * All visible events that can have an occurrence in the given key range:
     * one-time events by interval overlap, repeating events by start/until window.
     *
     * @return array[] Raw table rows.
     */
    public function getEventsForMonthView(int $month_start_key, int $month_end_key, int $viewer_uid): array
    {
        $viewer_uid = (int)$viewer_uid;

        // Calendar-based access: an event is visible if its calendar is one the
        // viewer may access (public global + owned + accepted memberships), or
        // the viewer is invited to the specific event (legacy event invites).
        $access_cids = $this->getViewerAccessCids($viewer_uid);
        $access = $access_cids ? ('calendar_id IN (' . implode(',', $access_cids) . ')') : '0';
        if ($viewer_uid > 0) {
            $access .= " OR eid IN (SELECT eid FROM " . TABLE_PREFIX
                . "rpgcalendar_event_invites WHERE uid = {$viewer_uid})";
        }

        $query = $this->db->query("
            SELECT *
            FROM " . TABLE_PREFIX . "rpgcalendar_events
            WHERE visible = 1
              AND ({$access})
              AND (
                    (repeat_type = 0 AND start_key <= {$month_end_key} AND end_key >= {$month_start_key})
                 OR (repeat_type > 0 AND start_key <= {$month_end_key}
                     AND (repeat_until_key = 0 OR repeat_until_key >= {$month_start_key}))
              )
        ");

        $rows = [];
        while ($row = $this->db->fetch_array($query)) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function getEvent(int $eid): ?array
    {
        $row = $this->db->fetch_array($this->db->simple_select(
            'rpgcalendar_events', '*', 'eid = ' . (int)$eid
        ));

        return $row ?: null;
    }

    /**
     * Inserts or updates an event. Expects validated integer date parts and
     * computes the sort keys. Returns the event id.
     *
     * @param array $data title, description, start_year/month/day,
     *                    end_year/month/day, repeat_type, repeat_until_* (or 0),
     *                    important, visible, uid
     */
    public function saveEvent(array $data, int $eid = 0): int
    {
        $until_key = 0;
        if (!empty($data['repeat_until_year'])
            && !empty($data['repeat_until_month'])
            && !empty($data['repeat_until_day'])
        ) {
            $until_key = Rpgcalendar_Engine::dateKey(
                (int)$data['repeat_until_year'],
                (int)$data['repeat_until_month'],
                (int)$data['repeat_until_day']
            );
        }

        $row = [
            'title'            => $this->db->escape_string((string)$data['title']),
            'description'      => $this->db->escape_string((string)$data['description']),
            'start_year'       => (int)$data['start_year'],
            'start_month'      => (int)$data['start_month'],
            'start_day'        => (int)$data['start_day'],
            'end_year'         => (int)$data['end_year'],
            'end_month'        => (int)$data['end_month'],
            'end_day'          => (int)$data['end_day'],
            'start_key'        => Rpgcalendar_Engine::dateKey((int)$data['start_year'], (int)$data['start_month'], (int)$data['start_day']),
            'end_key'          => Rpgcalendar_Engine::dateKey((int)$data['end_year'], (int)$data['end_month'], (int)$data['end_day']),
            'repeat_type'      => (int)$data['repeat_type'],
            'repeat_interval'  => max(1, (int)($data['repeat_interval'] ?? 1)),
            'repeat_until_key' => $until_key,
            'important'        => empty($data['important']) ? 0 : 1,
            'visible'          => empty($data['visible']) ? 0 : 1,
            'private'          => empty($data['private']) ? 0 : 1,
            'calendar_id'      => (int)($data['calendar_id'] ?? 0),
        ];

        if ($eid > 0) {
            $this->db->update_query('rpgcalendar_events', $row, 'eid = ' . (int)$eid);

            return $eid;
        }

        $row['source']   = isset($data['source']) ? $this->db->escape_string((string)$data['source']) : 'manual';
        $row['uid']      = (int)($data['uid'] ?? 0);
        $row['dateline'] = TIME_NOW;

        return (int)$this->db->insert_query('rpgcalendar_events', $row);
    }

    public function deleteEvent(int $eid): void
    {
        $this->db->delete_query('rpgcalendar_events', 'eid = ' . (int)$eid);
        $this->deleteInvites($eid);
    }

    // -------------------------------------------------------------------
    // Event invites
    // -------------------------------------------------------------------

    /** Adds pending invites for the given uids, skipping duplicates and self. */
    public function addInvites(int $eid, array $uids, int $author_uid = 0): void
    {
        foreach ($uids as $uid) {
            $uid = (int)$uid;
            if ($uid <= 0 || $uid === (int)$author_uid) {
                continue;
            }
            if ($this->getInvite($eid, $uid) !== null) {
                continue;
            }
            $this->db->insert_query('rpgcalendar_event_invites', [
                'eid'      => (int)$eid,
                'uid'      => $uid,
                'status'   => 0,
                'dateline' => TIME_NOW,
            ]);
        }
    }

    /** @return array[] Invites joined with username/usergroup for display. */
    public function getInvites(int $eid): array
    {
        $query = $this->db->query("
            SELECT i.*, u.username, u.usergroup, u.displaygroup
            FROM " . TABLE_PREFIX . "rpgcalendar_event_invites i
            INNER JOIN " . TABLE_PREFIX . "users u ON u.uid = i.uid
            WHERE i.eid = " . (int)$eid . "
            ORDER BY u.username ASC
        ");

        $rows = [];
        while ($row = $this->db->fetch_array($query)) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function getInvite(int $eid, int $uid): ?array
    {
        $row = $this->db->fetch_array($this->db->simple_select(
            'rpgcalendar_event_invites', '*',
            'eid = ' . (int)$eid . ' AND uid = ' . (int)$uid
        ));

        return $row ?: null;
    }

    /** Sets the response status (1 = accepted, 2 = declined). */
    public function setInviteStatus(int $eid, int $uid, int $status): void
    {
        $this->db->update_query('rpgcalendar_event_invites',
            ['status' => (int)$status],
            'eid = ' . (int)$eid . ' AND uid = ' . (int)$uid
        );
    }

    public function deleteInvites(int $eid): void
    {
        $this->db->delete_query('rpgcalendar_event_invites', 'eid = ' . (int)$eid);
    }

    public function deleteInvite(int $eid, int $uid): void
    {
        $this->db->delete_query('rpgcalendar_event_invites',
            'eid = ' . (int)$eid . ' AND uid = ' . (int)$uid);
    }

    /** @return int[] uids currently invited to the event */
    public function getInviteUids(int $eid): array
    {
        $query = $this->db->simple_select('rpgcalendar_event_invites', 'uid', 'eid = ' . (int)$eid);
        $uids = [];
        while ($row = $this->db->fetch_array($query)) {
            $uids[] = (int)$row['uid'];
        }

        return $uids;
    }

    public function countEvents(int $year = 0): int
    {
        $where = ($year !== 0) ? 'start_year = ' . (int)$year : '1=1';
        $row = $this->db->fetch_array($this->db->simple_select(
            'rpgcalendar_events', 'COUNT(*) AS num', $where
        ));

        return isset($row['num']) ? (int)$row['num'] : 0;
    }

    /** @return array[] */
    public function listEvents(int $year = 0, int $offset = 0, int $perpage = 30): array
    {
        $where = ($year !== 0) ? 'start_year = ' . (int)$year : '1=1';
        $query = $this->db->simple_select(
            'rpgcalendar_events', '*', $where,
            [
                'order_by'    => 'start_key',
                'order_dir'   => 'ASC',
                'limit_start' => max(0, (int)$offset),
                'limit'       => max(1, (int)$perpage),
            ]
        );

        $rows = [];
        while ($row = $this->db->fetch_array($query)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** Distinct start years for the ACP year filter. @return int[] */
    public function listEventYears(): array
    {
        $query = $this->db->query("
            SELECT DISTINCT start_year
            FROM " . TABLE_PREFIX . "rpgcalendar_events
            ORDER BY start_year ASC
        ");

        $years = [];
        while ($row = $this->db->fetch_array($query)) {
            $years[] = (int)$row['start_year'];
        }

        return $years;
    }

    // -------------------------------------------------------------------
    // Sub-calendars
    // -------------------------------------------------------------------

    public function getCalendar(int $cid): ?array
    {
        $row = $this->db->fetch_array($this->db->simple_select(
            'rpgcalendar_calendars', '*', 'cid = ' . (int)$cid
        ));

        return $row ?: null;
    }

    /**
     * Calendar ids whose events the viewer may see: public global calendars
     * (everyone) + own personal + accepted memberships (shared personal and
     * joined joinable-global calendars).
     *
     * @return int[]
     */
    public function getViewerAccessCids(int $uid): array
    {
        $uid  = (int)$uid;
        $cids = [];

        $q = $this->db->simple_select('rpgcalendar_calendars', 'cid', 'type = 1 AND joinable = 0');
        while ($r = $this->db->fetch_array($q)) {
            $cids[(int)$r['cid']] = true;
        }
        if ($uid > 0) {
            $q = $this->db->simple_select('rpgcalendar_calendars', 'cid', 'owner_uid = ' . $uid);
            while ($r = $this->db->fetch_array($q)) {
                $cids[(int)$r['cid']] = true;
            }
            $q = $this->db->simple_select('rpgcalendar_calendar_members', 'cid', 'uid = ' . $uid . ' AND status = 1');
            while ($r = $this->db->fetch_array($q)) {
                $cids[(int)$r['cid']] = true;
            }
        }

        return array_map('intval', array_keys($cids));
    }

    /** The user's accessible calendars (own personal + public globals + accepted memberships). */
    public function getCalendarsForUser(int $uid): array
    {
        $cids = $this->getViewerAccessCids((int)$uid);
        if (!$cids) {
            return [];
        }
        $query = $this->db->query("
            SELECT *
            FROM " . TABLE_PREFIX . "rpgcalendar_calendars
            WHERE cid IN (" . implode(',', $cids) . ")
            ORDER BY type ASC, name ASC
        ");

        $rows = [];
        while ($row = $this->db->fetch_array($query)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Calendars the user may assign events to: own personal, personal calendars
     * shared with them as editor, joinable globals they joined (participants),
     * and public globals if they are team.
     */
    public function getWritableCalendars(int $uid, bool $is_team): array
    {
        $uid         = (int)$uid;
        $editor_cids = $this->getEditorMemberCids($uid);

        $out = [];
        foreach ($this->getCalendarsForUser($uid) as $cal) {
            $cid = (int)$cal['cid'];
            if ((int)$cal['type'] === 1) {
                if ((int)$cal['joinable'] === 1) {
                    if ($is_team || in_array($cid, $editor_cids, true)) {
                        $out[] = $cal;
                    }
                } elseif ($is_team) {
                    $out[] = $cal;
                }
            } elseif ((int)$cal['owner_uid'] === $uid || in_array($cid, $editor_cids, true)) {
                $out[] = $cal;
            }
        }

        return $out;
    }

    /** All global calendars (for the ACP), regardless of joinable/membership. */
    public function getGlobalCalendars(): array
    {
        $query = $this->db->simple_select('rpgcalendar_calendars', '*', 'type = 1',
            ['order_by' => 'name', 'order_dir' => 'ASC']);
        $rows = [];
        while ($row = $this->db->fetch_array($query)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** Get-or-create the user's default personal calendar; returns its cid. */
    public function ensurePersonalCalendar(int $uid): int
    {
        require_once MYBB_ROOT . 'inc/plugins/rpgcalendar/core/installer.php';

        return Rpgcalendar_Installer::ensurePersonalCalendarFor((int)$uid);
    }

    /** @param array $data name, description, default_color, owner_uid, type, joinable */
    public function createCalendar(array $data): int
    {
        return (int)$this->db->insert_query('rpgcalendar_calendars', [
            'name'          => $this->db->escape_string((string)$data['name']),
            'description'   => $this->db->escape_string((string)($data['description'] ?? '')),
            'owner_uid'     => (int)($data['owner_uid'] ?? 0),
            'type'          => (int)($data['type'] ?? 0),
            'joinable'      => empty($data['joinable']) ? 0 : 1,
            'default_color' => $this->db->escape_string((string)($data['default_color'] ?? '#3b82f6')),
            'dateline'      => TIME_NOW,
        ]);
    }

    public function updateCalendar(int $cid, array $data): void
    {
        $row = [];
        if (isset($data['name'])) {
            $row['name'] = $this->db->escape_string((string)$data['name']);
        }
        if (isset($data['description'])) {
            $row['description'] = $this->db->escape_string((string)$data['description']);
        }
        if (isset($data['default_color'])) {
            $row['default_color'] = $this->db->escape_string((string)$data['default_color']);
        }
        if (isset($data['joinable'])) {
            $row['joinable'] = empty($data['joinable']) ? 0 : 1;
        }
        if ($row) {
            $this->db->update_query('rpgcalendar_calendars', $row, 'cid = ' . (int)$cid);
        }
    }

    /**
     * Deletes a calendar and moves its events to $fallback_cid (its owner's
     * default personal calendar, or the Forum calendar for global ones).
     */
    public function deleteCalendar(int $cid, int $fallback_cid): void
    {
        $cid = (int)$cid;
        if ($fallback_cid > 0 && $fallback_cid !== $cid) {
            $this->db->update_query('rpgcalendar_events',
                ['calendar_id' => (int)$fallback_cid], 'calendar_id = ' . $cid);
        }
        $this->db->delete_query('rpgcalendar_calendars', 'cid = ' . $cid);
        $this->db->delete_query('rpgcalendar_calendar_prefs',
            "calendar_key = 'cal-" . $cid . "'");
        $this->db->delete_query('rpgcalendar_calendar_members', 'cid = ' . $cid);
    }

    // -------------------------------------------------------------------
    // Calendar membership (sharing + self-subscribe)
    // -------------------------------------------------------------------

    public function getMember(int $cid, int $uid): ?array
    {
        $row = $this->db->fetch_array($this->db->simple_select(
            'rpgcalendar_calendar_members', '*',
            'cid = ' . (int)$cid . ' AND uid = ' . (int)$uid
        ));

        return $row ?: null;
    }

    /** Adds or updates a membership. role 0=viewer 1=editor; status 0=invited 1=accepted. */
    public function addMember(int $cid, int $uid, int $role, int $status): void
    {
        $existing = $this->getMember($cid, $uid);
        if ($existing) {
            $this->db->update_query('rpgcalendar_calendar_members',
                ['role' => (int)$role, 'status' => (int)$status],
                'id = ' . (int)$existing['id']);

            return;
        }
        $this->db->insert_query('rpgcalendar_calendar_members', [
            'cid'      => (int)$cid,
            'uid'      => (int)$uid,
            'role'     => (int)$role,
            'status'   => (int)$status,
            'dateline' => TIME_NOW,
        ]);
    }

    public function setMemberStatus(int $cid, int $uid, int $status): void
    {
        $this->db->update_query('rpgcalendar_calendar_members',
            ['status' => (int)$status], 'cid = ' . (int)$cid . ' AND uid = ' . (int)$uid);
    }

    public function removeMember(int $cid, int $uid): void
    {
        $this->db->delete_query('rpgcalendar_calendar_members',
            'cid = ' . (int)$cid . ' AND uid = ' . (int)$uid);
    }

    /** @return array[] members of a calendar joined with username/usergroup. */
    public function getCalendarMembers(int $cid): array
    {
        $query = $this->db->query("
            SELECT m.*, u.username, u.usergroup, u.displaygroup
            FROM " . TABLE_PREFIX . "rpgcalendar_calendar_members m
            INNER JOIN " . TABLE_PREFIX . "users u ON u.uid = m.uid
            WHERE m.cid = " . (int)$cid . "
            ORDER BY m.status DESC, u.username ASC
        ");
        $rows = [];
        while ($row = $this->db->fetch_array($query)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return int[] cids where the user is an accepted editor (may write). */
    public function getEditorMemberCids(int $uid): array
    {
        $uid = (int)$uid;
        if ($uid <= 0) {
            return [];
        }
        $q = $this->db->simple_select('rpgcalendar_calendar_members', 'cid',
            'uid = ' . $uid . ' AND status = 1 AND role = 1');
        $cids = [];
        while ($r = $this->db->fetch_array($q)) {
            $cids[] = (int)$r['cid'];
        }

        return $cids;
    }

    /** @return array[] pending calendar invitations for the user (joined with calendar). */
    public function getPendingCalendarInvites(int $uid): array
    {
        $query = $this->db->query("
            SELECT m.cid, m.role, c.name, c.owner_uid, c.default_color
            FROM " . TABLE_PREFIX . "rpgcalendar_calendar_members m
            INNER JOIN " . TABLE_PREFIX . "rpgcalendar_calendars c ON c.cid = m.cid
            WHERE m.uid = " . (int)$uid . " AND m.status = 0
            ORDER BY c.name ASC
        ");
        $rows = [];
        while ($row = $this->db->fetch_array($query)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array[] joinable global calendars the user has NOT joined yet. */
    public function getJoinableCalendars(int $uid): array
    {
        $uid = (int)$uid;
        $query = $this->db->query("
            SELECT c.*
            FROM " . TABLE_PREFIX . "rpgcalendar_calendars c
            WHERE c.type = 1 AND c.joinable = 1
              AND c.cid NOT IN (
                  SELECT cid FROM " . TABLE_PREFIX . "rpgcalendar_calendar_members WHERE uid = " . $uid . "
              )
            ORDER BY c.name ASC
        ");
        $rows = [];
        while ($row = $this->db->fetch_array($query)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array map cid => default_color, for colour resolution at render. */
    public function getCalendarColors(): array
    {
        $query = $this->db->simple_select('rpgcalendar_calendars', 'cid, default_color');
        $out = [];
        while ($row = $this->db->fetch_array($query)) {
            $out[(int)$row['cid']] = (string)$row['default_color'];
        }

        return $out;
    }

    /** Number of personal calendars a user owns (to protect the last one). */
    public function countPersonalCalendars(int $uid): int
    {
        $row = $this->db->fetch_array($this->db->simple_select(
            'rpgcalendar_calendars', 'COUNT(*) AS num',
            'type = 0 AND owner_uid = ' . (int)$uid
        ));

        return isset($row['num']) ? (int)$row['num'] : 0;
    }

    // -------------------------------------------------------------------
    // Per-user calendar preferences (colour + visibility)
    // -------------------------------------------------------------------

    /** @return array map calendar_key => ['color' => string, 'visible' => int] */
    public function getUserPrefs(int $uid): array
    {
        $uid = (int)$uid;
        if ($uid <= 0) {
            return [];
        }
        $query = $this->db->simple_select(
            'rpgcalendar_calendar_prefs', 'calendar_key, color, visible', 'uid = ' . $uid
        );

        $out = [];
        while ($row = $this->db->fetch_array($query)) {
            $out[(string)$row['calendar_key']] = [
                'color'   => (string)$row['color'],
                'visible' => (int)$row['visible'],
            ];
        }

        return $out;
    }

    /** Upserts a user's colour/visibility for one calendar key. */
    public function setPref(int $uid, string $key, ?string $color, ?int $visible): void
    {
        $uid = (int)$uid;
        $key = $this->db->escape_string($key);
        $existing = $this->db->fetch_array($this->db->simple_select(
            'rpgcalendar_calendar_prefs', 'id, color, visible',
            "uid = {$uid} AND calendar_key = '{$key}'"
        ));

        $row = [];
        if ($color !== null) {
            $row['color'] = $this->db->escape_string($color);
        }
        if ($visible !== null) {
            $row['visible'] = (int)$visible ? 1 : 0;
        }
        if (!$row) {
            return;
        }

        if ($existing) {
            $this->db->update_query('rpgcalendar_calendar_prefs', $row, 'id = ' . (int)$existing['id']);
        } else {
            $row['uid']          = $uid;
            $row['calendar_key'] = $key;
            if (!isset($row['visible'])) {
                $row['visible'] = 1;
            }
            $this->db->insert_query('rpgcalendar_calendar_prefs', $row);
        }
    }

    // -------------------------------------------------------------------
    // Month texts
    // -------------------------------------------------------------------

    public function getMonthText(int $year, int $month): ?array
    {
        $row = $this->db->fetch_array($this->db->simple_select(
            'rpgcalendar_monthtexts', '*',
            'year = ' . (int)$year . ' AND month = ' . (int)$month
        ));

        return $row ?: null;
    }

    /**
     * Parses the rpgcalendar_current_inplay setting into a month list.
     * Format: comma-separated "year-month" tokens (negative astronomical years
     * allowed), month 1-12, duplicates removed. Mirrors the input format of
     * Plotcp_Database::currentInplayMonths().
     *
     * @return array[] list of ['year' => int, 'month' => int]
     */
    public static function currentInplayMonths(string $raw): array
    {
        $months = [];
        $seen   = [];
        foreach (explode(',', $raw) as $token) {
            $token = trim($token);
            if ($token === '' || !preg_match('/^(-?\d+)-(\d{1,2})$/', $token, $m)) {
                continue;
            }
            $year  = (int)$m[1];
            $month = (int)$m[2];
            if ($month < 1 || $month > 12) {
                continue;
            }
            $key = $year . '-' . $month;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $months[]   = ['year' => $year, 'month' => $month];
        }

        return $months;
    }

    /**
     * Batch-loads the month texts for the given ['year','month'] pairs in one
     * query (OR-joined year/month conditions).
     *
     * @param array[] $months list of ['year' => int, 'month' => int]
     * @return array[] map "year-month" => row (year, month, content)
     */
    public function getMonthTexts(array $months): array
    {
        if (!$months) {
            return [];
        }

        $conds = [];
        foreach ($months as $m) {
            $conds[] = '(`year` = ' . (int)$m['year'] . ' AND `month` = ' . (int)$m['month'] . ')';
        }

        $query = $this->db->query("
            SELECT year, month, content
            FROM " . TABLE_PREFIX . "rpgcalendar_monthtexts
            WHERE " . implode(' OR ', $conds) . "
            ORDER BY `year`, `month`
        ");

        $out = [];
        while ($row = $this->db->fetch_array($query)) {
            $out[(int)$row['year'] . '-' . (int)$row['month']] = $row;
        }

        return $out;
    }

    /** Upsert against UNIQUE(year, month). */
    public function saveMonthText(int $year, int $month, string $content, int $uid): void
    {
        $existing = $this->getMonthText($year, $month);
        $row = [
            'content'  => $this->db->escape_string($content),
            'uid'      => (int)$uid,
            'dateline' => TIME_NOW,
        ];

        if ($existing) {
            $this->db->update_query('rpgcalendar_monthtexts', $row, 'mtid = ' . (int)$existing['mtid']);
        } else {
            $row['year']  = (int)$year;
            $row['month'] = (int)$month;
            $this->db->insert_query('rpgcalendar_monthtexts', $row);
        }
    }

    public function deleteMonthText(int $mtid): void
    {
        $this->db->delete_query('rpgcalendar_monthtexts', 'mtid = ' . (int)$mtid);
    }

    /** @return array[] */
    public function listMonthTexts(int $year = 0): array
    {
        $where = ($year !== 0) ? 'year = ' . (int)$year : '1=1';
        $query = $this->db->simple_select(
            'rpgcalendar_monthtexts', '*', $where,
            ['order_by' => 'year, month', 'order_dir' => 'ASC']
        );

        $rows = [];
        while ($row = $this->db->fetch_array($query)) {
            $rows[] = $row;
        }

        return $rows;
    }
}
