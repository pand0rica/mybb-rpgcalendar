<div class="rpgcalendar_calrow trow1">
<span class="rpgcalendar_calrow_name"><strong>{$join_name}</strong><br /><span class="smalltext">{$join_desc}</span></span>
<form action="rpgcalendar.php?action=do_calendar_join" method="post" class="rpgcalendar_calrow_form">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<input type="hidden" name="cid" value="{$join_cid}" />
<input type="submit" class="button" value="{$lang->rpgcalendar_join}" />
</form>
</div>
