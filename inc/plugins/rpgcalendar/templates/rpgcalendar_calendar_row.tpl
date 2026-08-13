<div class="rpgcalendar_calrow trow1">
<form action="rpgcalendar.php?action=do_calendar_row" method="post" class="rpgcalendar_calrow_form">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<input type="hidden" name="calkey" value="{$cal_key}" />
<span class="rpgcalendar_calrow_swatch" style="background:{$eff_color}"></span>
<span class="rpgcalendar_calrow_name">{$cal_name_field}</span>
<span class="rpgcalendar_calrow_type smalltext">{$cal_type_label}</span>
<input type="color" name="color" value="{$eff_color}" class="rpgcalendar_colorpick" title="{$lang->rpgcalendar_cal_color}" />
<label class="rpgcalendar_calrow_vis"><input type="checkbox" name="visible" value="1"{$vis_checked} /> {$lang->rpgcalendar_cal_visible}</label>
<input type="submit" class="button" value="{$lang->rpgcalendar_cal_save}" />
</form>
{$cal_controls}
</div>
