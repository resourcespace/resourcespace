# ResourceSpace coding agent instructions

## Scope and priorities

This file applies to the whole working copy. Read any nested `AGENTS.md` governing files you change;
more specific instructions take precedence within their directory. Explicit task instructions take
precedence over this file. This is ordinary Markdown following the [AGENTS.md format](https://agents.md/).

Deliver working, tested changes that follow ResourceSpace Coding standards v2.1. Legacy code is not
always a model to copy: use the standards below for new code while preserving existing contracts.
Keep changes focused on the requested task; preserve unrelated working-copy changes.

Before editing:

1. Inspect the relevant implementation, callers, configuration, hooks, and tests.
2. Check working-copy status. This project uses Subversion: use `svn status` and `svn diff` in an SVN
   checkout. Do not assume a `.git` directory means Git commands will work.
3. Find an existing helper or established implementation before adding one.
4. For behaviour changes, consult the relevant developer and user documentation linked below.
   Check that online instructions actually apply to this revision.

## Project map

ResourceSpace is a PHP and JavaScript digital asset management application backed by MySQL/MariaDB.

| Location | Responsibility |
| --- | --- |
| `include/` | Shared PHP functions, bootstrap, authentication, configuration, rendering |
| `pages/` | UI pages: request handling and presentation; put reusable business logic in `include/` |
| `api/` | API endpoints, including IIIF; shared API implementation is also in `include/` |
| `batch/`, `include/job_handlers/` | Offline scripts and background work |
| `dbstruct/`, `upgrade/` | Database definitions/default data and upgrade mechanisms |
| `plugins/` | Plugins, their hooks, configuration, language files, and optional database definitions |
| `languages/` | UI translations; `en.php` supplies fallback strings |
| `css/`, `js/`, `gfx/` | Styles, scripts, and UI graphics |
| `tests/test_list/`, `tests/performance_tests/` | Tests executed by `tests/test.php` |
| `e2e/`, `playwright.config.ts` | Browser tests and runner configuration |
| `vendor/`, `lib/`, `plugins/*/lib/` | Dependencies; avoid direct edits unless the task requires them |
| `filestore/` | Runtime assets and temporary files, not source code |

Do not expose credentials, private assets, or local configuration in code, logs, tests, or responses.
Do not modify installation-specific `include/config.php` or runtime data as a substitute for a code fix.

## Design and compatibility

- Keep solutions simple. Reuse existing technology; avoid new libraries, frameworks, speculative
  abstractions, and unused extension points. Keep functions, pages, and scripts focused.
- Preserve public function signatures, API behaviour, hook names/arguments/return semantics, existing
  configuration, and permission behaviour unless changing them is part of the task.
- Prefer plugin hooks for site-specific customisation. Implement general core fixes in the appropriate
  core code; do not move a requested core fix into a new plugin merely to avoid changing core.
- When changing a plugin, follow its existing structure and hook conventions. Check behaviour both
  with the plugin enabled and disabled where relevant.
- Keep new PHP code compatible with PHP **8.1**, as required by this repository's supplied coding
  policy, and preserve operation on 8.2, 8.3, and 8.4. Note that this checkout's `composer.json` requires
  PHP `^8.2` for dependency installation. Do not silently raise the coding-policy minimum or lower
  Composer's requirement to resolve this discrepancy; distinguish source compatibility from the
  installed dependency/runtime requirements and flag conflicts relevant to the task.
- Upgrades must continue to work by replacing application files. Use `dbstruct/` and the established
  upgrade mechanisms for persistent schema/data changes. Cover existing installations as well as new
  installs; do not rely on manual SQL, reinstalling, or overwriting site-specific settings.
- Inspect `include/config.default.php` and nearby configuration handling before adding a setting.
  Supply safe defaults and preserve existing installations' behaviour unless explicitly changing it.

## PHP and file conventions

- Follow PSR-1 and PSR-12 with ResourceSpace's variations, enforced where applicable by
  `phpcs.xml.dist`. Use four spaces, never tabs for indentation.
- Store text as UTF-8 without a BOM and use LF line endings. Keep source text ASCII where practical;
  preserve required Unicode in translations and user content. Files interpreted as PHP must use
  `.php`; this does not rename JavaScript, CSS, or dependency files.
- Use `<?php` and `<?php echo ...; ?>`, never short opening tags or `<?= ... ?>`.
- Prefer lines of 80 characters or fewer; 120 is a soft limit. Split long expressions sensibly.
- Put function/method opening braces on the next line; put control-structure opening braces on the
  same line. Write `} elseif (...) {` and `} else {`; never `else if`.
- Do not add spaces between a function name and `(` or just inside call parentheses. Place one space
  after commas and none before them. Put optional parameters after required parameters.
- Document all new functions with PHPDoc, including parameter meaning/types, return values, and
  important side effects or failure behaviour. Update documentation when changing a contract.
- Follow established names in the relevant subsystem, commonly `snake_case` for PHP helpers and
  variables. Do not rename existing symbols solely for style.
- Return early for invalid input and simple exceptional cases. Return a meaningful result when
  appropriate and handle failure values explicitly.
- Prefer `count`, `unset`, and `echo`; do not introduce `sizeof`, `delete`, `print`, `create_function`,
  or `eval`. See `phpcs.xml.dist` for the repository's prohibited/deprecated function checks.

## Security: check every request boundary

### Authentication and authorisation

- Use the existing bootstrap/authentication path. Protected pages must include
  `include/authenticate.php` as appropriate, including installations using anonymous access.
- Authentication is not authorisation. Apply `checkperm()` and the relevant resource, field,
  collection, download, and workflow access helpers on the server before reading or changing data.
  Inspect helper semantics and permission definitions instead of guessing permission strings.
- Enforce checks in direct page, AJAX, API, and bulk-operation paths. Hidden buttons and client-side
  checks do not enforce access. Validate each target in a bulk operation.
- Preserve external-share restrictions and restricted metadata visibility. Test an allowed user and
  a denied user, rather than testing only as an administrator.

### State changes and CSRF

- Use POST for state-changing web requests and call `enforcePostRequest($ajax)` before mutation,
  commonly `enforcePostRequest(getval('ajax', false))`.
- Render form tokens with `generateFormToken($form_id)`. Use `generateAjaxToken($form_id)` for the
  established AJAX pattern; it must be the last data entry with no trailing comma because it can
  return an empty string when CSRF protection is disabled.
- Verify valid requests work and GET/missing-token/invalid-token requests cannot mutate state.
  Follow the API's own authentication/request-validation contract for API endpoints.

### Validation and output encoding

- Validate type, range, length, and allowed values at the server boundary. Request getters alone do
  not establish that input is valid or that a user may act on it.
- Use `escape()` for HTML text and quoted attributes, including translated strings and errors.
  Use `encode_js_value()` when embedding PHP values in JavaScript; do not interpolate raw strings.
- Build rendered anchor URLs with `generateURL($url, $default_params, $overrides)` instead of manual
  query-string concatenation. It encodes parameters, not HTML: escape the result when rendering an
  HTML attribute. Validate/sanitise externally supplied URLs with the appropriate URL helper.
- For deliberately permitted rich HTML, use `strip_tags_and_attributes()` under the existing policy.
  Use DOMPurify where client-side HTML sanitisation is needed; prefer text insertion for plain text.
- Avoid shell calls where PHP can do the work. If needed, use established command helpers, validate
  inputs, and escape arguments individually. Never concatenate untrusted command fragments.
- Follow the ResourceSpace security guides and applicable OWASP guidance; review stored, reflected,
  and DOM output paths, not only the immediate response.

## Database access and consistency

All new SQL must use `ps_query()`, `ps_array()`, or `ps_value()` and prepared parameters. Write SQL
keywords in upper-case. Parameter arrays alternate type and value (`i`, `d`, `s`, `b`); do not escape
bound values or quote placeholders.

```php
$resources = ps_query(
    'SELECT ref, creation_date FROM resource WHERE file_extension = ?',
    ['s', 'jpg']
);
```

- For variable-length lists, use `ps_param_insert()` and `ps_param_fill()` and handle empty lists
  explicitly. Allowlist identifiers and sort directions; placeholders cannot bind SQL syntax.
- Do not introduce `sql_query()`, `sql_array()`, `sql_value()`, `getvalescaped()`, or SQL escaping as a
  replacement for binding. When touching genuine legacy SQL, prefer conversion to prepared calls.
  If a legacy query must remain, preserve its escaping and quoted-value requirements, including
  `escape_check_array_values()` for arrays; never copy that pattern into new code.
- Use domain helpers for writes so indexing, logging, permissions, hooks, and cache invalidation
  remain intact. Check query-cache behaviour when changing reads or writes.
- Avoid queries per resource in a loop when existing batch helpers can serve the same purpose.
  Evaluate performance changes with representative data while preserving result ordering and access.

## ResourceSpace data and user behaviour

- A resource combines its asset, previews, alternative files, and metadata. Do not assume assets
  live at predictable filenames; use the resource/file path helpers.
- Collections group independently existing resources and are not filesystem folders. A resource can
  belong to several collections. Private collection visibility does not itself make resources private;
  preserve the separate resource access checks.
- Metadata drives discovery and may also affect access. Preserve field-type behaviour, indexing,
  multilingual values, required fields, and field permissions when changing editing or searching.
- Fixed-list options are nodes. Check `$FIXED_LIST_FIELD_TYPES` in `include/definitions.php` and use
  `include/node_functions.php` helpers rather than treating all field values as free text or writing
  node/index tables directly. Preserve category-tree parent/child semantics.
- Check the relevant user guide for upload, editing, search, sharing, download, and workflow changes.
  Preserve search parameters and navigation state across links and modal/AJAX flows as appropriate.

## Files, uploads, and background processing

- Reuse `process_file_upload()` and `get_resource_path()` where appropriate; inspect their contracts.
  Validate extensions using the existing helpers and apply a narrow allowlist when practical.
- Use path/basename validators such as `is_valid_rs_path()`, `is_valid_upload_path()`, and
  `is_safe_basename()` as appropriate. Do not trust client filenames, MIME headers, or extensions alone.
- Prevent traversal, executable uploads, arbitrary overwrites, and access outside intended storage.
  Check upload failures and clean up temporary files through the established lifecycle.
- Preserve original assets and preview/alternative-file relationships. Use the existing job system
  for expensive work where that is the subsystem's established pattern.

## UI, CSS, JavaScript, and localisation

- Indent nested HTML by four spaces and put child elements on separate lines.
- Use existing rendering helpers, UI components, modal handling, and JavaScript libraries. Avoid
  adding a separate UI framework for a local change.
- Use existing CSS variables, e.g. `padding: var(--space-12);`, instead of duplicating available values.
  Name new CSS classes/IDs in kebab-case. Preserve existing selectors used by scripts/plugins.
- Put user-visible text in `$lang` entries, with English fallback entries in `languages/en.php` or
  the plugin's language files. Escape translated output for its rendering context.
- Preserve accessible labels, keyboard operation, focus handling, and understandable error feedback.
  Verify changed UI in the actual full-page and modal contexts it supports.

## Development and verification

Use the tooling present in this revision. The local PHP tests use ResourceSpace's own runner,
**not Pest**. The live Automated Testing article describes a newer v11.1 setup; do not assume its
`composer test:full` or `composer test:browser` commands exist here.

Run commands from the repository root. Inspect configuration and prerequisites first; never run
integration/browser tests against production data. `tests/test.php` creates/drops `rs_test_db` and
uses test storage. It needs a configured installation, database access, and relevant external tools.
Do not run concurrent suites against the same test database or use `-nosetup` without checking state.

| Check | Command / guidance |
| --- | --- |
| PHP syntax | `php -l path/to/changed.php` for each changed PHP file |
| Focused PHP tests | `php tests/test.php -time 1750` (example: test `001750_*`); pass multiple numeric IDs as needed |
| Full PHP suite | `php tests/test.php -time` |
| Composer test alias | `composer dev:tests` runs the runner with `-noteardown`, leaving its test database behind |
| Static analysis | `composer dev:phpstan` or `vendor/bin/phpstan analyse -c phpstan.neon.dist` |
| PHP style | If PHPCS is installed: `phpcs --standard=phpcs.xml.dist path/to/changed.php` |
| Browser tests | Inspect `e2e/`, `playwright.config.ts`, and CI setup; run an applicable spec with `pnpm exec playwright test path/to/spec --project=chromium` when dependencies and a test instance are available |

- The runner's flags are documented in `tests/test.php`. Some tests depend on shared setup or earlier
  fixtures; inspect dependencies before interpreting a focused failure as a regression.
- Add/update meaningful regression tests for bug fixes and behaviour changes where practical. Follow
  existing `tests/test_list/` conventions and test failure/permission cases. Restore modified globals,
  configuration, permissions, files, and database state so tests do not contaminate later tests.
- For UI changes, exercise the relevant browser flow; cover full-page/AJAX/modal and permission
  variants when affected. Do not claim browser verification when only syntax checks were run.
- Use `phpstan.neon.dist` and `phpcs.xml.dist`; do not hide new problems by weakening checks, updating
  baselines indiscriminately, or adding blanket suppressions. Distinguish pre-existing failures.
- `package.json`'s `test` script is a placeholder, not a functioning test suite. Do not report it as
  validation. PHPCS is not declared in this checkout's Composer dependencies; report if unavailable.
- For documentation-only changes, review accuracy, Markdown, links, and whitespace; application tests
  are unnecessary unless executable behaviour changed.

### Dependencies and builds

- Use the existing Composer and pnpm manifests/lockfiles. Install only when needed: `composer install`
  and `pnpm install --frozen-lockfile`, subject to the configured environment and task scope.
- Inspect install scripts first: pnpm's `postinstall` rebuilds dependencies and bumps cache versions.
  `pnpm run build:dependencies` performs those operations explicitly. Review generated-file changes.
- Avoid gratuitous dependency updates and edits to bundled/minified dependencies. When an update is
  requested, follow the documented build process and account for downstream patches in Composer.
- `composer build:release` mutates the working copy, updates SVN, and removes development files.
  It is a release operation, not a routine verification command; run only for an authorised release.

## Completion checklist

Before handing back work:

1. Review the diff for unrelated changes, secrets, debugging output, unsafe SQL/output, and accidental
   generated/runtime files. Preserve the user's existing changes.
2. Check compatibility, upgrade behaviour, hooks, permissions, configuration, and localisation for
   the paths affected by the task.
3. Run applicable checks; record exact commands, outcomes, and any unverified behaviour or blocker.
   Do not present unavailable or unrun checks as passing.
4. Summarise the resulting behaviour, why it changed, and material limitations. Document configuration
   or user-facing changes when needed. Do not commit, publish, or deploy unless requested.

## Reference documentation

Researched 2026-10-27. Online documentation can describe a later release; verify helper signatures,
commands, and supported features against this checkout. Keep these links as references rather than
copying version-specific commands uncritically.

- [AGENTS.md format and scope](https://agents.md/)
- [Developer documentation](https://www.resourcespace.com/knowledge-base/developers/)
- [Coding standards v2.1](https://www.resourcespace.com/knowledge-base/developers/coding_standards)
- [Software design principles](https://www.resourcespace.com/knowledge-base/developers/design_principals)
- [Project structure](https://www.resourcespace.com/knowledge-base/developers/project_structure)
- [Prepared statements](https://www.resourcespace.com/knowledge-base/developers/prepared_statements)
- [Access control](https://www.resourcespace.com/knowledge-base/developers/security/access_control)
- [CSRF](https://www.resourcespace.com/knowledge-base/developers/security/csrf)
- [Injection and output encoding](https://www.resourcespace.com/knowledge-base/developers/security/injection)
- [File management](https://www.resourcespace.com/knowledge-base/developers/security/file_management)
- [Fixed-list fields and nodes](https://www.resourcespace.com/knowledge-base/developers/fixed-list-fields)
- [Plugin development](https://www.resourcespace.com/knowledge-base/developers/modifications-and-writing-your-own-plugin)
- [Automated testing (check release applicability)](https://www.resourcespace.com/knowledge-base/developers/automated_testing)
- [Knowledge Base and user guides](https://www.resourcespace.com/knowledge-base/)
- [Resources and structure](https://www.resourcespace.com/knowledge-base/resources-and-structure)
- [Collections and visibility](https://www.resourcespace.com/knowledge-base/collections-public-and-themes)
- [Metadata](https://www.resourcespace.com/knowledge-base/metadata)
