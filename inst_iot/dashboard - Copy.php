<?php
include "konek.php";

// Fetch latest 10 records for table
$sql = "SELECT timestamp, lux, ppfd, dli_daily, sensor_status FROM light_monitoring ORDER BY timestamp DESC LIMIT 10";
$res = $conn->query($sql);
$tableRows = [];
while ($row = $res->fetch_assoc()) {
    $tableRows[] = $row;
}

// Fetch last 10 records ascending for charts
$sql2 = "SELECT timestamp, lux, ppfd, dli_daily FROM light_monitoring ORDER BY timestamp DESC LIMIT 10";
$res2 = $conn->query($sql2);
$chartRaw = [];
while ($r = $res2->fetch_assoc()) {
    $chartRaw[] = $r;
}
$chartData = array_reverse($chartRaw);

$latest = $tableRows[0] ?? ['lux' => 0, 'ppfd' => 0, 'dli_daily' => 0, 'sensor_status' => '-'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Greenhouse Light Monitor</title>
    <meta http-equiv="refresh" content="30">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0A1F14;
            --surface:   #122A1C;
            --surface2:  #1A3826;
            --border:    #2A5A3A;
            --mint:      #4ECCA3;
            --mint-dim:  #2A9E79;
            --amber:     #F5A623;
            --red:       #FF5C5C;
            --blue:      #5BA4FF;
            --text:      #E8F5EE;
            --muted:     #7AAF90;
            --data-font: 'DM Mono', monospace;
            --ui-font:   'Space Grotesk', sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--ui-font);
            min-height: 100vh;
            padding: 0;
        }

        /* ── Header ── */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 32px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }
        .header-brand { display: flex; align-items: center; gap: 12px; }
        .header-icon {
            width: 38px; height: 38px;
            background: var(--mint);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .header-title { font-size: 18px; font-weight: 700; letter-spacing: -0.3px; }
        .header-sub { font-size: 12px; color: var(--muted); margin-top: 1px; }
        .header-status {
            display: flex; align-items: center; gap: 8px;
            font-size: 12px; font-family: var(--data-font); color: var(--muted);
        }
        .status-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--mint);
            box-shadow: 0 0 8px var(--mint);
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; box-shadow: 0 0 8px var(--mint); }
            50%       { opacity: 0.5; box-shadow: 0 0 3px var(--mint); }
        }

        /* ── Main layout ── */
        .main { padding: 28px 32px; max-width: 1400px; margin: 0 auto; }

        /* ── Metric cards ── */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .metric-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px 22px;
            position: relative;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .metric-card:hover { border-color: var(--mint-dim); }
        .metric-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
            border-radius: 16px 16px 0 0;
        }
        .metric-card.c-mint::before  { background: var(--mint); }
        .metric-card.c-amber::before { background: var(--amber); }
        .metric-card.c-blue::before  { background: var(--blue); }
        .metric-card.c-red::before   { background: var(--red); }

        .metric-label { font-size: 11px; font-weight: 600; letter-spacing: 1.2px; text-transform: uppercase; color: var(--muted); margin-bottom: 10px; }
        .metric-value { font-family: var(--data-font); font-size: 34px; font-weight: 500; line-height: 1; color: var(--text); }
        .metric-unit  { font-size: 12px; color: var(--muted); margin-top: 5px; font-family: var(--data-font); }
        .metric-icon  { position: absolute; right: 18px; top: 18px; font-size: 22px; opacity: 0.25; }

        /* ── DLI ring hero ── */
        .dli-ring-wrap {
            display: flex; align-items: center; gap: 8px; margin-top: 10px;
        }
        .dli-ring {
            width: 52px; height: 52px;
            border-radius: 50%;
            border: 4px solid var(--border);
            border-top-color: var(--mint);
            border-right-color: var(--mint);
            display: flex; align-items: center; justify-content: center;
            position: relative;
            transition: border-color 0.4s;
        }
        .dli-ring-val { font-family: var(--data-font); font-size: 11px; font-weight: 500; }

        /* ── Chart section ── */
        .chart-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 28px;
        }
        .chart-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 20px; flex-wrap: wrap; gap: 12px;
        }
        .chart-title { font-size: 15px; font-weight: 600; }
        .chart-tabs  { display: flex; gap: 6px; flex-wrap: wrap; }
        .chart-tab {
            padding: 7px 16px;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--muted);
            font-family: var(--ui-font);
            font-size: 12px; font-weight: 500;
            cursor: pointer;
            transition: all 0.18s;
        }
        .chart-tab:hover    { border-color: var(--mint-dim); color: var(--text); }
        .chart-tab.active   { background: var(--mint); border-color: var(--mint); color: #0A1F14; font-weight: 700; }
        .chart-canvas-wrap  { position: relative; height: 280px; }

        /* ── Two-column lower ── */
        .lower-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 20px;
        }

        /* ── RGBW Panel ── */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
        }
        .panel-title {
            font-size: 15px; font-weight: 600; margin-bottom: 6px;
        }
        .panel-sub  { font-size: 12px; color: var(--muted); margin-bottom: 18px; }

        /* Plant selector */
        .plant-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 8px; margin-bottom: 20px;
        }
        .plant-btn {
            padding: 10px 10px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--surface2);
            color: var(--text);
            font-family: var(--ui-font);
            font-size: 12px; font-weight: 500;
            cursor: pointer;
            text-align: left;
            transition: all 0.18s;
            display: flex; align-items: center; gap: 8px;
        }
        .plant-btn:hover   { border-color: var(--mint-dim); }
        .plant-btn.active  { border-color: var(--mint); background: rgba(78,204,163,0.08); }
        .plant-emoji       { font-size: 18px; }

        /* RGBW sliders */
        .rgbw-label { font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); margin-bottom: 14px; }
        .rgbw-item  { margin-bottom: 14px; }
        .rgbw-row   { display: flex; align-items: center; gap: 10px; margin-bottom: 5px; }
        .rgbw-name  { font-size: 12px; font-weight: 600; width: 46px; }
        .rgbw-pct   { font-family: var(--data-font); font-size: 13px; color: var(--text); width: 38px; text-align: right; }
        .rgbw-bar-track {
            flex: 1; height: 6px;
            background: var(--surface2);
            border-radius: 10px; overflow: hidden;
        }
        .rgbw-bar-fill { height: 100%; border-radius: 10px; transition: width 0.4s; }
        input[type=range] {
            -webkit-appearance: none;
            width: 100%; height: 4px; border-radius: 10px;
            outline: none; cursor: pointer;
        }
        input[type=range].red-range   { background: linear-gradient(to right, #FF5C5C var(--val, 70%), var(--surface2) var(--val, 70%)); }
        input[type=range].green-range { background: linear-gradient(to right, #4ECCA3 var(--val, 20%), var(--surface2) var(--val, 20%)); }
        input[type=range].blue-range  { background: linear-gradient(to right, #5BA4FF var(--val, 50%), var(--surface2) var(--val, 50%)); }
        input[type=range].white-range { background: linear-gradient(to right, #E8F5EE var(--val, 30%), var(--surface2) var(--val, 30%)); }
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 14px; height: 14px; border-radius: 50%;
            background: var(--text);
            box-shadow: 0 0 4px rgba(0,0,0,0.4);
        }

        .apply-btn {
            width: 100%;
            margin-top: 6px;
            padding: 12px;
            border-radius: 10px;
            border: none;
            background: var(--mint);
            color: #0A1F14;
            font-family: var(--ui-font);
            font-size: 14px; font-weight: 700;
            cursor: pointer;
            transition: opacity 0.18s;
        }
        .apply-btn:hover { opacity: 0.85; }
        .apply-btn:active { opacity: 0.7; }
        .apply-toast {
            display: none;
            margin-top: 10px;
            background: rgba(78,204,163,0.12);
            border: 1px solid var(--mint-dim);
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 12px;
            color: var(--mint);
            text-align: center;
        }

        /* ── Table ── */
        .table-wrap { overflow-x: auto; }
        table {
            width: 100%; border-collapse: collapse;
            font-size: 13px;
        }
        thead th {
            text-align: left;
            padding: 10px 14px;
            font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }
        tbody td {
            padding: 11px 14px;
            font-family: var(--data-font);
            font-size: 12px;
            border-bottom: 1px solid rgba(42,90,58,0.4);
            color: var(--text);
        }
        tbody tr:hover td { background: var(--surface2); }
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 9px; border-radius: 20px;
            font-size: 11px; font-weight: 600; font-family: var(--ui-font);
        }
        .badge-sensor  { background: rgba(78,204,163,0.12); color: var(--mint); border: 1px solid var(--mint-dim); }
        .badge-manual  { background: rgba(245,166,35,0.12); color: var(--amber); border: 1px solid rgba(245,166,35,0.4); }
        .badge-dot     { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .metrics-grid      { grid-template-columns: 1fr 1fr; }
            .lower-grid        { grid-template-columns: 1fr; }
            .main              { padding: 20px 16px; }
            .header            { padding: 16px; }
        }
        @media (max-width: 520px) {
            .metrics-grid      { grid-template-columns: 1fr 1fr; }
            .metric-value      { font-size: 26px; }
        }
    </style>
</head>
<body>

<!-- ── Header ── -->
<header class="header">
    <div class="header-brand">
        <div class="header-icon">🌿</div>
        <div>
            <div class="header-title">Greenhouse Light Monitor</div>
            <div class="header-sub">Monitoring intensitas & distribusi cahaya</div>
        </div>
    </div>
    <div class="header-status">
        <span class="status-dot"></span>
        <span>Auto-refresh 30s &nbsp;|&nbsp; <?= date('d M Y, H:i') ?></span>
    </div>
</header>

<div class="main">

    <!-- ── Metric cards ── -->
    <div class="metrics-grid">
        <div class="metric-card c-mint">
            <div class="metric-icon">☀️</div>
            <div class="metric-label">Intensitas Cahaya</div>
            <div class="metric-value"><?= number_format($latest['lux']) ?></div>
            <div class="metric-unit">Lux</div>
        </div>
        <div class="metric-card c-amber">
            <div class="metric-icon">📡</div>
            <div class="metric-label">PPFD</div>
            <div class="metric-value"><?= number_format($latest['ppfd'], 1) ?></div>
            <div class="metric-unit">μmol/m²/s</div>
        </div>
        <div class="metric-card c-blue">
            <div class="metric-icon">📊</div>
            <div class="metric-label">DLI Hari Ini</div>
            <div class="metric-value"><?= number_format($latest['dli_daily'], 2) ?></div>
            <div class="metric-unit">mol/m²/hari</div>
        </div>
        <div class="metric-card c-red">
            <div class="metric-icon">🔌</div>
            <div class="metric-label">Sumber Cahaya</div>
            <div class="metric-value" style="font-size:18px; padding-top:8px;">
                <?= htmlspecialchars($latest['sensor_status']) ?>
            </div>
            <div class="metric-unit">Status sensor</div>
        </div>
    </div>

    <!-- ── Chart ── -->
    <div class="chart-section">
        <div class="chart-header">
            <div class="chart-title">Grafik Monitoring (10 Data Terakhir)</div>
            <div class="chart-tabs">
                <button class="chart-tab active" onclick="switchChart('dli', this)">DLI Harian</button>
                <button class="chart-tab" onclick="switchChart('lux', this)">Intensitas Lux</button>
                <button class="chart-tab" onclick="switchChart('ppfd', this)">PPFD</button>
                <button class="chart-tab" onclick="switchChart('all', this)">Semua Data</button>
            </div>
        </div>
        <div class="chart-canvas-wrap">
            <canvas id="mainChart"></canvas>
        </div>
    </div>

    <!-- ── Lower: RGBW + Table ── -->
    <div class="lower-grid">

        <!-- RGBW Panel -->
        <div class="panel">
            <div class="panel-title">💡 Pengaturan Cahaya RGBW</div>
            <div class="panel-sub">Pilih jenis tanaman untuk set otomatis proporsi cahaya</div>

            <div class="plant-grid">
                <button class="plant-btn active" onclick="setPlant('sayuran', this)"><span class="plant-emoji">🥬</span>Sayuran Daun</button>
                <button class="plant-btn" onclick="setPlant('buah', this)"><span class="plant-emoji">🍅</span>Sayuran Buah</button>
                <button class="plant-btn" onclick="setPlant('bunga', this)"><span class="plant-emoji">🌸</span>Tanaman Bunga</button>
                <button class="plant-btn" onclick="setPlant('semai', this)"><span class="plant-emoji">🌱</span>Penyemaian</button>
                <button class="plant-btn" onclick="setPlant('herbal', this)"><span class="plant-emoji">🌿</span>Herbal/Rempah</button>
                <button class="plant-btn" onclick="setPlant('custom', this)"><span class="plant-emoji">⚙️</span>Custom</button>
            </div>

            <div class="rgbw-label">Proporsi Sisa Asupan Cahaya</div>

            <!-- Red -->
            <div class="rgbw-item">
                <div class="rgbw-row">
                    <span class="rgbw-name" style="color:#FF5C5C">Red</span>
                    <div class="rgbw-bar-track"><div class="rgbw-bar-fill" id="bar-r" style="background:#FF5C5C; width:70%"></div></div>
                    <span class="rgbw-pct" id="pct-r">70%</span>
                </div>
                <input type="range" class="red-range" id="sl-r" min="0" max="100" value="70"
                    oninput="updateChannel('r', this.value)" style="--val:70%">
            </div>

            <!-- Green -->
            <div class="rgbw-item">
                <div class="rgbw-row">
                    <span class="rgbw-name" style="color:#4ECCA3">Green</span>
                    <div class="rgbw-bar-track"><div class="rgbw-bar-fill" id="bar-g" style="background:#4ECCA3; width:20%"></div></div>
                    <span class="rgbw-pct" id="pct-g">20%</span>
                </div>
                <input type="range" class="green-range" id="sl-g" min="0" max="100" value="20"
                    oninput="updateChannel('g', this.value)" style="--val:20%">
            </div>

            <!-- Blue -->
            <div class="rgbw-item">
                <div class="rgbw-row">
                    <span class="rgbw-name" style="color:#5BA4FF">Blue</span>
                    <div class="rgbw-bar-track"><div class="rgbw-bar-fill" id="bar-b" style="background:#5BA4FF; width:50%"></div></div>
                    <span class="rgbw-pct" id="pct-b">50%</span>
                </div>
                <input type="range" class="blue-range" id="sl-b" min="0" max="100" value="50"
                    oninput="updateChannel('b', this.value)" style="--val:50%">
            </div>

            <!-- White -->
            <div class="rgbw-item">
                <div class="rgbw-row">
                    <span class="rgbw-name" style="color:#E8F5EE">White</span>
                    <div class="rgbw-bar-track"><div class="rgbw-bar-fill" id="bar-w" style="background:#E8F5EE; width:30%"></div></div>
                    <span class="rgbw-pct" id="pct-w">30%</span>
                </div>
                <input type="range" class="white-range" id="sl-w" min="0" max="100" value="30"
                    oninput="updateChannel('w', this.value)" style="--val:30%">
            </div>

            <button class="apply-btn" onclick="applyRGBW()">Terapkan Pengaturan Cahaya</button>
            <div class="apply-toast" id="applyToast">✓ Pengaturan berhasil diterapkan ke sistem</div>
        </div>

        <!-- Table -->
        <div class="panel">
            <div class="panel-title">📋 10 Data Monitoring Terakhir</div>
            <div class="panel-sub" style="margin-bottom:16px">Diperbarui setiap 30 detik</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Lux</th>
                            <th>PPFD</th>
                            <th>DLI</th>
                            <th>Sumber</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableRows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['timestamp']) ?></td>
                            <td><?= number_format($row['lux']) ?></td>
                            <td><?= number_format($row['ppfd'], 1) ?></td>
                            <td><?= number_format($row['dli_daily'], 2) ?></td>
                            <td>
                                <?php
                                $s = strtolower($row['sensor_status'] ?? '');
                                $isSensor = str_contains($s, 'sensor') || str_contains($s, 'auto');
                                $cls = $isSensor ? 'badge-sensor' : 'badge-manual';
                                ?>
                                <span class="badge <?= $cls ?>">
                                    <span class="badge-dot"></span>
                                    <?= htmlspecialchars($row['sensor_status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /lower-grid -->
</div><!-- /main -->

<script>
// ── Chart data from PHP ──
const rawData = <?= json_encode($chartData) ?>;
const labels  = rawData.map(d => {
    const dt = new Date(d.timestamp);
    return dt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
});
const dliVals  = rawData.map(d => parseFloat(d.dli_daily));
const luxVals  = rawData.map(d => parseFloat(d.lux));
const ppfdVals = rawData.map(d => parseFloat(d.ppfd));

const baseDataset = {
    borderWidth: 2,
    pointRadius: 4,
    pointHoverRadius: 6,
    tension: 0.4,
    fill: true,
};

const chartConfigs = {
    dli: {
        datasets: [{
            ...baseDataset,
            label: 'DLI Harian (mol/m²)',
            data: dliVals,
            borderColor: '#4ECCA3',
            backgroundColor: 'rgba(78,204,163,0.10)',
            pointBackgroundColor: '#4ECCA3',
        }]
    },
    lux: {
        datasets: [{
            ...baseDataset,
            label: 'Intensitas Lux',
            data: luxVals,
            borderColor: '#F5A623',
            backgroundColor: 'rgba(245,166,35,0.10)',
            pointBackgroundColor: '#F5A623',
        }]
    },
    ppfd: {
        datasets: [{
            ...baseDataset,
            label: 'PPFD (μmol/m²/s)',
            data: ppfdVals,
            borderColor: '#5BA4FF',
            backgroundColor: 'rgba(91,164,255,0.10)',
            pointBackgroundColor: '#5BA4FF',
        }]
    },
    all: {
        datasets: [
            { ...baseDataset, label: 'DLI', data: dliVals, borderColor: '#4ECCA3', backgroundColor: 'rgba(78,204,163,0.05)', pointBackgroundColor: '#4ECCA3', yAxisID: 'y' },
            { ...baseDataset, label: 'PPFD', data: ppfdVals, borderColor: '#5BA4FF', backgroundColor: 'rgba(91,164,255,0.05)', pointBackgroundColor: '#5BA4FF', yAxisID: 'y' },
            { ...baseDataset, label: 'Lux (/100)', data: luxVals.map(v => v / 100), borderColor: '#F5A623', backgroundColor: 'rgba(245,166,35,0.05)', pointBackgroundColor: '#F5A623', yAxisID: 'y' },
        ]
    }
};

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: { color: '#7AAF90', font: { family: 'Space Grotesk', size: 12 }, boxWidth: 12, padding: 16 }
        },
        tooltip: {
            backgroundColor: '#122A1C',
            borderColor: '#2A5A3A',
            borderWidth: 1,
            titleColor: '#E8F5EE',
            bodyColor: '#7AAF90',
            titleFont: { family: 'Space Grotesk', size: 13 },
            bodyFont: { family: 'DM Mono', size: 12 },
            padding: 12,
        }
    },
    scales: {
        x: {
            grid: { color: 'rgba(42,90,58,0.4)' },
            ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 } }
        },
        y: {
            grid: { color: 'rgba(42,90,58,0.4)' },
            ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 } }
        }
    }
};

