<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\BacktestController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExecutionController;
use App\Http\Controllers\Api\FeatureStoreController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\MarketDataController;
use App\Http\Controllers\Api\ModelRegistryController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\ResearchController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\VaultController;
use App\Http\Controllers\Api\DataQualityController;
use App\Http\Controllers\Api\OnchainController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Armado Quant — API Routes
|--------------------------------------------------------------------------
| CEO: Brian  |  Manager: Claude  |  Employees: OpenClaw Agents
|
| All routes prefixed with /api automatically by bootstrap/app.php.
|--------------------------------------------------------------------------
*/

// ─── Public (for initial setup / health) ─────────────────
Route::get('/health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]));

// ─── Agent-Authenticated Routes ──────────────────────────
Route::middleware(AuthenticateAgent::class)->group(function () {

    // Agent self-management
    Route::post('/agents/heartbeat', [AgentController::class, 'heartbeat']);

    // System logs — queryable by agents and dashboard
    Route::get('/logs',         [LogController::class, 'index']);
    Route::get('/logs/summary', [LogController::class, 'summary']);
    Route::get('/logs/errors',  [LogController::class, 'errors']);

    // Trade Signals (Signal Engineer → Risk → Execution)
    Route::post('/signals', [WebhookController::class, 'receiveSignal'])->name('signals.receive');
    Route::get('/signals', [WebhookController::class, 'listSignals'])->name('signals.list');
    Route::patch('/signals/{uuid}', [WebhookController::class, 'updateSignal'])->name('signals.update');

    // Research Findings (Quant Researcher submits)
    Route::get('/research-findings', [ResearchController::class, 'index']);
    Route::post('/research-findings', [ResearchController::class, 'store']);
    Route::get('/research-findings/{uuid}', [ResearchController::class, 'show']);

    // Backtest Reports (Backtester submits)
    Route::get('/backtest-reports', [BacktestController::class, 'index']);
    Route::post('/backtest-reports', [BacktestController::class, 'store']);

    // Execution Reports (Execution Engineer submits)
    Route::get('/execution-reports', [ExecutionController::class, 'index']);
    Route::post('/execution-reports', [ExecutionController::class, 'store']);

    // Market Data (proxied from Binance)
    Route::get('/market-data/{symbol}/price', [MarketDataController::class, 'price']);
    Route::get('/market-data/{symbol}/klines', [MarketDataController::class, 'klines']);
    Route::get('/market-data/{symbol}/funding-rate', [MarketDataController::class, 'fundingRate']);
    Route::get('/market-data/{symbol}/open-interest', [MarketDataController::class, 'openInterest']);

    // Market Updates & Alerts (Market Analyst submits)
    Route::post('/market-updates', [MarketDataController::class, 'storeUpdate']);
    Route::get('/market-updates', [MarketDataController::class, 'latestUpdates']);
    Route::post('/alerts', [MarketDataController::class, 'storeAlert']);
    Route::get('/alerts', [MarketDataController::class, 'activeAlerts']);

    // Tasks (assigned by Manager/CEO, completed by agents)
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::patch('/tasks/{uuid}', [TaskController::class, 'update']);

    // Vault — agents can read their service credentials
    Route::get('/vault/{service}', [VaultController::class, 'getForService']);

    // Feature Store — pre-computed feature vectors (Algorithm Designer + Backtester)
    // One batch call returns everything. No follow-up calls needed.
    Route::post('/features/batch', [FeatureStoreController::class, 'batch']);

    // Model Registry — Algorithm Designer registers, Signal Engineer reads
    Route::get('/model-registry', [ModelRegistryController::class, 'index']);
    Route::post('/model-registry', [ModelRegistryController::class, 'store']);
    Route::get('/model-registry/{uuid}', [ModelRegistryController::class, 'show']);

    // Portfolio State — single source of truth for all agents
    Route::get('/portfolio/state', [PortfolioController::class, 'state']);
    Route::get('/portfolio/exposure', [PortfolioController::class, 'exposure']);
    Route::post('/portfolio/circuit-breaker', [PortfolioController::class, 'triggerCircuitBreaker']);
    Route::patch('/portfolio/strategies/{uuid}', [PortfolioController::class, 'updateStrategyPerformance']);
    Route::get('/portfolio/strategies', [PortfolioController::class, 'strategies']);

    // On-Chain & Sentiment (On-Chain Analyst reads/writes)
    Route::get('/market/onchain', [OnchainController::class, 'metrics']);
    Route::post('/market/onchain', [OnchainController::class, 'store']);
    Route::get('/market/sentiment', [OnchainController::class, 'sentiment']);
    Route::post('/market/sentiment', [OnchainController::class, 'storeSentiment']);

    // Data Quality (Data Quality Monitor reads/writes)
    Route::get('/data-quality', [DataQualityController::class, 'index']);
    Route::post('/data-quality', [DataQualityController::class, 'store']);

    // Coordinator — dashboard read + task creation (agent-authenticated)
    // Aliased as both /firm/overview and /dashboard/overview so coordinator AGENTS.md works either way.
    Route::get('/firm/overview', [DashboardController::class, 'overview']);
    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);
    Route::post('/tasks', [TaskController::class, 'store']);

    // Route aliases — agents reference these short names in their AGENTS.md files
    // Map to the canonical routes above for backwards compatibility
    Route::post('/market/store-update', [MarketDataController::class, 'storeUpdate']);   // alias for /market-updates
    Route::post('/market/store-alert', [MarketDataController::class, 'storeAlert']);     // alias for /alerts
    Route::get('/market/price', [MarketDataController::class, 'priceByQuery']);          // GET /market/price?symbol=BTCUSDT
    Route::get('/market/klines', [MarketDataController::class, 'klinesByQuery']);        // GET /market/klines?symbol=X&interval=Y
    Route::get('/market/funding-rate', [MarketDataController::class, 'fundingRateByQuery']); // GET /market/funding-rate?symbol=X

    // Binance account proxy (Execution Engineer needs account balance & open positions)
    Route::get('/binance/account', [MarketDataController::class, 'binanceAccount']);
    Route::get('/binance/positions', [MarketDataController::class, 'binancePositions']);
});

// ─── Manager / CEO Routes (session-authenticated) ───────
Route::middleware('auth:sanctum')->prefix('manager')->group(function () {

    // Dashboard overview
    Route::get('/dashboard', [DashboardController::class, 'overview']);

    // Agent registry
    Route::get('/agents', [AgentController::class, 'index']);
    Route::get('/agents/{agentId}', [AgentController::class, 'show']);

    // Task creation (Manager assigns work to agents)
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks', [TaskController::class, 'index']);

    // Research workflow (Manager approves/rejects)
    Route::patch('/research-findings/{uuid}/status', [ResearchController::class, 'updateStatus']);

    // Alert management
    Route::get('/alerts', [MarketDataController::class, 'activeAlerts']);
    Route::patch('/alerts/{uuid}/acknowledge', [MarketDataController::class, 'acknowledgeAlert']);

    // Model registry management (Manager approves models)
    Route::patch('/model-registry/{uuid}/status', [ModelRegistryController::class, 'updateStatus']);

    // Circuit breaker management (Manager/CEO resolve)
    Route::patch('/portfolio/circuit-breaker/{id}/resolve', [PortfolioController::class, 'resolveCircuitBreaker']);

    // Vault management (CEO stores keys)
    Route::post('/vault', [VaultController::class, 'store']);
    Route::get('/vault', [VaultController::class, 'listServices']);
});
