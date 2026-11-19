<div class="rpgcalendar_mini rpgcalendar_mini--{$mini_position}" id="rpgcalendar_mini">
<div class="rpgcalendar_mini_head">
<a href="rpgcalendar.php" class="rpgcalendar_mini_title">{$lang->rpgcalendar_mini_title}</a>
<a href="rpgcalendar.php?action=minisettings" class="rpgcalendar_mini_settings" title="{$lang->rpgcalendar_minisettings_link}">&#9881;</a>
<button type="button" class="rpgcalendar_mini_toggle" aria-expanded="true" title="{$lang->rpgcalendar_mini_toggle}">&#9662;</button>
</div>
<div class="rpgcalendar_mini_body">
<div class="rpgcalendar_mini_months">{$mini_months}</div>
<div class="rpgcalendar_mini_legend">
<span class="rpgcalendar_mini_legend_item"><i class="rpgcalendar_mini_swatch rpgcalendar_mini_swatch--birthday"></i>{$lang->rpgcalendar_mini_type_birthday}</span>
<span class="rpgcalendar_mini_legend_item"><i class="rpgcalendar_mini_swatch rpgcalendar_mini_swatch--event"></i>{$lang->rpgcalendar_mini_type_event}</span>
<span class="rpgcalendar_mini_legend_item"><i class="rpgcalendar_mini_swatch rpgcalendar_mini_swatch--scene"></i>{$lang->rpgcalendar_mini_type_scene}</span>
{$mini_legend_moon}
</div>
</div>
<div class="rpgcalendar_mini_popover" role="dialog" hidden="hidden"></div>
</div>
<script src="{$mini_asset}"></script>
