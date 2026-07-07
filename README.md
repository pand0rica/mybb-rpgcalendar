# RPG Calendar (rpgcalendar)

A MyBB 1.8 plugin that replaces the forum calendar view with a modern, minimalist month view
built for RPG forums. It merges four event sources into one grid:

- **Native MyBB events** – everything your members already entered keeps working and stays
  visible (read-only, managed through the normal calendar as before)
- **Extended events** – events in *any* year, e.g. 1247 or 3021 or -50 (the native calendar
  is limited to "current year + 5"), including multi-day spans and weekly/monthly/yearly repeats
- **Plots** – delivered automatically by a plot management plugin (e.g. Plot-CP) through a
  documented provider hook, no configuration needed
- **Birthdays** – from the native profile birthday or from a custom profile field
  (in-character birthdays that appear in every displayed year)

Plus a free text per month + year (weather, moon phases, …) above the grid and a table of
all important events below it.

![Month view](docs/screenshots/monthview.png)

## Requirements

- MyBB 1.8.x
- PHP 7.4 or 8.x
- No dependencies. The Plot-CP integration is optional and activates automatically once a
  provider plugin is installed.

## Installation

1. Upload all files from the archive into your MyBB root directory (the folder structure
   matches the forum structure: `rpgcalendar.php`, `inc/`, `admin/`).
2. Go to **ACP → Configuration → Plugins** and click **Install & Activate** next to
   *RPG Calendar*.
