<div class="rpgcalendar_nav">
<div class="rpgcalendar_nav_links">
<span class="rpgcalendar_nav_prev">{$nav_prev}</span>
<span class="rpgcalendar_nav_current"><strong>{$rpgcalendar_monthtitle}</strong></span>
<span class="rpgcalendar_nav_next">{$nav_next}</span>
</div>
<form action="rpgcalendar.php" method="get" class="rpgcalendar_nav_jump">
<select name="month" class="rpgcalendar_monthselect">{$month_options}</select>
<input type="number" name="year" value="{$year}" min="{$min_year}" max="{$max_year}" class="textbox rpgcalendar_yearinput" />
<input type="submit" class="button" value="{$lang->rpgcalendar_go}" />
</form>
</div>
