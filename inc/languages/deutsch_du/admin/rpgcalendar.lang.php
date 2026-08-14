<?php
// rpgcalendar - Admin-Sprachdatei (Deutsch, du-Form)

$l['rpgcalendar']             = "RPG-Kalender";
$l['rpgcalendar_description'] = "Erweiterte Kalender-Ereignisse und Monatstexte verwalten.";

// Settinggroup
$l['setting_group_rpgcalendar']      = "RPG-Kalender";
$l['setting_group_rpgcalendar_desc'] = "Einstellungen für das RPG-Kalender-Plugin.";

// Tabs
$l['rpgcalendar_tab_events']          = "Ereignisse";
$l['rpgcalendar_tab_desc_events']     = "Alle erweiterten Kalender-Ereignisse. Native Forentermine werden weiterhin über den normalen Kalender verwaltet.";
$l['rpgcalendar_tab_add_event']       = "Ereignis anlegen";
$l['rpgcalendar_tab_desc_add_event']  = "Neues erweitertes Ereignis anlegen. Jedes Jahr ist erlaubt, auch ferne Vergangenheit und Zukunft.";
$l['rpgcalendar_tab_edit_event']      = "Ereignis bearbeiten";
$l['rpgcalendar_tab_desc_edit_event'] = "Dieses erweiterte Ereignis ändern.";
$l['rpgcalendar_tab_monthtexts']      = "Monatstexte";
$l['rpgcalendar_tab_desc_monthtexts'] = "Ein Freitext pro Monat und Jahr (z.B. Wetter), wird über der Monatsansicht angezeigt. MyCode ist erlaubt.";
$l['rpgcalendar_tab_calendars']       = "Kalender";
$l['rpgcalendar_tab_desc_calendars']  = "Globale Teilkalender, die alle sehen. Persönliche Kalender legen Mitglieder selbst im Frontend an.";
$l['rpgcalendar_cal_add']             = "Globalen Kalender anlegen";
$l['rpgcalendar_cal_name']            = "Name";
$l['rpgcalendar_cal_color']           = "Standardfarbe";
$l['rpgcalendar_cal_color_desc']      = "Fallback-Farbe, bis ein Mitglied für diesen Kalender eine eigene Farbe wählt.";
$l['rpgcalendar_cal_joinable']        = "Beitretbar";
$l['rpgcalendar_cal_joinable_desc']   = "Wenn ja, können Mitglieder diesen Kalender selbst abonnieren und Ereignisse eintragen; nur Abonnenten sehen ihn. Wenn nein, ist es ein öffentlicher Kalender für alle sichtbar (Team schreibt).";
$l['rpgcalendar_calendar_saved']      = "Der Kalender wurde gespeichert.";
$l['rpgcalendar_calendar_deleted']    = "Der Kalender wurde gelöscht; seine Ereignisse wurden in den Forum-Kalender verschoben.";
$l['rpgcalendar_error_calendar_name'] = "Bitte einen Kalendernamen eingeben.";

// Ereignis-Liste
$l['rpgcalendar_th_date']      = "Datum";
$l['rpgcalendar_th_title']     = "Titel";
$l['rpgcalendar_th_repeat']    = "Wiederholung";
$l['rpgcalendar_th_important'] = "Wichtig";
$l['rpgcalendar_th_visible']   = "Sichtbar";
$l['rpgcalendar_th_controls']  = "Aktionen";
$l['rpgcalendar_no_events']    = "Noch keine erweiterten Ereignisse vorhanden.";
$l['rpgcalendar_filter_all_years'] = "Alle Jahre";
$l['rpgcalendar_filter']       = "Filtern";

