<?php

include_once __DIR__ . '/external_pdf_functions.php';

/**
 * Return whether the external workflow is enabled. Transport is enforced at the portal boundary.
 * @return bool Whether the condition is met.
 */
function consentmanager_external_enabled(): bool
{
    return in_array('consentmanager', $GLOBALS['plugins'] ?? [], true)
        && !empty($GLOBALS['consentmanager_external_enabled'])
        && empty($GLOBALS['consentmanager_external_verification']);
}

/**
 * Return the configured application URL with HTTPS for recipient capability links.
 * @return string Validated value or resolved URL.
 */
function consentmanager_external_portal_url(): string
{
    return preg_replace('/\Ahttp:/i', 'https:', $GLOBALS['baseurl'])
        . '/plugins/consentmanager/pages/consent.php';
}

/**
 * Require external functionality and general consent write permission.
 * @return void No return value; errors throw exceptions.
 */
function consentmanager_external_require_manager(): void
{
    if (!consentmanager_external_enabled() || !consentmanager_check_write() || !empty($GLOBALS['k'])) {
        throw new RuntimeException('cm_external_denied');
    }
}

/**
 * Validate a bounded plain text value; return it or throw a validation error.
 * @param array $data Input values to validate.
 * @param string $key Input key to read.
 * @param int $limit Maximum character count.
 * @param bool $required Whether an empty value is rejected.
 * @return string Validated value or resolved URL.
 */
function consentmanager_external_text(array $data, string $key, int $limit, bool $required = true): string
{
    $value = $data[$key] ?? '';
    if (!is_string($value) || mb_strlen($value) > $limit || ($required && trim($value) === '')) {
        throw new InvalidArgumentException('cm_external_invalid');
    }
    return trim($value);
}

/**
 * Validate an ISO date, returning null for an optional empty value.
 * @param string $value Stored JSON or ISO date value.
 * @param bool $required Whether an empty value is rejected.
 * @return ?string Validated date, or null if optional and empty.
 */
function consentmanager_external_date(string $value, bool $required = false): ?string
{
    if ($value === '' && !$required) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $value < date('Y-m-d')) {
        throw new InvalidArgumentException('cm_external_invalid');
    }
    return $value;
}

/**
 * Decode internal JSON, failing explicitly on corrupted stored data.
 * @param string $value Stored JSON or ISO date value.
 * @return array Requested metadata.
 */
function consentmanager_external_json(string $value): array
{
    $data = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('cm_external_invalid');
    }
    return $data;
}

/**
 * Record a workflow event without storing tokens, addresses or client IPs.
 * @param int $request Authorised request reference.
 * @param string $event Audit event identifier.
 * @param int $revision Current document revision.
 * @param string $hash SHA-256 document hash.
 * @return void No return value; errors throw exceptions.
 */
function consentmanager_external_event(int $request, string $event, int $revision = 0, string $hash = ''): void
{
    ps_query(
        'INSERT INTO consent_request_event (request, event, revision, document_hash, created, actor) '
        . 'VALUES (?, ?, ?, ?, NOW(), ?)',
        ['i', $request, 's', $event, 'i', $revision, 's', $hash, 'i', $GLOBALS['userref'] ?? null]
    );
}

/**
 * List templates visible to managers; disabled templates are included only for administrators.
 * @param bool $all Include disabled templates for administrators.
 * @return array Requested metadata.
 */
function consentmanager_external_templates(bool $all = false): array
{
    consentmanager_external_require_manager();
    return ps_query('SELECT * FROM consent_form_template' . (($all && checkperm('a')) ? '' : ' WHERE enabled = 1')
        . ' ORDER BY title, ref');
}

/**
 * Save or duplicate a validated plain-text template. Requests keep independent snapshots.
 * @param int $ref Existing template ID, or zero to create a new template.
 * @param array $data Title, organisation, wording, declarations, privacy, duration, options and fields.
 * @return int Saved template ID.
 */
function consentmanager_external_save_template(int $ref, array $data): int
{
    consentmanager_external_require_manager();
    if (!checkperm('a')) {
        throw new RuntimeException('cm_external_denied');
    }
    $title = consentmanager_external_text($data, 'title', 200);
    $config = [];
    foreach (['organisation', 'wording', 'declarations', 'privacy', 'duration'] as $key) {
        $config[$key] = consentmanager_external_text($data, $key, $key === 'organisation' ? 200 : 20000);
    }
    foreach (['options', 'fields'] as $key) {
        $lines = $data[$key] ?? [];
        if (!is_array($lines)) {
            // Accept the original plain-text editor payload as well as the pill selector's arrays.
            $lines = preg_split('/\r\n|\r|\n/', consentmanager_external_text($data, $key, 5000, false));
        }
        if (count($lines) > 20 || array_filter($lines, static fn($line) => !is_string($line))) {
            throw new InvalidArgumentException('cm_external_invalid');
        }
        $config[$key] = array_values(array_unique(array_filter(array_map('trim', $lines))));
        if (count($config[$key]) > 20 || array_filter($config[$key], static fn($line) => mb_strlen($line) > 200)) {
            throw new InvalidArgumentException('cm_external_invalid');
        }
    }
    $config['title'] = $title;
    $json = json_encode($config, JSON_THROW_ON_ERROR);
    $enabled = empty($data['enabled']) ? 0 : 1;
    if ($ref > 0) {
        ps_query(
            'UPDATE consent_form_template SET title = ?, configuration = ?, enabled = ?, '
            . 'version = version + 1, updated = NOW() WHERE ref = ?',
            ['s', $title, 's', $json, 'i', $enabled, 'i', $ref]
        );
    } else {
        ps_query('INSERT INTO consent_form_template (title, configuration, enabled, version, updated) '
            . 'VALUES (?, ?, ?, 1, NOW())', ['s', $title, 's', $json, 'i', $enabled]);
        $ref = sql_insert_id();
    }
    return $ref;
}

/**
 * Require central Consent Manager access, independently of a source collection's lifetime.
 * @param int $ref Record reference.
 * @return array Requested metadata.
 */
function consentmanager_external_manage_request(int $ref): array
{
    consentmanager_external_require_manager();
    $rows = ps_query('SELECT * FROM consent_request WHERE ref = ?', ['i', $ref]);
    if (!$rows) {
        throw new RuntimeException('cm_external_denied');
    }
    return $rows[0];
}

/**
 * Create a draft with immutable terms and resource identification snapshots.
 * @param int $collection Source collection, which the current user must manage.
 * @param array $data Recipient, template, usage and dates.
 * @param array $selected Explicit resource IDs from the collection.
 * @return int New draft ID. Sending is a separate operation.
 */
