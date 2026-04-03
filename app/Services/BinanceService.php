<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinanceService
{
    private string $baseUrl;

    private ?string $apiKey = null;

    private ?string $apiSecret = null;

    public function __construct(
        private readonly VaultService $vault,
    ) {
        $environment = config('services.binance.environment', 'production');

        $this->baseUrl = $environment === 'testnet'
            ? 'https://testnet.binancefuture.com'
            : 'https://fapi.binance.com';

        $this->apiKey = $this->vault->get('binance', 'api_key', $environment);
        $this->apiSecret = $this->vault->get('binance', 'api_secret', $environment);
    }

    /**
     * Get current price for a symbol.
     */
    public function getPrice(string $symbol): ?array
    {
        return $this->timedPublicRequest('GET', '/fapi/v1/ticker/price', ['symbol' => $symbol]);
    }

    /**
     * Get klines (candlestick) data.
     */
    public function getKlines(string $symbol, string $interval = '1h', int $limit = 500): ?array
    {
        return $this->timedPublicRequest('GET', '/fapi/v1/klines', [
            'symbol'   => $symbol,
            'interval' => $interval,
            'limit'    => $limit,
        ]);
    }

    /**
     * Get funding rate history.
     */
    public function getFundingRate(string $symbol, int $limit = 100): ?array
    {
        return $this->timedPublicRequest('GET', '/fapi/v1/fundingRate', [
            'symbol' => $symbol,
            'limit'  => $limit,
        ]);
    }

    /**
     * Get open interest.
     */
    public function getOpenInterest(string $symbol): ?array
    {
        return $this->timedPublicRequest('GET', '/fapi/v1/openInterest', ['symbol' => $symbol]);
    }

    /**
     * Place a new order (requires API key + secret).
     */
    public function placeOrder(array $params): ?array
    {
        if (! $this->apiKey || ! $this->apiSecret) {
            Log::channel('binance')->error('placeOrder: API keys not configured in vault');

            return null;
        }

        $params['timestamp'] = now()->getTimestampMs();
        $params['signature'] = $this->sign($params);

        $result = $this->timedAuthRequest('POST', '/fapi/v1/order', $params);

        if ($result) {
            Log::channel('trading')->info('ORDER_PLACED', [
                'symbol'   => $params['symbol'] ?? null,
                'side'     => $params['side'] ?? null,
                'type'     => $params['type'] ?? null,
                'quantity' => $params['quantity'] ?? null,
                'price'    => $params['price'] ?? null,
                'order_id' => $result['orderId'] ?? null,
            ]);
        }

        return $result;
    }

    /**
     * Query order status.
     */
    public function getOrder(string $symbol, string $orderId): ?array
    {
        if (! $this->apiKey || ! $this->apiSecret) {
            return null;
        }

        $params = [
            'symbol'    => $symbol,
            'orderId'   => $orderId,
            'timestamp' => now()->getTimestampMs(),
        ];
        $params['signature'] = $this->sign($params);

        return $this->timedAuthRequest('GET', '/fapi/v1/order', $params);
    }

    /**
     * Get account balance and positions.
     */
    public function getAccountInfo(): ?array
    {
        if (! $this->apiKey || ! $this->apiSecret) {
            return null;
        }

        $params = ['timestamp' => now()->getTimestampMs()];
        $params['signature'] = $this->sign($params);

        return $this->timedAuthRequest('GET', '/fapi/v2/account', $params);
    }

    /**
     * Get all open futures positions (non-zero notional).
     */
    public function getOpenPositions(): ?array
    {
        if (! $this->apiKey || ! $this->apiSecret) {
            return null;
        }

        $params = ['timestamp' => now()->getTimestampMs()];
        $params['signature'] = $this->sign($params);

        $data = $this->timedAuthRequest('GET', '/fapi/v2/positionRisk', $params);

        if ($data === null) {
            return null;
        }

        return collect($data)
            ->filter(fn ($p) => (float) ($p['positionAmt'] ?? 0) !== 0.0)
            ->values()
            ->toArray();
    }

    /**
     * Cancel an open order.
     */
    public function cancelOrder(string $symbol, string $orderId): ?array
    {
        if (! $this->apiKey || ! $this->apiSecret) {
            return null;
        }

        $params = [
            'symbol'    => $symbol,
            'orderId'   => $orderId,
            'timestamp' => now()->getTimestampMs(),
        ];
        $params['signature'] = $this->sign($params);

        $result = $this->timedAuthRequest('DELETE', '/fapi/v1/order', $params);

        if ($result) {
            Log::channel('trading')->info('ORDER_CANCELLED', [
                'symbol'   => $symbol,
                'order_id' => $orderId,
            ]);
        }

        return $result;
    }

    // ─── Private helpers ───────────────────────────────

    /**
     * Execute a public (unauthenticated) request and log latency to the binance channel.
     */
    private function timedPublicRequest(string $method, string $endpoint, array $params = []): ?array
    {
        $start = hrtime(true);
        try {
            $request = Http::timeout(10)->retry(2, 500);
            $response = match (strtoupper($method)) {
                'GET'  => $request->get($this->baseUrl.$endpoint, $params),
                default => $request->post($this->baseUrl.$endpoint, $params),
            };

            $ms = $this->elapsed($start);
            $this->logBinance($method, $endpoint, $ms, $response->status(), $response->successful());

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            $ms = $this->elapsed($start);
            $this->logBinanceError($method, $endpoint, $ms, $e->getMessage());

            return null;
        }
    }

    /**
     * Execute a signed (authenticated) request and log latency to the binance channel.
     */
    private function timedAuthRequest(string $method, string $endpoint, array $params = []): ?array
    {
        $start = hrtime(true);
        try {
            $request = Http::timeout(10)->withHeaders(['X-MBX-APIKEY' => $this->apiKey]);
            $response = match (strtoupper($method)) {
                'GET'    => $request->get($this->baseUrl.$endpoint, $params),
                'DELETE' => $request->delete($this->baseUrl.$endpoint, $params),
                default  => $request->post($this->baseUrl.$endpoint, $params),
            };

            $ms = $this->elapsed($start);
            $this->logBinance($method, $endpoint, $ms, $response->status(), $response->successful());

            if (! $response->successful()) {
                Log::channel('binance')->error("Binance {$method} {$endpoint} failed", [
                    'http_status' => $response->status(),
                    'body'        => $response->json(),
                    'latency_ms'  => $ms,
                ]);

                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            $ms = $this->elapsed($start);
            $this->logBinanceError($method, $endpoint, $ms, $e->getMessage());

            return null;
        }
    }

    private function elapsed(int $startNs): int
    {
        return (int) round((hrtime(true) - $startNs) / 1_000_000);
    }

    private function logBinance(string $method, string $endpoint, int $ms, int $httpStatus, bool $ok): void
    {
        $level = $ok ? 'debug' : 'warning';
        Log::channel('binance')->$level("Binance {$method} {$endpoint}", [
            'latency_ms'  => $ms,
            'http_status' => $httpStatus,
            'ok'          => $ok,
        ]);

        // Also flag slow Binance calls in the performance log (>500 ms is unusual)
        if ($ms > 500) {
            Log::channel('perf')->warning("SLOW BINANCE {$ms}ms — {$method} {$endpoint}", [
                'latency_ms'  => $ms,
                'http_status' => $httpStatus,
            ]);
        }
    }

    private function logBinanceError(string $method, string $endpoint, int $ms, string $error): void
    {
        Log::channel('binance')->error("Binance {$method} {$endpoint} EXCEPTION", [
            'latency_ms' => $ms,
            'error'      => $error,
        ]);
    }

    private function publicRequest(): PendingRequest
    {
        return Http::timeout(10)->retry(2, 500);
    }

    private function authenticatedRequest(): PendingRequest
    {
        return Http::timeout(10)
            ->withHeaders(['X-MBX-APIKEY' => $this->apiKey]);
    }

    private function sign(array $params): string
    {
        $queryString = http_build_query($params);

        return hash_hmac('sha256', $queryString, $this->apiSecret);
    }
}
