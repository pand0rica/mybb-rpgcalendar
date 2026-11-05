<div class="rpgcalendar_calrow trow1">
<span class="rpgcalendar_calrow_name">{$inv_name}</span>
<span class="smalltext">{$lang->sprintf($lang->rpgcalendar_invite_from, $inv_owner)} &middot; {$inv_role}</span>
<form action="rpgcalendar.php?action=do_share_respond" method="post" class="rpgcalendar_calrow_form">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<input type="hidden" name="cid" value="{$inv_cid}" />
<input type="submit" name="reply_accept" class="button" value="{$lang->rpgcalendar_invite_accept}" />
<input type="submit" name="reply_decline" class="button" value="{$lang->rpgcalendar_invite_decline}" />
</form>
</div>
