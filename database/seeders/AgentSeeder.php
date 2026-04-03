<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AgentSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            [
                'agent_id' => 'coordinator',
                'name' => 'Coordinator (Assistant Manager)',
                'role' => 'coordinator',
                'capabilities' => ['orchestration', 'state_monitoring', 'task_dispatch', 'firm_oversight'],
                // Coordinator uses: GET /api/firm/overview, GET /api/dashboard/overview,
                // POST /api/tasks, GET /api/tasks, PATCH /api/tasks/{uuid}
                'permissions' => [
                    'firm.overview.read', 'tasks.store', 'tasks.read', 'tasks.update',
                    'portfolio.read', 'data-quality.read', 'alerts.read', 'agents.heartbeat',
                ],
            ],
            [
                'agent_id' => 'quant-researcher',
                'name' => 'Quant Researcher',
                'role' => 'researcher',
                'capabilities' => ['alpha_discovery', 'statistical_analysis', 'data_collection'],
                'permissions' => ['research-findings.store', 'market-data.read', 'tasks.read', 'tasks.update'],
            ],
            [
                'agent_id' => 'backtester',
                'name' => 'Backtester',
                'role' => 'backtester',
                'capabilities' => ['strategy_validation', 'walk_forward_analysis', 'monte_carlo'],
                'permissions' => ['backtest-reports.store', 'research-findings.read', 'market-data.read', 'tasks.read', 'tasks.update'],
            ],
            [
                'agent_id' => 'risk-officer',
                'name' => 'Risk Officer (CRO)',
                'role' => 'risk',
                'capabilities' => ['risk_evaluation', 'portfolio_monitoring', 'circuit_breaker'],
                'permissions' => ['signals.read', 'alerts.store', 'market-data.read', 'tasks.read', 'tasks.update'],
            ],
            [
                'agent_id' => 'signal-engineer',
                'name' => 'Signal Engineer',
                'role' => 'signal',
                'capabilities' => ['signal_design', 'indicator_computation', 'filter_tuning'],
                'permissions' => ['signals.store', 'research-findings.read', 'market-data.read', 'tasks.read', 'tasks.update'],
            ],
            [
                'agent_id' => 'execution-engineer',
                'name' => 'Execution Engineer',
                'role' => 'execution',
                'capabilities' => ['order_placement', 'order_monitoring', 'position_management'],
                'permissions' => ['signals.read', 'execution-reports.store', 'vault.binance', 'market-data.read', 'tasks.read', 'tasks.update'],
            ],
            [
                'agent_id' => 'market-analyst',
                'name' => 'Market Analyst',
                'role' => 'analyst',
                'capabilities' => ['market_monitoring', 'anomaly_detection', 'regime_classification'],
                'permissions' => ['market-updates.store', 'alerts.store', 'market-data.read', 'tasks.read', 'tasks.update', 'portfolio.circuit-breaker'],
            ],
            [
                'agent_id' => 'algorithm-designer',
                'name' => 'Algorithm Designer',
                'role' => 'ml_engineer',
                'capabilities' => ['feature_engineering', 'model_training', 'hyperparameter_optimization', 'shap_explainability'],
                'permissions' => ['model-registry.store', 'features.batch', 'research-findings.read', 'tasks.read', 'tasks.update'],
            ],
            [
                'agent_id' => 'portfolio-manager',
                'name' => 'Portfolio Manager',
                'role' => 'portfolio',
                'capabilities' => ['capital_allocation', 'strategy_lifecycle', 'performance_reporting', 'tier_management'],
                'permissions' => ['portfolio.read', 'portfolio.strategies.update', 'portfolio.circuit-breaker', 'alerts.store', 'tasks.read', 'tasks.update'],
            ],
            [
                'agent_id' => 'data-quality-monitor',
                'name' => 'Data Quality Monitor',
                'role' => 'infrastructure',
                'capabilities' => ['data_validation', 'freshness_monitoring', 'anomaly_detection', 'pipeline_health'],
                'permissions' => ['market-data.read', 'data-quality.store', 'alerts.store', 'portfolio.circuit-breaker', 'tasks.read', 'tasks.update'],
            ],
            [
                'agent_id' => 'onchain-sentiment-analyst',
                'name' => 'On-Chain & Sentiment Analyst',
                'role' => 'onchain',
                'capabilities' => ['onchain_analysis', 'sentiment_scoring', 'whale_tracking', 'flow_analysis'],
                'permissions' => ['market.onchain.read', 'market.onchain.store', 'market.sentiment.store', 'research-findings.store', 'alerts.store', 'tasks.read', 'tasks.update'],
            ],
        ];

        foreach ($agents as $agentData) {
            $plainToken = Str::random(64);

            Agent::updateOrCreate(
                ['agent_id' => $agentData['agent_id']],
                [
                    'name' => $agentData['name'],
                    'role' => $agentData['role'],
                    'api_token' => hash('sha256', $plainToken),
                    'status' => 'offline',
                    'capabilities' => $agentData['capabilities'],
                    'permissions' => $agentData['permissions'],
                ],
            );

            $this->command->info("Agent: {$agentData['agent_id']} → Token: {$plainToken}");
        }

        $this->command->newLine();
        $this->command->warn('IMPORTANT: Save these tokens! They are hashed in the database and cannot be retrieved.');
        $this->command->warn('Set each token as MIDDLEWARE_API_KEY in the corresponding agent\'s .env file.');
    }
}
