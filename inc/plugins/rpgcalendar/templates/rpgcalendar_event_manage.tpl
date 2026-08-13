<div class="rpgcalendar_eventview_manage">
<a href="{$edit_url}" class="button rpgcalendar_btn_edit">{$lang->rpgcalendar_edit_event}</a>
<form action="rpgcalendar.php?action=delete" method="post" class="rpgcalendar_delete_form" onsubmit="return confirm('{$lang->rpgcalendar_delete_confirm}');">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<input type="hidden" name="eid" value="{$eid}" />
<input type="hidden" name="year" value="{$year}" />
<input type="hidden" name="month" value="{$month}" />
<input type="hidden" name="day" value="{$manage_day}" />
<button type="submit" class="button rpgcalendar_btn_delete">{$lang->rpgcalendar_delete_event}</button>
</form>
</div>
