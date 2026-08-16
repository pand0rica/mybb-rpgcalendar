<?php
// rpgcalendar - admin language file (English)

$l['rpgcalendar']             = "RPG Calendar";
$l['rpgcalendar_description'] = "Manage extended calendar events and monthly texts.";

// Setting group
$l['setting_group_rpgcalendar']      = "RPG Calendar";
$l['setting_group_rpgcalendar_desc'] = "Settings for the RPG Calendar plugin.";

// Tabs
$l['rpgcalendar_tab_events']          = "Events";
$l['rpgcalendar_tab_desc_events']     = "All extended calendar events. Native forum events are managed via the normal calendar.";
$l['rpgcalendar_tab_add_event']       = "Add event";
$l['rpgcalendar_tab_desc_add_event']  = "Create a new extended event. Any year is allowed, including the far past and future.";
$l['rpgcalendar_tab_edit_event']      = "Edit event";
$l['rpgcalendar_tab_desc_edit_event'] = "Change this extended event.";
$l['rpgcalendar_tab_monthtexts']      = "Month texts";
$l['rpgcalendar_tab_desc_monthtexts'] = "One free text per month and year (e.g. weather), shown above the month view. MyCode is allowed.";
$l['rpgcalendar_tab_calendars']       = "Calendars";
$l['rpgcalendar_tab_desc_calendars']  = "Global sub-calendars everyone can see. Members create their own personal calendars on the frontend.";
$l['rpgcalendar_cal_add']             = "Add global calendar";
$l['rpgcalendar_cal_name']            = "Name";
$l['rpgcalendar_cal_color']           = "Default colour";
$l['rpgcalendar_cal_color_desc']      = "Fallback colour shown until a member picks their own for this calendar.";
$l['rpgcalendar_cal_joinable']        = "Joinable";
$l['rpgcalendar_cal_joinable_desc']   = "If yes, members can self-subscribe to this calendar and add events; only subscribers see it. If no, it is a public calendar visible to everyone (team writes).";
$l['rpgcalendar_calendar_saved']      = "The calendar has been saved.";
$l['rpgcalendar_calendar_deleted']    = "The calendar has been deleted; its events moved to the Forum calendar.";
$l['rpgcalendar_error_calendar_name'] = "Please enter a calendar name.";

// Event list
$l['rpgcalendar_th_date']      = "Date";
$l['rpgcalendar_th_title']     = "Title";
$l['rpgcalendar_th_repeat']    = "Repeats";
$l['rpgcalendar_th_important'] = "Important";
$l['rpgcalendar_th_visible']   = "Visible";
$l['rpgcalendar_th_controls']  = "Actions";
$l['rpgcalendar_no_events']    = "No extended events yet.";
$l['rpgcalendar_filter_all_years'] = "All years";
$l['rpgcalendar_filter']       = "Filter";

// Event form
$l['rpgcalendar_field_title']        = "Title";
$l['rpgcalendar_field_title_desc']   = "Shown in the day cell and in the important-events table.";
$l['rpgcalendar_field_description']  = "Description";
$l['rpgcalendar_field_description_desc'] = "Optional. Shown (shortened) in the important-events table.";
$l['rpgcalendar_field_start']        = "Start date";
$l['rpgcalendar_field_start_desc']   = "Day / month / year. Any year is allowed (e.g. 1247 or -50).";
$l['rpgcalendar_field_multiday']     = "Multi-day event";
$l['rpgcalendar_field_end']          = "End date";
$l['rpgcalendar_field_end_desc']     = "Only used for multi-day events. Must not be before the start date.";
$l['rpgcalendar_field_repeat']       = "Repeat";
$l['rpgcalendar_field_repeat_desc']  = "Monthly repeats skip months without the start day (e.g. the 31st in February); yearly repeats on Feb 29 skip non-leap years.";
$l['rpgcalendar_field_interval']      = "Repeat interval";
$l['rpgcalendar_field_interval_desc'] = "Repeat every N units of the type above, e.g. 2 = every 2 weeks / months / years. 1 = every time.";
$l['rpgcalendar_field_repeat_until'] = "Repeat until";
$l['rpgcalendar_field_repeat_until_desc'] = "Optional last repeat date (day / month / year). Leave 0 to repeat forever.";
$l['rpgcalendar_field_important']    = "Important event";
$l['rpgcalendar_field_important_desc'] = "Important events are listed in the table below the month view.";
$l['rpgcalendar_field_visible']      = "Visible";
$l['rpgcalendar_field_visible_desc'] = "Invisible events are hidden from the calendar without deleting them.";

