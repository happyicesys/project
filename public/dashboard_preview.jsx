import { useState, useEffect } from "react";
import { AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";

// ── Mock data (mirrors what the real Laravel API returns) ──────────────────
const MOCK_AGENTS = [
  { agent_id:"quant-researcher",   name:"Quant Researcher",   role:"researcher", status:"online",  task:"Funding Rate Z-score MR",        done:5,  pending:2, daily:3  },
  { agent_id:"algorithm-designer", name:"Algorithm Designer", role:"ml_engineer",status:"online",  task:"Training LightGBM BTC 1h",        done:3,  pending:1, daily:1  },
  { agent_id:"backtester",         name:"Backtester",         role:"backtester", status:"working", task:"Walk-forward BTCUSDT MR",         done:4,  pending:3, daily:2  },
  { agent_id:"risk-officer",       name:"Risk Officer (CRO)", role:"risk",       status:"online",  task:"Monitoring portfolio exposure",    done:1,  pending:0, daily:8  },
  { agent_id:"signal-engineer",    name:"Signal Engineer",    role:"signal",     status:"online",  task:"Building entry filter ETHUSDT",   done:5,  pending:2, daily:3  },
  { agent_id:"execution-engineer", name:"Execution Engineer", role:"execution",  status:"online",  task:"Monitoring 2 open positions",     done:12, pending:0, daily:4  },
  { agent_id:"market-analyst",     name:"Market Analyst",     role:"analyst",    status:"online",  task:"EU session monitoring",           done:2,  pending:1, daily:14 },
  { agent_id:"portfolio-manager",  name:"Portfolio Manager",  role:"portfolio",  status:"online",  task:"Hourly assessment (idle)",        done:3,  pending:0, daily:1  },
];

const MOCK_SIGNALS = [
  { uuid:"s1", symbol:"BTCUSDT", direction:"LONG",  status:"EXECUTED",         risk_percentage:"0.80", entry_price:"82100" },
  { uuid:"s2", symbol:"ETHUSDT", direction:"SHORT", status:"APPROVED",         risk_percentage:"0.60", entry_price:"1842"  },
  { uuid:"s3", symbol:"SOLUSDT", direction:"LONG",  status:"PENDING",          risk_percentage:"0.50", entry_price:"142.3" },
  { uuid:"s4", symbol:"BTCUSDT", direction:"SHORT", status:"REJECTED_BY_RISK", risk_percentage:"1.20", entry_price:"83500" },
];

const MOCK_ACTIVITY = [
  { id:1,  time:"15:44", actor:"signal-engineer",    level:"info", text:"Submitted SOLUSDT LONG signal"           },
  { id:2,  time:"15:38", actor:"algorithm-designer", level:"succ", text:"LightGBM BTC 1h OOS Sharpe 1.42"         },
  { id:3,  time:"15:22", actor:"risk-officer",        level:"succ", text:"ETHUSDT SHORT approved"                  },
  { id:4,  time:"15:01", actor:"market-analyst",     level:"crit", text:"CRITICAL: BTC OI +12% in 1h"             },
  { id:5,  time:"14:55", actor:"backtester",          level:"succ", text:"BTCUSDT MR: PASS (Sharpe 1.38)"          },
  { id:6,  time:"14:40", actor:"risk-officer",        level:"warn", text:"BTCUSDT SHORT rejected: risk 1.2% > 1%" },
  { id:7,  time:"14:22", actor:"execution-engineer", level:"succ", text:"BTCUSDT LONG FILLED @ 82100 (0.8bps)"    },
  { id:8,  time:"14:10", actor:"quant-researcher",   level:"info", text:"Funding Rate Z-score MR: p=0.018"        },
  { id:9,  time:"13:55", actor:"portfolio-manager",  level:"info", text:"Deployed 18.4% | Sharpe 7d: 1.21"        },
  { id:10, time:"13:40", actor:"market-analyst",     level:"info", text:"Regime: TRENDING_UP | Risk: MEDIUM"      },
];

const MOCK_PNL = [
  {d:"Mar 27",v:0},{d:"Mar 28",v:0.8},{d:"Mar 29",v:1.4},
  {d:"Mar 30",v:0.9},{d:"Mar 31",v:2.1},{d:"Apr 1",v:3.4},{d:"Apr 2",v:4.2},
];

const MOCK_STRATEGIES = [
  { name:"BTC Funding Rate MR", tier:3, alloc:8.0,  sharpe:1.42, pnl:3.2,  status:"live"  },
  { name:"ETH Mean Reversion",  tier:2, alloc:5.0,  sharpe:1.18, pnl:1.8,  status:"live"  },
  { name:"SOL Momentum",        tier:1, alloc:0.0,  sharpe:0.94, pnl:0.0,  status:"paper" },
];

const MOCK_PORTFOLIO = { equity:10000, deployed:18.4, dailyPnl:1.24, weeklyPnl:4.2, openPos:2 };
const MOCK_MARKET    = { regime:"TRENDING_UP", risk:"MEDIUM", btc:83420, btcChg:2.14, funding:0.0042 };
const MOCK_STATS     = { agentsOnline:7, agentsTotal:8, tasksToday:22, inProgress:3, approved:1, executed:4, rejected:3, btPass:5, alerts:3, critAlerts:1 };

// ── Helpers ──────────────────────────────────────────────────────────────────
const sc = s => ({ online:"#48bb78", working:"#ed8936", offline:"#718096", error:"#fc8181" }[s]||"#718096");
const dc = d => d==="LONG"?"#48bb78":"#fc8181";
const rc = r => ({ TRENDING_UP:"#48bb78",TRENDING_DOWN:"#fc8181",RANGING:"#63b3ed",VOLATILE:"#ed8936",RISK_OFF:"#b794f4" }[r]||"#718096");
const lc = l => ({ succ:"#48bb78", warn:"#ed8936", crit:"#fc8181", info:"#63b3ed" }[l]||"#a0aec0");
const tc = t => (["#718096","#b794f4","#63b3ed","#48bb78","#ffd700"])[t]||"#718096";
const tl = t => (["Candidate","Paper","Small Live","Scaling","Core"])[t]||"—";
const f2 = v => Number(v).toFixed(2);

const B = ({c="#718096",children}) => (
  <span style={{background:c+"22",color:c,border:`1px solid ${c}44`,padding:"1px 6px",borderRadius:3,fontSize:9,fontWeight:700,letterSpacing:"0.05em"}}>{children}</span>
);

const roleIcon = { researcher:"🔬",ml_engineer:"🤖",backtester:"📊",risk:"🛡️",signal:"⚡",execution:"⚙️",analyst:"📡",portfolio:"💼" };

// ── Components ───────────────────────────────────────────────────────────────
function AgentCard({a}) {
  const pulse = a.status==="working";
  return (
    <div style={{background:"#161929",border:`1px solid ${sc(a.status)}33`,borderRadius:6,padding:"9px 11px"}}>
      <div style={{display:"flex",alignItems:"center",justifyContent:"space-between",marginBottom:5}}>
        <div style={{display:"flex",alignItems:"center",gap:5}}>
          <span style={{width:6,height:6,borderRadius:"50%",background:sc(a.status),display:"inline-block",
            boxShadow:pulse?`0 0 6px ${sc(a.status)}`:undefined}} />
          <span style={{color:"#e2e8f0",fontWeight:700,fontSize:10}}>{roleIcon[a.role]} {a.name}</span>
        </div>
        <B c={sc(a.status)}>{a.status.toUpperCase()}</B>
      </div>
      <div style={{color:"#718096",fontSize:9,marginBottom:7,whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis"}}>{a.task}</div>
      <div style={{display:"flex",gap:10}}>
        <span style={{color:"#48bb78",fontSize:9}}>✓ {a.done} done</span>
        <span style={{color:"#718096",fontSize:9}}>◷ {a.pending} pending</span>
        <span style={{color:"#63b3ed",fontSize:9}}>↻ {a.daily}/day</span>
      </div>
    </div>
  );
}

function Pipeline({signals}) {
  const stages = [
    {k:"PENDING",c:"#ed8936",label:"Pending Risk"},
    {k:"APPROVED",c:"#48bb78",label:"Approved"},
    {k:"EXECUTED",c:"#4299e1",label:"Executed"},
    {k:"REJECTED_BY_RISK",c:"#fc8181",label:"Rejected"},
  ];
  return (
    <div style={{display:"grid",gridTemplateColumns:"repeat(4,1fr)",gap:7}}>
      {stages.map(({k,c,label}) => {
        const items = signals.filter(s=>s.status===k);
        return (
          <div key={k} style={{background:"#161929",border:`1px solid ${c}33`,borderRadius:6,padding:"8px 9px"}}>
            <div style={{color:c,fontWeight:700,fontSize:9,marginBottom:5,display:"flex",justifyContent:"space-between"}}>
              <span>{label}</span><span style={{background:c+"22",padding:"0 5px",borderRadius:8}}>{items.length}</span>
            </div>
            {items.length===0
              ? <div style={{color:"#2d3748",fontSize:9}}>—</div>
              : items.map(s=>(
                <div key={s.uuid} style={{background:"#0d0f14",borderRadius:4,padding:"5px 7px",marginBottom:3}}>
                  <div style={{display:"flex",justifyContent:"space-between",marginBottom:1}}>
                    <span style={{color:"#e2e8f0",fontWeight:700,fontSize:10}}>{s.symbol}</span>
                    <span style={{color:dc(s.direction),fontSize:9,fontWeight:700}}>{s.direction}</span>
                  </div>
                  <div style={{color:"#4a5568",fontSize:9}}>Risk {s.risk_percentage}% · ${s.entry_price}</div>
                </div>
              ))
            }
          </div>
        );
      })}
    </div>
  );
}

function Feed({items}) {
  return (
    <div style={{background:"#161929",border:"1px solid #2d3748",borderRadius:6,padding:"9px 11px",height:"100%",overflowY:"auto"}}>
      <div style={{color:"#718096",fontWeight:700,fontSize:9,marginBottom:7,letterSpacing:"0.08em"}}>◉ LIVE ACTIVITY</div>
      {items.map(item=>(
        <div key={item.id} style={{display:"flex",gap:6,marginBottom:6,alignItems:"flex-start"}}>
          <span style={{color:"#4a5568",fontSize:9,flexShrink:0,marginTop:1,width:32}}>{item.time}</span>
          <span style={{width:5,height:5,borderRadius:"50%",background:lc(item.level),flexShrink:0,marginTop:3}} />
          <div style={{fontSize:9}}>
            <span style={{color:"#4a5568"}}>{item.actor} · </span>
            <span style={{color:"#cbd5e0"}}>{item.text}</span>
          </div>
        </div>
      ))}
    </div>
  );
}

// ── Main Dashboard ────────────────────────────────────────────────────────────
export default function Dashboard() {
  const [tick, setTick] = useState(0);
  useEffect(() => { const id = setInterval(()=>setTick(t=>t+1), 5000); return ()=>clearInterval(id); }, []);
  const now = new Date().toLocaleTimeString();

  const statPills = [
    { label:"Agents Online",  value:`${MOCK_STATS.agentsOnline}/${MOCK_STATS.agentsTotal}`, c:"#48bb78" },
    { label:"Tasks Today",    value:MOCK_STATS.tasksToday,   c:"#63b3ed" },
    { label:"In Progress",    value:MOCK_STATS.inProgress,   c:"#ed8936" },
    { label:"Signals Live",   value:MOCK_STATS.approved,     c:"#48bb78" },
    { label:"Executed Today", value:MOCK_STATS.executed,     c:"#4299e1" },
    { label:"Rejected",       value:MOCK_STATS.rejected,     c:"#fc8181" },
    { label:"Backtests ✓",   value:MOCK_STATS.btPass,       c:"#48bb78" },
    { label:"🔔 Alerts",      value:MOCK_STATS.alerts,       c:MOCK_STATS.critAlerts>0?"#fc8181":"#ed8936" },
  ];

  return (
    <div style={{background:"#0d0f14",minHeight:"100vh",padding:"10px 12px",display:"flex",flexDirection:"column",gap:8,fontFamily:"'SF Mono','Fira Code',monospace",fontSize:12}}>

      {/* Header */}
      <div style={{display:"flex",alignItems:"center",justifyContent:"space-between",background:"#161929",borderRadius:6,padding:"7px 12px",border:"1px solid #2d3748"}}>
        <div style={{display:"flex",alignItems:"center",gap:10}}>
          <span style={{fontSize:15,fontWeight:900,color:"#e2e8f0"}}>⚡ Armado Quant</span>
          <B c="#48bb78">● LIVE DEMO</B>
        </div>
        <div style={{display:"flex",alignItems:"center",gap:8}}>
          <B c={rc(MOCK_MARKET.regime)}>{MOCK_MARKET.regime.replace("_"," ")}</B>
          <B c={MOCK_MARKET.risk==="MEDIUM"?"#ed8936":"#48bb78"}>RISK: {MOCK_MARKET.risk}</B>
          <span style={{color:"#a0aec0",fontSize:10}}>BTC <b style={{color:"#48bb78"}}>${MOCK_MARKET.btc.toLocaleString()}</b> <span style={{color:"#48bb78"}}>+{f2(MOCK_MARKET.btcChg)}%</span></span>
          <span style={{color:"#4a5568",fontSize:9}}>↻ {now}</span>
        </div>
      </div>

      {/* Stat pills */}
      <div style={{display:"grid",gridTemplateColumns:"repeat(8,1fr)",gap:6}}>
        {statPills.map(p=>(
          <div key={p.label} style={{background:"#161929",border:`1px solid ${p.c}33`,borderRadius:6,padding:"7px 9px",textAlign:"center"}}>
            <div style={{color:p.c,fontWeight:900,fontSize:18,lineHeight:1}}>{p.value}</div>
            <div style={{color:"#4a5568",fontSize:9,marginTop:3}}>{p.label}</div>
          </div>
        ))}
      </div>

      {/* Main grid */}
      <div style={{display:"grid",gridTemplateColumns:"1fr 260px",gap:8,flex:1}}>
        <div style={{display:"flex",flexDirection:"column",gap:8}}>

          {/* Agents */}
          <div>
            <div style={{color:"#4a5568",fontSize:9,fontWeight:700,letterSpacing:"0.08em",marginBottom:5}}>▸ AGENT STATUS — 8 EMPLOYEES ONLINE</div>
            <div style={{display:"grid",gridTemplateColumns:"repeat(4,1fr)",gap:7}}>
              {MOCK_AGENTS.map(a=><AgentCard key={a.agent_id} a={a}/>)}
            </div>
          </div>

          {/* Signal pipeline */}
          <div>
            <div style={{color:"#4a5568",fontSize:9,fontWeight:700,letterSpacing:"0.08em",marginBottom:5}}>▸ SIGNAL PIPELINE</div>
            <Pipeline signals={MOCK_SIGNALS}/>
          </div>

          {/* Portfolio + Strategies */}
          <div style={{display:"grid",gridTemplateColumns:"220px 1fr",gap:8}}>

            {/* Portfolio card */}
            <div style={{background:"#161929",border:"1px solid #2d3748",borderRadius:6,padding:"9px 11px"}}>
              <div style={{color:"#718096",fontWeight:700,fontSize:9,marginBottom:8,letterSpacing:"0.08em"}}>💼 PORTFOLIO</div>
              <div style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:6,marginBottom:8}}>
                {[
                  {l:"Equity",    v:`$${MOCK_PORTFOLIO.equity.toLocaleString()}`, c:"#e2e8f0"},
                  {l:"Deployed",  v:`${MOCK_PORTFOLIO.deployed}%`, c:"#63b3ed"},
                  {l:"Daily P&L", v:`+${f2(MOCK_PORTFOLIO.dailyPnl)}%`, c:"#48bb78"},
                  {l:"Weekly",    v:`+${f2(MOCK_PORTFOLIO.weeklyPnl)}%`, c:"#48bb78"},
                ].map(m=>(
                  <div key={m.l} style={{background:"#0d0f14",borderRadius:4,padding:"5px 7px"}}>
                    <div style={{color:"#4a5568",fontSize:9}}>{m.l}</div>
                    <div style={{color:m.c,fontWeight:700,fontSize:13}}>{m.v}</div>
                  </div>
                ))}
              </div>
              <div style={{height:60}}>
                <ResponsiveContainer width="100%" height="100%">
                  <AreaChart data={MOCK_PNL} margin={{top:2,right:2,bottom:0,left:2}}>
                    <defs>
                      <linearGradient id="g" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%"  stopColor="#48bb78" stopOpacity={0.3}/>
                        <stop offset="95%" stopColor="#48bb78" stopOpacity={0}/>
                      </linearGradient>
                    </defs>
                    <XAxis dataKey="d" tick={{fill:"#4a5568",fontSize:7}} axisLine={false} tickLine={false}/>
                    <YAxis hide/>
                    <Tooltip contentStyle={{background:"#1a1d27",border:"1px solid #2d3748",fontSize:9}} formatter={v=>[`+${f2(v)}%`,"P&L"]}/>
                    <Area type="monotone" dataKey="v" stroke="#48bb78" strokeWidth={1.5} fill="url(#g)" dot={false}/>
                  </AreaChart>
                </ResponsiveContainer>
              </div>
            </div>

            {/* Strategy table */}
            <div style={{background:"#161929",border:"1px solid #2d3748",borderRadius:6,padding:"9px 11px"}}>
              <div style={{color:"#718096",fontWeight:700,fontSize:9,marginBottom:8,letterSpacing:"0.08em"}}>📈 STRATEGY PERFORMANCE</div>
              <table style={{width:"100%",borderCollapse:"collapse"}}>
                <thead>
                  <tr style={{color:"#4a5568",fontSize:9}}>
                    {["Strategy","Tier","Alloc","Sharpe 7d","P&L","Status"].map(h=>(
                      <th key={h} style={{padding:"3px 6px",textAlign:"left",borderBottom:"1px solid #2d3748",fontWeight:600}}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {MOCK_STRATEGIES.map((s,i)=>(
                    <tr key={i} style={{borderBottom:"1px solid #1a1d27"}}>
                      <td style={{padding:"5px 6px",color:"#e2e8f0",fontSize:10}}>{s.name}</td>
                      <td style={{padding:"5px 6px"}}><B c={tc(s.tier)}>{tl(s.tier)}</B></td>
                      <td style={{padding:"5px 6px",color:"#63b3ed",fontSize:10}}>{f2(s.alloc)}%</td>
                      <td style={{padding:"5px 6px",color:s.sharpe>=1?"#48bb78":"#ed8936",fontSize:10}}>{f2(s.sharpe)}</td>
                      <td style={{padding:"5px 6px",color:s.pnl>=0?"#48bb78":"#fc8181",fontSize:10}}>{s.pnl>=0?"+":""}{f2(s.pnl)}%</td>
                      <td style={{padding:"5px 6px"}}><B c={s.status==="live"?"#48bb78":"#b794f4"}>{s.status.toUpperCase()}</B></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Research pipeline */}
          <div style={{background:"#161929",border:"1px solid #2d3748",borderRadius:6,padding:"9px 11px"}}>
            <div style={{color:"#718096",fontWeight:700,fontSize:9,marginBottom:8,letterSpacing:"0.08em"}}>🔬 RESEARCH PIPELINE</div>
            <div style={{display:"grid",gridTemplateColumns:"repeat(5,1fr)",gap:6}}>
              {[
                {s:"Submitted",    n:4, c:"#63b3ed"},
                {s:"In Review",    n:1, c:"#b794f4"},
                {s:"→ Backtest",   n:2, c:"#ed8936"},
                {s:"Backtest ✓",  n:5, c:"#48bb78"},
                {s:"Promoted",     n:1, c:"#ffd700"},
              ].map(x=>(
                <div key={x.s} style={{background:"#0d0f14",borderRadius:4,padding:"8px",textAlign:"center",border:`1px solid ${x.c}22`}}>
                  <div style={{color:x.c,fontSize:22,fontWeight:900}}>{x.n}</div>
                  <div style={{color:"#4a5568",fontSize:9,marginTop:2}}>{x.s}</div>
                </div>
              ))}
            </div>
          </div>

        </div>

        {/* Right sidebar */}
        <div style={{display:"flex",flexDirection:"column",gap:8}}>
          <div style={{flex:1}}>
            <Feed items={MOCK_ACTIVITY}/>
          </div>

          {/* Risk meters */}
          <div style={{background:"#161929",border:"1px solid #2d3748",borderRadius:6,padding:"9px 11px"}}>
            <div style={{color:"#718096",fontWeight:700,fontSize:9,marginBottom:8,letterSpacing:"0.08em"}}>🛡️ RISK METERS</div>
            {[
              {l:"Portfolio Exposure", v:MOCK_PORTFOLIO.deployed, max:40, c:"#63b3ed", disp:`${MOCK_PORTFOLIO.deployed}% / 40%`},
              {l:"Daily Loss Used",    v:0.2, max:3, c:"#fc8181", disp:"0.20% / 3.0%"},
              {l:"Open Positions",     v:MOCK_PORTFOLIO.openPos, max:8, c:"#ed8936", disp:`${MOCK_PORTFOLIO.openPos} / 8`},
            ].map(m=>(
              <div key={m.l} style={{marginBottom:8}}>
                <div style={{display:"flex",justifyContent:"space-between",marginBottom:2}}>
                  <span style={{color:"#718096",fontSize:9}}>{m.l}</span>
                  <span style={{color:m.c,fontSize:9,fontWeight:700}}>{m.disp}</span>
                </div>
                <div style={{background:"#2d3748",borderRadius:2,height:4}}>
                  <div style={{background:m.c,borderRadius:2,height:4,width:`${Math.min(100,(m.v/m.max)*100)}%`}}/>
                </div>
              </div>
            ))}
            <div style={{background:"#0d0f14",borderRadius:4,padding:"6px 8px",marginTop:2}}>
              <div style={{color:"#4a5568",fontSize:9,marginBottom:2}}>BTC Funding Rate</div>
              <span style={{color:"#48bb78",fontWeight:700,fontSize:12}}>+{MOCK_MARKET.funding}%</span>
              <span style={{color:"#4a5568",fontSize:9}}> (neutral)</span>
            </div>
          </div>
        </div>
      </div>

      <div style={{color:"#2d3748",fontSize:8,textAlign:"center",paddingTop:2}}>
        Armado Quant · Manager: Claude · CEO: Brian · Auto-refresh 5s · Showing demo data until middleware is running
      </div>
    </div>
  );
}
