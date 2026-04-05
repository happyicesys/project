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

interface FirmStatus {
    agents: { total: number; online: number };
    tasks: { pending: number; in_progress: number; completed_today: number };
    signals: { pending: number; approved: number; executed: number; rejected: number };
    research: { submitted: number; approved_for_backtest: number; promoted: number };
    backtests: { pass: number; fail: number };
    executions_today: number;
    alerts: { unacknowledged: number; critical: number };
    latest_market: MarketUpdate | null;
    data_quality: { status: string; failing_checks: number };
    sentiment: { score: number; overall_bias: string; fear_greed_index: number } | null;
}

interface AlertRow {
    uuid: string;
    type: string;
    severity: string;
    description: string;
    symbol: string | null;
    created_at: string;
}

interface TaskRow {
    uuid: string;
    title: string;
    status: string;
    priority: string;
    assigned_to: string;
    created_at: string;
}

interface ResearchRow {
    uuid: string;
    signal_name: string;
    status: string;
    edge_metric: string;
    edge_value: number;
    p_value: number;
    created_at: string;
}

interface PageProps {
    firm_status: FirmStatus;
    agents: AgentRow[];
    recent_alerts: AlertRow[];
    active_tasks: TaskRow[];
    research_queue: ResearchRow[];
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
        case 'LOW':     return 'text-emerald-400';
        case 'MEDIUM':  return 'text-yellow-400';
        case 'HIGH':    return 'text-orange-400';
        case 'EXTREME': return 'text-red-400';
        default:        return 'text-slate-400';
    }
}

function statusColor(status: string): string {
    switch (status?.toUpperCase()) {
        case 'HEALTHY':  return 'text-emerald-400';
        case 'DEGRADED': return 'text-yellow-400';
        case 'CRITICAL': return 'text-red-400';
        default:         return 'text-slate-400';
    }
}

function regimeColor(regime: string): string {
    switch (regime?.toUpperCase()) {
        case 'BULL':     return 'text-emerald-400';
        case 'BEAR':     return 'text-red-400';
        case 'SIDEWAYS':
        case 'RANGING':  return 'text-yellow-400';
        case 'VOLATILE': return 'text-orange-400';
        default:         return 'text-slate-400';
    }
}

// ─── Sub-components ───────────────────────────────────────────────────────────

function Panel({ title, children, accent = false }: {
    title: string; children: React.ReactNode; accent?: boolean;
}) {
    return (
        <div className={`rounded-xl border p-4 flex flex-col gap-3 ${
            accent ? 'border-red-500/40 bg-red-950/20' : 'border-slate-700/60 bg-slate-900/60'
        }`}>
            <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-widest">{title}</h3>
            {children}
        </div>
    );
}

function Row({ label, value, valueClass = 'text-slate-300' }: {
    label: string; value: string | number; valueClass?: string;
}) {
    return (
        <div className="flex justify-between items-center text-sm">
            <span className="text-slate-500">{label}</span>
            <span className={`font-semibold tabular-nums ${valueClass}`}>{value}</span>
        </div>
    );
}

function AgentDot({ online }: { online: boolean }) {
    return (
        <span className={`inline-block h-2 w-2 rounded-full flex-shrink-0 mt-0.5 ${
            online ? 'bg-emerald-400 shadow-[0_0_6px_#34d399]' : 'bg-slate-600'
        }`} />
    );
}

// ─── Main Dashboard ───────────────────────────────────────────────────────────

