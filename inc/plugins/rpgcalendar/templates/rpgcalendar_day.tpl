<html>
<head>
<title>{$rpgcalendar_pagetitle}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="rpgcalendar">
<div class="rpgcalendar_eventview_back"><a href="{$day_backurl}">&laquo; {$lang->rpgcalendar_event_back}</a></div>
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder rpgcalendar_daytable">
<tr>
<td class="thead" colspan="2"><strong>{$rpgcalendar_daydate}</strong></td>
</tr>
<tr>
<td class="tcat rpgcalendar_daycol_type">{$lang->rpgcalendar_col_type}</td>
<td class="tcat">{$lang->rpgcalendar_col_event}</td>
</tr>
{$day_rows}
</table>
{$rpgcalendar_addbutton}
</div>
{$footer}
</body>
</html>
