# External consent collection

This extends Consent Manager. Successful submissions create ordinary consent records,
attachments and resource links. Existing consent expiry notifications and automatic
archiving remain authoritative.

## Administrator setup

Open **Admin > Plugins > Consent Manager > Options**. External requests are enabled
by default, as requested for this implementation. Turn them off there when needed;
manual consent management and completed evidence remain available.

Use **Consent form templates** in the external consent section of the Options page.
The table has New, Edit, Duplicate and Delete actions. Edit a template to disable it
without deleting it. Template changes and deletion never change existing requests.

Each template contains:

| Field | Purpose |
| --- | --- |
| Title | Template name and PDF title |
| Organisation | Organisation requesting the agreement |
| Full consent wording | Full terms, permitted uses, limits and exclusions |
| Mandatory declarations | Wording accepted through one required declaration checkbox |
| Privacy information | Data handling, privacy policy, contacts and withdrawal instructions |
| Consent duration | Descriptive wording; the actual expiry date is set on each request |
| Optional permissions | Add individual pills; recipients get initially unticked checkboxes |
| Additional information | Add field-label pills; each creates a required text response |

Type an optional permission or field label and press Enter to add it; remove it using
its x. Commas are part of an item, not separators. These lists are optional. Collect
only personal details the particular agreement needs. Templates use plain text,
including any privacy policy addresses. The organisation supplies its own approved
wording; the plugin does not decide whether an agreement constitutes GDPR consent.

Configure typed/drawn signatures, validity, default template ID, maximum assets (100 by default),
recipient PDF downloads, reminders and retention through the Options page.
The option to include the requester name and initiation date/time in the PDF is on
by default. New requests snapshot these details and the option at creation. Original
asset downloads are off by default. At least one signature method must be enabled.
Email and the normal ResourceSpace cron job must be configured. If TLS terminates
at a proxy, its trusted server configuration must mark HTTPS requests for PHP;
recipient requests are redirected to HTTPS and insecure POSTs are rejected.

Optional recipient identity verification is reserved for a future implementation.
Setting `$consentmanager_external_verification = true` fails closed: requests are
unavailable rather than silently proceeding without verification.

## Requesting and tracking consent

1. Open a personal or shared collection you can manage.
2. Choose **Actions > Consent > Request consent**. The Licensing plugin has its own
   separate Licensing group in this menu.
3. Enter the subject name/email and optional guardian details, select an enabled
   template, tick the standard Consent Manager usage mediums, set request expiry
   and optionally consent expiry, and enter a personal message if needed.
4. Review/select the thumbnail grid and choose **Send consent request**.
5. Track all requests under **Admin > Consents > Consent Requests**. Collections
   only start requests; deleting or changing a collection does not lose a request
   or change its asset snapshot.

Creating requests requires the existing general consent write permission (`cm` or
`t`), collection write/read access and sharing rights. Every selected asset must be
open to the requesting user, shareable and writable under Consent Manager rules.
Managers with general consent write permission manage requests centrally. Only
administrators (`a`) manage templates. External shares cannot create/manage requests.

Both central tables use the standard pager and retain independent filters. Requests
can be searched by recipient name and filtered by status. Action links have icons;
send/replace, revoke and withdrawal require confirmation and POST with CSRF tokens.

**Open recipient view** uses the same renderer and signature controls as the live
portal. Administrators can fill fields, draw and use Next to review a signed PDF draft.
Only submission and decline are disabled. Preview PDFs remain in memory and
preview changes are not saved to the request. It does not disclose,
replace or invalidate the recipient's token, or count as recipient PDF approval.

**Send / replace link** issues a new link, invalidating the previous one. Initial
sending and optional reminders use the existing email subsystem; reminders also
rotate links because raw tokens are not stored. Reminders have a configurable
interval and limit. Completion notifications go to the original requesting user.

### Statuses

- Draft: terms/assets have been frozen, but no link has been issued. This is an
  internal staging state; the collection form proceeds directly to sending.
- Pending: a request link has been issued.
- Viewed: the recipient has opened the request.
- Awaiting signature: the recipient completed the form and generated an unsigned PDF.
- Signed - awaiting submission: the signed PDF exists; **there is no active consent**.
- Completed: explicit submission stored and linked the signed document successfully.
- Declined, Expired or Revoked: no further recipient actions are allowed.
- Failed: initial email delivery was rejected. Check email configuration and resend.

A delivery accepted by ResourceSpace's mail subsystem is not proof of delivery to
the recipient's inbox. Temporary signing/storage failures preserve the current draft
and display an error; they do not produce successful consent or completion notices.

## Recipient workflow and document integrity

