# h4w-Anpassungen

Dieser Branch ist `main` **plus** die Anpassungen für das h4w-Forum. Regeln: Dev-Kit
`standards/conventions.md` §12 – Neuerungen zuerst auf `main`, dann `main` → `h4w` mergen,
nie umgekehrt. Deploy ins h4w-Test-/Live-Forum von diesem Branch.

## Anpassungen

| Bereich | Dateien | Zweck |
|---|---|---|
| CSS-Variablen des h4w-Themes | `inc/plugins/rpgcalendar/assets/rpgcalendar.css` | Farben/Größen als `var(--h4w-…, Fallback)` (11 Stellen), damit das h4w-Theme sie zentral steuert. Auf `main` stehen die Fallback-Werte direkt. |
| Monatstext auf dem Index | `inc/plugins/rpgcalendar/templates/rpgcalendar_current_monthtext_bit.tpl` | Auf der Indexseite steht der Monatstext als reiner Fließtext im Wetter-Block des h4w-Themes (`.h4w-forumbit-description-wetter`), ohne Tabellenkasten und Monatsüberschrift. Template `rpgcalendar_current_monthtext_bit`. |
