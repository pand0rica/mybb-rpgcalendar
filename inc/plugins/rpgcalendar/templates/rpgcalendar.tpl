<html>
<head>
<title>{$rpgcalendar_pagetitle}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="rpgcalendar">
{$rpgcalendar_nav}
{$rpgcalendar_calendars_link}
{$rpgcalendar_monthtext}
{$rpgcalendar_hint}
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder rpgcalendar_table">
<tr>
<td class="thead" colspan="7"><strong>{$rpgcalendar_monthtitle}</strong></td>
</tr>
<tr>
{$weekdayheader}
</tr>
{$weekrows}
</table>
{$rpgcalendar_eventtable}
{$rpgcalendar_footer_html}
</div>
{$footer}
</body>
</html>
