<?php

/** Branded document pages shared by live signing and administrator preview. */
class ConsentManagerPDF extends TCPDF
{
    public string $consent_reference = '';
    public string $consent_organisation = '';
    public ?string $consent_logo = null;
    public int $consent_signature_page = 0;
    public float $consent_signature_y = 55;

    /**
     * Let TCPDF keep a document block together using its normal automatic pagination.
     * @param float $height Required block height in millimetres.
     * @return void
     */
    public function ensureSpace(float $height): void
    {
        $this->checkPageBreak($height);
    }

    /** Render the configured application logo and consent reference on every page. */
    public function Header(): void
    {
        if ($this->consent_logo !== null) {
            $previous = $GLOBALS['use_error_exception'] ?? false;
            $GLOBALS['use_error_exception'] = true;
            try {
                if (strtolower(pathinfo($this->consent_logo, PATHINFO_EXTENSION)) === 'svg') {
                    $this->ImageSVG($this->consent_logo, 18, 10, 48, 13);
                } else {
                    $dimensions = try_getimagesize($this->consent_logo);
                    if ($dimensions) {
                        $scale = min(48 / $dimensions[0], 13 / $dimensions[1]);
                        $this->Image($this->consent_logo, 18, 10,
                            $dimensions[0] * $scale, $dimensions[1] * $scale);
                    }
                }
            } catch (Throwable $e) {
                debug('Consent Manager: PDF logo could not be rendered');
            } finally {
                $GLOBALS['use_error_exception'] = $previous;
            }
        }
        $this->SetTextColor(65, 81, 99);
        $this->SetFont('dejavusans', '', 8);
        $this->SetXY(80, 10);
        $this->MultiCell(112, 5, $this->consent_organisation, 0, 'R');
        $this->SetXY(80, 20);
        $this->Cell(112, 5, $this->consent_reference, 0, 0, 'R');
        $this->SetDrawColor(217, 225, 233);
        $this->Line(18, 29, 192, 29);
    }