function consentmanager_external_create(int $collection, array $data, array $selected): int
{
    consentmanager_external_require_manager();
    if (
        !collection_writeable($collection) || !collection_readable($collection)
        || !allow_collection_share(get_collection($collection))
    ) {
        throw new RuntimeException('cm_external_denied');
    }
    $name = consentmanager_external_text($data, 'recipient_name', 200);
    $email = consentmanager_external_text($data, 'recipient_email', 200);
    $guardian = consentmanager_external_text($data, 'guardian', 200, false);
    $selected_usage = $data['consent_usage'] ?? [];
    $allowed_usage = $GLOBALS['consent_usage_mediums'];
    if (
        !is_array($selected_usage) || !$selected_usage
        || array_filter($selected_usage, static fn($value) => !is_string($value))
        || array_diff($selected_usage, $allowed_usage)
    ) {
        throw new InvalidArgumentException('cm_external_invalid');
    }
    // Preserve the existing Consent Manager ordering and comma-separated storage contract.
    $usage = implode(', ', array_intersect($allowed_usage, array_unique($selected_usage)));
    $message = consentmanager_external_text($data, 'personal_message', 5000, false);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('cm_external_invalid');
    }
    $expires = consentmanager_external_date(consentmanager_external_text($data, 'expires', 10), true);
    $consent_expires = consentmanager_external_date(consentmanager_external_text($data, 'consent_expires', 10, false));
    $templates = consentmanager_external_templates();
    $template = array_values(array_filter($templates, static fn($row) => $row['ref'] == ($data['template'] ?? 0)));
    foreach ($selected as $resource) {
        if (
            (!is_int($resource) && !is_string($resource))
            || filter_var($resource, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
        ) {
            throw new InvalidArgumentException('cm_external_invalid');
        }
    }
    $selected = array_values(array_unique(array_map('intval', $selected)));
    if (
        !$template || !$selected || count($selected) > max(1, (int) $GLOBALS['consentmanager_external_max_resources'])
        || array_diff($selected, get_collection_resources($collection))
    ) {
        throw new InvalidArgumentException('cm_external_invalid');
    }
    $terms_snapshot = consentmanager_external_json($template[0]['configuration']);
    $terms_snapshot['requester'] = [
        'include' => (bool) $GLOBALS['consentmanager_external_include_requester'],
        'name' => $GLOBALS['userfullname'] ?: $GLOBALS['username'],
        'user' => (int) $GLOBALS['userref'],
        'initiated' => date('Y-m-d H:i:s T'),
    ];
    $snapshot = [];
    foreach ($selected as $resource) {
        if (
            $resource <= 0 || !consentmanager_check_write($resource) || get_resource_access($resource) !== 0
            || !can_share_resource($resource)
        ) {
            throw new RuntimeException('cm_external_denied');
        }
        $row = get_resource_data($resource);
        if (!$row) {
            throw new InvalidArgumentException('cm_external_invalid');
        }
        // Do not snapshot potentially restricted metadata. Stable resource references identify assets.
        $snapshot[$resource] = '#' . $resource . ' (' . $row['file_extension'] . ')';
    }
    consentmanager_external_begin('external_create');
    try {
        ps_query(
            'INSERT INTO consent_request (collection, collection_name, owner, recipient_name, recipient_email, '
            . 'guardian, usage_text, personal_message, template, template_version, terms, token_hash, expires, '
            . 'consent_expires, status, created, error) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
            ['i', $collection, 's', get_collection($collection)['name'], 'i', $GLOBALS['userref'],
                's', $name, 's', $email, 's', $guardian, 's', $usage, 's', $message,
                'i', $template[0]['ref'], 'i', $template[0]['version'], 's', json_encode($terms_snapshot, JSON_THROW_ON_ERROR),
            's',
            '',
            's',
            $expires . ' 23:59:59',
            's',
            $consent_expires,
            's',
            'draft',
            's',
            '']
        );
        $ref = sql_insert_id();
        foreach ($snapshot as $resource => $identification) {
            ps_query(
                'INSERT INTO consent_request_resource (request, resource, identification) VALUES (?, ?, ?)',
                ['i', $ref, 'i', $resource, 's', $identification]
            );
        }
        ps_query('INSERT INTO consent_request_response (request, updated) VALUES (?, NOW())', ['i', $ref]);
        consentmanager_external_event($ref, 'request_created');
        if (!db_end_transaction('external_create')) {
            throw new RuntimeException('cm_external_transaction');
        }
        return $ref;
    } catch (Throwable $e) {
        db_rollback_transaction('external_create');
        throw $e;
    }
}

/**
 * Return the frozen resource manifest for a request already authorised by the caller.
 * @param int $ref Record reference.
 * @return array Requested metadata.
 */
function consentmanager_external_resources(int $ref): array
{
    static $snapshots = [];
    $key = $GLOBALS['mysql_db'] . ':' . $ref;
    if (!isset($snapshots[$key])) {
        $snapshots[$key] = ps_query(
            'SELECT resource, identification FROM consent_request_resource WHERE request = ? ORDER BY resource',
            ['i', $ref]
        );
    }
    return $snapshots[$key];
}

/**
 * Issue a fresh recipient link for email or manual sharing, retaining only its hash.
 * @param int $ref Request reference.
 * @param bool $cron Internal scheduled reminder invocation.
 * @return array Request metadata and the newly generated URL, shown only to the authorised caller.
 */
function consentmanager_external_issue_link(int $ref, bool $cron = false): array
{
    if (!$cron) {
        consentmanager_external_manage_request($ref);
    } elseif (!consentmanager_external_enabled()) {
        throw new RuntimeException('cm_external_denied');
    }
    consentmanager_external_begin('external_issue_link');
    try {
        $rows = ps_query('SELECT * FROM consent_request WHERE ref = ? AND expires >= NOW() FOR UPDATE', ['i', $ref]);
        $request = $rows[0] ?? [];
        if (!$request || in_array($request['status'], ['completed', 'declined', 'revoked', 'expired'], true)) {
            throw new RuntimeException('cm_external_request_closed');
        }
        $token = bin2hex(random_bytes(32));
        ps_query('UPDATE consent_request SET token_hash = ?, status = IF(status IN (?, ?), ?, status), '
            . 'sent = NOW(), error = ?, reminders = reminders + ? WHERE ref = ?',
            ['s', hash('sha256', $token), 's', 'draft', 's', 'failed', 's', 'pending', 's', '',
                'i', $cron ? 1 : 0, 'i', $ref]);
        consentmanager_external_event($ref, 'request_link_created');
        if (!db_end_transaction('external_issue_link')) {
            throw new RuntimeException('cm_external_transaction');
        }
        return ['request' => $request,
            'url' => generateURL(consentmanager_external_portal_url(), ['token' => $token])];
    } catch (Throwable $e) {
        db_rollback_transaction('external_issue_link');
        throw $e;
    }
}

/**
 * Email a newly issued recipient link using ResourceSpace's existing mailer.
 * @param int $ref Manager-authorised request ID.
 * @param bool $cron Internal scheduled reminder invocation.
 * @return bool Whether the existing mail subsystem accepted the message.
 */
function consentmanager_external_send(int $ref, bool $cron = false): bool
{
    $issued = consentmanager_external_issue_link($ref, $cron);
    $request = $issued['request'];
    $url = $issued['url'];
    $terms = consentmanager_external_json($request['terms']);
    $owner = get_user($request['owner']);
    if (!$owner || !filter_var($owner['email'], FILTER_VALIDATE_EMAIL)) {
        ps_query('UPDATE consent_request SET status = ?, error = ? WHERE ref = ?',
            ['s', 'failed', 's', 'cm_external_mail_failed', 'i', $ref]);
        return false;
    }
    $message = $terms['organisation'] . "\n\n" . $GLOBALS['lang']['cm_external_invitation'] . "\n\n"
        . $request['personal_message'] . "\n\n" . $url . "\n\n"
        . $GLOBALS['lang']['cm_external_expires'] . ': ' . $request['expires'] . "\n"
        . $owner['fullname'] . ' <' . $owner['email'] . '>';
    // The mail helper detects HTML automatically, so encode all configurable/user-supplied text.
    $message = str_replace(escape($url), '<a href="' . escape($url) . '">'
        . escape($GLOBALS['lang']['cm_external_portal_title']) . '</a>', nl2br(escape($message)));
    $previous_errors = $GLOBALS['use_error_exception'] ?? false;
    $GLOBALS['use_error_exception'] = true;
    try {
        $sent = send_mail($request['recipient_email'], str_replace(["\r", "\n"], ' ', $terms['organisation']) . ': '
            . $GLOBALS['lang']['cm_external_request'], $message);
    } catch (Throwable $e) {
        $sent = false;
        debug('Consent Manager: request email delivery failed for request ' . $ref);
    } finally {
        $GLOBALS['use_error_exception'] = $previous_errors;
    }
    if (!$sent) {
        ps_query(
            'UPDATE consent_request SET error = ?, status = IF(status = ?, ?, status) WHERE ref = ?',
            ['s', 'cm_external_mail_failed', 's', 'pending', 's', 'failed', 'i', $ref]
        );
        consentmanager_external_event($ref, 'request_delivery_failed');
    } else {
        consentmanager_external_event($ref, 'request_sent');
    }
    return (bool) $sent;
}

