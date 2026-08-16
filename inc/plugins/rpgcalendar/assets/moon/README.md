# Moon phase icons

Default icon set for the rpgcalendar moon calendar. One SVG per principal phase,
named after the phase slug so the plugin can find it automatically:

| File          | Phase                                   |
|---------------|-----------------------------------------|
| `new.svg`     | New moon (Neumond)                      |
| `waxing.svg`  | Waxing half moon / first quarter        |
| `full.svg`    | Full moon (Vollmond)                    |
| `waning.svg`  | Waning half moon / last quarter         |

The folder is set as the default value of the **Moon image directory**
(`rpgcalendar_moon_imagedir`) setting. To use your own artwork, either drop
replacement files with the same names here, or point the setting at another
board-relative folder. As with the weather icons you can add dash-suffixed
variants (`full-1.svg`, `full-2.svg`, …); one is picked deterministically per
entry. If no image matches, the localized phase text is shown instead.

These simple SVGs are original artwork shipped with the plugin (GPLv3).
