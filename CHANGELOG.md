# Changelog

All notable changes to this project will be documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/).

## [1.2.0] - 2026-08-14

### New
- Play locations (ACP → RPG Calendar → Locations), each real or fictional. For **real**
  locations the plugin generates real weather from Open-Meteo (archive / forecast / climate
  normals, mapped from WMO weather codes to condition slugs) and public holidays from
  Nager.Date, stored as editable "auto" entries. Triggered by an ACP *Generate* button over a
  year range; re-generating replaces auto entries but keeps manual ones (editing an auto entry
  converts it to manual). City names are geocoded via Open-Meteo. **Fictional** locations show
  a hint asking the team to add data by hand when a shown month has no data.
- Each location exposes two virtual calendars, `weather-{locid}` and `holiday-{locid}`, that
  plug into the existing per-user calendar system: members recolour or hide them on the
  "My calendars" page like any other calendar (no separate toggle). Weather sits above
  holidays in a day cell; holidays also appear in the important-events table.
- Weather has one condition per WMO weather code (28 codes, localized labels), plus a derived
  `windy` condition (from the daily max wind speed, threshold configurable). Weather chips show
  an image named after the code (`61.svg`, …) from a configurable directory with a localized
  text fallback; several variant images per code are supported via a dash suffix
  (`61-1.svg`, …) and one is picked deterministically per entry. A default icon set ships with
  the plugin. One configurable symbol precedes every holiday. Weather and holiday chips are
  clickable and open a small detail page with a short, localized description.
- Month-text pre-fill in the ACP: generate a suggested month text from a location's weather and
  holidays, then edit and save it.
- Generated holidays use the country's local name (e.g. German for `DE`); the English name is
  no longer stored. Weather conditions show localized labels in the ACP too (localized dropdown
  in the form, localized label in the list). A "Delete all generated" button on the Weather and
  Holidays tabs removes all auto-generated entries at once (honouring the location filter),
  leaving manual entries intact.
- New tables `rpgcalendar_locations`, `rpgcalendar_weather`, `rpgcalendar_holidays`; new
  settings for the weather image directory, holiday symbol and missing-data hint. Tables are
  created idempotently on activation (upgrade path).

### Changed
- Shared `datedOccurrenceStarts()` engine helper for one-time and yearly occurrence math,
  reused by weather and holidays.

### Security
- New writes go through POST + `verify_post_check()`, input via `get_input()`, output escaped.
  Outbound requests happen only on an explicit ACP generate action, via MyBB's
  `fetch_remote_file()`, and send only team-entered location data (no member data).

## [1.1.0] - 2026-08-13

### New
- Day view (`action=day`): click a day cell to open a full list of that day's events;
  from here members can add events directly on the frontend
- Frontend event creation, editing and deletion for logged-in members, with the same
  power as the ACP form (multi-day spans, weekly/monthly/yearly repeats with an interval,
  important flag)
- Team groups (`rpgcalendar_team_groups` setting) may create **forum-wide** events visible
  to everyone; all other members create **personal** events for themselves
- Event invitations: invite individual members to an event via username autocomplete; they
  are notified by PM and can accept or decline, and see the event on their own calendar
- Personal and shared **sub-calendars**: a "My calendars" page where each member creates
  their own calendars and picks a per-user colour and show/hide state for every calendar
  they can see (including the built-in Birthdays calendar)
- Calendar sharing: share a calendar with other members as **Viewer** (read-only) or
  **Editor** (may add/edit events); invitees accept or decline via PM, and can leave later
- Joinable calendars: global calendars an admin marks as joinable let members self-subscribe
- ACP "Calendars" tab: manage global sub-calendars (name, default colour, joinable flag)
- Global template variable `{$rpgcalendar_current_monthtexts}` (`rpgcalendar_current_inplay`
  setting) to surface the current in-play month texts in templates and forum descriptions
- `rpgcalendar_html_titles` setting to optionally allow HTML in event titles on trusted calendars
- Provider contract extended with virtual sub-calendars: new `rpgcalendar_collect_calendars`
  hook and optional `calendar_key` / `default_color` fields on collected events, so plot
  plugins can expose their own colourable, hideable calendars

## [1.0.0] - 2026-07-07

### New
- Own calendar page `rpgcalendar.php` with a minimalist, responsive month view
  (MyBB theme classes, own stylesheet attached to the page only)
- Extended events with arbitrary years (signed SMALLINT, proleptic Gregorian calendar,
  astronomical year numbering – year 0 exists, negative years supported)
- Multi-day events across month/year borders and repeats (weekly/monthly/yearly)
  with optional end date; monthly repeats skip months without the start day,
  yearly Feb-29 repeats skip non-leap years
- Native MyBB events merged read-only via `get_events()` (incl. native repeats),
  linked to the native event page; silently skipped outside 1970–2037
- Month free text per month + year (MyCode parsed, no HTML)
- Important-events table below the month view
- Provider hook `rpgcalendar_collect_events` for plot plugins (Plot-CP) with
  defensive sanitizing – malformed provider entries never break the page;
  output hook `rpgcalendar_monthview_end`
- Birthdays from the native profile (year-range guarded) or from a custom
  profile field (IC birthdays, shown from the birth year onwards with computed age)
- Configurable runtime rewrite of calendar links in the template cache
  (`header_menu_calendar` by default, custom header templates addable via setting) –
  no template edits, no core edits, fully reversible
- ACP module (Tools → RPG Calendar): event list with year filter and pagination,
  add/edit/delete with full validation, month text management
- Day cells cap at 4 event chips with a "+N more" indicator
- Schema prepared for v1.1 ICS import (`rpgcalendar_feeds` table,
  `source`/`source_uid`/`feed_id`/`private` columns)

### Security
- All writes via POST + `verify_post_check()`, all input via `get_input()` with
  type constants, all output escaped with `htmlspecialchars_uni()`
- Provider URLs restricted to http(s)/relative (blocks `javascript:`)