$l['rpgcalendar_repeat_0'] = "No repeat";
$l['rpgcalendar_repeat_1'] = "Weekly";
$l['rpgcalendar_repeat_2'] = "Monthly";
$l['rpgcalendar_repeat_3'] = "Yearly";

$l['rpgcalendar_day']   = "Day";
$l['rpgcalendar_month'] = "Month";
$l['rpgcalendar_year']  = "Year";

$l['rpgcalendar_save']   = "Save event";
$l['rpgcalendar_edit']   = "Edit";
$l['rpgcalendar_delete'] = "Delete";

// Month texts
$l['rpgcalendar_th_year']        = "Year";
$l['rpgcalendar_th_month']       = "Month";
$l['rpgcalendar_th_text']        = "Text";
$l['rpgcalendar_no_monthtexts']  = "No month texts yet.";
$l['rpgcalendar_monthtext_add']  = "Add / edit month text";
$l['rpgcalendar_monthtext_add_desc'] = "If a text already exists for the chosen month and year it will be overwritten.";
$l['rpgcalendar_field_content']  = "Text";
$l['rpgcalendar_field_content_desc'] = "MyCode is allowed, HTML is not.";
$l['rpgcalendar_save_monthtext'] = "Save month text";

// Month names (for dropdowns and lists)
$l['rpgcalendar_month_1']  = "January";
$l['rpgcalendar_month_2']  = "February";
$l['rpgcalendar_month_3']  = "March";
$l['rpgcalendar_month_4']  = "April";
$l['rpgcalendar_month_5']  = "May";
$l['rpgcalendar_month_6']  = "June";
$l['rpgcalendar_month_7']  = "July";
$l['rpgcalendar_month_8']  = "August";
$l['rpgcalendar_month_9']  = "September";
$l['rpgcalendar_month_10'] = "October";
$l['rpgcalendar_month_11'] = "November";
$l['rpgcalendar_month_12'] = "December";

// Messages
$l['rpgcalendar_saved']             = "The event has been saved.";
$l['rpgcalendar_deleted']           = "The event has been deleted.";
$l['rpgcalendar_monthtext_saved']   = "The month text has been saved.";
$l['rpgcalendar_monthtext_deleted'] = "The month text has been deleted.";

// Errors
$l['rpgcalendar_error_invalid_title']        = "Please enter a title.";
$l['rpgcalendar_error_invalid_date']         = "The entered date is invalid (check day and month).";
$l['rpgcalendar_error_end_before_start']     = "The end date must not be before the start date.";
$l['rpgcalendar_error_year_range']           = "The year is outside the allowed range (see plugin settings).";
$l['rpgcalendar_error_invalid_repeat_until'] = "The 'repeat until' date is invalid or before the start date.";
$l['rpgcalendar_error_not_found']            = "The requested entry does not exist.";
$l['rpgcalendar_error_empty_text']           = "Please enter a text.";

