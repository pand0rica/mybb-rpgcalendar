<html>
<head>
<title>{$rpgcalendar_pagetitle}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="rpgcalendar">
<div class="rpgcalendar_eventview_back"><a href="{$form_backurl}">&laquo; {$lang->rpgcalendar_form_back}</a></div>
{$form_errors}
<form action="{$form_action}" method="post">
<input type="hidden" name="my_post_key" value="{$post_code}" />
<input type="hidden" name="nav_day" value="{$nav_day}" />
{$eid_field}
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder rpgcalendar_formtable">
<tr>
<td class="thead" colspan="2"><strong>{$form_title}</strong></td>
</tr>
<tr>
<td class="trow1" width="30%"><strong>{$lang->rpgcalendar_field_title}</strong></td>
<td class="trow1"><input type="text" name="title" value="{$title_value}" maxlength="200" class="textbox rpgcalendar_formfield" /></td>
</tr>
<tr>
<td class="trow2"><strong>{$lang->rpgcalendar_field_description}</strong></td>
<td class="trow2"><textarea name="description" rows="4" class="textbox rpgcalendar_formfield">{$description_value}</textarea></td>
</tr>
<tr>
<td class="trow1"><strong>{$lang->rpgcalendar_field_start}</strong></td>
<td class="trow1">{$start_inputs}</td>
</tr>
<tr>
<td class="trow2"><strong>{$lang->rpgcalendar_field_end}</strong></td>
<td class="trow2">{$end_inputs}<br /><span class="smalltext">{$lang->rpgcalendar_field_end_desc}</span></td>
</tr>
<tr>
<td class="trow2"><strong>{$lang->rpgcalendar_field_repeat}</strong></td>
<td class="trow2"><select name="repeat_type" class="textbox">{$repeat_options}</select>
&nbsp; {$lang->rpgcalendar_field_interval_every} <input type="number" name="repeat_interval" value="{$repeat_interval_value}" min="1" max="999" class="textbox rpgcalendar_dayinput" /><br /><span class="smalltext">{$lang->rpgcalendar_field_interval_desc}</span></td>
</tr>
<tr>
<td class="trow1"><strong>{$lang->rpgcalendar_field_repeat_until}</strong></td>
<td class="trow1">{$until_inputs}</td>
</tr>
<tr>
<td class="trow2"><strong>{$lang->rpgcalendar_field_important}</strong></td>
<td class="trow2"><label><input type="checkbox" name="important" value="1"{$important_checked} /> {$lang->rpgcalendar_field_important_desc}</label></td>
</tr>
{$calendar_row}
<tr>
<td class="trow1"><strong>{$lang->rpgcalendar_field_invites}</strong></td>
<td class="trow1"><input type="text" name="invites" id="rpgcalendar_invites" value="{$invite_value}" class="textbox rpgcalendar_formfield" /><br /><span class="smalltext">{$lang->rpgcalendar_field_invites_desc}</span></td>
</tr>
</table>
<div class="rpgcalendar_form_submit"><input type="submit" name="submit" value="{$lang->rpgcalendar_save_event}" class="button" /></div>
</form>
{$rpgcalendar_autocomplete}
</div>
{$footer}
</body>
</html>
