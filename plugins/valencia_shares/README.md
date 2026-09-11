# Valencia Collection Shares API (ResourceSpace-Plugin)

Exposes external collection share (access key / URL) APIs for the Valencia DAM macOS app.

## Install

Copy this folder to the server:

```text
plugins/valencia_shares/
```

Then enable **Valencia Collection Shares API** under Admin → Plugins and reload PHP/Apache.

## Files

- `valencia_shares.yaml` – plugin metadata
- `api/api_bindings.php` – `api_valencia_create_collection_share` etc.

## API functions

| Function | Purpose |
|----------|---------|
| `valencia_create_collection_share` | Create access key + return URL (`collection`, `access`, optional `expires`, `sharepwd`) |
| `valencia_list_collection_shares` | List existing shares for a collection |
| `valencia_delete_collection_share` | Revoke one access key |

Access: `0` = open (download), `1` = restricted.