/**
 * Resolve a bearer token, never accepting IDs in place of tokens. Completed links expire normally.
 * @param string $token Recipient bearer capability.
 * @return array Requested metadata.
 */
function consentmanager_external_resolve(string $token): array
{
    if (!consentmanager_external_enabled() || !preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
        throw new RuntimeException('cm_external_unavailable');
    }
    $rows = ps_query(
        'SELECT * FROM consent_request WHERE token_hash = ? AND expires >= NOW()',
        ['s', hash('sha256', $token)]
    );
    if (!$rows || in_array($rows[0]['status'], ['draft', 'revoked', 'expired', 'declined'], true)) {
        throw new RuntimeException('cm_external_unavailable');
    }
    return $rows[0];
}

/**
 * Run a recipient transition under the request row lock, with per-request rate limiting.
 * @param string $token Bearer token revalidated after locking (revocation race protection).
 * @param callable $operation Receives locked request and response; returns arbitrary result.
 * @return mixed Result of the operation; exceptions roll back all database mutations.
 */
function consentmanager_external_transition(string $token, callable $operation)
{
    $request = consentmanager_external_resolve($token);
    consentmanager_external_begin('external_transition');
    try {
        ps_query('SELECT ref FROM consent_request WHERE ref = ? FOR UPDATE', ['i', $request['ref']]);
        $request = consentmanager_external_resolve($token);
        $response = ps_query('SELECT *, IF(window_start >= DATE_SUB(NOW(), INTERVAL 1 MINUTE), attempts, 0) rate_attempts '
            . 'FROM consent_request_response WHERE request = ?', ['i', $request['ref']])[0];
        $attempts = (int) $response['rate_attempts'];
        if ($attempts >= 30) {
            throw new RuntimeException('cm_external_rate');
        }
        ps_query('UPDATE consent_request_response SET attempts = ?, window_start = IF(? = 0, NOW(), window_start) '
            . 'WHERE request = ?', ['i', $attempts + 1, 'i', $attempts, 'i', $request['ref']]);
        ps_query('SAVEPOINT consent_operation');
        try {
            $result = $operation($request, $response);
        } catch (Throwable $e) {
            // Failed validation must count towards the limit while its workflow mutations roll back.
            ps_query('ROLLBACK TO SAVEPOINT consent_operation');
            db_end_transaction('external_transition');
            throw $e;
        }
        if (!db_end_transaction('external_transition')) {
            throw new RuntimeException('cm_external_transaction');
        }
        return $result;
    } catch (Throwable $e) {
        db_rollback_transaction('external_transition');
        throw $e;
    }
}

/**
 * Make sure a recipient can still change a request; completed documents are immutable.
 * @param array $request Already authorised request metadata.
 * @return void No return value; errors throw exceptions.
 */
function consentmanager_external_require_incomplete(array $request): void
{
    if ($request['status'] === 'completed') {
        throw new RuntimeException('cm_external_invalid');
    }
}

/**
 * Validate responses and generate a fresh unsigned document, invalidating the old signature.
 * @param string $token Recipient capability.
 * @param array $data Subject name, guardian, declarations, optional choices and custom fields.
 * @return array Exact revision and unsigned hash generated from these responses.
 */
function consentmanager_external_complete_form(string $token, array $data): array
{
    return consentmanager_external_transition($token, static function ($request, $response) use ($data) {
        consentmanager_external_require_incomplete($request);
        $answers = consentmanager_external_answers($request, $data);
        $revision = (int) $response['revision'] + 1;
        [$bytes, $layout] = consentmanager_external_pdf($request, $answers, $revision);
        $hash = hash('sha256', $bytes);
        ps_query(
            'UPDATE consent_request_response SET revision = ?, responses = ?, layout = ?, layout_mac = ?, '
            . 'unsigned_pdf = ?, unsigned_hash = ?, unsigned_presented = NULL, signed_pdf = NULL, signed_hash = NULL, '
            . 'signed_presented = NULL, signatory = NULL, method = NULL, signature = NULL, signed_at = NULL, '
            . 'updated = NOW() WHERE request = ?',
            ['i', $revision, 's', json_encode($answers, JSON_THROW_ON_ERROR), 's', $layout,
                's', consentmanager_external_layout_mac($request['ref'], $revision, $hash, $layout),
            's',
            $bytes,
            's',
            $hash,
            'i',
            $request['ref']]
        );
        ps_query(
            'UPDATE consent_request SET status = ?, error = ? WHERE ref = ?',
            ['s', 'awaiting_signature', 's', '', 'i', $request['ref']]
        );
        consentmanager_external_event($request['ref'], 'form_completed', $revision, $hash);
        return ['revision' => $revision, 'unsigned_hash' => $hash];
    });
}

/**
 * Authenticate server-generated layout state before any deserialisation.
 * @param int $request Authorised request reference.
 * @param int $revision Current document revision.
 * @param string $hash SHA-256 document hash.
 * @param string $layout Server-generated serialised TCPDF layout.
 * @return string Validated value or resolved URL.
 */
function consentmanager_external_layout_mac(int $request, int $revision, string $hash, string $layout): string
{
    return hash_hmac('sha256', $request . ':' . $revision . ':' . $hash . ':' . $layout, $GLOBALS['scramble_key']);
}

/**
 * Fetch a revision-bound document and record that it was presented by the PDF endpoint.
 * @param string $token Recipient capability.
 * @param bool $signed Select signed or unsigned document.
 * @param int $revision Required current revision, preventing stale-tab approval.
 * @return string Exact PDF bytes; never generated here.
 */
function consentmanager_external_document(string $token, bool $signed, int $revision): string
{
    return consentmanager_external_transition($token, static function ($request, $response) use ($signed, $revision) {
        $prefix = $signed ? 'signed' : 'unsigned';
        $bytes = $response[$prefix . '_pdf'];
        $hash = $response[$prefix . '_hash'];
        if (
            (int) $response['revision'] !== $revision || !$bytes || !hash_equals($hash, hash('sha256', $bytes))
            || ($request['status'] === 'completed' && !$GLOBALS['consentmanager_external_recipient_copy'])
        ) {
            throw new RuntimeException('cm_external_invalid');
        }
        // Field names are an internal two-value allowlist, never recipient input.
        ps_query(
            'UPDATE consent_request_response SET ' . $prefix . '_presented = ? WHERE request = ?',
            ['s', $hash, 'i', $request['ref']]
        );
        consentmanager_external_event($request['ref'], $prefix . '_pdf_presented', $revision, $hash);
        return $bytes;
    });
}

/**
 * Apply a captured typed or drawn signature to the current authenticated document revision.
 * @param string $token Recipient capability.
 * @param array $data Revision, unsigned hash, signatory, method and signature representation.
 * @return void Signing never creates consent records.
 */
