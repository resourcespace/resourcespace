# Server-Patches für Valencia DAM (macOS)

Die Mac-App braucht erweiterte ResourceSpace-API-Funktionen, die im Standard nicht (vollständig) verfügbar sind.

---

## 1. Annotationen / Kommentare (neu)

ResourceSpace hat intern `getResourceAnnotations()` usw., aber **keine** `api_*`-Wrapper.
Ohne Patch liefert `/api/?function=getAnnotoriousResourceAnnotations…` eine **leere** Antwort.

### Installation

Auf dem DAM-Server (`dam.valencia.ch`):

```text
plugins/valencia_annotations/valencia_annotations.yaml
plugins/valencia_annotations/api/api_bindings.php
```

Quelle in diesem Repo:

```text
ServerPatches/valencia_annotations/
```

Danach:

1. Admin → Plugins → **Valencia Annotations API** aktivieren  
2. PHP-OPcache leeren / Apache neu laden

### API-Funktionen

| Funktion | Zweck |
|----------|--------|
| `valencia_get_resource_annotations` | Alle Markierungen + Text/Autor/Tags eines Assets |
| `valencia_get_resource_annotation_count` | Anzahl für ein Asset |
| `valencia_get_resource_annotation_counts` | Batch-Anzahlen (`refs=1,2,3`) |
| `getResourceAnnotations` / `getAnnotoriousResourceAnnotations` / `getResourceAnnotationsCount` | Kompatibilitäts-Aliase |

---

## 2. License Manager API-Patch

Die Mac-App braucht erweiterte API-Funktionen. Standard-ResourceSpace liefert für Lizenzen nur:

- `licensemanager_get_licenses` (Lizenzen **eines** Assets)

### Installation

Auf dem DAM-Server die Datei ersetzen:

```text
plugins/licensemanager/api/api_bindings.php
```

Quelle:

```text
ServerPatches/licensemanager/api/api_bindings.php
```

Danach ggf. PHP-OPcache leeren / Apache neu laden.

### Neue API-Funktionen

| Funktion | Zweck |
|----------|--------|
| `licensemanager_get_all_licenses` | Alle Lizenzen listen/suchen |
| `licensemanager_get_license` | Einzelne Lizenz |
| `licensemanager_create_license` | Neu anlegen |
| `licensemanager_update_license` | Bearbeiten |
| `licensemanager_delete_license` | Löschen |
| `licensemanager_link_license` | An Asset hängen |
| `licensemanager_unlink_license` | Von Asset lösen |
| `licensemanager_batch_link_unlink` | An Kollektion (alle Assets) |

Benutzer braucht die Permission `lm` (License Manager) oder Admin (`a`).

---

## 3. Collection Shares API (neu)

ResourceSpace hat intern `generate_collection_access_key()` / `get_collection_external_access()`, aber die Remote-API listet unter Share nur `delete_access_keys` (oft nur native-Auth).

### Installation

Auf dem DAM-Server (`dam.valencia.ch`):

```text
plugins/valencia_shares/valencia_shares.yaml
plugins/valencia_shares/api/api_bindings.php
```

Quelle in diesem Repo:

```text
ServerPatches/valencia_shares/
```

Danach:

1. Admin → Plugins → **Valencia Collection Shares API** aktivieren  
2. PHP-OPcache leeren / Apache neu laden

### API-Funktionen

| Funktion | Zweck |
|----------|--------|
| `valencia_create_collection_share` | Externen Access-Key erzeugen + URL zurückgeben |
| `valencia_list_collection_shares` | Bestehende Shares einer Kollektion listen |
| `valencia_delete_collection_share` | Einzelnen Access-Key widerrufen |
| `valencia_get_collection_users` | Zugewiesene User einer Kollektion (ohne Admin) |

Parameter `access`: `0` = offen (Download), `1` = eingeschränkt.  
URL-Format: `{baseURL}/?c={collection}&k={accessKey}`

---

## 4. Messages API (neu)

Inbox-Liste braucht `message_get` (nicht in der Remote-API).

### Installation

```text
plugins/valencia_messages/valencia_messages.yaml
plugins/valencia_messages/api/api_bindings.php
```

Quelle: `ServerPatches/valencia_messages/`

### API-Funktionen

| Funktion | Zweck |
|----------|--------|
| `valencia_list_user_messages` | Inbox listen |
| `valencia_get_user_message` | Einzelne Nachricht |
| `valencia_mark_message_seen` | Als gelesen markieren |
