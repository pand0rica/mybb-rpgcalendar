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
