# Valencia Messages API (ResourceSpace-Plugin)

Exposes inbox listing / seen for the Valencia DAM macOS app.

## Install

```text
plugins/valencia_messages/
```

Enable **Valencia Messages API** under Admin → Plugins.

## API

| Function | Purpose |
|----------|---------|
| `valencia_list_user_messages` | Inbox (`include_seen`, `limit`) |
| `valencia_get_user_message` | Single message |
| `valencia_mark_message_seen` | Mark `user_message.ref` seen |
