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

        $query = $this->db->query("
            SELECT *
            FROM " . TABLE_PREFIX . "rpgcalendar_events
            WHERE visible = 1
              AND (private = 0 OR uid = {$viewer_uid})
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
            'repeat_until_key' => $until_key,
            'important'        => empty($data['important']) ? 0 : 1,
            'visible'          => empty($data['visible']) ? 0 : 1,
        ];

        if ($eid > 0) {
            $this->db->update_query('rpgcalendar_events', $row, 'eid = ' . (int)$eid);

            return $eid;
        }

        $row['source']   = 'manual';
        $row['uid']      = (int)($data['uid'] ?? 0);
        $row['dateline'] = TIME_NOW;

        return (int)$this->db->insert_query('rpgcalendar_events', $row);
    }

    public function deleteEvent(int $eid): void
    {
        $this->db->delete_query('rpgcalendar_events', 'eid = ' . (int)$eid);
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