// ---------------------------------------------------------------------
// v1.2 - Locations, weather & holidays
// ---------------------------------------------------------------------
$l['rpgcalendar_tab_locations']        = "Locations";
$l['rpgcalendar_tab_desc_locations']   = "Places used for weather and holidays. Real locations can be generated automatically; fictional ones are filled in by hand.";
$l['rpgcalendar_tab_weather']          = "Weather";
$l['rpgcalendar_tab_desc_weather']     = "Weather entries per location. Generated entries can be edited; editing one keeps it on the next generation run.";
$l['rpgcalendar_tab_holidays']         = "Holidays";
$l['rpgcalendar_tab_desc_holidays']    = "Holiday entries per location. Generated entries can be edited freely.";
$l['rpgcalendar_tab_moon']             = "Moon";
$l['rpgcalendar_tab_desc_moon']        = "Moon phases per location. Generated entries can be edited; editing one keeps it on the next generation run.";
$l['rpgcalendar_tab_add_location']     = "Add location";
$l['rpgcalendar_tab_add_location_desc']  = "Create a new play location (real or fictional).";
$l['rpgcalendar_tab_edit_location']    = "Edit location";
$l['rpgcalendar_tab_edit_location_desc'] = "Change this location.";
$l['rpgcalendar_tab_add_weather']      = "Add weather";
$l['rpgcalendar_tab_add_weather_desc']   = "Add a manual weather entry for a location.";
$l['rpgcalendar_tab_edit_weather']     = "Edit weather";
$l['rpgcalendar_tab_edit_weather_desc']  = "Change this weather entry.";
$l['rpgcalendar_tab_add_holiday']      = "Add holiday";
$l['rpgcalendar_tab_add_holiday_desc']   = "Add a manual holiday for a location.";
$l['rpgcalendar_tab_edit_holiday']     = "Edit holiday";
$l['rpgcalendar_tab_edit_holiday_desc']  = "Change this holiday.";
$l['rpgcalendar_tab_add_moon']         = "Add moon phase";
$l['rpgcalendar_tab_add_moon_desc']      = "Add a manual moon phase for a location.";
$l['rpgcalendar_tab_edit_moon']        = "Edit moon phase";
$l['rpgcalendar_tab_edit_moon_desc']     = "Change this moon phase.";

// Location form
$l['rpgcalendar_field_locname']      = "Name";
$l['rpgcalendar_field_locname_desc'] = "The location name shown in the calendar.";
$l['rpgcalendar_field_loctype']      = "Type";
$l['rpgcalendar_field_loctype_desc'] = "Real locations read live weather (Open-Meteo) and public holidays (Nager.Date). Fictional locations must be filled in by hand.";
$l['rpgcalendar_loctype_fictional']  = "Fictional";
$l['rpgcalendar_loctype_real']       = "Real (world)";
$l['rpgcalendar_field_country']      = "Country code";
$l['rpgcalendar_field_country_desc'] = "ISO 3166 two-letter code (e.g. DE, GB, US) – used for the holiday source. Real locations only.";
$l['rpgcalendar_field_city']         = "City";
$l['rpgcalendar_field_city_desc']    = "City name for the weather source. Coordinates are looked up automatically if left empty.";
$l['rpgcalendar_field_coords']       = "Coordinates";
$l['rpgcalendar_field_coords_desc']  = "Latitude and longitude (optional). Filled in automatically from the city on the first generation.";
$l['rpgcalendar_field_moon_enabled']      = "Moon calendar";
$l['rpgcalendar_field_moon_enabled_desc'] = "Generate moon phases for this location. Enables the moon sub-calendar and the &quot;Generate&quot; button for it.";
$l['rpgcalendar_field_moon_cycle']        = "Cycle length (days)";
$l['rpgcalendar_field_moon_cycle_desc']   = "Length of one full moon cycle in days. Real locations ignore this and use Earth's real cycle (29.53 days); for fictional worlds enter your own (e.g. 28 or 40).";
$l['rpgcalendar_field_moon_ref']          = "Reference phase";
$l['rpgcalendar_field_moon_ref_desc']     = "A day on which the chosen phase occurs, anchoring the whole cycle. Fictional locations only – real locations are anchored to the real moon automatically.";
$l['rpgcalendar_field_disporder']    = "Display order";

// Weather / holiday / moon form
$l['rpgcalendar_field_location']      = "Location";
$l['rpgcalendar_field_location_desc'] = "Which location this entry belongs to.";
$l['rpgcalendar_field_condition']     = "Condition";
$l['rpgcalendar_field_condition_desc'] = "Condition slug – the plugin looks for an image of this name in the weather image directory. Standard slugs: clear, cloudy, fog, rain, snow, thunder.";
$l['rpgcalendar_field_phase']         = "Phase";
$l['rpgcalendar_field_phase_desc']    = "The moon phase for this day. The plugin looks for an image of this name in the moon image directory.";
$l['rpgcalendar_field_note']          = "Note";
$l['rpgcalendar_field_note_desc']     = "Optional (e.g. a temperature). Shown next to the weather icon.";

