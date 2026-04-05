import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { dashboard } from '@/routes';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: dashboard() }];

// ─── Types ────────────────────────────────────────────────────────────────────

interface AgentRow {
    agent_id: string;
    name: string;
    role: string;
    status: string;
    is_online: boolean;
    last_heartbeat_at: string | null;
}

interface MarketUpdate {
    btc_price: number;
    btc_24h_change_pct: number;
    market_regime: string;
    risk_level: string;
    funding_rate_btc: number | null;
    created_at: string;
}

interface Overview {
    firm_status: {
        agents: { total: number; online: number };
        tasks: { pending: number; in_progress: number; completed_today: number };
        signals: { pending: number; approved: number; executed: number; rejected: number };
        research: { submitted: number; approved_for_backtest: number; promoted: number };
        backtests: { pass: number; fail: number };
        executions_today: number;
        alerts: { unacknowledged: number; critical: number };
        latest_market: MarketUpdate | null;
        data_quality: { status: string; failing_checks: number };
        sentiment: { score: number; bias: string; fear_greed_index: number } | null;
    };
    timestamp: string;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function ago(iso: string | null): string {
    if (!iso) return 'never';
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (diff < 60) return `${diff}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    return `${Math.floor(diff / 3600)}h ago`;
}

function riskColor(level: string): string {
    switch (level?.toUpperCase()) {
        case 'LOW': return 'text-emerald-400';
        case 'MEDIUM': return 'text-yellow-400';
        case 'HIGH': return 'text-orange-400';
        case 'EXTREME': return 'text-red-400';
        default: return 'text-slate-400';
    }
}

function statusColor(status: string): string {
    switch (status?.toUpperCase()) {
        case 'HEALTHY': return 'text-emerald-400';
        case 'DEGRADED': return 'text-yellow-400';
        case 'CRITICAL': return 'text-red-400';
        default: return 'text-slate-400';
    }
}

function regimeColor(regime: string): string {
    switch (regime?.toUpperCase()) {
        case 'BULL': return 'text-emerald-400';
        case 'BEAR': return 'text-red-400';
        case 'SIDEWAYS':
        case 'RANGING': return 'text-yellow-400';
        case 'VOLATILE': return 'text-orange-400';
        default: return 'text-slate-400';
    }
}

// ─── Sub-components ───────────────────────────────────────────────────────────

function Stat({ label, value, sub, valueClass = 'text-white' }: {
    label: string; value: string | number; sub?: string; valueClass?: string;
}) {
    return (
        <div className="flex flex-col gap-0.5">
            <span className="text-xs text-slate-500 uppercase tracking-wider">{label}</span>
            <span className={`text-2xl font-bold tabular-nums ${valueClass}`}>{value}</span>
            {sub && <span className="text-xs text-slate-500">{sub}</span>}
        </div>
    );
}

function Panel({ title, children, accent = false }: {
    title: string; children: React.ReactNode; accent?: boolean;
}) {
    return (
        <div className={`rounded-xl border p-4 flex flex-col gap-4 ${accent
            ? 'border-red-500/40 bg-red-950/20'
            : 'border-slate-700/60 bg-slate-900/60'}`}>
            <h3 className="text-xs font-semibold text-slate-400 uppercase tracking-widest">{title}</h3>
            {children}
        </div>
    );
}

function Pill({ label, color }: { label: string; color: string }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${color}`}>
            {label}
        </span>
    );
}

function AgentDot({ online }: { online: boolean }) {
    return (
        <span className={`inline-block h-2 w-2 rounded-full flex-shrink-0 ${online
            ? 'bg-emerald-400 shadow-[0_0_6px_#34d399]'
            : 'bg-slate-600'}`} />
    );
}

// ─── Main Dashboard ───────────────────────────────────────────────────────────

