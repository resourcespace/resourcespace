# Browser code exchange for third-party API access

`pages/user/user_api_session.php` supports two delivery modes per entry in
`$api_issue_valid_destinations`. Missing `delivery` or `delivery => 'post'` keeps
the existing server-to-server POST callback, including its response-driven redirect.
`delivery => 'redirect_code'` redirects the browser with a temporary code instead.
ResourceSpace makes no outbound HTTP request in this mode.

This is a custom credential exchange using S256 PKCE. It does not implement an
OAuth token service, Bearer authentication, refresh tokens, or scopes. The result
is the existing ResourceSpace session API signing key, subject to the user's
normal permissions and session invalidation.

## Configuration

Configure the real integration browser return endpoints, replacing POST callback
URLs when enabling this mode. These endpoints must already be implemented on the
integration side. For example, for CI-HUB with `supportsNewLoginUrl: true`:

```php
$api_issue_valid_destinations['cihub'] = [
    'name' => 'CI-HUB',
    'delivery' => 'redirect_code',
    'url' => [
        'https://live.ci-hub.com/api/v1/auth/login',
        'https://stage.ci-hub.com/api/v1/auth/login',
    ],
    'querydata' => ['username', 'email', 'fullname'],
    'stateparam' => 'state',
];
```

The default CI-HUB entry uses `redirect_code` and full `/api/v1/auth/login`
return URLs for all listed environments, assuming `supportsNewLoginUrl: true`.
Deploy the matching CI-HUB integration alongside this change. Installations
overriding the CI-HUB entry must update their configuration explicitly. For an
older integration requiring the POST flow, set `delivery => 'post'` and restore
its POST callback URLs. Other entries retain their existing behaviour.
Use HTTPS; HTTP is accepted only for explicitly configured loopback URLs for
development. Redirect URLs cannot contain credentials or fragments. A string
`url` selects a fixed destination; an array requires an exact `url` request
parameter. Static scalar query parameters on the configured return URL survive
the redirect. The state parameter name must be a simple identifier and cannot
collide with `code`, `error`, `system`, `url`, `submit`, or `cancel`.

## Authorization

The integration generates a random, single-attempt `state` and an RFC 7636 verifier
(43-128 unreserved ASCII characters). It retains the verifier, exact return URL,
and trusted ResourceSpace base URL against that state. Compute the challenge as
unpadded base64url of SHA-256 of the verifier. Redirect the browser to:

```text
{resourcespace}/pages/user/user_api_session.php
    ?system=cihub
    &url={encoded exact configured return URL}
    &state={encoded random state}
    &code_challenge={S256 challenge}
    &code_challenge_method=S256
```

State must be nonempty and at most 1024 bytes. The challenge must be 43 base64url
characters. Only `S256` is supported. The browser goes through normal ResourceSpace
login/SSO and CSRF-protected consent. Approval returns HTTP 303 to the configured
URL with `code` and the original state. Cancellation returns `error=access_denied`
and state. Invalid requests/destinations display an error locally; they do not
redirect to an unvalidated location. No identity details or signing key enter the
return URL.

The integration must validate state against its originating browser/session before
redeeming the code. Do not use a ResourceSpace URL supplied by the return request
to decide where to send the verifier.

## Redemption

POST to `{resourcespace}/pages/user/user_api_session_exchange.php` with
`Content-Type: application/x-www-form-urlencoded` and these body fields:

| Field | Value |
| --- | --- |
| `system` | Destination key, e.g. `cihub` |
| `code` | Code received in the browser return |
| `code_verifier` | Original verifier retained by the integration |
| `redirect_uri` | Exact configured return URL used at authorization |

This request requires neither a ResourceSpace browser cookie nor a browser CSRF
token. The code and verifier authorize it. GET, query-string-only credentials,
cookie-only credentials, malformed fields, and other content types are rejected.

Success is HTTP 200 with JSON containing `sessionkey` and identity fields from
`querydata`. CI-HUB must include `username` in that configuration to sign API calls.
An example response is:

```json
{"sessionkey":"<signing key>","username":"alice","email":"alice@example.com","fullname":"Alice"}
```

Only fields allowed both at approval and at redemption are returned. Values are
read from the current user record. All exchange responses use `Cache-Control:
no-store`; credentials must not be logged or put into URLs.

| HTTP status | JSON error | Meaning |
| --- | --- | --- |
| 400 | `invalid_request` | Missing, empty, or non-string body field |
| 400 | `invalid_grant` | Invalid/expired/used code, verifier or binding, ineligible user, or changed session/configuration |
| 403 | `access_denied` | Remote API access disabled |
| 405 | `invalid_request` | Method other than POST (`Allow: POST`) |
| 415 | `invalid_request` | Unsupported content type |

Codes expire after 120 seconds and are consumed atomically on successful exchange.
If the response is lost, restart authorization rather than retrying a consumed
code. Invalid redemption attempts do not consume an otherwise valid code.
Logout/session replacement, account disablement/expiry, missing usergroup, and
the API's usergroup download-limit restriction prevent redemption. There is no
new access-token lifetime: the retrieved key follows existing session semantics.

## Storage and installation upgrades

`dbstruct/table_user_api_session_code.txt` and its index definition add the code
table through the normal schema mechanisms for new and existing installations.
No manual SQL is needed. Only code/key hashes, PKCE challenge, approved identity
field names and bindings are stored; no plaintext signing key or verifier is
persisted. Expired records are removed when issuing another code. Successful
redemption removes its record. Credential reads bypass query caches and replicas.

See `codex_cihub.txt` in the repository root for the corresponding CI-HUB work.
