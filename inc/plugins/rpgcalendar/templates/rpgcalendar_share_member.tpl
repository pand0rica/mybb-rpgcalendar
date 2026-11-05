<div class="rpgcalendar_calrow trow1">
<span class="rpgcalendar_calrow_name">{$member_link}</span>
<span class="smalltext">{$role_label} &middot; {$status_label}</span>
<form action="rpgcalendar.php?action=do_share_remove" method="post" class="rpgcalendar_calrow_delete">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<input type="hidden" name="cid" value="{$cid}" />
<input type="hidden" name="uid" value="{$member_uid}" />
<input type="submit" class="button rpgcalendar_btn_delete" value="{$lang->rpgcalendar_share_remove}" />
</form>
</div>