function consentmanager_external_sign(string $token, array $data): void
{
    consentmanager_external_transition($token, static function ($request, $response) use ($data) {
        consentmanager_external_require_incomplete($request);
        $signed = consentmanager_external_apply_signature($request, $response, $data);
        $bytes = $signed['signed_pdf'];
        $hash = $signed['signed_hash'];
        $name = $signed['signatory'];
        $method = $signed['method'];
        $signature = $signed['signature'];
        $signed_at = $signed['signed_at'];
        ps_query(
            'UPDATE consent_request_response SET signed_pdf = ?, signed_hash = ?, signed_presented = NULL, '
            . 'signatory = ?, method = ?, signature = ?, signed_at = ?, updated = NOW() WHERE request = ?',
            ['s', $bytes, 's', $hash, 's', $name, 's', $method, 's', $signature, 's', $signed_at, 'i', $request['ref']]
        );
        ps_query('UPDATE consent_request SET status = ? WHERE ref = ?', ['s', 'signed', 'i', $request['ref']]);
        consentmanager_external_event($request['ref'], 'signature_applied', $response['revision'], $hash);
    });
}

/**
 * Submit the exact previously displayed signed PDF, creating standard Consent Manager data atomically.
 * @param string $token Recipient capability, checked again under lock.
 * @param int $revision Current signed revision.
 * @param string $hash SHA-256 shown on final approval form.
 * @return int Existing-format consent reference; repeats are idempotent.
 */
function consentmanager_external_submit(string $token, int $revision, string $hash): int
{
    $consent = consentmanager_external_transition($token, static function ($request, $response) use ($revision, $hash) {
        if ($request['status'] === 'completed') {
            return (int) $request['consent'];
        }
        if (
            $request['status'] !== 'signed' || (int) $response['revision'] !== $revision
            || !$response['signed_pdf'] || $response['signed_hash'] !== $hash
            || $response['signed_presented'] !== $hash
            || !hash_equals($hash, hash('sha256', $response['signed_pdf']))
        ) {
            throw new RuntimeException('cm_external_invalid');
        }
        $owner = get_user($request['owner']);
        $permissions = $owner ? array_diff(array_merge(
            explode(',', $GLOBALS['global_permissions'] ?? ''),
            explode(',', $owner['permissions'] ?? '')
        ), explode(',', $GLOBALS['global_permissions_mask'] ?? '')) : [];
        if (
            !$owner || (int) $owner['approved'] !== 1 || (!in_array('cm', $permissions) && !in_array('t', $permissions))
            || (!empty($owner['account_expires']) && strtotime($owner['account_expires']) < time())
        ) {
            throw new RuntimeException('cm_external_denied');
        }
        $resources = consentmanager_external_resources($request['ref']);
        if (!$resources) {
            throw new RuntimeException('cm_external_invalid');
        }
        foreach ($resources as $resource) {
            if (!get_resource_data($resource['resource'], false)) {
                throw new RuntimeException('cm_external_invalid');
            }
        }
        // Narrow delegation to existing consent helpers only. No owner login/session/config is installed.
        // The cm capability was required when issuing the immutable snapshot and rechecked above.
        consentmanager_external_event($request['ref'], 'signed_pdf_submitted', $revision, $hash);
        $saved_permissions = $GLOBALS['userpermissions'] ?? null;
        $saved_user = $GLOBALS['userref'] ?? null;
        $GLOBALS['userpermissions'] = ['cm'];
        $GLOBALS['userref'] = $request['owner'];
        $file_path = null;
        try {
            $answers = consentmanager_external_json($response['responses']);
            $consent = consentmanager_create_consent(
                $answers['name'],
                null,
                null,
                $answers['guardian'],
                $request['recipient_email'],
                '',
                $request['usage_text'],
                'CM-' . $request['ref'] . "\nSHA-256: " . $hash . "\n" . $response['responses'],
                $answers['start'] ?? substr($response['signed_at'], 0, 10),
                $request['consent_expires'],
                (string) $request['owner']
            );
            if (!$consent) {
                throw new RuntimeException('cm_external_storage_failed');
            }
            $file_path = get_consent_file_path($consent);
            if (
                !consentmanager_save_file($consent, 'consent-' . $request['ref'] . '.pdf', $response['signed_pdf'])
                || !is_file($file_path) || !hash_equals($hash, hash_file('sha256', $file_path))
            ) {
                throw new RuntimeException('cm_external_storage_failed');
            }
            foreach ($resources as $resource) {
                if (!consentmanager_link_consent($consent, $resource['resource'])) {
                    throw new RuntimeException('cm_external_storage_failed');
                }
            }
            $count = ps_value(
                'SELECT COUNT(DISTINCT resource) value FROM resource_consent WHERE consent = ?',
                ['i', $consent],
                0
            );
            if ((int) $count !== count($resources)) {
                throw new RuntimeException('cm_external_storage_failed');
            }
            consentmanager_external_event($request['ref'], 'consent_record_created', $revision, $hash);
            ps_query(
                'UPDATE consent_request SET status = ?, consent = ?, completed = NOW(), error = ? WHERE ref = ?',
                ['s', 'completed', 'i', $consent, 's', '', 'i', $request['ref']]
            );
            // Keep the submitted bytes as immutable evidence; discard reusable signing state.
            ps_query('UPDATE consent_request_response SET layout = NULL, layout_mac = NULL, unsigned_pdf = NULL, '
                . 'updated = NOW() WHERE request = ?', ['i', $request['ref']]);
            consentmanager_external_event($request['ref'], 'request_completed', $revision, $hash);
            return (int) $consent;
        } catch (Throwable $e) {
            if ($file_path !== null && is_file($file_path) && !unlink($file_path)) {
                debug('Consent Manager: failed to remove uncommitted attachment');
            }
            throw $e;
        } finally {
            $GLOBALS['userpermissions'] = $saved_permissions;
            $GLOBALS['userref'] = $saved_user;
        }
    });
    // Notification happens after durable commit; cron retries if delivery fails.
    try {
        consentmanager_external_notify($consent);
    } catch (Throwable $e) {
        debug('Consent Manager: completion notification deferred for consent ' . $consent);
    }
    return $consent;
}

/**
 * Notify the owner only for completed, committed records; failed deliveries remain retryable.
 * @param int $consent Existing consent reference.
 * @return void No return value; errors throw exceptions.
 */
function consentmanager_external_notify(int $consent): void
{
    $rows = ps_query(
        'SELECT * FROM consent_request WHERE consent = ? AND status = ? AND notified = 0',
        ['i', $consent, 's', 'completed']
    );
    if (!$rows) {
        return;
    }
    $request = $rows[0];
    $notification = new ResourceSpaceUserNotification();
    $notification->set_subject($GLOBALS['lang']['cm_external_completed']);
    $url = generateURL($GLOBALS['baseurl'] . '/plugins/consentmanager/pages/edit.php', ['ref' => $consent]);
    $notification->set_text(escape($request['recipient_name'] . ' / ' . $request['collection_name'] . ' / '
        . $request['completed'] . ' / #' . $consent) . '<br><a href="' . escape($url) . '">'
        . escape($GLOBALS['lang']['cm_external_view_consent']) . '</a>');
    $previous_errors = $GLOBALS['use_error_exception'] ?? false;
    $GLOBALS['use_error_exception'] = true;
    try {
        send_user_notification([$request['owner']], $notification);
    } catch (Throwable $e) {
        debug('Consent Manager: completion notification delivery deferred for request ' . $request['ref']);
        return;
    } finally {
        $GLOBALS['use_error_exception'] = $previous_errors;
    }
    ps_query('UPDATE consent_request SET notified = 1 WHERE ref = ?', ['i', $request['ref']]);
}

