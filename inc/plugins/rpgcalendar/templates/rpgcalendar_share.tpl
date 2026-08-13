<html>
<head>
<title>{$rpgcalendar_pagetitle}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="rpgcalendar">
<div class="rpgcalendar_eventview_back"><a href="{$share_backurl}">&laquo; {$lang->rpgcalendar_calendars_back}</a></div>
<div class="tborder rpgcalendar_caltable">
<div class="thead"><strong>{$lang->sprintf($lang->rpgcalendar_share_heading, $share_cal_name)}</strong></div>
<div class="rpgcalendar_calrows">
{$member_rows}
</div>
</div>
<form action="rpgcalendar.php?action=do_share_add" method="post" class="rpgcalendar_caladd">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<input type="hidden" name="cid" value="{$cid}" />
<strong>{$lang->rpgcalendar_share_add}</strong>
<input type="text" name="username" id="rpgcalendar_shareuser" value="" class="textbox" placeholder="{$lang->rpgcalendar_ac_placeholder}" />
<select name="role" class="textbox">
<option value="viewer">{$lang->rpgcalendar_role_viewer}</option>
<option value="editor">{$lang->rpgcalendar_role_editor}</option>
</select>
<input type="submit" class="button" value="{$lang->rpgcalendar_share_add}" />
</form>
<p class="smalltext">{$lang->rpgcalendar_share_add_desc}</p>
{$rpgcalendar_autocomplete}
</div>
{$footer}
</body>
</html>