The emailed HTTPS capability link requires no account. It grants only access to the
request's frozen asset references and allowlisted previews. The portal is titled
**Consent request** and displays the application logo.

On step 1 the recipient reviews the assets and terms, completes declarations,
optional permissions and required information, and enters a typed or drawn
signature. Next generates the signed PDF draft for step 2. Each revision contains
the full terms, responses, expiry, usage choices and an asset manifest. Capturing
the signature or generating this draft never creates active consent.

The recipient can type their name or draw with a mouse, touch or stylus. The typed
option permits keyboard operation. Drawn signatures are selected by default when
enabled; selecting typed hides the drawing controls. The electronic-signature
confirmation is required only after the actual signed PDF is displayed on step 2.
Signing restores authenticated server-generated TCPDF layout state and adds a visible
signature in its reserved signature page; it does not reconstruct the consent terms.
The signed PDF is stored before being presented for final review.

After entering the signature, **Next** opens the signed PDF review step. The
electronic-signature declaration appears beside that PDF, where the recipient must
confirm it and separately select **Submit signed consent**. Submission checks
revision, presentation state and SHA-256, stores the already reviewed bytes through
the normal attachment helper, verifies the attachment hash, links every frozen asset
and commits the standard consent record. It never generates a PDF on submission.
Row locks and transactions prevent duplicate records; retries use the same bytes.
Corrections invalidate the signature. Closing the page before submission leaves the
request incomplete and creates no consent record. Valid requests can be resumed.

The drawing and typed name are electronic acknowledgements. Identity is not
independently verified, and no certificate-based cryptographic PDF signature is
claimed. SHA-256 verifies document integrity, not the signer's identity.

## Retention, withdrawal and operations

PDFs, working layouts, responses and signature strokes are stored in supplementary
database tables, not public temporary directories. After completion the signed PDF
also occupies the existing scrambled Consent Manager attachment path, unchanged.
Completed documents and their original snapshot/audit evidence are immutable through
the plugin's normal edit, attachment, delete, link and API helper paths. Manual
consents retain their existing behaviour. Renewals/amendments require a new request.

Use **Record withdrawal of consent** on a completed request. It preserves the signed
evidence, revokes the recipient capability and expires the existing consent record.
The existing expiry/automatic archive processing then applies. The signed PDF's
historical wording/date is not rewritten.

Cron expires request links, retries completion notifications, sends optional reminders
and removes temporary document/signature/response data for expired, declined or
revoked incomplete requests after the configured retention period. Completed evidence
and audit history are retained. Audit retention applies to incomplete requests.
Recipient copies are available only while the link remains valid and the download
setting permits them. Backups and direct database/filestore administrative access
remain governed by installation policy.

Schema upgrades use `dbstruct/table_consent_*.txt`, through the existing ResourceSpace
plugin schema updater; no manual installation SQL or core migrations are required.
Use transactional InnoDB tables and enabled ResourceSpace transactions. Keep the
installation's scramble key safe: it authenticates retained layout state. Do not
replace TCPDF during an in-progress signing session; if its persisted layout becomes
incompatible after an upgrade, regenerate the unsigned revision and sign again.

## Verification and limits

From the repository root:

```sh
php plugins/consentmanager/tests/integration/run.php
node plugins/consentmanager/tests/browser/run.cjs
```

Tests use the standard `rs_test_db` and refuse to overwrite an existing database.
Do not run them concurrently with another suite using that database. Test storage
is isolated under `/tmp`, cleaned on shutdown. `--cleanup` removes only a database
identified by this test's own fixture user after an interrupted run. Browser tests
require the existing Playwright installation, Chromium, OpenSSL, FFmpeg and `pdftotext`; they use a local
HTTPS proxy and no external email service.

Known limits:

- Identity verification, renewals and qualified/certificate signatures are deferred.
- Drawing requires JavaScript; typed signatures remain available without it.
  Drawing is isolated in `js/draw.js`; method visibility is in `js/signature.js`.
- Previews must already exist. Video playback requires a browser-supported MP4/WebM
  derivative. Legacy FLV previews and unsupported formats show a fallback. Original
  MP3 files are not streamed while originals are disabled; converted MP3 derivatives
  of other audio formats can be played. No original/alternative fallback is exposed.
- An embedded PDF viewer depends on browser support; opening/downloading the complete
  PDF is always available at review stages. A presentation event records delivery of
  the exact PDF, not proof that every page was read.
- Notifications use ResourceSpace's existing notification/mail delivery semantics.
  A crash at the delivery/acknowledgement boundary can result in a repeated completion
  notification; it cannot produce a duplicate consent record.
- Perform deployment-specific WCAG 2.2 AA, assistive-technology, mixed-media and
  large-collection acceptance testing. Automated checks are not a compliance claim.