/**
 * Revoke a pending capability, or record withdrawal while preserving completed evidence.
 * @param int $ref Record reference.
 * @param bool $withdraw Record withdrawal of completed consent instead of revocation.
 * @return void No return value; errors throw exceptions.
 */
function consentmanager_external_revoke(int $ref, bool $withdraw = false): void
{
    consentmanager_external_manage_request($ref);
    consentmanager_external_begin('external_revoke');
    try {
        $request = ps_query('SELECT * FROM consent_request WHERE ref = ? FOR UPDATE', ['i', $ref])[0];
        if ($withdraw && $request['status'] === 'completed') {
            // Existing expiry processing remains authoritative for notifications and archival actions.
            ps_query(
                'UPDATE consent SET expires = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE ref = ?',
                ['i', $request['consent']]
            );
            ps_query('UPDATE consent_request SET withdrawn = NOW(), token_hash = ? WHERE ref = ?', ['s', '', 'i', $ref]);
            consentmanager_external_event($ref, 'consent_withdrawn');
        } elseif ($request['status'] !== 'completed') {
            ps_query(
                'UPDATE consent_request SET status = ?, token_hash = ? WHERE ref = ?',
                ['s', 'revoked', 's', '', 'i', $ref]
            );
            consentmanager_external_event($ref, 'request_revoked');
        } else {
            throw new RuntimeException('cm_external_invalid');
        }
        db_end_transaction('external_revoke');
    } catch (Throwable $e) {
        db_rollback_transaction('external_revoke');
        throw $e;
    }
}

/**
 * Decline without creating a consent record or retaining a working signature.
 * @param string $token Recipient bearer capability.
 * @return void No return value; errors throw exceptions.
 */
function consentmanager_external_decline(string $token): void
{
    consentmanager_external_transition($token, static function ($request) {
        consentmanager_external_require_incomplete($request);
        ps_query(
            'UPDATE consent_request SET status = ?, token_hash = ? WHERE ref = ?',
            ['s', 'declined', 's', '', 'i', $request['ref']]
        );
        consentmanager_external_event($request['ref'], 'request_declined');
    });
}

/**
 * Return whether a standard consent has immutable external submission evidence.
 * @param int $consent Existing consent reference.
 * @return bool Whether the condition is met.
 */
function consentmanager_external_immutable(int $consent): bool
{
    return (int) ps_value(
        'SELECT COUNT(*) value FROM consent_request WHERE consent = ? AND status = ?',
        ['i', $consent, 's', 'completed'],
        0
    ) > 0;
}

/**
 * Process request expiry, optional rotating-link reminders, notification retries and draft retention.
 * @return void No return value; errors throw exceptions.
 */
function consentmanager_external_cron(): void
{
    if (!consentmanager_external_enabled()) {
        return;
    }
    foreach (
        ps_query(
            'SELECT ref FROM consent_request WHERE expires < NOW() AND status NOT IN (?, ?, ?, ?)',
            ['s', 'completed', 's', 'revoked', 's', 'declined', 's', 'expired']
        ) as $row
    ) {
        if (!db_begin_transaction('external_expire')) {
            return;
        }
        ps_query('SELECT ref FROM consent_request WHERE ref = ? FOR UPDATE', ['i', $row['ref']]);
        ps_query(
            'UPDATE consent_request SET status = ?, token_hash = ? WHERE ref = ? AND status <> ?',
            ['s', 'expired', 's', '', 'i', $row['ref'], 's', 'completed']
        );
        consentmanager_external_event($row['ref'], 'request_expired');
        db_end_transaction('external_expire');
    }
    if ($GLOBALS['consentmanager_external_reminders']) {
        foreach (
            ps_query(
                'SELECT ref FROM consent_request WHERE status IN (?, ?, ?, ?) AND expires >= NOW() '
                . 'AND sent < DATE_SUB(NOW(), INTERVAL ? DAY) AND reminders < ? LIMIT 100',
                ['s', 'pending', 's', 'viewed', 's', 'awaiting_signature', 's', 'signed',
                'i', max(1, (int) $GLOBALS['consentmanager_external_reminder_days']),
                'i',
                max(0, (int) $GLOBALS['consentmanager_external_reminder_limit'])]
            ) as $row
        ) {
            try {
                consentmanager_external_send($row['ref'], true);
            } catch (Throwable $e) {
                debug('Consent Manager: reminder could not be sent for request ' . (int) $row['ref']);
            }
        }
    }
    foreach (
        ps_query(
            'SELECT consent FROM consent_request WHERE status = ? AND notified = 0 LIMIT 100',
            ['s', 'completed']
        ) as $row
    ) {
        consentmanager_external_notify($row['consent']);
    }
    // Never discard an unexpired resumable draft or any completed signed evidence.
    ps_query(
        'UPDATE consent_request_response rr INNER JOIN consent_request r ON rr.request = r.ref '
        . 'SET rr.layout = NULL, rr.layout_mac = NULL, rr.unsigned_pdf = NULL, rr.signed_pdf = NULL, '
        . 'rr.signature = NULL, rr.responses = NULL WHERE r.status IN (?, ?, ?) '
        . 'AND rr.updated < DATE_SUB(NOW(), INTERVAL ? DAY)',
        ['s', 'expired', 's', 'revoked', 's', 'declined',
        'i',
        max(1, (int) $GLOBALS['consentmanager_external_temporary_days'])]
    );
    ps_query(
        'DELETE e FROM consent_request_event e INNER JOIN consent_request r ON e.request = r.ref '
        . 'WHERE r.status <> ? AND e.created < DATE_SUB(NOW(), INTERVAL ? DAY)',
        ['s', 'completed', 'i', max(1, (int) $GLOBALS['consentmanager_external_audit_days'])]
    );
}

/**
 * Return a scoped preview path and MIME type, or null when no safe derivative exists.
 * @param array $request Already authorised request metadata.
 * @param int $resource Snapshot resource reference.
 * @param bool $original Request an original only when configured to allow it.
 * @return ?array Authorised preview path and MIME type, or null if unavailable.
 */
function consentmanager_external_media(array $request, int $resource, bool $original = false): ?array
{
    $refs = array_column(consentmanager_external_resources($request['ref']), 'resource');
    if (!in_array($resource, $refs) || $request['status'] === 'completed') {
        throw new RuntimeException('cm_external_denied');
    }
    $data = get_resource_data($resource);
    if (!$data) {
        return null;
    }
    if ($original) {
        if (!$GLOBALS['consentmanager_external_originals']) {
            throw new RuntimeException('cm_external_denied');
        }
        $path = get_resource_path($resource, true, '', false, $data['file_extension']);
        return is_file($path) ? [$path, 'application/octet-stream',
            get_download_filename($resource, '', -1, $data['file_extension'])] : null;
    }
    $extension = $GLOBALS['ffmpeg_preview_extension'];
    if (in_array($extension, ['mp4', 'webm'], true)) {
        $path = get_resource_path($resource, true, 'pre', false, $extension);
        if (is_file($path)) {
            return [$path, 'video/' . $extension];
        }
    }
    // Empty-size MP3 can be a derivative for non-MP3 originals; never expose an original MP3 by default.
    if (strtolower($data['file_extension']) !== 'mp3') {
        $path = get_resource_path($resource, true, '', false, 'mp3');
        if (is_file($path)) {
            return [$path, 'audio/mpeg'];
        }
    }
    $path = get_resource_path($resource, true, 'pre', false, 'jpg');
    return is_file($path) ? [$path, 'image/jpeg'] : null;
}