const ctx = document.getElementById('mainChart').getContext('2d');
let mainChart = new Chart(ctx, {
    type: 'line',
    data: { labels, datasets: chartConfigs.dli.datasets },
    options: chartOptions
});

function switchChart(type, btn) {
    document.querySelectorAll('.chart-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    mainChart.data.datasets = chartConfigs[type].datasets;
    mainChart.update('active');
}

// ── RGBW Logic ──
const plantPresets = {
    sayuran: { r: 65, g: 15, b: 55, w: 25 },
    buah:    { r: 75, g: 10, b: 45, w: 35 },
    bunga:   { r: 60, g: 20, b: 65, w: 20 },
    semai:   { r: 50, g: 25, b: 70, w: 30 },
    herbal:  { r: 55, g: 30, b: 50, w: 40 },
    custom:  null,
};

function setPlant(type, btn) {
    document.querySelectorAll('.plant-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const p = plantPresets[type];
    if (!p) return;
    ['r','g','b','w'].forEach(ch => {
        document.getElementById('sl-' + ch).value = p[ch];
        updateChannel(ch, p[ch]);
    });
}

function updateChannel(ch, val) {
    val = parseInt(val);
    document.getElementById('pct-' + ch).textContent = val + '%';
    document.getElementById('bar-' + ch).style.width = val + '%';
    const sl = document.getElementById('sl-' + ch);
    sl.style.setProperty('--val', val + '%');
}

function applyRGBW() {
    const r = document.getElementById('sl-r').value;
    const g = document.getElementById('sl-g').value;
    const b = document.getElementById('sl-b').value;
    const w = document.getElementById('sl-w').value;

    // Send to server via fetch
    fetch('set_rgbw.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ red: r, green: g, blue: b, white: w })
    }).catch(() => {}); // Graceful fail if endpoint not yet ready

    const toast = document.getElementById('applyToast');
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 3000);
}

// Init slider visual states
['r','g','b','w'].forEach(ch => {
    const sl = document.getElementById('sl-' + ch);
    sl.style.setProperty('--val', sl.value + '%');
});
</script>

</body>
</html>