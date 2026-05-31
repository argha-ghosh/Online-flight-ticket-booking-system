<?php
require_once __DIR__ . "/../config/base_url.php";
include("../includes/adminheader.php");
include("../model/db_conn.php");

// ── STATISTICS ──
$total_bookings   = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings")->fetch_assoc()['c'] ?? 0);
$confirmed        = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status='confirmed'")->fetch_assoc()['c'] ?? 0);
$cancelled        = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status='cancelled'")->fetch_assoc()['c'] ?? 0);
$pending          = $total_bookings - $confirmed - $cancelled;
$revenue_row      = $conn->query("SELECT SUM(total_price) AS r FROM bookings WHERE status='confirmed'")->fetch_assoc();
$total_revenue    = (float)($revenue_row['r'] ?? 0);
$total_travelers  = (int)($conn->query("SELECT COUNT(*) AS c FROM webusers")->fetch_assoc()['c'] ?? 0);
$total_flights    = (int)($conn->query("SELECT COUNT(*) AS c FROM flights")->fetch_assoc()['c'] ?? 0);
$total_airlines   = (int)($conn->query("SELECT COUNT(*) AS c FROM airlines")->fetch_assoc()['c'] ?? 0);

// ── MOST BOOKED ROUTES (Top 6) ──
$routes = [];
$rq = $conn->query("
    SELECT from_location, to_location, COUNT(*) AS cnt, SUM(total_price) AS rev
    FROM bookings WHERE status='confirmed'
    GROUP BY from_location, to_location
    ORDER BY cnt DESC LIMIT 6
");
if ($rq) while ($row = $rq->fetch_assoc()) $routes[] = $row;
$max_route = max(1, max(array_column($routes,'cnt') ?: [1]));

// ── AIRLINE SHARE ──
$airlines = [];
$aq = $conn->query("
    SELECT f.airline_name, COUNT(*) AS cnt, SUM(b.total_price) AS rev
    FROM bookings b JOIN flights f ON b.flight_id=f.id
    WHERE b.status='confirmed'
    GROUP BY f.airline_name ORDER BY cnt DESC
");
if ($aq) while ($row = $aq->fetch_assoc()) $airlines[] = $row;
$total_airline_bk = max(1, array_sum(array_column($airlines,'cnt') ?: [1]));

// ── MONTHLY REVENUE (last 7 months) ──
$monthly = [];
$mq = $conn->query("
    SELECT DATE_FORMAT(booking_date,'%b %Y') AS mo,
           DATE_FORMAT(booking_date,'%Y-%m') AS mo_sort,
           SUM(total_price) AS rev, COUNT(*) AS cnt
    FROM bookings WHERE status='confirmed'
      AND booking_date >= DATE_SUB(NOW(), INTERVAL 7 MONTH)
    GROUP BY mo, mo_sort ORDER BY mo_sort ASC
");
if ($mq) while ($row = $mq->fetch_assoc()) $monthly[] = $row;

// ── DAILY BOOKINGS (last 14 days) ──
$daily = [];
$dq = $conn->query("
    SELECT DATE(booking_date) AS day, COUNT(*) AS cnt
    FROM bookings
    WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY day ORDER BY day ASC
");
if ($dq) while ($row = $dq->fetch_assoc()) $daily[] = $row;

// ── RECENT BOOKINGS (top 8) ──
$recent = [];
$rqr = $conn->query("
    SELECT b.*, w.name AS tname, w.email AS temail, f.flight_code, f.airline_name AS fal
    FROM bookings b
    JOIN webusers w ON b.user_id=w.id
    JOIN flights f  ON b.flight_id=f.id
    ORDER BY b.booking_date DESC, b.id DESC LIMIT 8
");
if ($rqr) while ($row = $rqr->fetch_assoc()) $recent[] = $row;

// ── CLASS SPLIT ──
$eco_cnt = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE class='Economy'  AND status='confirmed'")->fetch_assoc()['c'] ?? 0);
$biz_cnt = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE class='Business' AND status='confirmed'")->fetch_assoc()['c'] ?? 0);

// ── CHART DATA as JSON ──
$chart_months  = json_encode(array_column($monthly,'mo'));
$chart_rev     = json_encode(array_map(fn($r)=>(float)$r['rev'], $monthly));
$chart_mo_cnt  = json_encode(array_map(fn($r)=>(int)$r['cnt'],   $monthly));
$chart_days    = json_encode(array_column($daily,'day'));
$chart_day_cnt = json_encode(array_map(fn($r)=>(int)$r['cnt'],   $daily));
$chart_al_lbl  = json_encode(array_column($airlines,'airline_name'));
$chart_al_cnt  = json_encode(array_map(fn($r)=>(int)$r['cnt'],   $airlines));
$chart_rt_lbl  = json_encode(array_map(fn($r)=>$r['from_location'].'→'.$r['to_location'], $routes));
$chart_rt_cnt  = json_encode(array_column($routes,'cnt'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>GoZayan | Analytics Dashboard</title>
<link rel="stylesheet" href="component.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
/* ══════════════════════════════════
   ANALYTICS DASHBOARD STYLES
══════════════════════════════════ */
:root {
    --db-bg:      #f0f4f8;
    --card:       #ffffff;
    --primary:    #0b72e6;
    --primary-dk: #0556b3;
    --indigo:     #6c3de8;
    --emerald:    #059669;
    --amber:      #d97706;
    --rose:       #e11d48;
    --txt:        #0f172a;
    --txt-m:      #64748b;
    --border:     #e8f0fb;
    --shadow:     0 2px 16px rgba(11,114,230,.07);
    --shadow-lg:  0 8px 32px rgba(11,114,230,.12);
    --radius:     16px;
}

body { background: var(--db-bg); }

.db-wrap {
    max-width: 1440px; margin: 0 auto;
    padding: 32px 28px 60px;
    animation: fadeUp .4s ease;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

/* ── PAGE HEADER ── */
.db-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 16px; margin-bottom: 32px;
}
.db-header-left { display: flex; align-items: center; gap: 16px; }
.db-icon {
    width: 58px; height: 58px; border-radius: 18px;
    background: linear-gradient(135deg, var(--primary), var(--indigo));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.7rem; box-shadow: 0 8px 24px rgba(11,114,230,.28);
    flex-shrink: 0;
}
.db-header h1  { font-size: 1.55rem; font-weight: 900; color: var(--txt); letter-spacing: -.5px; }
.db-header p   { font-size: .84rem; color: var(--txt-m); margin-top: 3px; }
.db-live {
    display: flex; align-items: center; gap: 9px;
    background: #eff6ff; border: 1px solid #bfdbfe;
    color: var(--primary); padding: 8px 18px; border-radius: 50px;
    font-size: .8rem; font-weight: 700;
}
.pulse {
    width: 9px; height: 9px; border-radius: 50%;
    background: var(--primary); display: inline-block;
    animation: pulse 1.6s infinite;
}
@keyframes pulse {
    0%  { box-shadow: 0 0 0 0 rgba(11,114,230,.7); }
    70% { box-shadow: 0 0 0 8px rgba(11,114,230,0); }
    100%{ box-shadow: 0 0 0 0 rgba(11,114,230,0); }
}

/* ── KPI TILES ── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 18px; margin-bottom: 28px;
}
.kpi-card {
    background: var(--card); border-radius: var(--radius);
    padding: 22px 20px; border: 1px solid var(--border);
    box-shadow: var(--shadow); transition: transform .25s, box-shadow .25s;
    position: relative; overflow: hidden;
    display: flex; align-items: center; gap: 18px;
}
.kpi-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
.kpi-card::before {
    content:''; position:absolute; bottom:0; left:0;
    width:100%; height:4px; border-radius:0 0 var(--radius) var(--radius);
}
.kpi-card.c-blue::before   { background: linear-gradient(90deg,var(--primary),#60a5fa); }
.kpi-card.c-green::before  { background: linear-gradient(90deg,var(--emerald),#34d399); }
.kpi-card.c-purple::before { background: linear-gradient(90deg,var(--indigo),#a78bfa); }
.kpi-card.c-amber::before  { background: linear-gradient(90deg,var(--amber),#fbbf24); }
.kpi-card.c-rose::before   { background: linear-gradient(90deg,var(--rose),#fb7185); }
.kpi-card.c-teal::before   { background: linear-gradient(90deg,#0891b2,#22d3ee); }

.kpi-icon {
    width: 50px; height: 50px; border-radius: 14px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
}
.kpi-icon.i-blue   { background:rgba(11,114,230,.1);  color:var(--primary); }
.kpi-icon.i-green  { background:rgba(5,150,105,.1);   color:var(--emerald); }
.kpi-icon.i-purple { background:rgba(108,61,232,.1);  color:var(--indigo); }
.kpi-icon.i-amber  { background:rgba(217,119,6,.1);   color:var(--amber); }
.kpi-icon.i-rose   { background:rgba(225,29,72,.1);   color:var(--rose); }
.kpi-icon.i-teal   { background:rgba(8,145,178,.1);   color:#0891b2; }

.kpi-info { display:flex; flex-direction:column; }
.kpi-val  { font-size: 1.75rem; font-weight:900; color:var(--txt); line-height:1.1; letter-spacing:-.6px; }
.kpi-lbl  { font-size:.73rem; font-weight:700; color:var(--txt-m); text-transform:uppercase; letter-spacing:.5px; margin-top:4px; }

/* ── CHART GRID ── */
.chart-grid-2 {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 22px; margin-bottom: 22px;
}
.chart-grid-3 {
    display: grid; grid-template-columns: 1.6fr 1fr 1fr;
    gap: 22px; margin-bottom: 22px;
}
@media(max-width:1100px){ .chart-grid-3{ grid-template-columns:1fr 1fr; } }
@media(max-width:750px) { .chart-grid-2,.chart-grid-3{ grid-template-columns:1fr; } }

/* ── PANEL ── */
.panel {
    background: var(--card); border-radius: var(--radius);
    border: 1px solid var(--border); box-shadow: var(--shadow);
    overflow: hidden; display: flex; flex-direction: column;
}
.panel-hd {
    padding: 18px 22px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; flex-wrap:wrap; gap:8px;
}
.panel-hd h3 {
    font-size: 1rem; font-weight: 800; color: var(--txt);
    display: flex; align-items: center; gap: 10px;
}
.panel-hd h3::before {
    content:''; width:3px; height:1.1em; border-radius:3px;
    background: linear-gradient(180deg,var(--primary),var(--indigo));
    display:inline-block;
}
.panel-bd { padding: 20px 22px; flex:1; }

/* Chart canvas containers */
.chart-box { position:relative; }
.chart-box-tall  { height:280px; }
.chart-box-mid   { height:240px; }
.chart-box-short { height:210px; }

/* ── ROUTE BARS ── */
.route-list { display:flex; flex-direction:column; gap:13px; }
.route-item {
    background:#f8fafc; border:1px solid var(--border);
    border-radius:12px; padding:13px 16px; transition:all .2s;
}
.route-item:hover { transform:translateX(4px); border-color:rgba(11,114,230,.25); }
.route-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:9px; }
.route-name { font-size:.9rem; font-weight:800; color:var(--txt); display:flex; align-items:center; gap:7px; }
.route-name .arr { color:var(--primary); }
.route-bk-badge {
    background:#eff6ff; border:1px solid #bfdbfe;
    color:var(--primary); font-size:.71rem; font-weight:700;
    padding:2px 9px; border-radius:20px;
}
.bar-bg { height:7px; background:#e2e8f0; border-radius:7px; overflow:hidden; margin-bottom:7px; }
.bar-fill {
    height:100%; border-radius:7px;
    background: linear-gradient(90deg,var(--primary),var(--indigo));
    transition: width .5s ease-out;
}
.route-meta { display:flex; justify-content:space-between; font-size:.75rem; color:var(--txt-m); }
.route-meta .rev { color:var(--emerald); font-weight:700; }

/* ── AIRLINE CARDS ── */
.airline-stack { display:flex; flex-direction:column; gap:11px; }
.al-item {
    display:flex; align-items:center; gap:14px;
    background:#f8fafc; border:1px solid var(--border); border-radius:12px; padding:12px 15px;
    transition:all .2s;
}
.al-item:hover { border-color:rgba(11,114,230,.2); background:#f0f7ff; }
.al-logo {
    width:40px; height:40px; border-radius:11px; flex-shrink:0;
    background:linear-gradient(135deg,#0a2d6e,#0d1f35);
    color:#fff; font-size:.75rem; font-weight:800;
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 4px 10px rgba(10,45,110,.2);
}
.al-info { flex:1; min-width:0; }
.al-name { font-size:.88rem; font-weight:700; color:var(--txt); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.al-bk   { font-size:.74rem; color:var(--txt-m); margin-top:2px; }
.al-bk b { color:var(--txt); }
.al-right { text-align:right; flex-shrink:0; }
.al-rev  { font-size:.92rem; font-weight:800; color:var(--emerald); }
.al-pct  { font-size:.7rem; background:rgba(5,150,105,.1); color:var(--emerald); border-radius:20px; padding:2px 8px; font-weight:700; display:inline-block; margin-top:3px; }

/* ── STATUS SPLIT ── */
.status-split { display:flex; flex-direction:column; gap:12px; }
.ss-item {
    display:flex; align-items:center; gap:12px;
}
.ss-dot { width:12px; height:12px; border-radius:50%; flex-shrink:0; }
.ss-lbl { font-size:.85rem; font-weight:600; color:var(--txt); flex:1; }
.ss-val { font-size:.9rem; font-weight:800; color:var(--txt); }
.ss-bar-bg { height:8px; background:#f0f4f8; border-radius:8px; margin-top:4px; overflow:hidden; }
.ss-bar-fill { height:100%; border-radius:8px; transition:width .5s; }

/* ── BOOKING TABLE ── */
.bk-table { width:100%; border-collapse:collapse; font-size:.87rem; }
.bk-table th {
    background:#fafcff; padding:13px 18px; font-weight:700;
    color:var(--txt-m); font-size:.71rem; text-transform:uppercase;
    letter-spacing:.7px; border-bottom:1.5px solid var(--border); text-align:left;
}
.bk-table td { padding:13px 18px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.bk-table tr:last-child td { border-bottom:none; }
.bk-table tr:hover td { background:#f8fbff; }
.bk-ref  { font-family:monospace; font-weight:700; color:var(--primary); }
.bk-name { font-weight:700; color:var(--txt); }
.bk-mail { font-size:.73rem; color:var(--txt-m); margin-top:2px; }
.bk-route{ font-weight:600; color:var(--txt); display:flex; align-items:center; gap:5px; }
.bk-route span { color:var(--primary); }
.bk-sub  { font-size:.73rem; color:var(--txt-m); margin-top:2px; }
.bk-price{ font-size:.92rem; font-weight:800; color:var(--txt); }
.badge {
    display:inline-flex; align-items:center; gap:4px;
    padding:3px 11px; border-radius:50px; font-size:.7rem; font-weight:700; border:1px solid;
}
.badge.confirmed { background:#dcfce7; color:#15803d; border-color:#bbf7d0; }
.badge.cancelled { background:#fee2e2; color:#dc2626; border-color:#fecaca; }
.badge.pending   { background:#fef9c3; color:#854d0e; border-color:#fde68a; }

/* ── EMPTY ── */
.empty-state { padding:40px; text-align:center; color:var(--txt-m); }
.empty-state .ei { font-size:3rem; opacity:.25; margin-bottom:12px; display:block; }

/* ── SCROLLABLE TABLE ── */
.table-scroll { overflow-x:auto; }
</style>
</head>
<body>

<div class="db-wrap">

    <!-- HEADER -->
    <div class="db-header">
        <div class="db-header-left">
            <div class="db-icon">📊</div>
            <div>
                <h1>Analytics Dashboard</h1>
                <p>Real-time booking intelligence, revenue, and flight demand</p>
            </div>
        </div>
        <div class="db-live"><span class="pulse"></span> LIVE MONITOR</div>
    </div>

    <!-- KPI TILES -->
    <div class="kpi-grid">
        <div class="kpi-card c-green">
            <div class="kpi-icon i-green">💵</div>
            <div class="kpi-info">
                <div class="kpi-val">$<?= number_format($total_revenue, 0) ?></div>
                <div class="kpi-lbl">Total Revenue</div>
            </div>
        </div>
        <div class="kpi-card c-blue">
            <div class="kpi-icon i-blue">🎫</div>
            <div class="kpi-info">
                <div class="kpi-val"><?= number_format($confirmed) ?></div>
                <div class="kpi-lbl">Confirmed Bookings</div>
            </div>
        </div>
        <div class="kpi-card c-purple">
            <div class="kpi-icon i-purple">👥</div>
            <div class="kpi-info">
                <div class="kpi-val"><?= number_format($total_travelers) ?></div>
                <div class="kpi-lbl">Registered Travellers</div>
            </div>
        </div>
        <div class="kpi-card c-amber">
            <div class="kpi-icon i-amber">✈️</div>
            <div class="kpi-info">
                <div class="kpi-val"><?= number_format($total_flights) ?></div>
                <div class="kpi-lbl">Total Flights</div>
            </div>
        </div>
        <div class="kpi-card c-rose">
            <div class="kpi-icon i-rose">❌</div>
            <div class="kpi-info">
                <div class="kpi-val"><?= number_format($cancelled) ?></div>
                <div class="kpi-lbl">Cancelled</div>
            </div>
        </div>
        <div class="kpi-card c-teal">
            <div class="kpi-icon i-teal">🏢</div>
            <div class="kpi-info">
                <div class="kpi-val"><?= number_format($total_airlines) ?></div>
                <div class="kpi-lbl">Partner Airlines</div>
            </div>
        </div>
    </div>

    <!-- ROW 1: Revenue Line + Daily Bookings Bar -->
    <div class="chart-grid-2">

        <!-- Monthly Revenue Line Chart -->
        <div class="panel">
            <div class="panel-hd">
                <h3>📈 Monthly Revenue Trend</h3>
                <span style="font-size:.78rem;color:var(--txt-m);">Last 7 months</span>
            </div>
            <div class="panel-bd">
                <div class="chart-box chart-box-tall">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Daily Bookings Bar -->
        <div class="panel">
            <div class="panel-hd">
                <h3>📅 Daily Booking Activity</h3>
                <span style="font-size:.78rem;color:var(--txt-m);">Last 14 days</span>
            </div>
            <div class="panel-bd">
                <div class="chart-box chart-box-tall">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 2: Routes Bar + Airline Doughnut + Status Doughnut -->
    <div class="chart-grid-3">

        <!-- Top Routes Horizontal Bar -->
        <div class="panel">
            <div class="panel-hd">
                <h3>🔥 Most Booked Routes</h3>
            </div>
            <div class="panel-bd">
                <?php if(empty($routes)): ?>
                    <div class="empty-state"><span class="ei">🗺️</span><p>No route data yet.</p></div>
                <?php else: ?>
                <div class="chart-box chart-box-tall" style="margin-bottom:20px;">
                    <canvas id="routeChart"></canvas>
                </div>
                <div class="route-list">
                    <?php foreach($routes as $r):
                        $pct = round(($r['cnt']/$max_route)*100);
                    ?>
                    <div class="route-item">
                        <div class="route-top">
                            <div class="route-name">
                                <?=htmlspecialchars($r['from_location'])?>
                                <span class="arr">→</span>
                                <?=htmlspecialchars($r['to_location'])?>
                            </div>
                            <span class="route-bk-badge"><?=$r['cnt']?> booking<?=$r['cnt']!=1?'s':''?></span>
                        </div>
                        <div class="bar-bg"><div class="bar-fill" style="width:<?=$pct?>%"></div></div>
                        <div class="route-meta">
                            <span>Revenue: <b class="rev">$<?=number_format($r['rev'],0)?></b></span>
                            <span><?=$pct?>% of top</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Airline Doughnut -->
        <div class="panel">
            <div class="panel-hd"><h3>✈️ Airline Share</h3></div>
            <div class="panel-bd">
                <?php if(empty($airlines)): ?>
                    <div class="empty-state"><span class="ei">🏢</span><p>No data yet.</p></div>
                <?php else: ?>
                <div class="chart-box chart-box-mid" style="margin-bottom:18px;">
                    <canvas id="airlineChart"></canvas>
                </div>
                <div class="airline-stack">
                    <?php foreach($airlines as $a):
                        $pct = round(($a['cnt']/$total_airline_bk)*100,1);
                        $init = strtoupper(substr(preg_replace('/[^A-Za-z ]/','',$a['airline_name']),0,3));
                    ?>
                    <div class="al-item">
                        <div class="al-logo"><?=htmlspecialchars($init)?></div>
                        <div class="al-info">
                            <div class="al-name"><?=htmlspecialchars($a['airline_name'])?></div>
                            <div class="al-bk">Bookings: <b><?=$a['cnt']?></b></div>
                        </div>
                        <div class="al-right">
                            <div class="al-rev">$<?=number_format($a['rev'],0)?></div>
                            <div class="al-pct"><?=$pct?>%</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status + Class Split -->
        <div class="panel">
            <div class="panel-hd"><h3>🎯 Booking Status</h3></div>
            <div class="panel-bd">
                <div class="chart-box chart-box-short" style="margin-bottom:20px;">
                    <canvas id="statusChart"></canvas>
                </div>
                <?php $tb = max(1,$total_bookings); ?>
                <div class="status-split">
                    <div class="ss-item" style="flex-direction:column;align-items:flex-start;">
                        <div style="display:flex;align-items:center;gap:10px;width:100%;margin-bottom:5px;">
                            <div class="ss-dot" style="background:#059669;"></div>
                            <span class="ss-lbl">Confirmed</span>
                            <span class="ss-val" style="margin-left:auto;"><?=$confirmed?></span>
                        </div>
                        <div class="ss-bar-bg" style="width:100%;">
                            <div class="ss-bar-fill" style="width:<?=round($confirmed/$tb*100)?>%;background:#059669;"></div>
                        </div>
                    </div>
                    <div class="ss-item" style="flex-direction:column;align-items:flex-start;">
                        <div style="display:flex;align-items:center;gap:10px;width:100%;margin-bottom:5px;">
                            <div class="ss-dot" style="background:#e11d48;"></div>
                            <span class="ss-lbl">Cancelled</span>
                            <span class="ss-val" style="margin-left:auto;"><?=$cancelled?></span>
                        </div>
                        <div class="ss-bar-bg" style="width:100%;">
                            <div class="ss-bar-fill" style="width:<?=round($cancelled/$tb*100)?>%;background:#e11d48;"></div>
                        </div>
                    </div>
                </div>

                <div style="margin-top:22px;">
                    <div class="panel-hd" style="padding:0 0 12px 0;border-bottom:1px solid var(--border);margin-bottom:14px;">
                        <h3 style="font-size:.9rem;">💺 Cabin Class Split</h3>
                    </div>
                    <div class="chart-box" style="height:140px;">
                        <canvas id="classChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RECENT BOOKINGS TABLE -->
    <div class="panel">
        <div class="panel-hd">
            <h3>⚡ Real-Time Booking Monitor</h3>
            <span style="font-size:.78rem;color:var(--txt-m);">Latest 8 bookings</span>
        </div>
        <?php if(empty($recent)): ?>
            <div class="empty-state"><span class="ei">📋</span><p>No bookings yet.</p></div>
        <?php else: ?>
        <div class="table-scroll">
            <table class="bk-table">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Traveller</th>
                        <th>Route</th>
                        <th>Depart</th>
                        <th>Pax</th>
                        <th>Status</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($recent as $b): ?>
                <tr>
                    <td><span class="bk-ref">#<?=str_pad($b['id'],6,'0',STR_PAD_LEFT)?></span></td>
                    <td>
                        <div class="bk-name"><?=htmlspecialchars($b['tname'])?></div>
                        <div class="bk-mail"><?=htmlspecialchars($b['temail'])?></div>
                    </td>
                    <td>
                        <div class="bk-route"><?=htmlspecialchars($b['from_location'])?><span>→</span><?=htmlspecialchars($b['to_location'])?></div>
                        <div class="bk-sub"><?=htmlspecialchars($b['fal'])?> · <?=htmlspecialchars($b['flight_code'])?></div>
                    </td>
                    <td>
                        <div><?=date('d M Y',strtotime($b['depart_date']))?></div>
                        <div class="bk-sub">Booked <?=date('d M, H:i',strtotime($b['booking_date']))?></div>
                    </td>
                    <td>
                        <div><?=$b['adults']?> Adult<?=$b['adults']>1?'s':''?></div>
                        <?php if($b['children']>0): ?><div class="bk-sub"><?=$b['children']?> Child<?=$b['children']>1?'ren':''?></div><?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?=$b['status']?>">
                            <?=$b['status']==='confirmed'?'✔ Confirmed':($b['status']==='cancelled'?'✖ Cancelled':'⏳ Pending')?>
                        </span>
                    </td>
                    <td>
                        <div class="bk-price">$<?=number_format($b['total_price'],0)?></div>
                        <div class="bk-sub"><?=ucfirst($b['payment_method']??'—')?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /db-wrap -->

<!-- ══════════ CHARTS ══════════ -->
<script>
// Shared chart defaults
Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
Chart.defaults.color = '#64748b';
Chart.defaults.plugins.legend.position = 'bottom';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.padding = 16;

const BLUE   = 'rgba(11,114,230,1)';
const BLUE_A = 'rgba(11,114,230,0.12)';
const INDIGO = 'rgba(108,61,232,1)';
const GREEN  = 'rgba(5,150,105,1)';
const AMBER  = 'rgba(217,119,6,1)';
const ROSE   = 'rgba(225,29,72,1)';
const TEAL   = 'rgba(8,145,178,1)';
const PALETTE= [BLUE,INDIGO,GREEN,AMBER,ROSE,TEAL,'rgba(234,88,12,1)','rgba(124,58,237,1)'];

// ── 1. Monthly Revenue — Area Line ──
const revMonths = <?= $chart_months ?>;
const revData   = <?= $chart_rev ?>;
const revCounts = <?= $chart_mo_cnt ?>;

if(document.getElementById('revenueChart')) {
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: revMonths.length ? revMonths : ['No data'],
            datasets: [{
                label: 'Revenue ($)',
                data: revData,
                borderColor: BLUE,
                backgroundColor: BLUE_A,
                borderWidth: 2.5,
                tension: 0.45,
                fill: true,
                pointBackgroundColor: BLUE,
                pointRadius: 5,
                pointHoverRadius: 8,
            },{
                label: 'Bookings (#)',
                data: revCounts,
                borderColor: INDIGO,
                backgroundColor: 'rgba(108,61,232,0.08)',
                borderWidth: 2,
                tension: 0.45,
                fill: false,
                yAxisID: 'y2',
                pointBackgroundColor: INDIGO,
                pointRadius: 4,
                pointHoverRadius: 7,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { color: '#f0f4f8' } },
                y: {
                    grid: { color: '#f0f4f8' },
                    ticks: { callback: v => '$' + v.toLocaleString() }
                },
                y2: {
                    position: 'right', grid: { display: false },
                    ticks: { stepSize: 1 }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.datasetIndex===0
                            ? ' Revenue: $' + Number(ctx.raw).toLocaleString()
                            : ' Bookings: ' + ctx.raw
                    }
                }
            }
        }
    });
}

// ── 2. Daily Bookings — Bar ──
const dayLabels = <?= $chart_days ?>;
const dayData   = <?= $chart_day_cnt ?>;

if(document.getElementById('dailyChart')) {
    new Chart(document.getElementById('dailyChart'), {
        type: 'bar',
        data: {
            labels: dayLabels.length ? dayLabels : ['No data'],
            datasets: [{
                label: 'Bookings',
                data: dayData,
                backgroundColor: dayData.map((_,i) =>
                    `rgba(11,114,230,${0.4 + (i/dayData.length)*0.6})`
                ),
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: '#f0f4f8' }, ticks: { stepSize: 1 } }
            },
            plugins: { legend: { display: false } }
        }
    });
}

// ── 3. Routes — Horizontal Bar ──
const rtLabels = <?= $chart_rt_lbl ?>;
const rtData   = <?= $chart_rt_cnt ?>;

if(document.getElementById('routeChart')) {
    new Chart(document.getElementById('routeChart'), {
        type: 'bar',
        data: {
            labels: rtLabels.length ? rtLabels : ['No data'],
            datasets: [{
                label: 'Bookings',
                data: rtData,
                backgroundColor: PALETTE.slice(0, rtData.length),
                borderRadius: 6,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            scales: {
                x: { grid: { color: '#f0f4f8' }, ticks: { stepSize: 1 } },
                y: { grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });
}

// ── 4. Airline Share — Doughnut ──
const alLabels = <?= $chart_al_lbl ?>;
const alData   = <?= $chart_al_cnt ?>;

if(document.getElementById('airlineChart')) {
    new Chart(document.getElementById('airlineChart'), {
        type: 'doughnut',
        data: {
            labels: alLabels.length ? alLabels : ['No data'],
            datasets: [{
                data: alData.length ? alData : [1],
                backgroundColor: PALETTE,
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 10,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { position:'bottom', labels:{ font:{ size:11 } } },
                tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} bookings` } }
            }
        }
    });
}

// ── 5. Status Doughnut ──
if(document.getElementById('statusChart')) {
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Confirmed','Cancelled','Pending'],
            datasets: [{
                data: [<?=$confirmed?>, <?=$cancelled?>, <?=$pending?>],
                backgroundColor: [GREEN, ROSE, AMBER],
                borderWidth: 2, borderColor: '#fff', hoverOffset: 8,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { position:'bottom', labels:{ font:{size:11} } }
            }
        }
    });
}

// ── 6. Class Split — Polar ──
if(document.getElementById('classChart')) {
    new Chart(document.getElementById('classChart'), {
        type: 'polarArea',
        data: {
            labels: ['Economy','Business'],
            datasets: [{
                data: [<?=$eco_cnt?>, <?=$biz_cnt?>],
                backgroundColor: ['rgba(11,114,230,0.75)','rgba(108,61,232,0.75)'],
                borderColor: ['#fff','#fff'],
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position:'right', labels:{ font:{size:11}, padding:10 } }
            },
            scales: { r: { grid: { color: '#f0f4f8' }, ticks: { display:false } } }
        }
    });
}
</script>

<?php include("../includes/footer.php"); ?>