/**
 * Stream an authorised derivative with single-range support for mobile media players.
 * @param array $media Trusted local path and allowlisted MIME type returned by external_media().
 * @return void Exits after streaming.
 */
function consentmanager_external_stream(array $media): void
{
    [$path, $mime] = $media;
    $length = filesize($path);
    $start = 0;
    $end = $length - 1;
    if (isset($_SERVER['HTTP_RANGE'])) {
        if (
            !preg_match('/\Abytes=(\d*)-(\d*)\z/D', $_SERVER['HTTP_RANGE'], $matches)
            || ($matches[1] === '' && $matches[2] === '')
        ) {
            http_response_code(416);
            header('Content-Range: bytes */' . $length);
            exit;
        }
        if ($matches[1] === '') {
            $start = max(0, $length - (int) $matches[2]);
        } else {
            $start = (int) $matches[1];
            $end = $matches[2] === '' ? $end : min($end, (int) $matches[2]);
        }
        if ($start > $end || $start >= $length) {
            http_response_code(416);
            header('Content-Range: bytes */' . $length);
            exit;
        }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$length");
    }
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . ($end - $start + 1));
    if ($mime === 'application/octet-stream') {
        $filename = str_replace(["\r", "\n", '"', '\\'], '_', $media[2]);
        header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    }
    $stream = fopen($path, 'rb');
    if ($stream === false) {
        http_response_code(404);
        exit;
    }
    fseek($stream, $start);
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($stream) && !connection_aborted()) {
        $chunk = fread($stream, min(65536, $remaining));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
    }
    fclose($stream);
    exit;
}

/**
 * Retrieve response metadata for an already authorised request, without exposing server layout or PDF blobs.
 * @param int $ref Record reference.
 * @return array Requested metadata.
 */
function consentmanager_external_response(int $ref): array
{
    return ps_query('SELECT request, revision, responses, unsigned_hash, signed_hash, signatory, method, signed_at '
        . 'FROM consent_request_response WHERE request = ?', ['i', $ref])[0];
}

/**
 * Record the first visit and invalidate signed state when explicitly returning to corrections.
 * @param string $token Recipient bearer capability.
 * @param bool $correct Invalidate signature state for corrections.
 * @return void No return value; errors throw exceptions.
 */
function consentmanager_external_visit(string $token, bool $correct = false): void
{
    consentmanager_external_transition($token, static function ($request, $response) use ($correct) {
        if ($request['status'] === 'pending') {
            ps_query(
                'UPDATE consent_request SET status = ?, viewed = NOW() WHERE ref = ?',
                ['s', 'viewed', 'i', $request['ref']]
            );
            consentmanager_external_event($request['ref'], 'request_viewed');
        }
        if ($correct) {
            consentmanager_external_require_incomplete($request);
            ps_query('UPDATE consent_request_response SET signed_pdf = NULL, signed_hash = NULL, '
                . 'signed_presented = NULL, unsigned_presented = NULL, signatory = NULL, method = NULL, '
                . 'signature = NULL, signed_at = NULL, updated = NOW() WHERE request = ?', ['i', $request['ref']]);
            ps_query('UPDATE consent_request SET status = ? WHERE ref = ?', ['s', 'viewed', 'i', $request['ref']]);
            consentmanager_external_event($request['ref'], 'corrections_started', $response['revision']);
        }
    });
}

/**
 * Retrieve manager-visible events; authorisation is enforced here for direct callers too.
 * @param int $ref Record reference.
 * @return array Requested metadata.
 */
function consentmanager_external_events(int $ref): array
{
    consentmanager_external_manage_request($ref);
    return ps_query('SELECT event, revision, document_hash, created, actor FROM consent_request_event '
        . 'WHERE request = ? ORDER BY ref', ['i', $ref]);
}

/**
 * Return collection and resources for the manager's request form, enforcing access centrally.
 * @param int $collection Source collection reference; zero selects all requests.
 * @return array Requested metadata.
 */
function consentmanager_external_collection_context(int $collection): array
{
    consentmanager_external_require_manager();
    if (!collection_writeable($collection) || !collection_readable($collection)) {
        throw new RuntimeException('cm_external_denied');
    }
    $refs = get_collection_resources($collection);
    $assets = [];
    foreach (get_resource_data_batch($refs) as $resource) {
        $access = get_resource_access($resource['ref']);
        if ($access === RESOURCE_ACCESS_CONFIDENTIAL) {
            continue;
        }
        $preview = get_resource_preview($resource, ['thm', 'col'], $access);
        $assets[] = [
            'ref' => $resource['ref'],
            'preview' => $preview ? $preview['url'] : '',
            'fallback' => get_nopreview_html((string) $resource['file_extension'], $resource['resource_type']),
        ];
    }
    return ['collection' => get_collection($collection), 'resources' => $refs, 'assets' => $assets];
}

/**
 * Return completed evidence metadata for an authorised standard-consent viewer, even when external is disabled.
 * @param int $consent Existing consent reference.
 * @return array Requested metadata.
 */
function consentmanager_external_record(int $consent): array
{
    if (!consentmanager_check_read()) {
        throw new RuntimeException('cm_external_denied');
    }
    $rows = ps_query('SELECT r.ref, r.collection, r.withdrawn, rr.signed_hash, rr.signatory, rr.method, rr.signed_at '
        . 'FROM consent_request r INNER JOIN consent_request_response rr ON r.ref = rr.request '
        . 'WHERE r.consent = ? AND r.status = ?', ['i', $consent, 's', 'completed']);
    if (!$rows) {
        throw new RuntimeException('cm_external_invalid');
    }
    return $rows[0];
}

/**
 * Read an existing document for an authenticated manager without advancing recipient approval state.
 * @param int $request Request reference, authorised again here.
 * @param bool $signed Select the signed document.
 * @param int $revision Current document revision.
 * @return string Exact existing document bytes.
 */
function consentmanager_external_preview_document(int $request, bool $signed, int $revision): string
{
    consentmanager_external_manage_request($request);
    $rows = ps_query('SELECT revision, unsigned_pdf, unsigned_hash, signed_pdf, signed_hash '
        . 'FROM consent_request_response WHERE request = ?', ['i', $request]);
    $response = $rows[0] ?? [];
    $prefix = $signed ? 'signed' : 'unsigned';
    $bytes = $response[$prefix . '_pdf'] ?? '';
    if (
        !$bytes || (int) $response['revision'] !== $revision
        || !hash_equals($response[$prefix . '_hash'], hash('sha256', $bytes))
    ) {
        throw new RuntimeException('cm_external_invalid');
    }
    return $bytes;
}

/**
 * Return the supported request status identifiers for filtering and localisation.
 * @return array Requested metadata.
 */
function consentmanager_external_statuses(): array
{
    return ['draft', 'pending', 'viewed', 'awaiting_signature', 'signed', 'completed',
        'declined', 'expired', 'revoked', 'withdrawn', 'failed'];
}

/**
 * Retrieve one bounded page of centrally managed requests.
 * @param string $find Recipient name search.
 * @param string $status One supported status, or all.
 * @param int $offset Requested zero-based starting row.
 * @param int $per_page Configured list page size, capped at 100.
 * @return array Rows, total, offset and page size; never document or signature data.
 */
