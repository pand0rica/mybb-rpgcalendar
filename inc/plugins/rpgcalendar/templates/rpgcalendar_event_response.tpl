<div class="rpgcalendar_eventview_response">
<div class="rpgcalendar_response_status">{$lang->rpgcalendar_invite_your_status} <strong class="rpgcalendar_invite--{$my_status_class}">{$my_status_label}</strong></div>
<form action="rpgcalendar.php?action=invite_respond" method="post" class="rpgcalendar_response_form">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<input type="hidden" name="eid" value="{$eid}" />
<input type="hidden" name="year" value="{$year}" />
<input type="hidden" name="month" value="{$month}" />
<input type="hidden" name="day" value="{$resp_day}" />
<button type="submit" name="reply" value="accept" class="button rpgcalendar_btn_accept">{$lang->rpgcalendar_invite_accept}</button>
<button type="submit" name="reply" value="decline" class="button rpgcalendar_btn_decline">{$lang->rpgcalendar_invite_decline}</button>
</form>
</div>