3. Go to **ACP → Configuration → Settings → RPG Calendar** and review the settings
   (see [Configuration](#configuration)).
4. Open `yourforum.tld/rpgcalendar.php` – the month view appears. If the *Rewrite header
   calendar link* setting is enabled, the calendar link in the forum header now points there
   automatically.

## Usage

### First steps after activation

1. **ACP → Configuration → Settings → RPG Calendar**: check *Native calendar ID* (the ID of
   the calendar whose events should be merged in; the default calendar of a fresh MyBB has
   ID 1) and set *Minimum/Maximum year* to the range your RPG plays in.
2. Open the calendar page once and use the month/year jump form to navigate to your RPG's
   current in-game month.
3. Optionally set *Default year* / *Default month* so the page opens on your in-game date
   instead of the real-world date.

### Managing extended events (Admin)

Path: **ACP → Tools & Maintenance → RPG Calendar**

- The **Events** tab lists all extended events with a year filter and pagination.
- **Add event** creates a new event:
  - *Title* – shown in the day cell and the important-events table
  - *Description* – optional, shown (shortened) in the important-events table
  - *Start date* – day / month / year; any year within the configured range is allowed
  - *Multi-day event* + *End date* – for events spanning several days (may cross month and
    year borders)
  - *Repeat* – none / weekly / monthly / yearly, with an optional *Repeat until* date.
    Conventions: a monthly event on the 31st skips months without a 31st; a yearly event
    on Feb 29 only occurs in leap years.
  - *Important event* – important events are listed in the table below the month view
  - *Visible* – hide an event without deleting it
- **Edit** / **Delete** are available in the list. Deleting asks no questions – it is a
  single event row, re-create it if needed.

Native forum events are *not* edited here – they are managed through the normal MyBB
calendar like before, and rpgcalendar displays them read-only (with a link to the native
event page).

### Month texts (Admin)

Path: **ACP → Tools & Maintenance → RPG Calendar → Month texts**

One free text per month + year, shown in a box above the grid – ideal for weather, moon
phases or a short summary of what is going on in your world that month. MyCode is allowed,
HTML is not. Saving a text for a month that already has one overwrites it.

### Birthdays

Two sources, selected by the *Birthday source* setting:

- **0 (default)**: the native profile birthday. Shown only when the displayed year is close
  to the real current year (*Birthday year range* setting), because real birthdays make no
  sense in the year 1247. The age is only shown in the real current year.
- **Profile field ID**: a custom profile field containing an in-character birthday in the
  format `day-month-year` (e.g. `12-3-1200`) or `day-month`. These birthdays appear in
  *every* displayed year from the birth year onwards, with the age computed against the
  displayed year – perfect for character birthdays in an RPG timeline.

### For members

Members just open the calendar (header link or `rpgcalendar.php`) and navigate months with
the ‹ › links or the month/year jump form. Private native events stay private. Members keep
adding their own events through the native calendar as before.

## Configuration

Path: **ACP → Configuration → Settings → RPG Calendar**

| Setting | Meaning |
|---|---|
| Enable RPG Calendar | Master switch for the frontend page |
| Rewrite header calendar link | Points the calendar link in the header to rpgcalendar.php (runtime rewrite, fully reversible by disabling) |
| Link rewrite templates | Comma-separated template names in which `calendar.php` links are rewritten. Default `header_menu_calendar` (the MyBB standard). Themes with custom headers can add their own template names here – no template edit needed |
| Allowed user groups | Who may view the calendar page (none selected = everyone) |
| Native calendar ID | The native calendar whose events are merged in; its "start of week" is reused |
| Show native events | Merge native events (technically limited to years 1970–2037) |
| Show birthdays | Show birthdays in the grid |
| Birthday source | 0 = native profile birthday, otherwise the fid of a custom profile field |
| Birthday year range | For the native source only: max. distance from the real current year |
| Minimum / Maximum year | Navigation and input limits |
| Default year / month | Start view when opened without parameters; 0 = real today |

## Integration for plot plugins (Plot-CP)

rpgcalendar fires a provider hook once per rendered month. Any plugin can deliver events –
they appear in the grid and (by default) in the important-events table with a `plot` badge:

```php
$plugins->add_hook('rpgcalendar_collect_events', 'plotcp_rpgcalendar_collect');

function plotcp_rpgcalendar_collect(array &$args)
{
    // $args = ['year' => 1247, 'month' => 3, 'events' => [...]]
    // load your plots overlapping that month, then append:
    $args['events'][] = [
        'id'        => 'plot-' . $plot_id,      // unique string id
        'source'    => 'plot',
        'title'     => $plot_title,             // raw text, rpgcalendar escapes it
        'url'       => 'plotcp.php?action=view&pid=' . $plot_id,  // optional
        'start'     => ['y' => 1247, 'm' => 3, 'd' => 12],
        'end'       => ['y' => 1247, 'm' => 4, 'd' => 2],  // optional, >= start
        'important' => true,                    // optional, default true
        'badge'     => 'plot',                  // optional CSS badge suffix
    ];
}
```

Contract guarantees:

- The hook fires exactly once per month render with `year`, `month`, `events`.
- Events outside the requested month are ignored – providers may over-deliver.
- Escaping is done by rpgcalendar at render time – deliver raw titles.
- Malformed entries are silently discarded; a broken provider never breaks the page.
- Only `http(s)` or relative URLs are accepted.

There is also an output hook `rpgcalendar_monthview_end`
(`['year' => int, 'month' => int, 'output' => &$html]`) to append content below the
important-events table.

## Calendar math (important to know)

- All dates use the **proleptic Gregorian calendar** with astronomical year numbering
  (year 0 exists, negative years are allowed). Historical Julian dates before 1582 are not
  converted – this is a deliberate simplification for fictional RPG timelines.
- A monthly repeat on day 31 skips shorter months (no shifting to the 30th).
- A yearly repeat on Feb 29 occurs only in leap years (no shifting to Feb 28).

## Uninstallation

1. **ACP → Configuration → Plugins → RPG Calendar → Deactivate** (removes templates,
   settings and the stylesheet).
2. Click **Uninstall** (drops the three plugin tables `rpgcalendar_events`,
   `rpgcalendar_monthtexts`, `rpgcalendar_feeds` – your extended events and month texts are
   deleted; native calendar data is never touched).
3. Delete the uploaded files (`rpgcalendar.php`, `inc/plugins/rpgcalendar.php`,
   `inc/plugins/rpgcalendar/`, `admin/modules/tools/rpgcalendar.php`, the language files).

## Screenshots

![Month view with events and month text](docs/screenshots/monthview.png)
![Important events table](docs/screenshots/eventtable.png)
![ACP: event list](docs/screenshots/acp-events.png)
![ACP: add event](docs/screenshots/acp-add-event.png)
![ACP: month texts](docs/screenshots/acp-monthtexts.png)

## Planned for v1.1

- Read-only Google Calendar import via secret ICS URLs: team feeds in the ACP, private
  feeds in the UserCP, fetched periodically by a MyBB task. The database schema is already
  prepared (`rpgcalendar_feeds` table, `source`/`source_uid`/`feed_id`/`private` columns).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