// Moon phase labels (also the reference-phase selector)
$l['rpgcalendar_mphase_new']    = "New moon";
$l['rpgcalendar_mphase_waxing'] = "Waxing half moon";
$l['rpgcalendar_mphase_full']   = "Full moon";
$l['rpgcalendar_mphase_waning'] = "Waning half moon";
$l['rpgcalendar_field_yearly']        = "Every year";
$l['rpgcalendar_field_yearly_desc']   = "If yes, this entry repeats on the same date in every year from the start year onwards.";

// Location list
$l['rpgcalendar_th_locname'] = "Name";
$l['rpgcalendar_th_type']    = "Type";
$l['rpgcalendar_th_place']   = "City / country";
$l['rpgcalendar_th_moon']    = "Moon";
$l['rpgcalendar_no_locations'] = "No locations yet.";
$l['rpgcalendar_confirm_delete_location'] = "Really delete? All weather, holiday and moon data of this location will be removed.";

// Weather / holiday / moon list
$l['rpgcalendar_th_location']  = "Location";
$l['rpgcalendar_th_condition'] = "Condition";
$l['rpgcalendar_th_phase']     = "Phase";
$l['rpgcalendar_th_source']    = "Source";
$l['rpgcalendar_no_weather']   = "No weather entries yet.";
$l['rpgcalendar_no_holidays']  = "No holidays yet.";
$l['rpgcalendar_no_moon']      = "No moon phases yet.";
$l['rpgcalendar_filter_all_locations'] = "All locations";
$l['rpgcalendar_yearly_short'] = "yearly";

// Generate
$l['rpgcalendar_generate_title'] = "Generate weather, holidays &amp; moon";
$l['rpgcalendar_generate_desc']  = "For a real location: fetch real weather and holidays for the chosen year range. For any moon-enabled location: compute the moon phases (works for any year). Stored as editable entries, replacing previously generated ones.";
$l['rpgcalendar_generate_btn']   = "Generate";
$l['rpgcalendar_generate_what']      = "Generate what?";
$l['rpgcalendar_generate_what_desc'] = "Tick which sources to (re)generate. Each one replaces only its own generated entries; unticked sources are left completely untouched (so you can refresh the moon without re-generating hand-edited holidays). Weather &amp; holidays only apply to real locations, the moon only to moon-enabled ones.";
$l['rpgcalendar_generate_done']  = "Generation finished: {1} weather entries, {2} holidays and {3} moon phases.";
$l['rpgcalendar_generate_failed'] = "Nothing could be generated. Details: {1}";
$l['rpgcalendar_generate_nothing'] = "Please tick at least one source to generate.";
$l['rpgcalendar_generate_notapplicable'] = "None of the ticked sources apply to this location: weather and holidays need a real location, and the moon needs the moon calendar to be enabled on it.";

// Month text pre-fill
$l['rpgcalendar_monthtext_prefill']      = "Pre-fill from weather / holidays";
$l['rpgcalendar_monthtext_prefill_desc'] = "Pick a location, month and year to generate a suggested month text below. You can edit it before saving.";
$l['rpgcalendar_monthtext_prefill_btn']  = "Generate suggestion";
$l['rpgcalendar_monthtext_prefilled']    = "The month text field below was filled from the location's weather and holidays. Review and save it.";
$l['rpgcalendar_monthtext_prefill_empty'] = "No weather or holiday data was found for that location, month and year.";
$l['rpgcalendar_monthtext_weather_prefix'] = "Weather:";
$l['rpgcalendar_monthtext_holiday_prefix'] = "Holidays:";

