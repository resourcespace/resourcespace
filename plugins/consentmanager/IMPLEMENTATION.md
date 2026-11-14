# External consent collection: repository implementation plan

The implementation is contained in Consent Manager. External workflow data supplements
the existing `consent` and `resource_consent` tables; it does not replace them.

## Repository findings

- `hooks/all.php` provides collection Actions and cron hooks.
- `consentmanager_create_consent()` accepts eleven arguments and checks general
  write permission (`t` or `cm`). `consentmanager_link_consent()` checks each resource.
- `consentmanager_batch_link_unlink()` reads current collection membership and can
  skip inaccessible resources. It is unsuitable for an immutable request snapshot;
  use individual links against the stored snapshot and fail on any missing link.
- `consentmanager_save_file()` stores attachment bytes in the established scrambled
  `.bin` path. Its existing implementation needs checked writes for this workflow.
- Existing expiry notifications and automatic archiving operate on ordinary consent
  dates and links. They require no separate external-consent expiry system.
- Plugin `dbstruct/table_*.txt` definitions support upgrades and new installations.
- Composer already supplies TCPDF through HTML2PDF. No PDF import dependency exists.
  Retain authenticated, server-generated TCPDF layout state alongside the unsigned
  PDF. Restore that state to append a visible signature in its reserved signature
  page, retaining terms, responses and manifest. Never regenerate on submission.
- External collection shares expose more of the application than this workflow
  requires. Use a separate capability endpoint, without creating share keys or
  authenticating the recipient as the owner.

## Implementation sequence

1. Add configuration defaults and supplementary template, request, resource, response
   and event tables using plugin database definitions.
2. Centralise validation, permissions, snapshotting, tokens, template versions,
   request transitions, document integrity and finalisation in plugin includes.
3. Add thin authenticated management pages and a minimal external portal. Serve
   previews through the capability endpoint without exposing storage paths.
4. Persist unsigned and signed bytes before presentation. Record presentation
   against revision/hash and require explicit submission of the signed revision.
   Editing invalidates all signature/presentation state.
5. Lock requests during transitions and final submission; require transactions.
   Attach checked bytes and link the immutable resource snapshot through existing
   helpers. Verify the resulting file before commit. Notify only after commit.
6. Integrate cron reminders, expiry and temporary-data retention, and preserve
   completed evidence on revocation/withdrawal. Provide administrator guidance.
7. Run plugin regression tests, PHP syntax and applicable static/browser checks.

## Security and compatibility boundaries

Require `cm` or `t` plus collection write access to issue requests, and
resource access and consent write access for every issued asset. Administrators
manage templates. General consent writers track requests centrally, independently
of source collection membership or deletion. Tokens use 256 random bits and SHA-256 at rest. External POSTs
use a separate secure browser cookie and ResourceSpace CSRF tokens. HTTPS is
required. Documents remain in database blobs until copied unchanged into the
existing attachment storage. No originals or alternative-file paths are accepted
from recipients. Completed records retain immutable evidence. Signatures are
electronic acknowledgements, not verified identities or certificate signatures.

References checked against source, rather than assuming newer documentation
commands apply:
[plugin development](https://www.resourcespace.com/knowledge-base/developers/modifications-and-writing-your-own-plugin),
[access control](https://www.resourcespace.com/knowledge-base/developers/security/access_control).

User override: external consent requests are enabled by default. Secure access still
requires HTTPS, but the setting label does not mention it.

User workflow refinement: the recipient now supplies details and captures a signature
on step 1, then Next presents the signed PDF draft on step 2. The electronic-signature
declaration is confirmed beside that actual PDF before explicit submission. A separate
unsigned-PDF review is no longer a visible workflow stage. Submission still stores the
already displayed signed bytes unchanged; capturing a mark never creates active consent.
Live and administrator preview use the same validator, PDF generator, signature logic
and portal renderer. Preview Next works entirely in memory, while submit/decline remain
disabled and server-side forbidden.
