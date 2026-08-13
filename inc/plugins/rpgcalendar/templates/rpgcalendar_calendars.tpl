<html>
<head>
<title>{$rpgcalendar_pagetitle}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="rpgcalendar">
<div class="rpgcalendar_eventview_back"><a href="{$calendars_backurl}">&laquo; {$lang->rpgcalendar_calendars_back}</a></div>
<div class="tborder rpgcalendar_caltable">
<div class="thead"><strong>{$lang->rpgcalendar_calendars_title}</strong></div>
<div class="rpgcalendar_calrows">
{$calendar_rows}
</div>
</div>
{$calendar_addform}
{$calendar_invites_block}
{$calendar_joinable_block}
</div>
{$footer}
</body>
</html>