export default function Dashboard({
    firm_status: initialFs,
    agents: initialAgents,
    recent_alerts: initialAlerts,
    active_tasks: initialTasks,
    research_queue: initialResearch,
    timestamp: initialTs,
}: PageProps) {
    const [fs, setFs] = useState<FirmStatus>(initialFs);
    const [agents, setAgents] = useState<AgentRow[]>(initialAgents);
    const [alerts, setAlerts] = useState<AlertRow[]>(initialAlerts);
    const [tasks, setTasks] = useState<TaskRow[]>(initialTasks);
    const [research, setResearch] = useState<ResearchRow[]>(initialResearch);
    const [lastFetch, setLastFetch] = useState<string>(new Date(initialTs).toLocaleTimeString());
    const [countdown, setCountdown] = useState(30);
    const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const countdownRef = useRef<ReturnType<typeof setInterval> | null>(null);

    async function refresh() {
        try {
            const res = await fetch('/dashboard/overview', { credentials: 'same-origin' });
            if (!res.ok) return;
            const data: PageProps = await res.json();
            setFs(data.firm_status);
            setAgents(data.agents);
            setAlerts(data.recent_alerts ?? []);
            setTasks(data.active_tasks ?? []);
            setResearch(data.research_queue ?? []);
            setLastFetch(new Date(data.timestamp).toLocaleTimeString());
        } catch {
            // silently ignore — page still shows last known state
        }
    }

    useEffect(() => {
        timerRef.current = setInterval(() => {
            setCountdown(30);
            refresh();
        }, 30_000);

        countdownRef.current = setInterval(() => {
            setCountdown(c => Math.max(0, c - 1));
        }, 1_000);

        return () => {
            if (timerRef.current) clearInterval(timerRef.current);
            if (countdownRef.current) clearInterval(countdownRef.current);
        };
    }, []);

    const mkt = fs.latest_market;
    const fleetHealthy = fs.agents.online === fs.agents.total;
    const hasCritical = fs.alerts.critical > 0;

    return (
        <>
            <Head title="Armado Quant — Dashboard" />

            {/* ── Sticky header ── */}
            <div className="flex items-center justify-between px-4 py-2.5 border-b border-slate-800 bg-slate-950/90 sticky top-0 z-10 backdrop-blur text-sm">
                <div className="flex items-center gap-3">
                    <span className="font-semibold text-slate-200 tracking-tight">ARMADO QUANT</span>
                    <span className="text-slate-700">|</span>
                    <span className={`font-medium ${fleetHealthy ? 'text-emerald-400' : 'text-red-400'}`}>
                        {fleetHealthy ? '● OPERATIONAL' : `● ${fs.agents.online}/${fs.agents.total} ONLINE`}
                    </span>
                    {hasCritical && (
                        <span className="font-medium text-red-400 animate-pulse">
                            ⚠ {fs.alerts.critical} CRITICAL
                        </span>
                    )}
                </div>
                <div className="flex items-center gap-2 text-xs text-slate-600">
                    <span>Updated {lastFetch}</span>
                    <button
                        onClick={() => { setCountdown(30); refresh(); }}
                        className="text-slate-500 hover:text-slate-300 transition-colors px-1.5 py-0.5 rounded border border-slate-800 hover:border-slate-600"
                    >
                        ↻ {countdown}s
                    </button>
                </div>
            </div>

            <div className="p-4 space-y-4 bg-slate-950 min-h-full">

                {/* ── Row 1: Pipeline metrics ── */}
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">

                    <Panel title="Fleet">
                        <div className="text-center">
                            <div className={`text-4xl font-bold tabular-nums ${fleetHealthy ? 'text-emerald-400' : 'text-red-400'}`}>
                                {fs.agents.online}<span className="text-slate-600 text-2xl">/{fs.agents.total}</span>
                            </div>
                            <div className="text-xs text-slate-600 mt-1">agents online</div>
                        </div>
                    </Panel>

                    <Panel title="Tasks">
                        <Row label="Pending"    value={fs.tasks.pending}    valueClass={fs.tasks.pending > 0 ? 'text-yellow-400' : 'text-slate-500'} />
                        <Row label="In progress" value={fs.tasks.in_progress} valueClass={fs.tasks.in_progress > 0 ? 'text-blue-400' : 'text-slate-500'} />
                        <Row label="Done today"  value={fs.tasks.completed_today} valueClass="text-emerald-400" />
                    </Panel>

                    <Panel title="Signals">
                        <Row label="Pending"  value={fs.signals.pending}  valueClass={fs.signals.pending > 0 ? 'text-yellow-400' : 'text-slate-500'} />
                        <Row label="Approved" value={fs.signals.approved} valueClass={fs.signals.approved > 0 ? 'text-emerald-400' : 'text-slate-500'} />
                        <Row label="Executed" value={fs.signals.executed} valueClass="text-slate-400" />
                        <Row label="Rejected" value={fs.signals.rejected} valueClass={fs.signals.rejected > 0 ? 'text-red-400' : 'text-slate-500'} />
                    </Panel>

                    <Panel title="Research">
                        <Row label="Submitted"   value={fs.research.submitted}             valueClass={fs.research.submitted > 0 ? 'text-yellow-400' : 'text-slate-500'} />
                        <Row label="In backtest" value={fs.research.approved_for_backtest} valueClass={fs.research.approved_for_backtest > 0 ? 'text-blue-400' : 'text-slate-500'} />
                        <Row label="Promoted"    value={fs.research.promoted}              valueClass={fs.research.promoted > 0 ? 'text-emerald-400' : 'text-slate-500'} />
                    </Panel>

                    <Panel title="Backtests">
                        <Row label="Pass" value={fs.backtests.pass} valueClass={fs.backtests.pass > 0 ? 'text-emerald-400' : 'text-slate-500'} />
                        <Row label="Fail" value={fs.backtests.fail} valueClass={fs.backtests.fail > 0 ? 'text-red-400' : 'text-slate-500'} />
                        <Row label="Executions" value={fs.executions_today} valueClass="text-slate-400" />
                    </Panel>

                    <Panel title="Alerts" accent={hasCritical}>
                        <Row label="Critical"  value={fs.alerts.critical}       valueClass={hasCritical ? 'text-red-400' : 'text-slate-500'} />
                        <Row label="Unacked"   value={fs.alerts.unacknowledged} valueClass={fs.alerts.unacknowledged > 0 ? 'text-orange-400' : 'text-slate-500'} />
                    </Panel>
                </div>

                {/* ── Row 2: Market + Data Quality ── */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">

                    <Panel title="Market — BTC">
                        {mkt ? (
                            <div className="flex flex-col gap-3">
                                <div className="flex items-end gap-3">
                                    <span className="text-4xl font-bold tabular-nums text-white">
                                        ${Number(mkt.btc_price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </span>
                                    <span className={`text-base font-semibold mb-1 ${Number(mkt.btc_24h_change_pct) >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
                                        {Number(mkt.btc_24h_change_pct) >= 0 ? '+' : ''}{Number(mkt.btc_24h_change_pct).toFixed(2)}%
                                    </span>
                                </div>
                                <div className="flex gap-5 text-sm flex-wrap">
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
                                            <span className={`font-semibold ${Number(mkt.funding_rate_btc) >= 0 ? 'text-yellow-400' : 'text-blue-400'}`}>
                                                {(Number(mkt.funding_rate_btc) * 100).toFixed(4)}%
                                            </span>
                                        </div>
                                    )}
                                </div>
                                <span className="text-xs text-slate-600">Last update {ago(mkt.created_at)}</span>
                            </div>
                        ) : (
                            <span className="text-sm text-slate-600">Waiting for market analyst first report…</span>
                        )}
                    </Panel>

                    <Panel title="Data Pipeline">
                        <div className="flex items-center gap-3">
                            <span className={`text-3xl font-bold ${statusColor(fs.data_quality.status)}`}>
                                {fs.data_quality.status || 'UNKNOWN'}
                            </span>
                            {fs.data_quality.failing_checks > 0 && (
                                <span className="text-xs text-red-300 bg-red-950 border border-red-700/40 rounded-full px-2 py-0.5">
                                    {fs.data_quality.failing_checks} failing
                                </span>
                            )}
                        </div>
                        {fs.sentiment ? (
                            <div className="flex gap-5 text-sm">
                                <div>
                                    <span className="text-slate-500">Sentiment </span>
                                    <span className="text-slate-300 font-semibold">{fs.sentiment.overall_bias}</span>
                                </div>
                                <div>
                                    <span className="text-slate-500">Fear/Greed </span>
                                    <span className={`font-semibold ${
                                        fs.sentiment.fear_greed_index > 60 ? 'text-emerald-400' :
                                        fs.sentiment.fear_greed_index < 40 ? 'text-red-400' : 'text-yellow-400'
                                    }`}>{fs.sentiment.fear_greed_index}</span>
                                </div>
                            </div>
                        ) : (
                            <span className="text-xs text-slate-600">No sentiment data yet</span>
                        )}
                        <Row label="Executions today" value={fs.executions_today} valueClass="text-slate-400" />
                    </Panel>
                </div>

                {/* ── Row 3: Agent fleet ── */}
                <Panel title={`Agent Fleet — ${fs.agents.online}/${fs.agents.total} Online`}>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2">
                        {agents.map(agent => (
                            <div
                                key={agent.agent_id}
                                className={`flex items-start gap-2.5 rounded-lg px-3 py-2.5 border text-sm ${
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
                                    <span className="text-xs text-slate-600">{agent.role}</span>
                                    <span className="text-xs text-slate-700 mt-0.5">
                                        ♥ {agent.last_heartbeat_at ? ago(agent.last_heartbeat_at) : 'never'}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                </Panel>

                {/* ── Row 4: Detail panels ── */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-3">

                    {/* Active Alerts */}
                    <Panel title={`Active Alerts (${alerts.length})`} accent={fs.alerts.critical > 0}>
                        {alerts.length === 0 ? (
                            <span className="text-xs text-slate-600">No unacknowledged alerts</span>
                        ) : (
                            <div className="flex flex-col gap-2">
                                {alerts.map(a => (
                                    <div key={a.uuid} className="flex flex-col gap-0.5 border-b border-slate-800 pb-2 last:border-0 last:pb-0">
                                        <div className="flex items-center gap-2">
                                            <span className={`text-xs font-semibold ${
                                                a.severity === 'CRITICAL' ? 'text-red-400' :
                                                a.severity === 'WARNING'  ? 'text-yellow-400' : 'text-slate-400'
                                            }`}>{a.severity}</span>
                                            <span className="text-xs text-slate-500 font-mono">{a.type}</span>
                                            {a.symbol && <span className="text-xs text-slate-600">{a.symbol}</span>}
                                            <span className="text-xs text-slate-700 ml-auto">{ago(a.created_at)}</span>
                                        </div>
                                        <p className="text-xs text-slate-400 leading-relaxed line-clamp-2">{a.description}</p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Panel>

                    {/* Active Tasks */}
                    <Panel title={`Active Tasks (${tasks.length})`}>
                        {tasks.length === 0 ? (
                            <span className="text-xs text-slate-600">No active tasks</span>
                        ) : (
                            <div className="flex flex-col gap-2">
                                {tasks.map(t => (
                                    <div key={t.uuid} className="flex flex-col gap-0.5 border-b border-slate-800 pb-2 last:border-0 last:pb-0">
                                        <div className="flex items-center gap-2">
                                            <span className={`text-xs font-semibold ${
                                                t.status === 'in_progress' ? 'text-blue-400' : 'text-yellow-400'
                                            }`}>{t.status === 'in_progress' ? 'RUNNING' : 'PENDING'}</span>
                                            <span className={`text-xs ${
                                                t.priority === 'critical' ? 'text-red-400' :
                                                t.priority === 'high'     ? 'text-orange-400' : 'text-slate-500'
                                            }`}>{t.priority.toUpperCase()}</span>
                                            <span className="text-xs text-slate-600 ml-auto">{ago(t.created_at)}</span>
                                        </div>
                                        <p className="text-xs text-slate-300 truncate">{t.title}</p>
                                        <span className="text-xs text-slate-600">→ {t.assigned_to}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Panel>

                    {/* Research Queue */}
                    <Panel title={`Research Queue (${research.length})`}>
                        {research.length === 0 ? (
                            <span className="text-xs text-slate-600">No findings awaiting backtest</span>
                        ) : (
                            <div className="flex flex-col gap-2">
                                {research.map(r => (
                                    <div key={r.uuid} className="flex flex-col gap-0.5 border-b border-slate-800 pb-2 last:border-0 last:pb-0">
                                        <div className="flex items-center gap-2">
                                            <span className={`text-xs font-semibold ${
                                                r.status === 'in_backtest'          ? 'text-blue-400' :
                                                r.status === 'approved_for_backtest'? 'text-yellow-400' : 'text-slate-400'
                                            }`}>{r.status.replace(/_/g, ' ').toUpperCase()}</span>
                                            <span className="text-xs text-slate-700 ml-auto">{ago(r.created_at)}</span>
                                        </div>
                                        <p className="text-xs text-slate-300 font-mono truncate">{r.signal_name}</p>
                                        <span className="text-xs text-slate-600">
                                            {r.edge_metric}: {r.edge_value?.toFixed(3)} · p={r.p_value?.toFixed(3)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Panel>

                </div>

            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