// Weather condition labels (for the month-text suggestion)
$l['rpgcalendar_wcond_clear']   = "Clear";
$l['rpgcalendar_wcond_cloudy']  = "Cloudy";
$l['rpgcalendar_wcond_fog']     = "Fog";
$l['rpgcalendar_wcond_rain']    = "Rain";
$l['rpgcalendar_wcond_snow']    = "Snow";
$l['rpgcalendar_wcond_thunder'] = "Thunderstorm";

// Additional errors
$l['rpgcalendar_error_invalid_name']    = "Please enter a name.";
$l['rpgcalendar_error_country']         = "Real locations need a two-letter ISO country code.";
$l['rpgcalendar_error_city_or_coords']  = "Real locations need a city or coordinates.";
$l['rpgcalendar_error_not_real']        = "This location has nothing to generate: weather and holidays need a real location, and the moon calendar is turned off.";
$l['rpgcalendar_error_location']        = "Please choose a valid location.";
$l['rpgcalendar_error_condition']       = "Please enter a weather condition.";
$l['rpgcalendar_error_phase']           = "Please choose a moon phase.";
$l['rpgcalendar_error_moon_cycle']      = "The moon cycle length must be between 1 and 3660 days.";
$l['rpgcalendar_error_moon_ref']        = "The moon reference date is invalid or outside the allowed year range.";

// Bulk delete of generated entries
$l['rpgcalendar_delete_auto']            = "Delete generated";
$l['rpgcalendar_confirm_delete_auto']    = "Delete the generated (auto) entries in the selected location and range? Manually added or edited entries are kept.";
$l['rpgcalendar_delete_auto_done']       = "The generated entries have been deleted.";
$l['rpgcalendar_delete_auto_from']       = "from year";
$l['rpgcalendar_delete_auto_to']         = "to year";
$l['rpgcalendar_delete_auto_range_desc'] = "Leave both year fields empty to delete all generated entries for the chosen location. Only generated (auto) entries are affected.";

// Weather condition labels per WMO code + windy (v1.2)
$l['rpgcalendar_wcond_0'] = "Clear sky";
$l['rpgcalendar_wcond_1'] = "Mainly clear";
$l['rpgcalendar_wcond_2'] = "Partly cloudy";
$l['rpgcalendar_wcond_3'] = "Overcast";
$l['rpgcalendar_wcond_45'] = "Fog";
$l['rpgcalendar_wcond_48'] = "Rime fog";
$l['rpgcalendar_wcond_51'] = "Light drizzle";
$l['rpgcalendar_wcond_53'] = "Moderate drizzle";
$l['rpgcalendar_wcond_55'] = "Dense drizzle";
$l['rpgcalendar_wcond_56'] = "Light freezing drizzle";
$l['rpgcalendar_wcond_57'] = "Dense freezing drizzle";
$l['rpgcalendar_wcond_61'] = "Slight rain";
$l['rpgcalendar_wcond_63'] = "Moderate rain";
$l['rpgcalendar_wcond_65'] = "Heavy rain";
$l['rpgcalendar_wcond_66'] = "Light freezing rain";
$l['rpgcalendar_wcond_67'] = "Heavy freezing rain";
$l['rpgcalendar_wcond_71'] = "Slight snowfall";
$l['rpgcalendar_wcond_73'] = "Moderate snowfall";
$l['rpgcalendar_wcond_75'] = "Heavy snowfall";
$l['rpgcalendar_wcond_77'] = "Snow grains";
$l['rpgcalendar_wcond_80'] = "Slight rain showers";
$l['rpgcalendar_wcond_81'] = "Moderate rain showers";
$l['rpgcalendar_wcond_82'] = "Violent rain showers";
$l['rpgcalendar_wcond_85'] = "Slight snow showers";
$l['rpgcalendar_wcond_86'] = "Heavy snow showers";
$l['rpgcalendar_wcond_95'] = "Thunderstorm";
$l['rpgcalendar_wcond_96'] = "Thunderstorm with slight hail";
$l['rpgcalendar_wcond_99'] = "Thunderstorm with heavy hail";
$l['rpgcalendar_wcond_windy'] = "Windy";
