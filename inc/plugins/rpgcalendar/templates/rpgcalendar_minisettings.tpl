<html>
<head>
<title>{$rpgcalendar_pagetitle}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="rpgcalendar">
<div class="rpgcalendar_eventview_back"><a href="{$minisettings_backurl}">&laquo; {$lang->rpgcalendar_calendars_back}</a></div>
<form action="rpgcalendar.php?action=do_minisettings" method="post">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<div class="tborder rpgcalendar_caltable">
<div class="thead"><strong>{$lang->rpgcalendar_minisettings_title}</strong></div>
<div class="trow1 rpgcalendar_calrows">
<p class="smalltext">{$lang->rpgcalendar_minisettings_hint}</p>
<p><label><input type="checkbox" name="mini_enabled" value="1"{$mini_enabled_checked} /> {$lang->rpgcalendar_minisettings_enable}</label></p>
<p>{$lang->rpgcalendar_minisettings_position}:
<select name="mini_position">
<option value="inplay"{$mini_pos_inplay_selected}>{$lang->rpgcalendar_minisettings_pos_inplay}</option>
<option value="header"{$mini_pos_header_selected}>{$lang->rpgcalendar_minisettings_pos_header}</option>
</select></p>
<p><strong>{$lang->rpgcalendar_minisettings_show}</strong></p>
<p><label><input type="checkbox" name="show_birthdays" value="1"{$mini_birthdays_checked} /> {$lang->rpgcalendar_minisettings_show_birthdays}</label><br />
{$lang->rpgcalendar_minisettings_show_events}:
<select name="events_mode">
<option value="all"{$mini_events_all_selected}>{$lang->rpgcalendar_minisettings_events_all}</option>
<option value="important"{$mini_events_important_selected}>{$lang->rpgcalendar_minisettings_events_important}</option>
<option value="none"{$mini_events_none_selected}>{$lang->rpgcalendar_minisettings_events_none}</option>
</select><br />
<label><input type="checkbox" name="edges_only" value="1"{$mini_edges_checked} /> {$lang->rpgcalendar_minisettings_edges_only}</label><br />
<label><input type="checkbox" name="show_scenes" value="1"{$mini_scenes_checked} /> {$lang->rpgcalendar_minisettings_show_scenes}</label><br />
<label><input type="checkbox" name="show_moon" value="1"{$mini_moon_checked} /> {$lang->rpgcalendar_minisettings_show_moon}</label></p>
<p class="smalltext">{$lang->rpgcalendar_minisettings_scenes_hint}</p>
<p><input type="submit" class="button" value="{$lang->rpgcalendar_cal_save}" /></p>
</div>
</div>
</form>
</div>
{$footer}
</body>
</html>