function consentmanager_external_request_page(string $find, string $status, int $offset, int $per_page): array
{
    consentmanager_external_require_manager();
    if (mb_strlen($find) > 200 || !in_array($status, array_merge(['all'], consentmanager_external_statuses()), true)) {
        throw new InvalidArgumentException('cm_external_invalid');
    }
    $where = [];
    $params = [];
    if ($find !== '') {
        $where[] = 'recipient_name LIKE ?';
        $params = ['s', '%' . $find . '%'];
    }
    if ($status === 'withdrawn') {
        $where[] = 'withdrawn IS NOT NULL';
    } elseif ($status !== 'all') {
        $where[] = 'status = ?';
        array_push($params, 's', $status);
        if ($status === 'completed') {
            $where[] = 'withdrawn IS NULL';
        }
    }
    $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $total = (int) ps_value('SELECT COUNT(*) value FROM consent_request' . $sql, $params, 0);
    $per_page = max(1, min(100, $per_page));
    $offset = min(max(0, $offset), max(0, (int) (ceil($total / $per_page) - 1) * $per_page));
    $rows = ps_query(
        'SELECT ref, recipient_name, collection_name, status, expires, error, consent, withdrawn '
        . 'FROM consent_request' . $sql . ' ORDER BY ref DESC LIMIT ? OFFSET ?',
        array_merge($params, ['i', $per_page, 'i', $offset])
    );
    return ['rows' => $rows, 'total' => $total, 'offset' => $offset, 'per_page' => $per_page];
}

/**
 * Render the standard pager without leaking its mutable global state between two tables.
 * @param array $options Standard pager options, including table-specific URL parameters.
 * @return void
 */
