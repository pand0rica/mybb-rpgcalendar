# h4w-Anpassungen

Dieser Branch ist `main` **plus** die Anpassungen für das h4w-Forum. Regeln: Dev-Kit
`standards/conventions.md` §12 – Neuerungen zuerst auf `main`, dann `main` → `h4w` mergen,
nie umgekehrt. Deploy ins h4w-Test-/Live-Forum von diesem Branch.

## Anpassungen

| Bereich | Dateien | Zweck |
|---|---|---|
| CSS-Variablen des h4w-Themes | `inc/plugins/rpgcalendar/assets/rpgcalendar.css` | Farben/Größen als `var(--h4w-…, Fallback)` (11 Stellen), damit das h4w-Theme sie zentral steuert. Auf `main` stehen die Fallback-Werte direkt. |
| Monatstext auf dem Index | `inc/plugins/rpgcalendar/templates/rpgcalendar_current_monthtext_bit.tpl` | Auf der Indexseite steht der Monatstext als reiner Fließtext im Wetter-Block des h4w-Themes (`.h4w-forumbit-description-wetter`), ohne Tabellenkasten und Monatsüberschrift. Template `rpgcalendar_current_monthtext_bit`. |
| Wetter-Link und Zeitsprung-Hinweis | `inc/plugins/rpgcalendar/templates/rpgcalendar_current_monthtexts.tpl` | Unter dem Monatstext auf dem Index: Link zum Wetterrückblick sowie fest eingetragener Hinweis auf den nächsten Zeitsprung und den Zielzeitraum. Der Hinweis ist statischer Text und muss bei jedem Zeitsprung von Hand angepasst werden. Template `rpgcalendar_current_monthtexts`. |
| Mini-Kalender-Farben | `inc/plugins/rpgcalendar/assets/rpgcalendar_mini.css` | Am Dateiende ein Override-Block, der die `--rpgcal-mini-*`-Variablen auf die h4w-Theme-Tokens (`--h4w-accent-new`, `-accent-new-color`, `-accent-2/-3`, `-linkcolor-main`, `-bg-1`, `-border`) setzt. Szene = voller Akzent, Ereignis/Geburtstag = abgestufte Tönungen mit Seiten-Textfarbe. Auf `main` stehen neutrale Werte. |
