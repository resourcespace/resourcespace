# Valencia Annotations API (ResourceSpace-Plugin)

Exposes annotation/comment APIs for the Valencia DAM macOS app.

## Install

Copy this folder to the server:

```text
plugins/valencia_annotations/
```

Then enable **Valencia Annotations API** under Admin → Plugins and reload PHP/Apache.

## Files

- `valencia_annotations.yaml` – plugin metadata
- `api/api_bindings.php` – `api_valencia_get_resource_annotations` etc.