function consentmanager_render_pager(array $options): void
{
    $keys = ['curpage', 'url', 'url_params', 'totalpages', 'offset', 'per_page', 'confirm_page_change'];
    $saved = [];
    foreach ($keys as $key) {
        $saved[$key] = ['exists' => array_key_exists($key, $GLOBALS), 'value' => $GLOBALS[$key] ?? null];
    }
    try {
        pager(false, false, $options + ['confirm_page_change' => '']);
    } finally {
        foreach ($saved as $key => $state) {
            if ($state['exists']) {
                $GLOBALS[$key] = $state['value'];
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }
}

/**
 * Require an administrator for template management, returning one template or throwing if absent.
 * @param int $ref Record reference.
 * @return array Requested metadata.
 */
function consentmanager_external_template(int $ref): array
{
    consentmanager_external_require_manager();
    if (!checkperm('a')) {
        throw new RuntimeException('cm_external_denied');
    }
    $rows = ps_query('SELECT * FROM consent_form_template WHERE ref = ?', ['i', $ref]);
    if (!$rows) {
        throw new RuntimeException('cm_external_invalid');
    }
    return $rows[0];
}

/**
 * Delete a template; all issued request terms and historical documents retain independent snapshots.
 * @param int $ref Record reference.
 * @return void No return value; errors throw exceptions.
 */
function consentmanager_external_delete_template(int $ref): void
{
    consentmanager_external_template($ref);
    ps_query('DELETE FROM consent_form_template WHERE ref = ?', ['i', $ref]);
}

/**
 * Render the recipient portal for a live capability or authenticated administrator preview.
 * @param array|null $request Authorised request metadata, or null for an unavailable link.
 * @param string $token Live recipient capability; empty in administrator preview.
 * @param string $error Already localised plain text error.
 * @param bool $declined Whether to display decline confirmation.
 * @param bool $preview Enable interactive, unsaved administrator preview controls.
 * @param array|null $preview_response In-memory preview document, or null to display stored request state.
 * @return void
 */
function consentmanager_external_render_portal(
    ?array $request,
    string $token,
    string $error = '',
    bool $declined = false,
    bool $preview = false,
    ?array $preview_response = null
): void {
    global $baseurl, $baseurl_short, $language, $lang, $applicationname,
        $consentmanager_external_recipient_copy, $consentmanager_external_originals,
        $consentmanager_external_drawn;
    if ($request !== null) {
        get_resource_data_batch(array_column(consentmanager_external_resources($request['ref']), 'resource'));
    }
    include __DIR__ . '/external_portal.php';
}

/**
 * Start a workflow transaction only when every affected table supports rollback and row locks.
 * @param string $name Transaction name passed to the existing database helper.
 * @return void
 * @throws RuntimeException If transactions are disabled or tables are not transactional InnoDB tables.
 */
function consentmanager_external_begin(string $name): void
{
    static $verified = [];
    $database = $GLOBALS['mysql_db'];
    if (!isset($verified[$database])) {
        $tables = ['resource', 'resource_log', 'consent', 'resource_consent', 'consent_request',
            'consent_request_resource', 'consent_request_response', 'consent_request_event'];
        $rows = ps_query(
            'SELECT COUNT(*) total, SUM(ENGINE = ?) transactional FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . ps_param_insert(count($tables)) . ')',
            array_merge(['s', 'InnoDB'], ps_param_fill($tables, 's'))
        );
        if ((int) $rows[0]['total'] !== count($tables) || (int) $rows[0]['transactional'] !== count($tables)) {
            throw new RuntimeException('cm_external_transaction');
        }
        $verified[$database] = true;
    }
    if (!db_begin_transaction($name)) {
        throw new RuntimeException('cm_external_transaction');
    }
}

/**
 * Validate recipient answers identically for the live form and administrator preview.
 * @param array $request Authorised immutable request metadata.
 * @param array $data Recipient form values.
 * @return array Validated answers suitable for document generation.
 */
function consentmanager_external_answers(array $request, array $data): array
{
    $terms = consentmanager_external_json($request['terms']);
    if (!is_array($data['options'] ?? []) || !is_array($data['fields'] ?? [])) {
        throw new InvalidArgumentException('cm_external_invalid');
    }
    $answers = ['name' => consentmanager_external_text($data, 'name', 200),
        'guardian' => consentmanager_external_text($data, 'guardian', 200, false)];
    if (($data['declarations'] ?? '') !== 'yes') {
        throw new InvalidArgumentException('cm_external_invalid');
    }
    $answers['declarations'] = true;
    $answers['start'] = date('Y-m-d');
    $answers['options'] = [];
    foreach ($terms['options'] as $index => $label) {
        $answers['options'][$index] = isset($data['options'][$index]) && $data['options'][$index] === 'yes';
    }
    $answers['fields'] = [];
    foreach ($terms['fields'] as $index => $label) {
        $answers['fields'][$index] = consentmanager_external_text($data['fields'] ?? [], (string) $index, 1000);
    }
    return $answers;
}

/**
 * Apply a captured signature to authenticated document layout without storing or submitting it.
 * @param array $request Authorised immutable request metadata.
 * @param array $response Exact unsigned document revision and authenticated layout.
 * @param array $data Signature method, signatory, representation and document binding.
 * @return array Signed PDF bytes, hash and signature audit details.
 */
function consentmanager_external_apply_signature(array $request, array $response, array $data): array
{
    $name = consentmanager_external_text($data, 'signatory', 200);
    $method = consentmanager_external_text($data, 'method', 20);
    if (
        (int) ($data['revision'] ?? -1) !== (int) $response['revision']
        || !$response['unsigned_hash']
        || ($data['hash'] ?? '') !== $response['unsigned_hash']
        || !hash_equals($response['unsigned_hash'], hash('sha256', $response['unsigned_pdf']))
    ) {
        throw new InvalidArgumentException('cm_external_invalid');
    }
    $mac = consentmanager_external_layout_mac(
        $request['ref'],
        $response['revision'],
        $response['unsigned_hash'],
        $response['layout']
    );
    if (!hash_equals($mac, $response['layout_mac'])) {
        throw new RuntimeException('cm_external_pdf_failed');
    }
    // Only authenticated server-created state is accepted; recipients cannot supply layout objects.
    $pdf = unserialize($response['layout'], ['allowed_classes' => ['TCPDF', 'ConsentManagerPDF']]);
    if (!$pdf instanceof TCPDF) {
        throw new RuntimeException('cm_external_pdf_failed');
    }
    $signature_page = $pdf instanceof ConsentManagerPDF && $pdf->consent_signature_page > 0
        ? $pdf->consent_signature_page : $pdf->getNumPages();
    $signature_y = $pdf instanceof ConsentManagerPDF ? $pdf->consent_signature_y : 55;
    $pdf->setPage($signature_page);
    $pdf->SetXY(20, $signature_y + 5);
    $signature = $name;
    if ($method === 'typed' && $GLOBALS['consentmanager_external_typed']) {
        $pdf->SetFont('dejavusans', 'I', 18);
        $pdf->MultiCell(166, 12, $name, 0, 'L', false, 1);
    } elseif ($method === 'drawn' && $GLOBALS['consentmanager_external_drawn']) {
        // Bounded vector strokes avoid client image decoders, SVG markup and external URLs.
        if (empty($data['signature'])) {
            throw new InvalidArgumentException('cm_external_draw_required');
        }
        $signature = consentmanager_external_text($data, 'signature', 80000);
        $strokes = consentmanager_external_json($signature);
        $points = 0;
        $segments = 0;
        $pdf->SetLineWidth(0.6);
        foreach ($strokes as $stroke) {
            if (!is_array($stroke)) {
                throw new InvalidArgumentException('cm_external_invalid');
            }
            $previous = null;
            foreach ($stroke as $point) {
                if (
                    ++$points > 4000 || !is_array($point) || count($point) !== 2
                    || !is_numeric($point[0] ?? null) || !is_numeric($point[1] ?? null)
                    || $point[0] < 0 || $point[0] > 1 || $point[1] < 0 || $point[1] > 1
                ) {
                    throw new InvalidArgumentException('cm_external_invalid');
                }
            if ($previous !== null && ($previous[0] != $point[0] || $previous[1] != $point[1])) {
                    $pdf->Line(
                        20 + $previous[0] * 168,
                        $signature_y + 2 + $previous[1] * 40,
                        20 + $point[0] * 168,
                        $signature_y + 2 + $point[1] * 40
                    );
                    $segments++;
                }
                $previous = $point;
            }
        }
        if ($segments < 1) {
            throw new InvalidArgumentException('cm_external_draw_required');
        }
    } else {
        throw new InvalidArgumentException('cm_external_invalid');
    }
    $signed_at = date('Y-m-d H:i:s');
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetXY(18, $signature_y + 50);
    consentmanager_external_pdf_table($pdf, [
        [$GLOBALS['lang']['cm_external_signatory'], $name],
        [$GLOBALS['lang']['cm_external_method'], $GLOBALS['lang']['cm_external_method_' . $method]],
        [$GLOBALS['lang']['cm_external_signed_at'], $signed_at . ' ' . date('T')],
    ]);
    $pdf->Ln(5);
    $pdf->MultiCell(174, 6, $GLOBALS['lang']['cm_external_signature_notice'], 0, 'L', false, 1);
    $bytes = $pdf->Output('', 'S');
    $hash = hash('sha256', $bytes);
    return ['signed_pdf' => $bytes, 'signed_hash' => $hash, 'signed_presented' => null,
        'signatory' => $name, 'method' => $method, 'signature' => $signature, 'signed_at' => $signed_at];
}

/**
 * Prepare a signed draft for the second, explicit PDF review step. This never submits consent.
 * @param string $token Recipient capability.
 * @param array $data Completed form and captured signature.
 * @return void
 */
function consentmanager_external_prepare_review(string $token, array $data): void
{
    $document = consentmanager_external_complete_form($token, $data);
    consentmanager_external_sign($token, array_merge(
        $data,
        ['revision' => $document['revision'], 'hash' => $document['unsigned_hash']]
    ));
}

/**
 * Build an unsaved preview using the live form validator, PDF generator and signature implementation.
 * @param array $request Manager-authorised request metadata.
 * @param array $data Completed preview form and captured signature.
 * @return array In-memory preview response; no request, audit or consent records are changed.
 */
function consentmanager_external_prepare_preview(array $request, array $data): array
{
    $answers = consentmanager_external_answers($request, $data);
    $current = consentmanager_external_response($request['ref']);
    $revision = (int) $current['revision'] + 1;
    [$bytes, $layout] = consentmanager_external_pdf($request, $answers, $revision);
    $hash = hash('sha256', $bytes);
    $response = ['revision' => $revision, 'responses' => json_encode($answers, JSON_THROW_ON_ERROR),
        'unsigned_pdf' => $bytes, 'unsigned_hash' => $hash, 'layout' => $layout,
        'layout_mac' => consentmanager_external_layout_mac($request['ref'], $revision, $hash, $layout)];
    return array_merge($response, consentmanager_external_apply_signature(
        $request,
        $response,
        array_merge($data, ['revision' => $revision, 'hash' => $hash])
    ));
}

/**
 * Restore validated preview answers for corrections without saving draft state.
 * @param array $request Manager-authorised request metadata.
 * @param string $responses Previously rendered answers, treated as untrusted input.
 * @return array In-memory form response; signature and document state are invalidated.
 */
function consentmanager_external_preview_corrections(array $request, string $responses): array
{
    $answers = consentmanager_external_json($responses);
    $data = $answers;
    $data['declarations'] = !empty($answers['declarations']) ? 'yes' : '';
    $data['options'] = array_map(static fn($value) => $value ? 'yes' : '', $answers['options'] ?? []);
    $answers = consentmanager_external_answers($request, $data);
    $response = consentmanager_external_response($request['ref']);
    $response['responses'] = json_encode($answers, JSON_THROW_ON_ERROR);
    $response['signed_hash'] = null;
    return $response;
}

/**
 * Retrieve active, expired or withdrawn display states for an authorised set of consent records.
 * @param array $consents Consent references to display.
 * @param int|null $resource Optional resource context for resource-level consent readers.
 * @return array Consent reference to status map. Unauthorised or unrelated references are omitted.
 */
function consentmanager_consent_display_statuses(array $consents, ?int $resource = null): array
{
    if (!consentmanager_check_read($resource) || !$consents) {
        return [];
    }
    $params = ps_param_fill($consents, 'i');
    $restriction = '';
    if ($resource !== null) {
        $restriction = ' AND c.ref IN (SELECT consent FROM resource_consent WHERE resource = ?)';
        array_push($params, 'i', $resource);
    }
    $rows = ps_query("SELECT c.ref, CASE WHEN cr.withdrawn IS NOT NULL THEN 'withdrawn' "
        . "WHEN c.expires < CURDATE() THEN 'expired' ELSE 'active' END display_status "
        . 'FROM consent c LEFT JOIN consent_request cr ON cr.consent = c.ref '
        . 'WHERE c.ref IN (' . ps_param_insert(count($consents)) . ')' . $restriction, $params);
    return array_column($rows, 'display_status', 'ref');
}

/**
 * Render an inactive name with a strike-through and an explicit status label.
 * @param string $name Escaped here for HTML display.
 * @param string $status Active, expired, withdrawn or another request display status.
 * @param bool $label Show a label when there is no separate status column.
 * @return void Outputs HTML using the same presentation in consent and request lists.
 */
function consentmanager_render_status_name(string $name, string $status, bool $label = true): void
{
    if (in_array($status, ['expired', 'withdrawn', 'revoked'], true)) {
        echo '<s class="consent-inactive-name">' . escape($name) . '</s>';
        if ($label) {
            echo ' <span class="consent-withdrawn-status">'
                . escape($GLOBALS['lang']['cm_external_status_' . $status]) . '</span>';
        }
    } else {
        echo escape($name);
    }
}
