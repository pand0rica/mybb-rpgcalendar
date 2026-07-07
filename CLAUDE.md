# rpgcalendar – Plugin Context

## Purpose

MyBB 1.8 plugin that replaces the native calendar view with a modern, minimalist month view
for RPG forums. It merges four event sources into one grid:

1. **Extended events** (own table `rpgcalendar_events`) – support arbitrary years
   (far past/future, signed SMALLINT), multi-day spans and repeats (weekly/monthly/yearly).
2. **Native MyBB events** (`mybb_events`, read-only) – displayed unchanged, only within
   1970–2037 (native events cannot exist outside that range).
3. **Plots** from the (planned) Plot-CP plugin – via the provider hook, never persisted here.
4. **Birthdays** – native `users.birthday` or a custom profile field (ACP setting).

Plus: one free text per month+year (weather etc.) and a table of important events per month.
The native `calendar.php` is NEVER modified – the header calendar link is rewritten at
runtime (template cache) when the setting is enabled.

## Architecture

Class/core split (like Blacklist):

- `inc/plugins/rpgcalendar.php` – lifecycle + hook registration only
- `inc/plugins/rpgcalendar/core/engine.php` – `Rpgcalendar_Engine`: calendar math
  (proleptic Gregorian day numbers, NO timestamps/mktime/DateTime), occurrence expansion,
  month grid, source merging, provider hook
- `inc/plugins/rpgcalendar/core/database.php` – `Rpgcalendar_Database`: all CRUD,
  overlap query via `start_key`/`end_key` (= y*10000+m*100+d, monotonic for negative years)
- `inc/plugins/rpgcalendar/core/native_adapter.php` – `Rpgcalendar_NativeAdapter`:
  the ONLY place that touches `mybb_events`/`get_events()`/`get_birthdays()`
- `inc/plugins/rpgcalendar/core/installer.php` – `Rpgcalendar_Installer`: tables,
  settings (idempotent), templates from .tpl files, stylesheet in all themes, full cleanup
- `rpgcalendar.php` (MyBB root) – frontend entry
- `admin/modules/tools/rpgcalendar.php` – ACP module (events + monthtexts, tabs)

## Tables

- `rpgcalendar_events` – extended events; `source` column prepared for 'ics' (v1.1)
- `rpgcalendar_monthtexts` – UNIQUE(year, month)
- `rpgcalendar_feeds` – created in v1.0, used from v1.1 (ICS import)

## Integration for Plot-CP (provider hook)

rpgcalendar fires once per month render:

```php
$hook_args = ['year' => $year, 'month' => $month, 'events' => []];
$plugins->run_hooks('rpgcalendar_collect_events', $hook_args);
```

A provider plugin (e.g. Plot-CP) registers:

```php
$plugins->add_hook('rpgcalendar_collect_events', 'plotcp_rpgcalendar_collect');

function plotcp_rpgcalendar_collect(array &$args): void
{
    // load own plots overlapping $args['year'] / $args['month'], then:
    $args['events'][] = [
        'id'        => 'plot-' . $plot_id,
        'source'    => 'plot',
        'title'     => $plot_title,               // raw, rpgcalendar escapes on render
        'url'       => 'plotcp.php?action=view&pid=' . $plot_id,
        'start'     => ['y' => 1247, 'm' => 3, 'd' => 12],
        'end'       => ['y' => 1247, 'm' => 4, 'd' => 2],   // >= start; single day: = start
        'important' => true,                      // shows up in the month event table
        'badge'     => 'plot',                    // CSS class rpgcalendar_event--plot
    ];
}
```

Contract guarantees: hook fires exactly once per month render; events outside the requested
month are ignored (providers may over-deliver); escaping is done by rpgcalendar at render
time; malformed entries are silently discarded (a broken provider must never break the page).
There is also an optional output hook `rpgcalendar_monthview_end`
(`['year','month','output' => &$html]`) below the event table.

## Calendar math conventions (do not change lightly)

- Proleptic Gregorian calendar for ALL years; astronomical year numbering (year 0 exists).
- `dayNumber()` = days-from-civil algorithm (pure integer, works for negative years);
  `weekday()` derived from it. NO mktime/gmmktime outside the native adapter, NO DateTime.
- Monthly repeat on day 31 skips shorter months; yearly repeat on Feb 29 skips non-leap
  years (no shifting to Feb 28). Documented in README.

## What NOT to change

- Never write to `mybb_events` / `mybb_calendars` – native data is read-only here.
- Never patch `calendar.php` or any core file (the predecessor plugin did; that's why it died).
- `start_key`/`end_key` are computed in PHP (`Rpgcalendar_Database`), not by the DB.
- Keep `rpgcalendar_feeds` + `source`/`source_uid`/`feed_id`/`private` columns even though
  v1.0 doesn't use them – v1.1 (ICS import) depends on them.

## Testing

No local MyBB. Deploy to test forum via scp/rsync (`<user>@…`, path
`<forum-root>/`), lint with `php -l` over SSH, run `tests/cli-selftest.php`
on the server (bootstraps `inc/init.php`), verify pages with curl. `tests/` is not part
of a release zip.
