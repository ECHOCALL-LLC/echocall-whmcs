<?php
/**
 * EchoCall REST API client (OpenAPI 1.24 and later).
 *
 * - Requests can run as one customer of the reseller (X-EchoCall-Customer).
 *   The hub then scopes every read and write to that customer, so the client
 *   area can never reach another customer's data.
 * - Only reads are retried. A write that timed out may already be booked, and
 *   a second attempt would book it twice (a top up, a new account).
 * - Redirects are not followed, so the bearer key never travels to another host.
 * - Every call goes to the WHMCS module log (Utilities > Logs > Module Log) with
 *   the API key masked. WHMCS only records it while that log is switched on.
 *
 * @package EchoCall
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

final class EchoCallApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly string $errorCode = ''
    ) {
        parent::__construct($message, $status);
    }
}

final class EchoCallAPI
{
    public const VERSION = '2.0.0';
    public const DEFAULT_BASE_URL = 'https://hub.echocall.de/api/v1';

    private const CONNECT_TIMEOUT = 10;
    private const REQUEST_TIMEOUT = 30;
    private const READ_ATTEMPTS = 3;

    private string $apiKey;
    private string $baseUrl;
    private ?int $customerId = null;

    public function __construct(string $apiKey, string $baseUrl = self::DEFAULT_BASE_URL)
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            throw new EchoCallApiException('The EchoCall API key is missing in the product module settings.', 0, 'missing_key');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = self::normalizeBaseUrl($baseUrl);
    }

    /**
     * https only. Plain http is accepted for localhost, so the module can be
     * tested against a hub running on the same machine.
     */
    public static function normalizeBaseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        if ($url === '') {
            return self::DEFAULT_BASE_URL;
        }
        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
        if ($host === '' || !($scheme === 'https' || ($scheme === 'http' && $isLocal))) {
            throw new EchoCallApiException('The API base URL must start with https://', 0, 'invalid_base_url');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new EchoCallApiException('The API base URL must not carry credentials, a query or a fragment.', 0, 'invalid_base_url');
        }
        return $url;
    }

    /** A copy of this client whose requests run as one customer of the reseller. */
    public function asCustomer(int $customerId): self
    {
        if ($customerId <= 0) {
            throw new EchoCallApiException('No EchoCall customer is linked to this service yet.', 0, 'no_customer');
        }
        $copy = clone $this;
        $copy->customerId = $customerId;
        return $copy;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query, null);
    }

    public function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, [], $body);
    }

    public function patch(string $path, array $body = []): array
    {
        return $this->request('PATCH', $path, [], $body);
    }

    public function put(string $path, array $body = []): array
    {
        return $this->request('PUT', $path, [], $body);
    }

    public function delete(string $path): array
    {
        return $this->request('DELETE', $path, [], null);
    }

    private function request(string $method, string $path, array $query, ?array $body): array
    {
        if (!preg_match('#^/[A-Za-z0-9/_\-]*$#', $path)) {
            throw new EchoCallApiException('Invalid API path.', 0, 'invalid_path');
        }
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $attempts = $method === 'GET' ? self::READ_ATTEMPTS : 1;
        $status = 0;
        $raw = '';
        for ($attempt = 1; ; $attempt++) {
            try {
                [$status, $raw] = $this->send($method, $url, $body);
            } catch (EchoCallApiException $e) {
                if ($attempt >= $attempts) {
                    $this->log($method, $path, $body, 0, $e->getMessage());
                    throw $e;
                }
                sleep($attempt);
                continue;
            }
            if (($status === 429 || $status === 503) && $attempt < $attempts) {
                sleep($attempt);
                continue;
            }
            break;
        }

        $data = $raw === '' ? [] : json_decode($raw, true);
        $this->log($method, $path, $body, $status, is_array($data) ? $data : substr($raw, 0, 500));
        if (!is_array($data)) {
            throw new EchoCallApiException("Unexpected answer from EchoCall (HTTP {$status}).", $status, 'invalid_response');
        }

        if ($status >= 400) {
            $error = $data['error'] ?? null;
            $message = '';
            $code = '';
            if (is_array($error)) {
                $message = $error['message'] ?? '';
                $code = (string)($error['code'] ?? '');
            } elseif (is_string($error)) {
                $message = $error;
            }
            if (!is_string($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }
            if ($message === '' && isset($data['message']) && is_string($data['message'])) {
                $message = $data['message'];
            }
            throw new EchoCallApiException($message !== '' ? $message : "EchoCall answered HTTP {$status}.", $status, $code);
        }

        return $data;
    }

    /** @return array{0:int,1:string} */
    private function send(string $method, string $url, ?array $body): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
            'User-Agent: EchoCall-WHMCS/' . self::VERSION,
        ];
        if ($this->customerId !== null) {
            $headers[] = 'X-EchoCall-Customer: ' . $this->customerId;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode(
                $body === [] ? new stdClass() : $body,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            $headers[] = 'Content-Type: application/json';
        }
        $options[CURLOPT_HTTPHEADER] = $headers;

        $handle = curl_init($url);
        if ($handle === false) {
            throw new EchoCallApiException('cURL is not available on this server.', 0, 'network');
        }
        curl_setopt_array($handle, $options);
        $raw = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        unset($handle);

        if ($errno !== 0 || !is_string($raw)) {
            throw new EchoCallApiException('EchoCall is not reachable right now (' . $error . ').', 0, 'network');
        }
        return [$status, $raw];
    }

    /** @param array|string $response */
    private function log(string $method, string $path, ?array $body, int $status, $response): void
    {
        if (!function_exists('logModuleCall')) {
            return;
        }
        $request = $body === null ? '' : (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($this->customerId !== null) {
            $request = 'as customer ' . $this->customerId . ($request === '' ? '' : "\n" . $request);
        }
        logModuleCall('echocall', $method . ' ' . $path, $request, $response, 'HTTP ' . $status, [$this->apiKey]);
    }
}
