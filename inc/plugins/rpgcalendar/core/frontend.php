<?php
/**
 * rpgcalendar – Frontend helper
 *
 * Permission checks, event-form validation and invite resolution for the
 * public day view / add form. Mirrors the ACP validation so both entry points
 * behave identically. Never touches native MyBB calendar data.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

class Rpgcalendar_Frontend
{
    const STATUS_PENDING  = 0;
    const STATUS_ACCEPTED = 1;
    const STATUS_DECLINED = 2;

    /** Logged-in members may create personal events. */
    public static function canAddEvents(): bool
    {
        global $mybb;

        return (int)$mybb->user['uid'] > 0;
    }

    /** Team-group members (or admins) may create forum-wide events. */
    public static function isTeam(): bool
    {
        global $mybb;

        if ((int)$mybb->user['uid'] <= 0) {
            return false;
        }
        if ((int)($mybb->usergroup['cancp'] ?? 0) === 1) {
            return true;
        }

        $groups = (string)($mybb->settings['rpgcalendar_team_groups'] ?? '');
        if ($groups === '' || $groups === '-1') {
            return false;
        }

        return (bool)is_member($groups);
    }

    /**
     * May the current user edit/delete this extended event? The author always
     * can; team members (and admins) can manage every extended event.
     */
    public static function canManageEvent(array $event): bool
    {
        global $mybb;

        $uid = (int)$mybb->user['uid'];
        if ($uid <= 0) {
            return false;
        }
        if ((int)$event['uid'] === $uid) {
            return true;
        }

        return self::isTeam();
    }

    /**
     * May the current user assign events to this calendar?
     * - public global: team only
     * - joinable global: joined participants (accepted members) or team
     * - personal: owner, or a member shared as editor
     */
    public static function canWriteCalendar(array $calendar): bool
    {
        global $mybb;

        $uid = (int)$mybb->user['uid'];
        if ($uid <= 0) {
            return false;
        }
        $cid = (int)$calendar['cid'];

        if ((int)$calendar['type'] === 1) {
            if (self::isTeam()) {
                return true;
            }
            if ((int)($calendar['joinable'] ?? 0) === 1) {
                $member = (new Rpgcalendar_Database())->getMember($cid, $uid);

                return $member !== null && (int)$member['status'] === 1;
            }

            return false; // public global: team only
        }

        // personal calendar
        if ((int)$calendar['owner_uid'] === $uid) {
            return true;
        }
        $member = (new Rpgcalendar_Database())->getMember($cid, $uid);

        return $member !== null && (int)$member['status'] === 1 && (int)$member['role'] === 1;
    }

    /** Validates a #rrggbb colour; returns it lowercased or '' if invalid. */
    public static function sanitizeColor(string $color): string
    {
        $color = trim($color);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : '';
    }

    /**
     * Inline style for an event chip from an effective #rrggbb colour:
     * a solid left border plus a translucent background (like the CSS badges).
     */
    public static function eventStyle(string $hex): string
    {
        $hex = self::sanitizeColor($hex);
        if ($hex === '') {
            return '';
        }
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));

        return 'border-left-color:' . $hex . ';background:rgba(' . $r . ',' . $g . ',' . $b . ',0.12);';
    }

    /** @return int[] [min_year, max_year] from the plugin settings */
    public static function yearBounds(): array
    {
        global $mybb;

        $min = isset($mybb->settings['rpgcalendar_min_year']) ? (int)$mybb->settings['rpgcalendar_min_year'] : -2000;
        $max = isset($mybb->settings['rpgcalendar_max_year']) ? (int)$mybb->settings['rpgcalendar_max_year'] : 5000;

        return [max(-32768, $min), min(32767, $max)];
    }

    /**
     * Reads and validates the frontend event form. Mirrors the ACP validation.
     *
     * @return array [data, errors]
     */
    public static function readEventInput(): array
    {
        global $mybb, $lang;

        $errors = [];
        list($min_year, $max_year) = self::yearBounds();

        $data = [
            'title'       => trim($mybb->get_input('title')),
            'description' => trim($mybb->get_input('description')),
            'start_year'  => $mybb->get_input('start_year', MyBB::INPUT_INT),
            'start_month' => $mybb->get_input('start_month', MyBB::INPUT_INT),
            'start_day'   => $mybb->get_input('start_day', MyBB::INPUT_INT),
            'repeat_type'     => $mybb->get_input('repeat_type', MyBB::INPUT_INT),
            'repeat_interval' => max(1, $mybb->get_input('repeat_interval', MyBB::INPUT_INT)),
            'important'       => $mybb->get_input('important', MyBB::INPUT_INT),
            'calendar_id'     => $mybb->get_input('calendar_id', MyBB::INPUT_INT),
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

        // End date: the end date is always authoritative. end == start means a
        // single-day event, end > start a multi-day span. (There is no separate
        // "multi-day" checkbox any more – it silently swallowed the end date
        // whenever it was left unticked, so spans only showed on their first day.)
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

    /** Three inline day/month/year inputs for a date field (raw HTML). */
    public static function dateInputs(string $prefix, int $day, int $month, int $year, bool $allow_zero = false): string
    {
        global $lang;

        list($min_year, $max_year) = self::yearBounds();
        if ($allow_zero && $min_year > 0) {
            $min_year = 0;
        }
        $min_day = $allow_zero ? 0 : 1;

        $out  = '<input type="number" name="' . $prefix . '_day" value="' . (int)$day
            . '" min="' . $min_day . '" max="31" class="textbox rpgcalendar_dayinput" /> ';
        $out .= '<select name="' . $prefix . '_month" class="textbox">';
        if ($allow_zero) {
            $out .= '<option value="0"' . ($month === 0 ? ' selected="selected"' : '') . '>-</option>';
        }
        for ($m = 1; $m <= 12; $m++) {
            $out .= '<option value="' . $m . '"' . ($m === $month ? ' selected="selected"' : '') . '>'
                . htmlspecialchars_uni($lang->{'rpgcalendar_month_' . $m}) . '</option>';
        }
        $out .= '</select> ';
        $out .= '<input type="number" name="' . $prefix . '_year" value="' . (int)$year
            . '" min="' . $min_year . '" max="' . $max_year . '" class="textbox rpgcalendar_yearinput" />';

        return $out;
    }

    /** <option> list for the repeat-type select. */
    public static function repeatOptions(int $selected): string
    {
        global $lang;

        $out = '';
        for ($r = 0; $r <= 3; $r++) {
            $out .= '<option value="' . $r . '"' . ($r === $selected ? ' selected="selected"' : '') . '>'
                . htmlspecialchars_uni($lang->{'rpgcalendar_repeat_' . $r}) . '</option>';
        }

        return $out;
    }

    /**
     * Resolves a comma/newline separated list of usernames into uids.
     * Unknown names are reported as errors; the author is silently skipped.
     *
     * @return array [uids, errors, names] names = resolved usernames for redisplay
     */
    public static function resolveInvites(string $raw, int $author_uid): array
    {
        global $lang;

        $raw = trim($raw);
        if ($raw === '') {
            return [[], [], []];
        }

        require_once MYBB_ROOT . 'inc/functions_user.php';

        $uids   = [];
        $names  = [];
        $errors = [];
        foreach (preg_split('/[\r\n,]+/', $raw) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $user = get_user_by_username($name);
            if (!$user || empty($user['uid'])) {
                $errors[] = $lang->sprintf($lang->rpgcalendar_error_unknown_user, htmlspecialchars_uni($name));
                continue;
            }
            $uid = (int)$user['uid'];
            if ($uid === $author_uid || isset($uids[$uid])) {
                continue;
            }
            $uids[$uid]  = $uid;
            $names[$uid] = (string)$user['username'];
        }

        return [array_values($uids), $errors, array_values($names)];
    }
}
