# ResourceSpace tests: agent instructions

## Scope and sources

These instructions apply to `tests/` and its support code. Write new tests with
Pest. Maintain existing `test_list/` tests when needed; do not add new legacy tests
or migrate unrelated tests as part of a focused change.

For this directory, this guide supersedes the root `AGENTS.md` statements that
Pest and the `test:*` Composer scripts are unavailable. Keep the root's applicable
coding, security and working-copy rules. Ask the user about unresolved policy or
workflow choices rather than treating either document as infallible.

Read the [automated testing guide](https://www.resourcespace.com/knowledge-base/developers/automated_testing).
Verify commands and helper contracts against this checkout before using them.
The implementation references are:

- `composer.json`, `phpunit.xml`, `tests/bootstrap.php` and `tests/Pest.php`.
- `tests/ResourceSpace*TestCase.php` and `tests/Support/ResourceSpaceTestEnvironment.php`.
- `tests/Support/browser-test-runner.sh` and the environment scripts beside it.
- `include/test_functions.php` and the relevant core function definitions.

## Choose the suite

| Directory | Use for | Base class assigned by `Pest.php` |
| --- | --- | --- |
| `Unit/` | Functions that work without application boot, database or filesystem side effects | `ResourceSpaceTestCase` |
| `Integration/` | Core helpers that need application configuration, users, database, indexing or storage | `ResourceSpaceBootTestCase` |
| `Browser/` | User actions, forms, navigation, JavaScript, AJAX, uploads and rendered permissions | `ResourceSpaceBrowserTestCase` |

Plugin-specific tests live in `plugins/<name>/tests/Unit/`, `Integration/` or
`Browser/`. The corresponding Pest suites discover these directories automatically
and apply the same base classes and groups as core tests. Keep plugin test files
named `*Test.php`; no per-plugin registration is needed. Discovery depends on the
directory existing, not on plugin activation. Tests that need an active plugin
must arrange activation in the test environment and restore its previous state.
Keep Pest tests in these subdirectories so the legacy runner does not execute them.

Use the smallest suite that exercises the actual behaviour. Add browser coverage
when the regression depends on the UI or request path; calling a core helper
alone does not test authentication, CSRF or JavaScript behaviour.

Name test files `*Test.php`, organised by feature. Use descriptive Pest `test()`
or `it()` closures and `expect()` assertions. Use named datasets for input
variations, as in `Unit/GenerateURLTest.php`. Do not repeat the base-class/group
registration already provided by `Pest.php`. Follow project PHP conventions and
use `declare(strict_types=1);` in new files.

## Run tests

Run from the repository root. Use a dedicated development/test installation.
Provisioning drops and recreates `rs_test_db` and resets test storage; cleanup
drops the database and removes the test filestore. Inspect the test configuration
and provisioning paths before running these operations. Never use production
configuration, credentials, assets or databases.

The published guide specifies PHP 8.4+ for the development environment. The root
Composer requirement of PHP `^8.2` is not the full development dependency
requirement. Check the lockfile/platform requirements; do not lower dependencies
or change application compatibility policy to make tests run on an older PHP.
Ask before installing Composer/Node dependencies or browser/system packages.
Browser tests need Playwright/Chromium and the feature's external utilities.

| Purpose | Command |
| --- | --- |
| Unit suite | `composer test:unit` |
| Integration suite | `composer test:integration` |
| Browser suite | `composer test:browser` |
| All three suites | `composer test:full` |

Prefer Composer scripts so the project controls test execution and environment
setup. For example, run a focused unit test with
`composer test:unit -- --filter=GenerateURLTest`. See the automated testing guide
for browser filtering, debugging and repeated runs. Provisioning and cleanup
scripts are internal implementation details of the suite runners.

The Composer runners select the `Unit`, `Integration` and `Browser` suites from
`phpunit.xml`, including both core and plugin tests. For example, run the Brand
Guidelines with `composer test:unit -- --filter=BrandGuidelinesTest`.

The browser runner provisions the environment, starts its own PHP server at
`127.0.0.1:8081`, checks readiness and stops that server on exit. It cleans up on
success and preserves the environment on failure. Server diagnostics are in
`tests/.runtime/php-server.log`; check for sensitive data before sharing logs.

Use `--nosetup` only after confirming that a suitable test environment exists.
Do not run integration, browser or legacy suites concurrently against the shared
database/configuration/storage. The browser runner rejects parallel execution.
`composer dev:tests` still invokes the legacy runner; `pnpm test` is a placeholder.

## Isolation, configuration and fixtures

- `tests/bootstrap.php` loads function definitions and Composer autoloading; it
  does not perform normal application boot for unit tests. The unit base class
  resets only plugin/hook globals. Restore any other globals your unit test sets.
- The integration base class starts the environment once and clears request,
  cookie, session and file superglobals before each test. It does **not** roll
  back database writes, restore configuration or reset the current user for
  every test. The browser base class supplies helpers without that integration
  reset lifecycle. Do not assume either suite has per-test database isolation.
- Create your own fixtures with domain helpers and assert their creation
  succeeded. Track returned IDs. Use `generateRandomTestID(16)` for distinct
  resource titles, collection names and other values that could collide.
- Keep tests human readable. Use Pest's `beforeEach()`, `afterEach()`,
  `beforeAll()`, `afterAll()` and per-test `->after()` for setup and cleanup,
  rather than wrapping test bodies in `try`/`finally`. Restore modified globals,
  users, permissions, sysvars and shared records in the appropriate teardown
  hook. Remove temporary field/schema fixtures with domain helpers. Make teardown
  tolerate setup failing partway through. Do not rely on another test's records
  or execution order.
- Do not add blanket transaction rollback: filesystem operations and browser
  requests use state outside the test process's database transaction.
- Get fixture paths with `testFixture('Images/image_1.jpg')`. The default fixture
  type is `Files`; the helper checks that the file exists. Reuse existing image,
  embedded-metadata JPEG, PDF, audio and video fixtures where suitable. Never use
  private assets. Keep new fixtures small and add them with `svn add`.

Use `setTestConfiguration()` for configuration loaded by subsequent browser
requests. It appends PHP to the marked block in `include/config.test.php`;
`setTestConfiguration('')` clears that block. Use literal PHP/nowdoc strings so
the test process does not interpolate configuration variable names:

```php
beforeAll(fn () => setTestConfiguration(<<<'PHP'
    $upload_then_edit = true;
PHP));

afterAll(fn () => setTestConfiguration(''));
```

Clear per-test overrides in per-test teardown when they are not intended for the
whole file. Account for file-level overrides before clearing the shared block.
Writing the block does not reload globals in the already booted test process;
for an integration test, explicitly set and restore the relevant runtime globals
when appropriate. Never edit `include/config.php`. Do not leave test overrides or
generated runtime files in the final diff.

## Common ResourceSpace operations

Use domain helpers for setup and state changes, preserving indexing, hooks and
logging. Read their signatures, permissions and failure values before calling.
Prepared `ps_query()`, `ps_value()` and `ps_array()` reads can verify persistence;
do not write application tables directly to bypass the behaviour being tested.

### Users, permissions and request security

`ResourceSpaceTestEnvironment::setUpDefaultTestUser()` sets the PHP process's
current user to the provisioned administrator. Use `setup_user(get_user($ref))`
for another test user, validating lookup success first. Restore the previous
user after the test. Browser authentication is separate: use `$this->loginAs()`
or `$this->loginAsAdmin()`; changing PHP globals does not authenticate a browser.

Test allowed and denied users for permission changes. Use `bypass_permissions()`
only for necessary fixture setup, never around the action whose authorisation
you are testing. This helper restores permissions after a normal return, without
`finally`; account for exceptions when using it in setup.

For request-boundary fixes, verify a valid request succeeds and forbidden requests
leave state unchanged. Cover GET, missing/invalid CSRF tokens and unauthorised
targets where relevant, including individual targets in bulk operations. A hidden
button assertion alone does not demonstrate server-side enforcement.

### Browser interaction and uploads

Use the concrete `BrowserPage` under `Support/Browser/` for shared browser
interactions. `EditPage`, `SimpleSearchPage` and `ResourceTypesPage` extend it
with feature-specific convenience methods. Wrappers follow the browser session;
they do not enforce the current application page or change type on navigation.
Use `UiElementSelector` for shared UI controls. `getPage()` returns the Pest page.
Start browser interactions, including login, inside the test body. The current
Pest browser plugin connects Playwright after `beforeEach()` hooks have run;
keep those hooks for non-browser setup.
Keep shared browser capabilities in `BrowserPage` and feature-specific convenience
methods in the relevant subclass. Keep helpers local to a test file until broader
reuse is needed. AJAX synchronisation and Uppy uploads currently live in
`BrowserPage`; this placement can evolve. Extract a trait or composed helper when
it provides a concrete reuse or readability benefit, including for uploads.
Preserve fluent chains across navigation and explain the benefit of an extraction.
Avoid speculative abstractions or extracting methods solely because they form a
named group.

ResourceSpace's `CentralSpaceLoad` can change the URL before replacing the page
content. Use `waitForAjax()` after actions that replace content and assert a
relevant destination element. For diagnosing jQuery activity, call
`->startAjaxTracking()` early. Avoid fixed sleeps; AJAX inactivity
alone does not prove upload processing or a background job has completed.

For Uppy uploads, `addFileToUppy(testFixture(...))` queues a file without starting
the transfer. Use `uploadAndWaitFor($destination_selector)` to start it and wait
for an element that appears only at the expected destination. Choose a selector
for the actual workflow, rather than an element already on the upload page.
Then assert metadata, collection membership, file details or stored extracted
text as appropriate. Use `EditPage` helpers for title/date fields and refine
metadata save/lock actions.

Use unique titles with `expectResourceIdByNodeName()` or
`expectResourceIdsByNodeName()` to locate uploaded resources. Those helpers search
node names without restricting the metadata field, so avoid ambiguous values.
Scope UI assertions to the relevant resource/field. Pass dynamic PHP values to
JavaScript through `encode_js_value()` rather than raw interpolation.

## Verification and handoff

Run syntax checks for changed PHP and the focused suite through its appropriate
runner. Broaden coverage when the change affects shared helpers or several
workflows. Report exact commands, results, unavailable dependencies and any
unverified browser/permission variants. Do not claim passing tests from syntax
checks alone or weaken assertions to hide a missing prerequisite.

For documentation-only changes, check commands, helper names, local references,
Markdown and whitespace; application suites are unnecessary. Review `svn diff`
and `svn status`, preserve unrelated changes and add new source files with
`svn add`. Do not commit, update the working copy or install dependencies without
the required user approval.