    /** Render page numbers and the durable consent reference. */
    public function Footer(): void
    {
        $this->SetY(-16);
        $this->SetDrawColor(217, 225, 233);
        $this->Line(18, $this->GetY() - 2, 192, $this->GetY() - 2);
        $this->SetFont('dejavusans', '', 8);
        $this->SetTextColor(90, 105, 120);
        $this->Cell(100, 6, $this->consent_reference);
        $this->Cell(74, 6, $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

/**
 * Resolve the configured logo to an existing local branding file without fetching arbitrary URLs.
 * @return string Local configured logo, or the standard ResourceSpace logo.
 */
function consentmanager_external_pdf_logo(): string
{
    $root = dirname(__DIR__, 3);
    $logo = strtok(get_header_image(false, false, 'light'), '?#');
    $mappings = [
        rtrim($GLOBALS['storageurl'] ?? ($GLOBALS['baseurl'] . '/filestore'), '/') . '/' => $GLOBALS['storagedir'],
        rtrim($GLOBALS['baseurl'], '/') . '/' => $root,
        $GLOBALS['baseurl_short'] => $root,
    ];
    foreach ($mappings as $prefix => $directory) {
        if ($prefix !== '' && str_starts_with($logo, $prefix)) {
            $candidate = $directory . '/' . rawurldecode(substr($logo, strlen($prefix)));
            if (is_file($candidate) && is_valid_rs_path($candidate)
                && in_array(strtolower(pathinfo($candidate, PATHINFO_EXTENSION)), ['svg', 'png', 'jpg', 'jpeg', 'gif'], true)) {
                return $candidate;
            }
        }
    }
    return $root . '/gfx/titles/title-black.svg';
}

/**
 * Render a consistent section heading and spacing.
 * @param TCPDF $pdf Open document.
 * @param string $title Plain-text section title.
 * @return void
 */
function consentmanager_external_pdf_heading(TCPDF $pdf, string $title): void
{
    $pdf->Ln(5);
    $pdf->SetTextColor(24, 47, 70);
    $pdf->SetFont('dejavusans', 'B', 13);
    $pdf->MultiCell(0, 9, $title, 0, 'L', false, 1);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetTextColor(45, 55, 65);
}

/**
 * Render a readable label/value table using escaped plain-text data.
 * @param TCPDF $pdf Open document.
 * @param array $rows Label/value pairs.
 * @return void
 */
function consentmanager_external_pdf_table(TCPDF $pdf, array $rows): void
{
    if (!$rows) {
        return;
    }
    $html = '<table cellpadding="7" cellspacing="0" width="100%">';
    foreach ($rows as [$label, $value]) {
        $html .= '<tr nobr="true"><td width="34%" bgcolor="#eaf0f5"><b>' . escape($label)
            . '</b></td><td width="66%" bgcolor="#f6f8fa">' . nl2br(escape((string) $value)) . '</td></tr>';
    }
    $pdf->writeHTML($html . '</table>', true, false, true, false, '');
}

/**
 * Resolve a small existing resource image, never an original or alternative file.
 * @param int $resource Snapshot resource reference.
 * @return array|null Preview path and dimensions, or null if no usable image exists.
 */
function consentmanager_external_pdf_thumbnail(int $resource): ?array
{
    $data = get_resource_data($resource);
    $extension = $data['preview_extension'] ?? 'jpg';
    if (!in_array($extension, ['jpg', 'png'], true)) {
        $extension = 'jpg';
    }
    foreach (['thm', 'col', 'pre'] as $size) {
        $path = get_resource_path($resource, true, $size, false, $extension);
        if (!is_file($path)) {
            continue;
        }
        $previous = $GLOBALS['use_error_exception'] ?? false;
        try {
            $dimensions = try_getimagesize($path);
        } finally {
            $GLOBALS['use_error_exception'] = $previous;
        }
        if ($dimensions && $dimensions[0] > 0 && $dimensions[1] > 0) {
            return [$path, $dimensions[0], $dimensions[1]];
        }
    }
    return null;
}

/**
 * Render a compact photo manifest with a durable reference for every covered resource.
 * @param TCPDF $pdf Open document.
 * @param int $request Authorised request reference.
 * @return void
 */
function consentmanager_external_pdf_assets(ConsentManagerPDF $pdf, int $request): void
{
    $resources = consentmanager_external_resources($request);
    get_resource_data_batch(array_column($resources, 'resource'));
    $column = 0;
    $row_y = $pdf->GetY();
    foreach ($resources as $resource) {
        if ($column === 0) {
            $pdf->ensureSpace(53);
            $row_y = $pdf->GetY();
        }
        $x = 18 + $column * 59;
        $pdf->SetDrawColor(220, 228, 236);
        $pdf->SetFillColor(248, 250, 252);
        $pdf->RoundedRect($x, $row_y, 56, 49, 2, '1111', 'DF');
        $thumbnail = consentmanager_external_pdf_thumbnail((int) $resource['resource']);
        if ($thumbnail !== null) {
            [$path, $width, $height] = $thumbnail;
            $scale = min(50 / $width, 33 / $height);
            $image_width = $width * $scale;
            $image_height = $height * $scale;
            $pdf->Image($path, $x + (56 - $image_width) / 2, $row_y + 3 + (33 - $image_height) / 2,
                $image_width, $image_height);
        } else {
            $pdf->SetXY($x + 3, $row_y + 14);
            $pdf->SetFont('dejavusans', '', 8);
            $pdf->MultiCell(50, 5, $GLOBALS['lang']['cm_external_no_preview'], 0, 'C');
        }
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetTextColor(45, 55, 65);
        $pdf->SetXY($x + 3, $row_y + 39);
        $pdf->MultiCell(50, 6, $resource['identification'], 0, 'C');
        $column = ($column + 1) % 3;
        $pdf->SetXY(18, $column === 0 ? $row_y + 54 : $row_y);
    }
    if ($column !== 0) {
        $pdf->SetXY(18, $row_y + 54);
    }
}

/**
 * Generate the branded consent document and retain its exact layout for visible signature capture.
 * @param array $request Immutable request snapshot.
 * @param array $answers Validated form responses.
 * @param int $revision Document revision.
 * @return array Unsigned PDF bytes and authenticated server-only layout state.
 */
function consentmanager_external_pdf(array $request, array $answers, int $revision): array
{
    $terms = consentmanager_external_json($request['terms']);
    $lang = $GLOBALS['lang'];
    $pdf = new ConsentManagerPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->consent_reference = 'CM-' . $request['ref'] . ' / ' . $revision;
    $pdf->consent_organisation = $terms['organisation'];
    $pdf->consent_logo = consentmanager_external_pdf_logo();
    $pdf->SetMargins(18, 36, 18);
    $pdf->SetAutoPageBreak(true, 22);
    $pdf->SetTitle($terms['title']);
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', 'B', 21);
    $pdf->SetTextColor(24, 47, 70);
    $pdf->MultiCell(0, 12, $terms['title'], 0, 'L', false, 1);
    consentmanager_external_pdf_heading($pdf, $lang['cm_external_pdf_details']);
    $rows = [[$lang['cm_external_name'], $answers['name']],
        [$lang['cm_external_usage'], $request['usage_text']],
        [$lang['cm_external_start'], $answers['start'] ?? date('Y-m-d')],
        [$lang['cm_external_pdf_expiry'], $request['consent_expires'] ?? $lang['cm_external_no_expiry']]];
    if ($answers['guardian'] !== '') {
        $rows[] = [$lang['cm_external_guardian'], $answers['guardian']];
    }
    $requester = $terms['requester'] ?? ['include' => $GLOBALS['consentmanager_external_include_requester'],
        'name' => (get_user($request['owner'])['fullname'] ?? '') ?: '#' . $request['owner'],
        'user' => $request['owner'], 'initiated' => $request['created']];
    if ($requester['include']) {
        $rows[] = [$lang['cm_external_requested_by'], $requester['name'] . ' (#' . $requester['user'] . ')'];
        $rows[] = [$lang['cm_external_requested_at'], $requester['initiated']];
    }
    consentmanager_external_pdf_table($pdf, $rows);
    foreach (['wording' => 'cm_external_pdf_terms', 'declarations' => 'cm_external_declarations'] as $key => $label) {
        consentmanager_external_pdf_heading($pdf, $lang[$label]);
        $pdf->MultiCell(0, 6, $terms[$key], 0, 'L', false, 1);
    }
    if ($terms['options']) {
        consentmanager_external_pdf_heading($pdf, $lang['cm_external_options']);
        $rows = [];
        foreach ($terms['options'] as $index => $label) {
            $rows[] = [$label, $lang[$answers['options'][$index] ? 'cm_external_granted' : 'cm_external_declined']];
        }
        consentmanager_external_pdf_table($pdf, $rows);
    }
    if ($terms['fields']) {
        consentmanager_external_pdf_heading($pdf, $lang['cm_external_fields']);
        $rows = [];
        foreach ($terms['fields'] as $index => $label) {
            $rows[] = [$label, $answers['fields'][$index]];
        }
        consentmanager_external_pdf_table($pdf, $rows);
    }
    consentmanager_external_pdf_heading($pdf, $lang['cm_external_privacy']);
    $pdf->MultiCell(0, 6, $terms['privacy'], 0, 'L', false, 1);
    consentmanager_external_pdf_heading($pdf, $lang['cm_external_manifest']);
    consentmanager_external_pdf_assets($pdf, (int) $request['ref']);
    $pdf->ensureSpace(154);
    consentmanager_external_pdf_heading($pdf, $lang['cm_external_signature_page']);
    $pdf->consent_signature_page = $pdf->getPage();
    $pdf->consent_signature_y = $pdf->GetY() + 2;
    $pdf->SetDrawColor(157, 174, 190);
    $pdf->SetFillColor(248, 250, 252);
    $pdf->RoundedRect(18, $pdf->consent_signature_y, 174, 45, 2, '1111', 'DF');
    $layout = serialize($pdf);
    $copy = clone $pdf;
    $bytes = $copy->Output('', 'S');
    if (!str_starts_with($bytes, '%PDF-') || strlen($bytes) > 12000000 || strlen($layout) > 12000000) {
        throw new RuntimeException('cm_external_pdf_failed');
    }
    return [$bytes, $layout];
}