// Ereignis-Formular
$l['rpgcalendar_field_title']        = "Titel";
$l['rpgcalendar_field_title_desc']   = "Wird in der Tageszelle und in der Ereignistabelle angezeigt.";
$l['rpgcalendar_field_description']  = "Beschreibung";
$l['rpgcalendar_field_description_desc'] = "Optional. Wird (gekürzt) in der Ereignistabelle angezeigt.";
$l['rpgcalendar_field_start']        = "Startdatum";
$l['rpgcalendar_field_start_desc']   = "Tag / Monat / Jahr. Jedes Jahr ist erlaubt (z.B. 1247 oder -50).";
$l['rpgcalendar_field_multiday']     = "Mehrtägiges Ereignis";
$l['rpgcalendar_field_end']          = "Enddatum";
$l['rpgcalendar_field_end_desc']     = "Nur bei mehrtägigen Ereignissen. Darf nicht vor dem Startdatum liegen.";
$l['rpgcalendar_field_repeat']       = "Wiederholung";
$l['rpgcalendar_field_repeat_desc']  = "Monatliche Wiederholungen überspringen Monate ohne den Starttag (z.B. den 31. im Februar); jährliche am 29.2. überspringen Nicht-Schaltjahre.";
$l['rpgcalendar_field_interval']      = "Wiederhol-Intervall";
$l['rpgcalendar_field_interval_desc'] = "Wiederholung alle N Einheiten der obigen Art, z.B. 2 = alle 2 Wochen / Monate / Jahre. 1 = jedes Mal.";
$l['rpgcalendar_field_repeat_until'] = "Wiederholen bis";
$l['rpgcalendar_field_repeat_until_desc'] = "Optionales letztes Wiederholungsdatum (Tag / Monat / Jahr). 0 lassen für unbegrenzt.";
$l['rpgcalendar_field_important']    = "Wichtiges Ereignis";
$l['rpgcalendar_field_important_desc'] = "Wichtige Ereignisse erscheinen in der Tabelle unter der Monatsansicht.";
$l['rpgcalendar_field_visible']      = "Sichtbar";
$l['rpgcalendar_field_visible_desc'] = "Unsichtbare Ereignisse werden ausgeblendet, ohne sie zu löschen.";

$l['rpgcalendar_repeat_0'] = "Keine Wiederholung";
$l['rpgcalendar_repeat_1'] = "Wöchentlich";
$l['rpgcalendar_repeat_2'] = "Monatlich";
$l['rpgcalendar_repeat_3'] = "Jährlich";

$l['rpgcalendar_day']   = "Tag";
$l['rpgcalendar_month'] = "Monat";
$l['rpgcalendar_year']  = "Jahr";

$l['rpgcalendar_save']   = "Ereignis speichern";
$l['rpgcalendar_edit']   = "Bearbeiten";
$l['rpgcalendar_delete'] = "Löschen";

// Monatstexte
$l['rpgcalendar_th_year']        = "Jahr";
$l['rpgcalendar_th_month']       = "Monat";
$l['rpgcalendar_th_text']        = "Text";
$l['rpgcalendar_no_monthtexts']  = "Noch keine Monatstexte vorhanden.";
$l['rpgcalendar_monthtext_add']  = "Monatstext anlegen / bearbeiten";
$l['rpgcalendar_monthtext_add_desc'] = "Existiert für Monat und Jahr bereits ein Text, wird er überschrieben.";
$l['rpgcalendar_field_content']  = "Text";
$l['rpgcalendar_field_content_desc'] = "MyCode ist erlaubt, HTML nicht.";
$l['rpgcalendar_save_monthtext'] = "Monatstext speichern";

// Monatsnamen (für Dropdowns und Listen)
$l['rpgcalendar_month_1']  = "Januar";
$l['rpgcalendar_month_2']  = "Februar";
$l['rpgcalendar_month_3']  = "März";
$l['rpgcalendar_month_4']  = "April";
$l['rpgcalendar_month_5']  = "Mai";
$l['rpgcalendar_month_6']  = "Juni";
$l['rpgcalendar_month_7']  = "Juli";
$l['rpgcalendar_month_8']  = "August";
$l['rpgcalendar_month_9']  = "September";
$l['rpgcalendar_month_10'] = "Oktober";
$l['rpgcalendar_month_11'] = "November";
$l['rpgcalendar_month_12'] = "Dezember";

// Meldungen
$l['rpgcalendar_saved']             = "Das Ereignis wurde gespeichert.";
$l['rpgcalendar_deleted']           = "Das Ereignis wurde gelöscht.";
$l['rpgcalendar_monthtext_saved']   = "Der Monatstext wurde gespeichert.";
$l['rpgcalendar_monthtext_deleted'] = "Der Monatstext wurde gelöscht.";

// Fehler
$l['rpgcalendar_error_invalid_title']        = "Bitte gib einen Titel ein.";
$l['rpgcalendar_error_invalid_date']         = "Das eingegebene Datum ist ungültig (Tag und Monat prüfen).";
$l['rpgcalendar_error_end_before_start']     = "Das Enddatum darf nicht vor dem Startdatum liegen.";
$l['rpgcalendar_error_year_range']           = "Das Jahr liegt außerhalb des erlaubten Bereichs (siehe Plugin-Einstellungen).";
$l['rpgcalendar_error_invalid_repeat_until'] = "Das 'Wiederholen bis'-Datum ist ungültig oder liegt vor dem Startdatum.";
$l['rpgcalendar_error_not_found']            = "Der angeforderte Eintrag existiert nicht.";
$l['rpgcalendar_error_empty_text']           = "Bitte gib einen Text ein.";