export default function Dashboard() {
    const [overview, setOverview] = useState<Overview | null>(null);
    const [agents, setAgents] = useState<AgentRow[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [countdown, setCountdown] = useState(30);
    const [lastFetch, setLastFetch] = useState<string>('');
    const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const countdownRef = useRef<ReturnType<typeof setInterval> | null>(null);

    async function fetchData() {
        try {
            const [ovRes, agRes] = await Promise.all([
                fetch('/api/manager/dashboard', { credentials: 'same-origin' }),
                fetch('/api/manager/agents', { credentials: 'same-origin' }),
            ]);
            if (!ovRes.ok) throw new Error(`Overview ${ovRes.status}`);
            const ovJson = await ovRes.json();
            setOverview(ovJson);
            if (agRes.ok) {
                const agJson = await agRes.json();
                setAgents(agJson.agents ?? []);
            }
            setLastFetch(new Date().toLocaleTimeString());
            setError(null);
        } catch (e: unknown) {
            setError(e instanceof Error ? e.message : 'Fetch failed');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        fetchData();

        timerRef.current = setInterval(() => {
            setCountdown(30);
            fetchData();
        }, 30_000);

        countdownRef.current = setInterval(() => {
            setCountdown(c => Math.max(0, c - 1));
        }, 1_000);

        return () => {
            if (timerRef.current) clearInterval(timerRef.current);
            if (countdownRef.current) clearInterval(countdownRef.current);
        };
    }, []);

    if (loading) return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full items-center justify-center text-slate-500 text-sm">
                Loading firm data…
            </div>
        </>
    );

    if (error) return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full items-center justify-center text-red-400 text-sm">
                Error: {error}
            </div>
        </>
    );

    const fs = overview!.firm_status;
    const mkt = fs.latest_market;
    const agentsOnline = fs.agents.online;
    const agentsTotal = fs.agents.total;
    const fleetHealthy = agentsOnline === agentsTotal;
    const hasCriticalAlerts = fs.alerts.critical > 0;

    return (
        <>
            <Head title="Armado Quant — Dashboard" />

            {/* ── Header bar ── */}
            <div className="flex items-center justify-between px-4 py-3 border-b border-slate-800 bg-slate-950/80 sticky top-0 z-10 backdrop-blur">
                <div className="flex items-center gap-3">
                    <span className="text-sm font-semibold text-slate-200 tracking-tight">ARMADO QUANT</span>
                    <span className="text-xs text-slate-600">|</span>
                    <span className={`text-xs font-medium ${fleetHealthy ? 'text-emerald-400' : 'text-red-400'}`}>
                        {fleetHealthy ? '● OPERATIONAL' : `● ${agentsOnline}/${agentsTotal} AGENTS ONLINE`}
                    </span>
                    {hasCriticalAlerts && (
                        <span className="text-xs font-medium text-red-400 animate-pulse">
                            ⚠ {fs.alerts.critical} CRITICAL ALERT{fs.alerts.critical > 1 ? 'S' : ''}
                        </span>
                    )}
                </div>
                <div className="flex items-center gap-3 text-xs text-slate-600">
                    {lastFetch && <span>Updated {lastFetch}</span>}
                    <button
                        onClick={() => { setCountdown(30); fetchData(); }}
                        className="text-slate-500 hover:text-slate-300 transition-colors"
                    >
                        ↻ {countdown}s
                    </button>
                </div>
            </div>

            <div className="p-4 space-y-4 bg-slate-950 min-h-full text-slate-300">

                {/* ── Row 1: Key metrics ── */}
                <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <Panel title="Fleet">
                        <Stat
                            label="Online"
                            value={`${agentsOnline}/${agentsTotal}`}
                            valueClass={fleetHealthy ? 'text-emerald-400' : 'text-red-400'}
                        />
                    </Panel>

                    <Panel title="Tasks">
                        <div className="flex flex-col gap-1 text-sm">
                            <div className="flex justify-between">
                                <span className="text-slate-500">Pending</span>
                                <span className={fs.tasks.pending > 0 ? 'text-yellow-400 font-semibold' : 'text-slate-400'}>{fs.tasks.pending}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">In progress</span>
                                <span className="text-blue-400 font-semibold">{fs.tasks.in_progress}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Done today</span>
                                <span className="text-emerald-400">{fs.tasks.completed_today}</span>
                            </div>
                        </div>
                    </Panel>

                    <Panel title="Signals">
                        <div className="flex flex-col gap-1 text-sm">
                            <div className="flex justify-between">
                                <span className="text-slate-500">Pending</span>
                                <span className={fs.signals.pending > 0 ? 'text-yellow-400 font-semibold' : 'text-slate-400'}>{fs.signals.pending}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Approved</span>
                                <span className={fs.signals.approved > 0 ? 'text-emerald-400 font-semibold' : 'text-slate-400'}>{fs.signals.approved}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Executed</span>
                                <span className="text-slate-400">{fs.signals.executed}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Rejected</span>
                                <span className={fs.signals.rejected > 0 ? 'text-red-400' : 'text-slate-400'}>{fs.signals.rejected}</span>
                            </div>
                        </div>
                    </Panel>

                    <Panel title="Research">
                        <div className="flex flex-col gap-1 text-sm">
                            <div className="flex justify-between">
                                <span className="text-slate-500">Submitted</span>
                                <span className={fs.research.submitted > 0 ? 'text-yellow-400 font-semibold' : 'text-slate-400'}>{fs.research.submitted}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">In backtest</span>
                                <span className={fs.research.approved_for_backtest > 0 ? 'text-blue-400' : 'text-slate-400'}>{fs.research.approved_for_backtest}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Promoted</span>
                                <span className={fs.research.promoted > 0 ? 'text-emerald-400' : 'text-slate-400'}>{fs.research.promoted}</span>
                            </div>
                        </div>
                    </Panel>

                    <Panel title="Backtests">
                        <div className="flex flex-col gap-1 text-sm">
                            <div className="flex justify-between">
                                <span className="text-slate-500">Pass</span>
                                <span className={fs.backtests.pass > 0 ? 'text-emerald-400 font-semibold' : 'text-slate-400'}>{fs.backtests.pass}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Fail</span>
                                <span className={fs.backtests.fail > 0 ? 'text-red-400' : 'text-slate-400'}>{fs.backtests.fail}</span>
                            </div>
                        </div>
                    </Panel>

                    <Panel title="Alerts" accent={hasCriticalAlerts}>
                        <div className="flex flex-col gap-1 text-sm">
                            <div className="flex justify-between">
                                <span className="text-slate-500">Critical</span>
                                <span className={fs.alerts.critical > 0 ? 'text-red-400 font-bold' : 'text-slate-400'}>{fs.alerts.critical}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Unacked</span>
                                <span className={fs.alerts.unacknowledged > 0 ? 'text-orange-400' : 'text-slate-400'}>{fs.alerts.unacknowledged}</span>
                            </div>
                        </div>
                    </Panel>
                </div>

                {/* ── Row 2: Market + Data Quality ── */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <Panel title="Market">
                        {mkt ? (
                            <div className="flex flex-col gap-3">
                                <div className="flex items-end gap-3">
                                    <span className="text-3xl font-bold tabular-nums text-white">
                                        ${Number(mkt.btc_price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </span>
                                    <span className={`text-sm font-semibold mb-1 ${mkt.btc_24h_change_pct >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
                                        {mkt.btc_24h_change_pct >= 0 ? '+' : ''}{Number(mkt.btc_24h_change_pct).toFixed(2)}%
                                    </span>
                                </div>
                                <div className="flex gap-4 text-sm flex-wrap">
                                    <div>
                                        <span className="text-slate-500">Regime </span>
                                        <span className={`font-semibold ${regimeColor(mkt.market_regime)}`}>{mkt.market_regime}</span>
                                    </div>
                                    <div>
                                        <span className="text-slate-500">Risk </span>
                                        <span className={`font-semibold ${riskColor(mkt.risk_level)}`}>{mkt.risk_level}</span>
                                    </div>
                                    {mkt.funding_rate_btc != null && (
                                        <div>
                                            <span className="text-slate-500">Funding </span>
                                            <span className={`font-semibold ${mkt.funding_rate_btc >= 0 ? 'text-yellow-400' : 'text-blue-400'}`}>
                                                {(mkt.funding_rate_btc * 100).toFixed(4)}%
                                            </span>
                                        </div>
                                    )}
                                </div>
                                <span className="text-xs text-slate-600">Updated {ago(mkt.created_at)}</span>
                            </div>
                        ) : (
                            <span className="text-sm text-slate-600">No market update yet</span>
                        )}
                    </Panel>

                    <Panel title="Data Pipeline">
                        <div className="flex flex-col gap-3">
                            <div className="flex items-center gap-3">
                                <span className={`text-2xl font-bold ${statusColor(fs.data_quality.status)}`}>
                                    {fs.data_quality.status || 'UNKNOWN'}
                                </span>
                                {fs.data_quality.failing_checks > 0 && (
                                    <Pill
                                        label={`${fs.data_quality.failing_checks} failing check${fs.data_quality.failing_checks > 1 ? 's' : ''}`}
                                        color="bg-red-950 text-red-300 ring-red-500/30"
                                    />
                                )}
                            </div>
                            <div className="flex gap-4 text-sm">
                                <div>
                                    <span className="text-slate-500">Executions today </span>
                                    <span className="text-slate-300 font-semibold">{fs.executions_today}</span>
                                </div>
                            </div>
                            {fs.sentiment && (
                                <div className="flex gap-4 text-sm">
                                    <div>
                                        <span className="text-slate-500">Sentiment </span>
                                        <span className="text-slate-300 font-semibold">{fs.sentiment.bias}</span>
                                    </div>
                                    <div>
                                        <span className="text-slate-500">Fear/Greed </span>
                                        <span className={`font-semibold ${
                                            fs.sentiment.fear_greed_index > 60 ? 'text-emerald-400' :
                                            fs.sentiment.fear_greed_index < 40 ? 'text-red-400' : 'text-yellow-400'
                                        }`}>{fs.sentiment.fear_greed_index}</span>
                                    </div>
                                </div>
                            )}
                        </div>
                    </Panel>
                </div>

                {/* ── Row 3: Agent fleet ── */}
                <Panel title={`Agent Fleet — ${agentsOnline}/${agentsTotal} Online`}>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2">
                        {agents.map(agent => (
                            <div
                                key={agent.agent_id}
                                className={`flex items-center gap-2 rounded-lg px-3 py-2 text-sm border ${
                                    agent.is_online
                                        ? 'border-emerald-800/40 bg-emerald-950/20'
                                        : 'border-slate-800 bg-slate-900/40'
                                }`}
                            >
                                <AgentDot online={agent.is_online} />
                                <div className="flex flex-col min-w-0">
                                    <span className={`font-medium truncate ${agent.is_online ? 'text-slate-200' : 'text-slate-500'}`}>
                                        {agent.name || agent.agent_id}
                                    </span>
                                    <span className="text-xs text-slate-600 truncate">
                                        {agent.last_heartbeat_at ? ago(agent.last_heartbeat_at) : 'never'}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                </Panel>

            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
