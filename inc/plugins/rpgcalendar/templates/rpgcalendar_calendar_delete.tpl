<form action="rpgcalendar.php?action=do_calendar_delete" method="post" class="rpgcalendar_calrow_delete" onsubmit="return confirm('{$lang->rpgcalendar_cal_delete_confirm}');">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<input type="hidden" name="cid" value="{$cid}" />
<input type="submit" class="button rpgcalendar_btn_delete" value="{$lang->rpgcalendar_cal_delete}" />
</form>
