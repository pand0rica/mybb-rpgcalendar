# Changelog

All notable changes to this project will be documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/).

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
