<div class="rpgcalendar_eventview_response">
<div class="rpgcalendar_response_status">{$lang->rpgcalendar_invite_your_status} <strong class="rpgcalendar_invite--{$my_status_class}">{$my_status_label}</strong></div>
<form action="rpgcalendar.php?action=invite_respond" method="post" class="rpgcalendar_response_form">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<input type="hidden" name="eid" value="{$eid}" />
<input type="hidden" name="year" value="{$year}" />
<input type="hidden" name="month" value="{$month}" />
<input type="hidden" name="day" value="{$resp_day}" />
<input type="submit" name="reply_accept" class="button rpgcalendar_btn_accept" value="{$lang->rpgcalendar_invite_accept}" />
<input type="submit" name="reply_decline" class="button rpgcalendar_btn_decline" value="{$lang->rpgcalendar_invite_decline}" />
</form>
</div>
