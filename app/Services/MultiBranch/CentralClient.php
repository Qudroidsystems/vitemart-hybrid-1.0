<?php

namespace App\Services\MultiBranch;

use App\Models\Installation;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Branch → HQ HTTP client. Every request is signed with this installation's secret.
 */
class CentralClient
{
    public function __construct(protected ?Installation $install = null)
    {
        $this->install = $install ?? Install::current();
    }

    public function baseUrl(): string
    {
        $url = rtrim((string) $this->install?->central_url, '/');
        if (!$url) {
            throw new \RuntimeException('This installation is not connected to headquarters.');
        }
        return $url;
    }

    protected bool $quick = false;

    /** Short timeouts for calls made while a person waits (they fall back to the background retry). */
    public function quick(bool $on = true): static
    {
        $this->quick = $on;
        return $this;
    }

    public function send(string $method, string $path, array $data = [], int $timeout = 30): Response
    {
        if ($this->quick) {
            $timeout = min($timeout, 5);
        }
        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');
        $body = $method === 'GET' ? '' : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ts = time();
        $signPath = parse_url($path, PHP_URL_PATH);

        $req = Http::timeout($timeout)->connectTimeout($this->quick ? 2 : 10)->acceptJson()->withHeaders([
            'Authorization' => 'Bearer ' . $this->install->token,
            'X-Installation' => $this->install->remote_installation_uid,
            'X-Timestamp' => (string) $ts,
            'X-Signature' => Signature::make((string) $this->install->hmac_secret, $method, $signPath, $body, $ts),
            'X-App-Version' => config('vitemart.version'),
        ]);

        $url = $this->baseUrl() . $path;
        return $method === 'GET'
            ? $req->get($url, $data)
            : $req->withBody($body, 'application/json')->send($method, $url);
    }

    public function get(string $path, array $query = []): Response
    {
        // (query goes in as data: Guzzle would otherwise replace a query string written into the URL)
        return $this->send('GET', $path, $query);
    }

    public function post(string $path, array $data = []): Response
    {
        return $this->send('POST', $path, $data);
    }

    /** Unsigned call used only for enrollment (we have no secret yet). */
    public static function enroll(string $centralUrl, array $payload): Response
    {
        return Http::timeout(30)->acceptJson()->asJson()
            ->post(rtrim($centralUrl, '/') . '/api/v1/enrollments', $payload);
    }
}
