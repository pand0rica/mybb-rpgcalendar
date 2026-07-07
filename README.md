# RPG Calendar (rpgcalendar)

A MyBB 1.8 plugin that replaces the forum calendar view with a modern, minimalist month view
built for RPG forums: events in any year (far past or future), plots from a plot management
plugin, a free text per month (e.g. weather), and a table of all important events per month —
while keeping all existing native calendar entries visible.

> **Status: in development.** This README will be completed for the v1.0.0 release.

## Requirements

- MyBB 1.8.x
- PHP 7.4 or 8.x
- No dependencies (the Plot-CP integration is optional and activates automatically)

## Planned features (v1.0)

- Own calendar page `rpgcalendar.php` with a minimalist month view (MyBB theme classes)
- Native MyBB events keep working and are displayed read-only
- Extended events with arbitrary years (e.g. year 1247 or 3021), multi-day and repeating
- Free text per month + year (weather, moon phases, …)
- Table of important events below each month
- Provider hook `rpgcalendar_collect_events` for other plugins (Plot-CP)
- Birthdays from the native profile or from a custom profile field (IC birthdays)
- Management via ACP (Tools → RPG Calendar)

## Planned for v1.1

- Read-only Google Calendar import via secret ICS URLs (team feeds in ACP, private feeds
  in UserCP), fetched by a MyBB task

## Installation / Usage / Configuration

Will be documented for the v1.0.0 release.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
