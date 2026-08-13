<div class="rpgcalendar_calrow trow1">
<span class="rpgcalendar_calrow_name">{$inv_name}</span>
<span class="smalltext">{$lang->sprintf($lang->rpgcalendar_invite_from, $inv_owner)} &middot; {$inv_role}</span>
<form action="rpgcalendar.php?action=do_share_respond" method="post" class="rpgcalendar_calrow_form">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<input type="hidden" name="cid" value="{$inv_cid}" />
<button type="submit" name="reply" value="accept" class="button">{$lang->rpgcalendar_invite_accept}</button>
<button type="submit" name="reply" value="decline" class="button">{$lang->rpgcalendar_invite_decline}</button>
</form>
</div>
