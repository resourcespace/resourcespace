# Regression automation progress

[regression-coverage.csv](regression-coverage.csv) records all 1,028 catalogue rows. A single test can provide evidence for several rows. The `test_path` and `test_name` columns identify the assertions to revisit. A blank path means no test evidence has been entered for that row. `untested` includes rows that still need a source audit.

| Status | Meaning |
| --- | --- |
| `existing` | An existing test appears to cover the stated outcome. Check the execution column before relying on it. |
| `new` | New assertions cover the stated outcome and have passed in this workspace. |
| `partial` | The cited assertions cover only some of the requested outcome or still need a successful run. |
| `blocked` | The requested run depends on a platform, service, executable or fixture still to be supplied. |
| `manual` | Visual judgement, a native desktop feature or an external action still needs a manual check. |
| `context` | Heading, setup text or cross-reference. |

## Coverage by area

| Catalogue sections | Evidence and remaining checks |
| --- | --- |
| 1–3 Setup, upload and download | Chromium verifies both upload modes, collection routing, alternative files, Upload Here, file picking and drag-and-drop. Pest integration verifies signed download options, original and preview bytes, and ZIP and TAR archive contents. Office conversion, some media formats and platform installs still need verification. |
| 4–6 Editing, search and collections | New bulk-edit integration cases isolate text modes and fixed-list log effects. Display-condition tests cover OR, AND and hidden dependencies. Browser cases exercise editing, file replacement, deletion and restoration, collection membership and share revocation. Search state, ordering, field widgets and collection actions have further variants. |
| 7–10 Administration, system, user menu and sharing | Pest covers managed requests, account scopes, tabs, jobs, saved searches, sharing and a password lifecycle. The Messages popup tests the rendered link, reply control and persisted read state. CSV, admin reports, filters, preferences and external upload shares need more coverage. |
| 11–16 Plugins, Dash, Login, commenting, video and staticsync | Chromium verifies the saved-search Dashboard tile lifecycle, Brand Guidelines editing and reordering, and login and account flows. Pest integration covers CSV import, Action Dates, licence and consent records, Resource Usage, RSS, annotation controls and staticsync. Many plugin variants remain open. |
| 17–21 SMTP, API, offline jobs, scripts and config | Pest checks signed API calls, multipart uploads, message access with remote authentication disabled, and delivery of queued archive and CSV output. Local mail capture and remaining job and script entry points have gaps. |
| 22–32 Responsive UI, browsers, help, notifications, media and IIIF | Chromium verifies the responsive shell and collection bar. Firefox passes edit, upload and search-panel smoke cases. HTTP integration checks the PWA manifest and both IIIF presentation versions. The map tracks native platforms, external dependencies and remaining browser interactions. |

The checkout's standalone Playwright workflow runs the example spec against the Playwright website. ResourceSpace's application browser tests run through Pest. Version claims in the catalogue are test targets to confirm against current application support and the separate development dependency requirements.

### Counts by section

| Section | Verified | Existing, run pending | Partial | Untested | Blocked or manual |
| --- | ---: | ---: | ---: | ---: | ---: |
| 1 | 2 | 0 | 1 | 0 | 5 |
| 2 | 37 | 0 | 3 | 1 | 3 |
| 3 | 6 | 0 | 10 | 0 | 0 |
| 4 | 26 | 0 | 43 | 16 | 0 |
| 5 | 22 | 0 | 34 | 12 | 0 |
| 6 | 4 | 0 | 27 | 6 | 0 |
| 7 | 10 | 0 | 16 | 22 | 1 |
| 8 | 37 | 0 | 43 | 33 | 1 |
| 9 | 10 | 0 | 14 | 13 | 1 |
| 10 | 7 | 0 | 7 | 8 | 1 |
| 11 | 41 | 0 | 34 | 80 | 32 |
| 12 | 6 | 0 | 1 | 3 | 0 |
| 13 | 4 | 0 | 2 | 10 | 0 |
| 14 | 3 | 0 | 3 | 0 | 0 |
| 15 | 2 | 0 | 2 | 1 | 0 |
| 16 | 4 | 0 | 4 | 0 | 1 |
| 17 | 0 | 0 | 0 | 0 | 1 |
| 18 | 2 | 0 | 2 | 0 | 0 |
| 19 | 4 | 0 | 9 | 4 | 0 |
| 20 | 1 | 0 | 2 | 0 | 0 |
| 21 | 4 | 0 | 3 | 0 | 1 |
| 22 | 1 | 0 | 5 | 2 | 0 |
| 23 | 1 | 0 | 0 | 0 | 3 |
| 24 | 1 | 0 | 1 | 0 | 0 |
| 25 | 0 | 0 | 6 | 0 | 0 |
| 26 | 0 | 0 | 0 | 1 | 2 |
| 27 | 0 | 0 | 1 | 0 | 1 |
| 28 | 2 | 0 | 0 | 0 | 0 |
| 29 | 2 | 0 | 3 | 0 | 0 |
| 30 | 2 | 0 | 3 | 1 | 0 |
| 31 | 1 | 0 | 0 | 0 | 0 |
| 32 | 1 | 0 | 3 | 0 | 0 |

