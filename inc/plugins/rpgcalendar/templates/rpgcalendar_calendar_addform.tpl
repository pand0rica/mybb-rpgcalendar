<form action="rpgcalendar.php?action=do_calendar_add" method="post" class="rpgcalendar_caladd">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<strong>{$lang->rpgcalendar_cal_add}</strong>
<input type="text" name="name" value="" maxlength="120" class="textbox" placeholder="{$lang->rpgcalendar_cal_name}" />
<input type="color" name="color" value="#3b82f6" class="rpgcalendar_colorpick" />
<input type="submit" class="button" value="{$lang->rpgcalendar_cal_add}" />
</form>