// ---------------------------------------------------------------------
// v1.2 - Locations, weather & holidays
// ---------------------------------------------------------------------
$l['rpgcalendar_tab_locations']        = "Spielorte";
$l['rpgcalendar_tab_desc_locations']   = "Orte für Wetter und Feiertage. Reale Orte können automatisch generiert werden, fiktive werden von Hand gepflegt.";
$l['rpgcalendar_tab_weather']          = "Wetter";
$l['rpgcalendar_tab_desc_weather']     = "Wetter-Einträge pro Ort. Generierte Einträge sind editierbar; ein bearbeiteter Eintrag bleibt bei der nächsten Generierung erhalten.";
$l['rpgcalendar_tab_holidays']         = "Feiertage";
$l['rpgcalendar_tab_desc_holidays']    = "Feiertags-Einträge pro Ort. Generierte Einträge sind frei editierbar.";
$l['rpgcalendar_tab_add_location']     = "Ort anlegen";
$l['rpgcalendar_tab_add_location_desc']  = "Einen neuen Spielort anlegen (real oder fiktiv).";
$l['rpgcalendar_tab_edit_location']    = "Ort bearbeiten";
$l['rpgcalendar_tab_edit_location_desc'] = "Diesen Ort ändern.";
$l['rpgcalendar_tab_add_weather']      = "Wetter anlegen";
$l['rpgcalendar_tab_add_weather_desc']   = "Einen manuellen Wetter-Eintrag für einen Ort anlegen.";
$l['rpgcalendar_tab_edit_weather']     = "Wetter bearbeiten";
$l['rpgcalendar_tab_edit_weather_desc']  = "Diesen Wetter-Eintrag ändern.";
$l['rpgcalendar_tab_add_holiday']      = "Feiertag anlegen";
$l['rpgcalendar_tab_add_holiday_desc']   = "Einen manuellen Feiertag für einen Ort anlegen.";
$l['rpgcalendar_tab_edit_holiday']     = "Feiertag bearbeiten";
$l['rpgcalendar_tab_edit_holiday_desc']  = "Diesen Feiertag ändern.";

// Ort-Formular
$l['rpgcalendar_field_locname']      = "Name";
$l['rpgcalendar_field_locname_desc'] = "Der im Kalender angezeigte Ortsname.";
$l['rpgcalendar_field_loctype']      = "Typ";
$l['rpgcalendar_field_loctype_desc'] = "Reale Orte lesen echtes Wetter (Open-Meteo) und Feiertage (Nager.Date) aus. Fiktive Orte müssen von Hand gepflegt werden.";
$l['rpgcalendar_loctype_fictional']  = "Fiktiv";
$l['rpgcalendar_loctype_real']       = "Real (Welt)";
$l['rpgcalendar_field_country']      = "Ländercode";
$l['rpgcalendar_field_country_desc'] = "ISO-3166-Ländercode aus zwei Buchstaben (z.B. DE, GB, US) – für die Feiertagsquelle. Nur reale Orte.";
$l['rpgcalendar_field_city']         = "Stadt";
$l['rpgcalendar_field_city_desc']    = "Stadtname für die Wetterquelle. Koordinaten werden automatisch ermittelt, wenn leer.";
$l['rpgcalendar_field_coords']       = "Koordinaten";
$l['rpgcalendar_field_coords_desc']  = "Breiten- und Längengrad (optional). Werden bei der ersten Generierung automatisch aus der Stadt ermittelt.";
$l['rpgcalendar_field_disporder']    = "Reihenfolge";

// Wetter-/Feiertags-Formular
$l['rpgcalendar_field_location']      = "Ort";
$l['rpgcalendar_field_location_desc'] = "Zu welchem Ort dieser Eintrag gehört.";
$l['rpgcalendar_field_condition']     = "Wetterlage";
$l['rpgcalendar_field_condition_desc'] = "Wetter-Slug – das Plugin sucht ein gleichnamiges Bild im Wetter-Bildordner. Standard-Slugs: clear, cloudy, fog, rain, snow, thunder.";
$l['rpgcalendar_field_note']          = "Notiz";
$l['rpgcalendar_field_note_desc']     = "Optional (z.B. Temperatur). Wird neben dem Wettersymbol angezeigt.";
$l['rpgcalendar_field_yearly']        = "Jedes Jahr";
$l['rpgcalendar_field_yearly_desc']   = "Wenn ja, wiederholt sich dieser Eintrag ab dem Startjahr jedes Jahr am selben Datum.";

