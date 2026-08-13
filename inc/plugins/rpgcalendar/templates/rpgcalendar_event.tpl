<html>
<head>
<title>{$event_pagetitle}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="rpgcalendar">
<div class="rpgcalendar_eventview_back"><a href="{$event_backurl}">&laquo; {$lang->rpgcalendar_event_back}</a></div>
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder rpgcalendar_eventview">
<tr>
<td class="thead"><strong>{$event_title}</strong></td>
</tr>
<tr>
<td class="trow1 rpgcalendar_eventview_meta">
<span class="rpgcalendar_eventview_date">{$event_date}</span>
{$event_author_line}
</td>
</tr>
<tr>
<td class="trow2 rpgcalendar_eventview_body">{$event_description}</td>
</tr>
</table>
{$event_invites}
{$event_response_form}
{$event_manage}
</div>
{$footer}
</body>
</html>
