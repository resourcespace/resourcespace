# Valencia App Link

Macht die **bestehenden** DAM-Links in Slack/Mail klickbar und öffnet optional die Mac-App:

- `https://dam.valencia.ch/?c=228` → Kollektion
- `https://dam.valencia.ch/?r=6094` → Asset

Die Mac-App kopiert genau dieses Format («Interne URL kopieren»).

## Warum ein Plugin?

`https://…` ist klickbar. Ohne Extra-Schritt öffnet der Klick aber nur den **Browser**.  
Das Plugin erkennt `?c=` / `?r=` auf den normalen DAM-Seiten und:

1. versucht die Mac-App zu starten (`valenciadam://…`)
2. zeigt einen Banner **«App öffnen»** (Fallback, falls der Browser die Umleitung blockiert)

Ein separates URL-Format ist **nicht** nötig.

## Installation

```text
plugins/valencia_app_link/valencia_app_link.yaml
plugins/valencia_app_link/hooks/all.php
plugins/valencia_app_link/link.php   # optional, alte Landing-Page
```

Admin → Plugins → **Valencia App Link** aktivieren.

## Optional: `link.php`

Weiterhin erreichbar unter  
`/plugins/valencia_app_link/link.php?c=…` — wird von der App nicht mehr zum Kopieren genutzt.
