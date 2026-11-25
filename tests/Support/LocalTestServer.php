<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * A disposable HTTP server for Pest integration tests of the real application.
 */
final class LocalTestServer
{
    private Process $process;
    private ?string $original_configuration = null;
    public readonly string $url;

    /**
     * Bind an ephemeral loopback port and wait for the test router to respond.
     *
     * @throws RuntimeException If the isolated HTTP server cannot be started.
     */
    public function __construct()
    {
        if (getenv('RS_TEST_MODE') !== '1') {
            throw new RuntimeException('HTTP integration tests require the isolated test environment.');
        }

        $listener = stream_socket_server('tcp://127.0.0.1:0');
        if ($listener === false) {
            throw new RuntimeException('Could not allocate the test server port.');
        }
        $address = stream_socket_get_name($listener, false);
        fclose($listener);
        $this->url = 'http://' . $address;
        $token = bin2hex(random_bytes(16));
        $this->process = new Process(
            [PHP_BINARY, '-d', 'opcache.enable=0', '-S', $address, 'tests/Support/browser-server-router.php'],
            RESOURCESPACE_BASE_PATH,
            ['RS_TEST_MODE' => '1', 'RS_BROWSER_SERVER_TOKEN' => $token]
        );
        $this->process->setTimeout(null);
        $this->process->start();

        $deadline = microtime(true) + 5;
        do {
            $curl = curl_init($this->url . '/__rs_browser_ready');
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT_MS => 100]);
            if (curl_exec($curl) === $token) {
                return;
            }
            usleep(50000);
        } while ($this->process->isRunning() && microtime(true) < $deadline);

        $this->close();
        throw new RuntimeException('The isolated HTTP server did not become ready.');
    }

    /**
     * Apply non-sensitive settings to subsequent requests and restore the test configuration on close.
     *
     * @param array<string, mixed> $settings Fixture settings to expose to application requests.
     * @return void
     */
    public function configure(array $settings): void
    {
        require_once RESOURCESPACE_BASE_PATH . '/include/test_functions.php';
        $configuration = file_get_contents(RESOURCESPACE_BASE_PATH . '/include/config.test.php');
        if ($configuration === false) {
            throw new RuntimeException('Could not read the test server configuration.');
        }
        $this->original_configuration ??= $configuration;

        $lines = [];
        foreach ($settings as $key => $value) {
            $lines[] = '$GLOBALS[' . var_export($key, true) . '] = ' . var_export($value, true) . ';';
        }
        setTestConfiguration(implode(PHP_EOL, $lines));
    }

    /**
     * Supply a fixture user's session and the normal browser-check cookie for a direct HTTP request.
     *
     * @param string $session Temporary session value stored for the fixture user.
     * @return list<string> Headers for an authenticated same-origin request.
     */
    public function sessionHeaders(#[\SensitiveParameter] string $session): array
    {
        $agent = 'ResourceSpace Pest integration';
        $browser = xor_base64_encode(hash_hmac('sha512', $agent . date('Ymd'), $GLOBALS['browser_check_key']));
        $user_cookie = $session === '' ? '' : 'user=' . $session . '; ';
        return [
            'User-Agent: ' . $agent,
            'Cookie: ' . $user_cookie . 'browser_check_cookie=' . $browser,
            'Origin: ' . $this->url,
        ];
    }

    /**
     * Issue a GET, form POST or multipart POST to the isolated application without following redirects.
     *
     * @param string $path Site-relative path and optional query string.
     * @param array<string, mixed>|null $post Fields for a form POST.
     * @param list<string> $headers Request headers such as the fixture session cookie.
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function request(string $path, ?array $post = null, array $headers = []): array
    {
        $response_headers = [];
        $curl = curl_init($this->url . $path);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$response_headers): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $response_headers[strtolower($parts[0])] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        if ($post !== null) {
            $multipart = array_filter($post, static fn (mixed $value): bool => $value instanceof \CURLFile);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $multipart === [] ? http_build_query($post) : $post);
        }
        $body = curl_exec($curl);
        if ($body === false) {
            throw new RuntimeException('Local test request failed: ' . curl_error($curl));
        }

        return [
            'status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
            'headers' => $response_headers,
            'body' => $body,
        ];
    }

    /**
     * Read hidden fields from a form in a real application response.
     *
     * @param string $html HTML document body.
     * @param string $form_xpath XPath expression selecting the relevant form.
     * @return array<string, string> Hidden inputs keyed by name.
     */
    public static function hiddenFields(string $html, string $form_xpath): array
    {
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $fields = [];
        foreach ($xpath->query($form_xpath . '//input[@type="hidden" and @name]') as $input) {
            if ($input instanceof \DOMElement) {
                $fields[$input->getAttribute('name')] = $input->getAttribute('value');
            }
        }
        return $fields;
    }

    /**
     * Stop the HTTP server and release its port.
     */
    public function close(): void
    {
        $this->process->stop(0.1);
        if ($this->original_configuration !== null) {
            $path = RESOURCESPACE_BASE_PATH . '/include/config.test.php';
            if (file_put_contents($path, $this->original_configuration, LOCK_EX) === false) {
                throw new RuntimeException('Could not restore the test server configuration.');
            }
            $this->original_configuration = null;
        }
    }
}