Headings and setup rows are listed in the CSV and excluded from these totals.

### Evidence by test layer

| Layer | Rows with cited evidence | Verified existing | Verified new | Partially verified |
| --- | ---: | ---: | ---: | ---: |
| browser | 103 | 47 | 32 | 24 |
| environment matrix | 10 | 0 | 1 | 1 |
| integration | 456 | 0 | 163 | 256 |
| manual | 7 | 0 | 0 | 0 |
| unit | 1 | 0 | 0 | 1 |

## Next coverage passes

| Next checks | Approach |
| --- | --- |
| Download delivery and access (80–98, 451, 601–631) | Reuse the verified byte and archive fixtures. Extend the permitted size and share settings through the existing download routes. Exercise the external upload terms gate and show that accepted uploads appear in the intended collection. |
| Fields, editing and collection actions (105–211, 270–288, 318–345) | Verify permissions, persistence and logs in Pest integration. Use Chromium for field option widgets, map selection, drag-and-drop and actions whose state depends on the current browser selection. |
| Administration, reports and jobs (348–403, 488–547, 959–979) | Assert exact visible rows and persisted results with isolated fixtures. Add the remaining report periods, list filtering, CSV and job cleanup using the existing HTTP and CLI helpers. |
| Plugins (634–879) | Use fixture inputs for offline conversion and import, and verify application results at the service boundary. SMTP delivery, live identity providers, provider integrations and local model runners stay pending until the necessary isolated service is available. |
| Installations, browsers and visual checks (6–16, 997–1012, 1033–1034) | Keep checks that can run locally in the main suite. Record exact version, OS and native browser requirements for scenarios outside the Linux environment. |

## Runtime budget and commands

The target is **under one hour wall clock** for the full local regression run, including setup and cleanup. The 24 November run took **29 minutes 17 seconds** on Linux, including the duplicate browser case since removed. The previous clean passing run took **26 minutes 58 seconds**. Aim for 20 minutes or less by reducing repeated browser setup and keeping configuration combinations in integration tests where the user-facing browser interaction already has coverage.

```sh
time composer test:full
composer test:unit
composer test:integration
composer test:browser -- --filter='AccountWorkflowsTest|ResourceWorkflowsTest|CollectionWorkflowsTest|SearchNavigationTest|MessagePopupTest'
composer test:browser -- --browser firefox --filter='search filter panel|an edit to an existing resource|uploaded resources can be added to an existing collection'
COMPOSER_PROCESS_TIMEOUT=0 composer dev:phpstan -- --no-progress --error-format=table
```

The normal commands provision and clean up the dedicated test database. For a verified, already-provisioned test environment, use `composer test:browser -- --nosetup --noteardown` and run integration separately.

```sh
RS_TEST_MODE=1 php -d auto_prepend_file=tests/Support/bootstrap-resourcespace-test-env.php \
    vendor/bin/pest --testsuite=Integration
```

Run only one suite that writes to the shared test database at a time. Track clean Windows installs, native Safari and live-service scenarios separately from the Linux runtime target.

## Legacy runner retirement

The 177 former regression cases have verified Pest replacements. Revision r30152 removed the numbered files and the former runner. `composer dev:tests` runs Pest unit and integration tests. `composer test:performance` runs the standalone benchmarks, or set `RS_BENCHMARK=500` to run one of them. The working copy also contains a separate, unversioned output-escaping scanner test that runs directly with PHP.

## Verification record

