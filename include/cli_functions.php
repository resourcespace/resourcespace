<?php

/**
 * Check whether the output supports an animated status line.
 *
 * @return bool True for interactive terminals that support ANSI controls.
 */
function cli_is_interactive(): bool
{
    static $interactive = null;
    return $interactive ??= defined('STDOUT') && stream_isatty(STDOUT) && getenv('TERM') !== 'dumb';
}

/**
 * Style CLI text when the terminal supports colour and the user has enabled it.
 *
 * @param string $text Message or part of the progress display.
 * @param string $style Message style.
 * @return string Text with optional ANSI formatting.
 */
function cli_style(string $text, string $style): string
{
    static $colour = null;
    $colour ??= cli_is_interactive() && getenv('NO_COLOR') === false;
    if (!$colour) {
        return $text;
    }
    $code = match ($style) {
        'heading' => '1;36',
        'count' => '1',
        'success' => '1;32',
        'warning' => '33',
        'error' => '1;31',
        'muted' => '2',
        default => '',
    };
    return $code === '' ? $text : "\033[{$code}m{$text}\033[0m";
}

/**
 * Print a persistent progress message, clearing any current animation frame first.
 *
 * @param string $message Progress text.
 * @param string $style Optional terminal message style.
 * @return void
 */
function cli_log(string $message, string $style = ''): void
{
    fwrite(STDOUT, (cli_is_interactive() ? "\r\033[2K" : '')
        . cli_style($message, $style) . "\n");
}

/**
 * Format a single-line preview padded to a fixed terminal width.
 *
 * @param string $text Text to display.
 * @param int $width Width in terminal columns.
 * @return string Truncated and space-padded text.
 */
function cli_preview_column(string $text, int $width = 50): string
{
    if ($width <= 0) {
        return '';
    }
    $text = preg_replace('/\x1B\[[0-?]*[ -\/]*[@-~]/', '', $text) ?? '';
    $text = preg_replace('/[\s\p{Z}]+/u', ' ', $text) ?? '';
    $text = trim(preg_replace('/[\p{Cc}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text) ?? '');
    $preview = mb_strimwidth($text, 0, $width, '…', 'UTF-8');
    return $preview . str_repeat(' ', $width - mb_strwidth($preview, 'UTF-8'));
}

/**
 * Get the spinner and filled and empty segments of a progress bar.
 *
 * @param int $finished Completed work items.
 * @param int $total Total work items.
 * @param int $frame Animation frame index.
 * @return array{string, string, string} Spinner, completed segment and remaining segment.
 */
function cli_progress_frame(int $finished, int $total, int $frame): array
{
    $spinner = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
    $filled = max(0, min(12, (int) floor(12 * $finished / max(1, $total))));
    return [$spinner[$frame % count($spinner)], str_repeat('━', $filled), str_repeat('─', 12 - $filled)];
}

/**
 * Render the queue's current progress without scrolling an interactive terminal.
 *
 * @param int $finished Completed work items.
 * @param int $total Total work items.
 * @param string $detail Optional short status text.
 * @param int $frame Animation frame index.
 * @return void
 */
function cli_progress(int $finished, int $total, string $detail, int $frame = 0): void
{
    if (!cli_is_interactive()) {
        cli_log("{$finished}/{$total}" . ($detail === '' ? '' : "  {$detail}"));
        return;
    }

    [$spinner, $complete, $remaining] = cli_progress_frame($finished, $total, $frame);
    $bar = cli_style($complete, 'success') . cli_style($remaining, 'muted');
    fwrite(STDOUT, "\r\033[2K" . cli_style($spinner, 'heading')
        . "  {$bar}  " . cli_style("{$finished}/{$total}", 'count')
        . ($detail === '' ? '' : cli_style("  ·  {$detail}", 'muted')));
}

/**
 * Clear the current terminal status line.
 *
 * @return void
 */
function cli_clear_status(): void
{
    if (cli_is_interactive()) {
        fwrite(STDOUT, "\r\033[2K");
    }
}

/**
 * Read the size of the output terminal.
 *
 * @return array{rows: int, columns: int} Terminal dimensions with defaults when unavailable.
 */
function cli_terminal_size(): array
{
    $columns = (int) (getenv('COLUMNS') ?: 80);
    $rows = (int) (getenv('LINES') ?: 24);
    if (cli_is_interactive() && DIRECTORY_SEPARATOR === '/' && function_exists('proc_open')) {
        // Read the output terminal's size without running a shell.
        $process = proc_open(['stty', 'size'], [STDOUT, ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (is_resource($process)) {
            $size = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) === 0 && preg_match('/^(\d+)\s+(\d+)$/', trim((string) $size), $match)) {
                $rows = (int) $match[1] ?: $rows;
                $columns = (int) $match[2] ?: $columns;
            }
        }
    }
    return ['rows' => $rows, 'columns' => $columns];
}

/**
 * Redraw a live status block, reserving space for the persistent log.
 *
 * @param list<array{text: string, style?: string}> $lines Single-line status messages.
 * @param int $rendered_rows Number of rows to clear before redraw. Updated for the next call.
 * @param array{rows: int, columns: int} $size Terminal dimensions.
 * @return void
 */
function cli_render_status_block(array $lines, int &$rendered_rows, array $size): void
{
    if (!cli_is_interactive() || $lines === []) {
        return;
    }
    cli_clear_status_block($rendered_rows);
    $limit = max(2, $size['rows'] - 4);
    if (count($lines) > $limit) {
        $hidden = count($lines) - $limit + 1;
        $lines = array_slice($lines, 0, $limit - 1);
        $lines[] = ['text' => "  {$hidden} more status rows", 'style' => 'muted'];
    }
    $output = [];
    foreach ($lines as $line) {
        $text = preg_replace('/[\p{Cc}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', ' ', $line['text']) ?? '';
        $text = mb_strimwidth($text, 0, max(1, $size['columns'] - 1), '…', 'UTF-8');
        $output[] = cli_style($text, $line['style'] ?? '');
    }
    fwrite(STDOUT, "\r" . implode("\033[K\n\r", $output) . "\033[K");
    $rendered_rows = count($output);
}

/**
 * Clear a previously rendered block and leave the cursor ready for ordinary logging.
 *
 * @param int $rendered_rows Number of status rows on screen. Reset to zero.
 * @return void
 */
function cli_clear_status_block(int &$rendered_rows): void
{
    if ($rendered_rows > 0) {
        fwrite(STDOUT, "\r\033[2K" . str_repeat("\033[1A\r\033[2K", $rendered_rows - 1));
        $rendered_rows = 0;
    }
}