// Ort-Liste
$l['rpgcalendar_th_locname'] = "Name";
$l['rpgcalendar_th_type']    = "Typ";
$l['rpgcalendar_th_place']   = "Stadt / Land";
$l['rpgcalendar_no_locations'] = "Noch keine Orte vorhanden.";
$l['rpgcalendar_confirm_delete_location'] = "Wirklich löschen? Alle Wetter- und Feiertagsdaten dieses Orts werden entfernt.";

// Wetter-/Feiertags-Liste
$l['rpgcalendar_th_location']  = "Ort";
$l['rpgcalendar_th_condition'] = "Wetterlage";
$l['rpgcalendar_th_source']    = "Quelle";
$l['rpgcalendar_no_weather']   = "Noch keine Wetter-Einträge vorhanden.";
$l['rpgcalendar_no_holidays']  = "Noch keine Feiertage vorhanden.";
$l['rpgcalendar_filter_all_locations'] = "Alle Orte";
$l['rpgcalendar_yearly_short'] = "jährlich";

// Generieren
$l['rpgcalendar_generate_title'] = "Wetter &amp; Feiertage generieren";
$l['rpgcalendar_generate_desc']  = "Für einen realen Ort: echtes Wetter und Feiertage für den gewählten Jahresbereich abrufen und als editierbare Einträge speichern (ersetzt zuvor generierte).";
$l['rpgcalendar_generate_btn']   = "Generieren";
$l['rpgcalendar_generate_done']  = "Generierung abgeschlossen: {1} Wetter-Einträge und {2} Feiertage.";
$l['rpgcalendar_generate_failed'] = "Es konnte nichts generiert werden. Details: {1}";

// Monatstext-Vorbelegung
$l['rpgcalendar_monthtext_prefill']      = "Aus Wetter / Feiertagen vorbelegen";
$l['rpgcalendar_monthtext_prefill_desc'] = "Wähle Ort, Monat und Jahr, um unten einen Monatstext-Vorschlag zu erzeugen. Du kannst ihn vor dem Speichern bearbeiten.";
$l['rpgcalendar_monthtext_prefill_btn']  = "Vorschlag erzeugen";
$l['rpgcalendar_monthtext_prefilled']    = "Das Textfeld unten wurde aus Wetter und Feiertagen des Orts befüllt. Prüfen und speichern.";
$l['rpgcalendar_monthtext_prefill_empty'] = "Für diesen Ort, Monat und Jahr wurden keine Wetter- oder Feiertagsdaten gefunden.";
$l['rpgcalendar_monthtext_weather_prefix'] = "Wetter:";
$l['rpgcalendar_monthtext_holiday_prefix'] = "Feiertage:";

// Wetter-Bezeichnungen (für den Monatstext-Vorschlag)
$l['rpgcalendar_wcond_clear']   = "Klar";
$l['rpgcalendar_wcond_cloudy']  = "Bewölkt";
$l['rpgcalendar_wcond_fog']     = "Nebel";
$l['rpgcalendar_wcond_rain']    = "Regen";
$l['rpgcalendar_wcond_snow']    = "Schnee";
$l['rpgcalendar_wcond_thunder'] = "Gewitter";

// Weitere Fehler
$l['rpgcalendar_error_invalid_name']    = "Bitte gib einen Namen ein.";
$l['rpgcalendar_error_country']         = "Reale Orte brauchen einen zweistelligen ISO-Ländercode.";
$l['rpgcalendar_error_city_or_coords']  = "Reale Orte brauchen eine Stadt oder Koordinaten.";
$l['rpgcalendar_error_not_real']        = "Dieser Ort ist kein realer Ort und kann nicht generiert werden.";
$l['rpgcalendar_error_location']        = "Bitte einen gültigen Ort wählen.";
$l['rpgcalendar_error_condition']       = "Bitte eine Wetterlage eingeben.";

// Massen-Löschen generierter Einträge
$l['rpgcalendar_delete_auto']         = "Alle generierten löschen";
$l['rpgcalendar_confirm_delete_auto'] = "Alle generierten (auto) Einträge löschen? Manuell angelegte oder bearbeitete Einträge bleiben erhalten.";
$l['rpgcalendar_delete_auto_done']    = "Alle generierten Einträge wurden gelöscht.";
