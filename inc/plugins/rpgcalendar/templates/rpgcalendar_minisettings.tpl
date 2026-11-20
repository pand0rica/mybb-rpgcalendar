<html>
<head>
<title>{$rpgcalendar_pagetitle}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$usercpnav}
<td valign="top">
<div class="rpgcalendar">
<div class="rpgcalendar_eventview_back"><a href="{$minisettings_backurl}">&laquo; {$lang->rpgcalendar_calendars_back}</a></div>
<form action="rpgcalendar.php?action=do_minisettings" method="post">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder h4w-cp-form">
<tr>
<td class="thead"><strong>{$lang->rpgcalendar_minisettings_title}</strong></td>
</tr>
<tr>
<td class="trow1" valign="top">
<p class="smalltext">{$lang->rpgcalendar_minisettings_hint}</p>
<fieldset class="trow2">
<legend><strong>{$lang->rpgcalendar_minisettings_general}</strong></legend>
<table cellspacing="0" cellpadding="2">
<tr>
<td valign="middle" width="1"><input type="checkbox" class="checkbox" name="mini_enabled" id="mini_enabled" value="1"{$mini_enabled_checked} /></td>
<td><span class="smalltext"><label for="mini_enabled">{$lang->rpgcalendar_minisettings_enable}</label></span></td>
</tr>
<tr>
<td colspan="2"><span class="smalltext"><label for="mini_position">{$lang->rpgcalendar_minisettings_position}</label></span></td>
</tr>
<tr>
<td colspan="2">
<select name="mini_position" id="mini_position">
<option value="inplay"{$mini_pos_inplay_selected}>{$lang->rpgcalendar_minisettings_pos_inplay}</option>
<option value="header"{$mini_pos_header_selected}>{$lang->rpgcalendar_minisettings_pos_header}</option>
</select>
</td>
</tr>
</table>
</fieldset>
<br />
<fieldset class="trow2">
<legend><strong>{$lang->rpgcalendar_minisettings_show}</strong></legend>
<table cellspacing="0" cellpadding="2">
<tr>
<td valign="middle" width="1"><input type="checkbox" class="checkbox" name="show_birthdays" id="show_birthdays" value="1"{$mini_birthdays_checked} /></td>
<td><span class="smalltext"><label for="show_birthdays">{$lang->rpgcalendar_minisettings_show_birthdays}</label></span></td>
</tr>
<tr>
<td valign="middle" width="1"><input type="checkbox" class="checkbox" name="show_scenes" id="show_scenes" value="1"{$mini_scenes_checked} /></td>
<td><span class="smalltext"><label for="show_scenes">{$lang->rpgcalendar_minisettings_show_scenes}</label></span></td>
</tr>
<tr>
<td valign="middle" width="1"><input type="checkbox" class="checkbox" name="show_moon" id="show_moon" value="1"{$mini_moon_checked} /></td>
<td><span class="smalltext"><label for="show_moon">{$lang->rpgcalendar_minisettings_show_moon}</label></span></td>
</tr>
<tr>
<td colspan="2"><span class="smalltext"><label for="events_mode">{$lang->rpgcalendar_minisettings_show_events}</label></span></td>
</tr>
<tr>
<td colspan="2">
<select name="events_mode" id="events_mode">
<option value="all"{$mini_events_all_selected}>{$lang->rpgcalendar_minisettings_events_all}</option>
<option value="important"{$mini_events_important_selected}>{$lang->rpgcalendar_minisettings_events_important}</option>
<option value="none"{$mini_events_none_selected}>{$lang->rpgcalendar_minisettings_events_none}</option>
</select>
</td>
</tr>
<tr>
<td valign="middle" width="1"><input type="checkbox" class="checkbox" name="edges_only" id="edges_only" value="1"{$mini_edges_checked} /></td>
<td><span class="smalltext"><label for="edges_only">{$lang->rpgcalendar_minisettings_edges_only}</label></span></td>
</tr>
</table>
</fieldset>
<p class="smalltext">{$lang->rpgcalendar_minisettings_scenes_hint}</p>
</td>
</tr>
</table>
<br />
<div align="center"><input type="submit" class="button" value="{$lang->rpgcalendar_cal_save}" /></div>
</form>
</div>
</td>
</tr>
</table>
{$footer}
</body>
</html>