- The serial `composer test:full` run on 24 November passed 490 unit cases with 657 assertions, 523 integration cases with 5,033 assertions, and 74 Chromium cases. Integration skipped the read-only account and Ghostscript PDF checks. The command exited 1 after a standalone Upload Here case timed out at the Country autocomplete. Both upload-mode cases passed the identical assertions through the shared helper. The standalone case has been removed and row 62 now cites the retained cases. Chromium took 1,402.96 seconds.
- On a fresh environment after removing the duplicate, `composer test:browser -- --filter=UploadHereTest` passed all seven retained cases with 216 assertions in 170.76 seconds and cleaned up its test environment.
- PHPStan reported no errors when run after the full suite. All changed PHP files passed `php -l`.
- The 24 November full attempt passed 490 unit and 515 integration cases, with two prerequisite skips. Chromium was interrupted after five existing cases failed. All five passed in the subsequent focused browser run. The PHPStan process that ran alongside the first browser attempt reached Composer's 300-second limit.
- The subsequent focused runs verified the search disk usage count, archive selection and reset, external sharing controls, slideshow ordering and visibility, format conversion, and query-cache settings. Chromium verified both bulk-edit entry points, the existing-values expansion, warning dismissal and the addition of exactly the selected resources to the current collection. The profile test verified that deleting a user removes their avatar file and leaves the comment visible.
- A focused run of the collection membership, metadata persistence and share lifecycle files passed 47 cases with 377 assertions in 32.87 seconds. It adds verification for metadata pills and their search links across all five fixed-list types, per-field editing permissions, the collection log action, the empty-collection share setting and the signed-in resource share link.
- The initial clean full run passed 490 unit cases and reached the 300-second Composer timeout during integration. The integration runner now uses the same timeout configuration as the browser runner.
- The clean retry passed 490 unit cases with 657 assertions in 7.84 seconds and 402 integration cases with 3,328 assertions in 164.46 seconds. The two skips required a read-only database account and Ghostscript PDF access.
- The complete timed command finished in 28 minutes 13 seconds. Chromium ran 67 cases in 25 minutes 6 seconds, with 48 passing and 19 failing. Login, logout, account flows, alternative files and Messages passed. The failed cases included upload synchronisation, Dashboard and resource-type selectors, and Brand Guidelines navigation. The suite needs a successful rerun before the runtime target is verified for a passing build.
- The API consent and multipart cases passed with 184 assertions in 14.02 seconds. The broader focused API, offline worker, consent and news rerun passed 12 cases with 309 assertions.
- A later fresh integration run passed 452 cases with 3,870 assertions in 216.56 seconds, with the same two prerequisite skips. This run included the new media format, archive, signed download, CSV import, licence, Action Dates and metadata script tests.
- The standalone unit rerun passed 490 cases with 657 assertions in 17.36 seconds. PHPStan reported no errors after the integration corrections. `RS_BENCHMARK=500 composer test:performance` completed the standalone field search benchmark in 20.98 seconds.
- The focused Chromium run passed the new 360px dark-mode and collection-bar scenario plus both required-field checks. Several upload checks raced the server because the installed Playwright wrapper did not execute its `waitForFunction()` call. The revised tests use Pest's retrying page assertions.
- The next upload rerun passed all ten edit-first cases, all eight Upload Here cases and real file-picker and drop events. A later Chromium run passed both login cases, both locked-field cases, the PDF and MP3 preview links, the Dashboard tile lifecycle and Advanced Search switching. The latest focused Brand Guidelines reorder case passed including verification after reopening the page.
- The focused integration run for bulk editing, package delivery, resource permissions, Resource Usage and RSS passed 32 cases with 334 assertions. The focused run for IIIF, the requested preview job and site navigation passed nine cases with 137 assertions. A full attempt made before the final two integration corrections passed 477 integration cases, skipped two and failed two. That attempt stopped before Chromium.
- On PHP 8.5.4, a clean `composer test:full` exited successfully in 26 minutes 58 seconds. Unit passed 490 cases with 657 assertions in 5.50 seconds. Integration passed 482 cases with 4,287 assertions in 263.80 seconds, with the two prerequisite skips. Chromium passed 72 cases with 1,478 assertions in 1,331.57 seconds. The separate catalogue additions made after discovery are verified in focused runs.
- Firefox passed four cases with 30 assertions in 103.39 seconds, then passed a metadata phrase search with 21 assertions in 22.31 seconds. Linux WebKit could not start because the host lacks `libmanette-0.2.so.0`. Native Safari and Windows installation remain outside the local run.
- The subsequent Chromium date-controls case passed with 18 assertions in 21.64 seconds. It verifies autosave of individual date and time values, protects the stored date from invalid input, and uses the native browser date input when configured.
