<?php
include "konek.php";

$rgbwLatest = ['red_pct' => 70, 'green_pct' => 20, 'blue_pct' => 50, 'white_pct' => 30];
$sqlRgbw = "SELECT red_pct, green_pct, blue_pct, white_pct FROM rgbw_settings ORDER BY applied_at DESC LIMIT 1";
$resRgbw = $conn->query($sqlRgbw);
if ($resRgbw && $rowRgbw = $resRgbw->fetch_assoc()) {
    $rgbwLatest['red_pct']   = (int)$rowRgbw['red_pct'];
    $rgbwLatest['green_pct'] = (int)$rowRgbw['green_pct'];
    $rgbwLatest['blue_pct']  = (int)$rowRgbw['blue_pct'];
    $rgbwLatest['white_pct'] = (int)$rowRgbw['white_pct'];
}

$chartInitial = [];
$resInit = $conn->query("SELECT timestamp, lux, ppfd, dli_daily, temperature, humidity FROM light_monitoring ORDER BY timestamp DESC LIMIT 10");
$chartInitRaw = [];
while ($row = $resInit->fetch_assoc()) {
    $chartInitRaw[] = $row;
}
$chartInitial = array_reverse($chartInitRaw);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Greenhouse Light Monitor</title>
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
        .header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 32px; border-bottom: 1px solid var(--border); background: var(--surface);
        }
        .header-brand { display: flex; align-items: center; gap: 12px; }
        .header-icon {
            width: 38px; height: 38px; background: var(--mint); border-radius: 10px;
            display: flex; align-items: center; justify-content: center; font-size: 20px;
        }
        .header-title { font-size: 18px; font-weight: 700; letter-spacing: -0.3px; }
        .header-sub { font-size: 12px; color: var(--muted); margin-top: 1px; }
        .header-status {
            display: flex; align-items: center; gap: 20px;
            font-size: 12px; font-family: var(--data-font); color: var(--muted);
        }
        .status-dot {
            width: 8px; height: 8px; border-radius: 50%; background: var(--mint);
            box-shadow: 0 0 8px var(--mint); animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; box-shadow: 0 0 8px var(--mint); }
            50%       { opacity: 0.5; box-shadow: 0 0 3px var(--mint); }
        }
        .auto-refresh-toggle {
            display: flex; align-items: center; gap: 8px;
        }
        .toggle-switch {
            position: relative; display: inline-block; width: 36px; height: 20px;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: var(--surface2); border-radius: 20px;
            border: 1px solid var(--border); transition: 0.3s;
        }
        .toggle-slider:before {
            position: absolute; content: ""; height: 14px; width: 14px; left: 2px; bottom: 2px;
            background-color: var(--muted); border-radius: 50%; transition: 0.3s;
        }
        input:checked + .toggle-slider {
            background-color: var(--mint-dim); border-color: var(--mint);
        }
        input:checked + .toggle-slider:before {
            transform: translateX(16px); background-color: #0A1F14;
        }
        .main { padding: 28px 32px; max-width: 1400px; margin: 0 auto; }
        .metrics-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px;
        }
        .metric-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
            padding: 20px 22px; position: relative; overflow: hidden; transition: border-color 0.2s;
        }
        .metric-card:hover { border-color: var(--mint-dim); }
        .metric-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
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
        .chart-section {
            background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
            padding: 24px; margin-bottom: 28px;
        }
        .chart-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 20px; flex-wrap: wrap; gap: 12px;
        }
        .chart-title { font-size: 15px; font-weight: 600; }
        .chart-controls { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .chart-select {
            background: var(--surface2); border: 1px solid var(--border); color: var(--text);
            padding: 6px 12px; border-radius: 8px; font-family: var(--ui-font); font-size: 12px; cursor: pointer;
        }
        .chart-canvas-wrap {
            position: relative; height: 280px;
            overflow-x: auto;
            overflow-y: hidden;
            width: 100%;
        }
        .chart-canvas-wrap canvas {
            display: block;
            height: 100%;
        }
        .chart-nav {
            display: flex; align-items: center; gap: 8px; margin-top: 16px;
        }
        .chart-nav select, .chart-nav button {
            background: var(--surface2); border: 1px solid var(--border); color: var(--text);
            padding: 6px 12px; border-radius: 8px; font-family: var(--ui-font); font-size: 12px; cursor: pointer;
        }
        .chart-nav button:hover { border-color: var(--mint-dim); }
        .chart-nav button:disabled { opacity: 0.5; cursor: default; }
        .lower-grid {
            display: grid; grid-template-columns: 1fr 1.5fr; gap: 20px;
        }
        .panel {
            background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 24px;
        }
        .panel-title { font-size: 15px; font-weight: 600; margin-bottom: 6px; }
        .panel-sub  { font-size: 12px; color: var(--muted); margin-bottom: 18px; }
        .plant-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px;
        }
        .plant-btn {
            padding: 10px 10px; border-radius: 10px; border: 1px solid var(--border);
            background: var(--surface2); color: var(--text); font-family: var(--ui-font);
            font-size: 12px; font-weight: 500; cursor: pointer; text-align: left;
            transition: all 0.18s; display: flex; align-items: center; gap: 8px;
        }
        .plant-btn:hover   { border-color: var(--mint-dim); }
        .plant-btn.active  { border-color: var(--mint); background: rgba(78,204,163,0.08); }
        .plant-emoji       { font-size: 18px; }
        .rgbw-composition {
            font-size: 12px; font-family: var(--data-font); color: var(--muted);
            margin-bottom: 18px; padding: 8px 12px; background: var(--surface2);
            border-radius: 8px; border: 1px solid var(--border);
        }
        .rgbw-label { font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); margin-bottom: 14px; }
        .rgbw-item  { margin-bottom: 14px; }
        .rgbw-row   { display: flex; align-items: center; gap: 10px; margin-bottom: 5px; }
        .rgbw-name  { font-size: 12px; font-weight: 600; width: 46px; }
        .rgbw-pct   { font-family: var(--data-font); font-size: 13px; color: var(--text); width: 38px; text-align: right; }
        .rgbw-bar-track {
            flex: 1; height: 6px; background: var(--surface2); border-radius: 10px; overflow: hidden;
        }
        .rgbw-bar-fill { height: 100%; border-radius: 10px; transition: width 0.4s; }
        input[type=range] {
            -webkit-appearance: none; width: 100%; height: 4px; border-radius: 10px;
            outline: none; cursor: pointer;
        }
        input[type=range].red-range   { background: linear-gradient(to right, #FF5C5C var(--val, 70%), var(--surface2) var(--val, 70%)); }
        input[type=range].green-range { background: linear-gradient(to right, #4ECCA3 var(--val, 20%), var(--surface2) var(--val, 20%)); }
        input[type=range].blue-range  { background: linear-gradient(to right, #5BA4FF var(--val, 50%), var(--surface2) var(--val, 50%)); }
        input[type=range].white-range { background: linear-gradient(to right, #E8F5EE var(--val, 30%), var(--surface2) var(--val, 30%)); }
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none; width: 14px; height: 14px; border-radius: 50%;
            background: var(--text); box-shadow: 0 0 4px rgba(0,0,0,0.4);
        }
        .rgbw-abs-row {
            display: flex; align-items: center; gap: 10px; margin-top: 4px;
        }
        .rgbw-abs-label { width: 46px; font-size: 10px; color: var(--muted); }
        .rgbw-abs-input {
            width: 60px; padding: 4px 6px; background: var(--surface2);
            border: 1px solid var(--border); border-radius: 6px; color: var(--text);
            font-family: var(--data-font); font-size: 12px; text-align: center; outline: none;
        }
        .rgbw-abs-input:focus { border-color: var(--mint); }
        .rgbw-abs-unit { font-size: 10px; color: var(--muted); margin-left: 4px; }
        .apply-btn {
            width: 100%; margin-top: 6px; padding: 12px; border-radius: 10px;
            border: none; background: var(--mint); color: #0A1F14; font-family: var(--ui-font);
            font-size: 14px; font-weight: 700; cursor: pointer; transition: opacity 0.18s;
        }
        .apply-btn:hover { opacity: 0.85; }
        .apply-btn:active { opacity: 0.7; }
        .apply-toast {
            display: none; margin-top: 10px; background: rgba(78,204,163,0.12);
            border: 1px solid var(--mint-dim); border-radius: 8px; padding: 9px 14px;
            font-size: 12px; color: var(--mint); text-align: center;
        }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th {
            text-align: center; padding: 10px 8px; font-size: 11px; font-weight: 600;
            letter-spacing: 1px; text-transform: uppercase; color: var(--muted);
            border-bottom: 1px solid var(--border);
        }
        tbody td {
            padding: 8px 8px; font-family: var(--data-font); font-size: 12px;
            border-bottom: 1px solid rgba(42,90,58,0.4); color: var(--text);
        }
        tbody tr:hover td { background: var(--surface2); }
        td.num { text-align: center; }
        td:first-child, td:last-child { text-align: center; }
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; font-family: var(--ui-font);
        }
        .badge-sensor  { background: rgba(78,204,163,0.12); color: var(--mint); border: 1px solid var(--mint-dim); }
        .badge-manual  { background: rgba(245,166,35,0.12); color: var(--amber); border: 1px solid rgba(245,166,35,0.4); }
        .badge-dot     { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
        .pagination {
            display: flex; align-items: center; justify-content: space-between;
            margin-top: 14px; flex-wrap: wrap; gap: 12px;
        }
        .pagination-info { font-size: 12px; color: var(--muted); }
        .pagination-controls { display: flex; align-items: center; gap: 8px; }
        .pagination-controls button, .pagination-controls select {
            background: var(--surface2); border: 1px solid var(--border); color: var(--text);
            padding: 6px 12px; border-radius: 8px; font-family: var(--ui-font); font-size: 12px; cursor: pointer;
        }
        .pagination-controls button:hover { border-color: var(--mint-dim); }
        .pagination-controls button:disabled { opacity: 0.5; cursor: default; }
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
        <span>Auto-refresh <span id="refreshIntervalText">5s</span> &nbsp;|&nbsp; <span id="liveTime">--</span></span>
        <div class="auto-refresh-toggle">
            <label class="toggle-switch">
                <input type="checkbox" id="autoRefreshToggle" checked onchange="toggleAutoRefresh()">
                <span class="toggle-slider"></span>
            </label>
        </div>
    </div>
</header>

<div class="main">

    <div class="metrics-grid">
        <div class="metric-card c-mint">
            <div class="metric-icon">☀️</div>
            <div class="metric-label">Intensitas Cahaya</div>
            <div class="metric-value" id="metric-lux">--</div>
            <div class="metric-unit">Lux</div>
        </div>
        <div class="metric-card c-amber">
            <div class="metric-icon">📡</div>
            <div class="metric-label">PPFD</div>
            <div class="metric-value" id="metric-ppfd">--</div>
            <div class="metric-unit">μmol/m²/s</div>
        </div>
        <div class="metric-card c-blue">
            <div class="metric-icon">📊</div>
            <div class="metric-label">DLI Hari Ini</div>
            <div class="metric-value" id="metric-dli">--</div>
            <div class="metric-unit">mol/m²/hari</div>
        </div>
        <div class="metric-card c-red">
            <div class="metric-icon">🔌</div>
            <div class="metric-label">Sumber Cahaya</div>
            <div class="metric-value" id="metric-source" style="font-size:18px; padding-top:8px;">--</div>
            <div class="metric-unit">Status sensor</div>
        </div>
    </div>

    <div class="chart-section">
        <div class="chart-header">
            <div class="chart-title">Grafik Monitoring</div>
            <div class="chart-controls">
                <select class="chart-select" id="chartTypeSelect" onchange="switchChart(this.value, this)">
                    <option value="dli">DLI</option>
                    <option value="lux">Intensitas Cahaya (Lux)</option>
                    <option value="ppfd">PPFD</option>
                    <option value="temp">Suhu</option>
                    <option value="hum">Kelembapan</option>
                    <option value="all">Semua</option>
                </select>
            </div>
        </div>
        <div class="chart-canvas-wrap" id="chartContainer">
            <canvas id="mainChart"></canvas>
        </div>
        <div class="chart-nav">
            <span style="font-size:12px; color:var(--muted);">Data:</span>
            <select id="chartLimitSelect" onchange="changeChartLimit()">
                <option value="10">10</option>
                <option value="50" selected>50</option>
                <option value="100">100</option>
                <option value="500">500</option>
                <option value="1000">1000</option>
            </select>
            <button id="chartPrevBtn" onclick="navigateChart(-1)">◀ Mundur</button>
            <button id="chartNextBtn" onclick="navigateChart(1)">Maju ▶</button>
            <button id="resetChartBtn" onclick="resetChartOffset()">↺ Terbaru</button>
        </div>
    </div>

    <div class="lower-grid">

        <div class="panel">
            <div class="panel-title">💡 Pengaturan Cahaya RGBW</div>
            <div class="panel-sub">Pilih jenis tanaman untuk set otomatis proporsi cahaya</div>

            <div class="plant-grid">
                <button class="plant-btn" data-plant="sayuran" onclick="setPlant('sayuran', this)"><span class="plant-emoji">🥬</span>Sayuran Daun</button>
                <button class="plant-btn" data-plant="buah" onclick="setPlant('buah', this)"><span class="plant-emoji">🍅</span>Sayuran Buah</button>
                <button class="plant-btn" data-plant="bunga" onclick="setPlant('bunga', this)"><span class="plant-emoji">🌸</span>Tanaman Bunga</button>
                <button class="plant-btn" data-plant="semai" onclick="setPlant('semai', this)"><span class="plant-emoji">🌱</span>Penyemaian</button>
                <button class="plant-btn" data-plant="herbal" onclick="setPlant('herbal', this)"><span class="plant-emoji">🌿</span>Herbal/Rempah</button>
                <button class="plant-btn" data-plant="custom" onclick="setPlant('custom', this)"><span class="plant-emoji">⚙️</span>Custom</button>
            </div>

            <div class="rgbw-composition" id="rgbwComposition">
                R=<?= $rgbwLatest['red_pct'] ?>% &nbsp; G=<?= $rgbwLatest['green_pct'] ?>% &nbsp; B=<?= $rgbwLatest['blue_pct'] ?>% &nbsp; W=<?= $rgbwLatest['white_pct'] ?>%
            </div>

            <div class="rgbw-label">Proporsi Sisa Asupan Cahaya</div>

            <div class="rgbw-item">
                <div class="rgbw-row">
                    <span class="rgbw-name" style="color:#FF5C5C">Red</span>
                    <div class="rgbw-bar-track"><div class="rgbw-bar-fill" id="bar-r" style="background:#FF5C5C; width:<?= $rgbwLatest['red_pct'] ?>%"></div></div>
                    <span class="rgbw-pct" id="pct-r"><?= $rgbwLatest['red_pct'] ?>%</span>
                </div>
                <input type="range" class="red-range" id="sl-r" min="0" max="100" value="<?= $rgbwLatest['red_pct'] ?>"
                    oninput="updateChannel('r', this.value, 'sl', false)" style="--val:<?= $rgbwLatest['red_pct'] ?>%">
                <div class="rgbw-abs-row">
                    <span class="rgbw-abs-label"></span>
                    <input type="number" class="rgbw-abs-input" id="abs-r" min="0" max="255" value="<?= round($rgbwLatest['red_pct'] / 100 * 255) ?>"
                        onchange="updateChannel('r', this.value, 'abs', false)">
                    <span class="rgbw-abs-unit">/ 255</span>
                </div>
            </div>

            <div class="rgbw-item">
                <div class="rgbw-row">
                    <span class="rgbw-name" style="color:#4ECCA3">Green</span>
                    <div class="rgbw-bar-track"><div class="rgbw-bar-fill" id="bar-g" style="background:#4ECCA3; width:<?= $rgbwLatest['green_pct'] ?>%"></div></div>
                    <span class="rgbw-pct" id="pct-g"><?= $rgbwLatest['green_pct'] ?>%</span>
                </div>
                <input type="range" class="green-range" id="sl-g" min="0" max="100" value="<?= $rgbwLatest['green_pct'] ?>"
                    oninput="updateChannel('g', this.value, 'sl', false)" style="--val:<?= $rgbwLatest['green_pct'] ?>%">
                <div class="rgbw-abs-row">
                    <span class="rgbw-abs-label"></span>
                    <input type="number" class="rgbw-abs-input" id="abs-g" min="0" max="255" value="<?= round($rgbwLatest['green_pct'] / 100 * 255) ?>"
                        onchange="updateChannel('g', this.value, 'abs', false)">
                    <span class="rgbw-abs-unit">/ 255</span>
                </div>
            </div>

            <div class="rgbw-item">
                <div class="rgbw-row">
                    <span class="rgbw-name" style="color:#5BA4FF">Blue</span>
                    <div class="rgbw-bar-track"><div class="rgbw-bar-fill" id="bar-b" style="background:#5BA4FF; width:<?= $rgbwLatest['blue_pct'] ?>%"></div></div>
                    <span class="rgbw-pct" id="pct-b"><?= $rgbwLatest['blue_pct'] ?>%</span>
                </div>
                <input type="range" class="blue-range" id="sl-b" min="0" max="100" value="<?= $rgbwLatest['blue_pct'] ?>"
                    oninput="updateChannel('b', this.value, 'sl', false)" style="--val:<?= $rgbwLatest['blue_pct'] ?>%">
                <div class="rgbw-abs-row">
                    <span class="rgbw-abs-label"></span>
                    <input type="number" class="rgbw-abs-input" id="abs-b" min="0" max="255" value="<?= round($rgbwLatest['blue_pct'] / 100 * 255) ?>"
                        onchange="updateChannel('b', this.value, 'abs', false)">
                    <span class="rgbw-abs-unit">/ 255</span>
                </div>
            </div>

            <div class="rgbw-item">
                <div class="rgbw-row">
                    <span class="rgbw-name" style="color:#E8F5EE">White</span>
                    <div class="rgbw-bar-track"><div class="rgbw-bar-fill" id="bar-w" style="background:#E8F5EE; width:<?= $rgbwLatest['white_pct'] ?>%"></div></div>
                    <span class="rgbw-pct" id="pct-w"><?= $rgbwLatest['white_pct'] ?>%</span>
                </div>
                <input type="range" class="white-range" id="sl-w" min="0" max="100" value="<?= $rgbwLatest['white_pct'] ?>"
                    oninput="updateChannel('w', this.value, 'sl', false)" style="--val:<?= $rgbwLatest['white_pct'] ?>%">
                <div class="rgbw-abs-row">
                    <span class="rgbw-abs-label"></span>
                    <input type="number" class="rgbw-abs-input" id="abs-w" min="0" max="255" value="<?= round($rgbwLatest['white_pct'] / 100 * 255) ?>"
                        onchange="updateChannel('w', this.value, 'abs', false)">
                    <span class="rgbw-abs-unit">/ 255</span>
                </div>
            </div>

            <button class="apply-btn" onclick="applyRGBW()">Terapkan Pengaturan Cahaya</button>
            <div class="apply-toast" id="applyToast">Pengaturan berhasil diterapkan ke sistem</div>
        </div>

        <div class="panel">
            <div class="panel-title">📋 Data Monitoring</div>
            <div class="panel-sub" style="margin-bottom:16px">Riwayat pengukuran</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Lux</th>
                            <th>PPFD</th>
                            <th>DLI</th>
                            <th>Suhu (°C)</th>
                            <th>Kelembapan (%)</th>
                            <th>Sumber</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <div class="pagination-info" id="pageInfo">Halaman 1 dari 1</div>
                <div class="pagination-controls">
                    <select id="rowsPerPageSelect" onchange="changeRowsPerPage()">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <button id="prevPageBtn" onclick="changeTablePage(-1)">◀ Sebelumnya</button>
                    <button id="nextPageBtn" onclick="changeTablePage(1)">Berikutnya ▶</button>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// Data awal dari PHP
const initialChartData = <?= json_encode($chartInitial) ?>;

// Variabel global
let currentChartType = 'dli';
let chartLimit = 50;
let chartOffset = 0;
let toastTimeout = null;
let autoRefreshInterval = null;
let refreshEnabled = true;
let totalChartData = 0;

// Chart initialization
const ctx = document.getElementById('mainChart').getContext('2d');
let mainChart = new Chart(ctx, {
    type: 'line',
    data: { labels: [], datasets: [] },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#7AAF90', font: { family: 'Space Grotesk', size: 12 }, boxWidth: 12, padding: 16 } },
            tooltip: {
                backgroundColor: '#122A1C', borderColor: '#2A5A3A', borderWidth: 1,
                titleColor: '#E8F5EE', bodyColor: '#7AAF90',
                titleFont: { family: 'Space Grotesk', size: 13 },
                bodyFont: { family: 'DM Mono', size: 12 }, padding: 12,
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (context.parsed.y !== null) {
                            label += context.parsed.y.toFixed(2);
                            if (context.dataset.label && context.dataset.label.includes('DLI')) {
                                label += ' mol/m²';
                            } else if (context.dataset.label && context.dataset.label.includes('PPFD')) {
                                label += ' μmol/m²/s';
                            } else if (context.dataset.label && context.dataset.label.includes('Lux')) {
                                label += ' Lux';
                            } else if (context.dataset.label && context.dataset.label.includes('Suhu')) {
                                label += ' °C';
                            } else if (context.dataset.label && context.dataset.label.includes('Kelembapan')) {
                                label += ' %';
                            }
                        }
                        return label;
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { color: 'rgba(42,90,58,0.4)' },
                ticks: {
                    color: '#7AAF90',
                    font: { family: 'DM Mono', size: 11 },
                    maxRotation: 45,
                    minRotation: 45,
                    autoSkip: true,
                    autoSkipPadding: 20
                }
            },
            y: {
                grid: { color: 'rgba(42,90,58,0.4)' },
                ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 } }
            }
        }
    }
});

function formatTimestamp(ts) {
    const d = new Date(ts);
    if (isNaN(d.getTime())) return ts;
    const day = String(d.getDate()).padStart(2,'0');
    const month = String(d.getMonth()+1).padStart(2,'0');
    const year = d.getFullYear();
    const hours = String(d.getHours()).padStart(2,'0');
    const minutes = String(d.getMinutes()).padStart(2,'0');
    return `${day}/${month}/${year}; ${hours}:${minutes}`;
}

function buildChartData(chartData, type) {
    const labels = chartData.map(d => formatTimestamp(d.timestamp));
    const dliVals = chartData.map(d => d.dli_daily);
    const luxVals = chartData.map(d => d.lux);
    const ppfdVals = chartData.map(d => d.ppfd);
    const tempVals = chartData.map(d => d.temperature);
    const humVals = chartData.map(d => d.humidity);

    const baseDataset = {
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 5,
        tension: 0.4,
        fill: false,
    };

    let datasets = [];
    let yAxesOptions = {};

    switch(type) {
        case 'dli':
            datasets = [{ ...baseDataset, label: 'DLI (mol/m²)', data: dliVals, borderColor: '#4ECCA3', pointBackgroundColor: '#4ECCA3', yAxisID: 'y' }];
            yAxesOptions = {
                x: { grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 }, maxRotation: 45, minRotation: 45, autoSkip: true, autoSkipPadding: 20 } },
                y: { grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 } } }
            };
            break;
        case 'lux':
            datasets = [{ ...baseDataset, label: 'Intensitas Cahaya (Lux)', data: luxVals, borderColor: '#F5A623', pointBackgroundColor: '#F5A623', yAxisID: 'y' }];
            yAxesOptions = {
                x: { grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 }, maxRotation: 45, minRotation: 45, autoSkip: true, autoSkipPadding: 20 } },
                y: { grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 } } }
            };
            break;
        case 'ppfd':
            datasets = [{ ...baseDataset, label: 'PPFD (μmol/m²/s)', data: ppfdVals, borderColor: '#5BA4FF', pointBackgroundColor: '#5BA4FF', yAxisID: 'y' }];
            yAxesOptions = {
                x: { grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 }, maxRotation: 45, minRotation: 45, autoSkip: true, autoSkipPadding: 20 } },
                y: { grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 } } }
            };
            break;
        case 'temp':
            datasets = [{ ...baseDataset, label: 'Suhu (°C)', data: tempVals, borderColor: '#FF5C5C', pointBackgroundColor: '#FF5C5C', yAxisID: 'y' }];
            yAxesOptions = {
                x: { grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 }, maxRotation: 45, minRotation: 45, autoSkip: true, autoSkipPadding: 20 } },
                y: { grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 } } }
            };
            break;
        case 'hum':
            datasets = [{ ...baseDataset, label: 'Kelembapan (%)', data: humVals, borderColor: '#88B0FF', pointBackgroundColor: '#88B0FF', yAxisID: 'y' }];
            yAxesOptions = {
                x: { grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 }, maxRotation: 45, minRotation: 45, autoSkip: true, autoSkipPadding: 20 } },
                y: { grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 } } }
            };
            break;
        case 'all':
        default:
            datasets = [
                { ...baseDataset, label: 'DLI', data: dliVals, borderColor: '#4ECCA3', pointBackgroundColor: '#4ECCA3', yAxisID: 'y' },
                { ...baseDataset, label: 'PPFD', data: ppfdVals, borderColor: '#5BA4FF', pointBackgroundColor: '#5BA4FF', yAxisID: 'y' },
                { ...baseDataset, label: 'Lux (/100)', data: luxVals.map(v => v/100), borderColor: '#F5A623', pointBackgroundColor: '#F5A623', yAxisID: 'y' },
                { ...baseDataset, label: 'Suhu (°C)', data: tempVals, borderColor: '#FF5C5C', pointBackgroundColor: '#FF5C5C', yAxisID: 'y1' },
                { ...baseDataset, label: 'Kelembapan (%)', data: humVals, borderColor: '#88B0FF', pointBackgroundColor: '#88B0FF', yAxisID: 'y1' },
            ];
            yAxesOptions = {
                x: { grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 }, maxRotation: 45, minRotation: 45, autoSkip: true, autoSkipPadding: 20 } },
                y: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'DLI / PPFD / Lux' }, grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90' } },
                y1: { type: 'linear', display: true, position: 'right', title: { display: true, text: 'Suhu / Kelembapan' }, grid: { drawOnChartArea: false }, ticks: { color: '#7AAF90' } }
            };
            break;
    }

    return { labels, datasets, scales: yAxesOptions };
}

function applyChartData(chartData) {
    if (!chartData || chartData.length === 0) {
        mainChart.data.labels = [];
        mainChart.data.datasets = [];
        mainChart.update('none');
        updateChartWidth(0);
        return;
    }
    const result = buildChartData(chartData, currentChartType);
    mainChart.data.labels = result.labels;
    mainChart.data.datasets = result.datasets;
    if (result.scales) {
        mainChart.options.scales = result.scales;
    } else {
        mainChart.options.scales = {
            x: { grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 }, maxRotation: 45, minRotation: 45, autoSkip: true, autoSkipPadding: 20 } },
            y: { grid: { color: 'rgba(42,90,58,0.4)' }, ticks: { color: '#7AAF90', font: { family: 'DM Mono', size: 11 } } }
        };
    }
    mainChart.update('none');
    updateChartWidth(chartData.length);
}

function updateChartWidth(dataCount) {
    const canvas = document.getElementById('mainChart');
    const container = document.getElementById('chartContainer');
    if (!canvas || !container) return;
    const baseWidthPerPoint = 60;
    let totalWidth = dataCount * baseWidthPerPoint;
    if (totalWidth < container.clientWidth) {
        totalWidth = container.clientWidth;
    }
    canvas.style.width = totalWidth + 'px';
    canvas.style.height = '100%';
}

function switchChart(type, element) {
    if (element && element.value) type = element.value;
    currentChartType = type;
    document.getElementById('chartTypeSelect').value = type;
    if (window.cachedChartData) {
        applyChartData(window.cachedChartData);
    }
}

function changeChartLimit() {
    chartLimit = parseInt(document.getElementById('chartLimitSelect').value);
    chartOffset = 0;
    fetchDataAndUpdate();
}

function navigateChart(direction) {
    if (direction === -1) {
        chartOffset += chartLimit;
    } else if (direction === 1) {
        chartOffset = Math.max(0, chartOffset - chartLimit);
    }
    fetchDataAndUpdate();
}

function resetChartOffset() {
    chartOffset = 0;
    fetchDataAndUpdate();
}

function updateChartNavButtons(chartDataLength) {
    const prevBtn = document.getElementById('chartPrevBtn');
    const nextBtn = document.getElementById('chartNextBtn');
    const resetBtn = document.getElementById('resetChartBtn');
    if (chartDataLength !== undefined) {
        if (chartDataLength < chartLimit) {
            prevBtn.disabled = true;
        } else {
            prevBtn.disabled = false;
        }
    } else {
        prevBtn.disabled = false;
    }
    nextBtn.disabled = (chartOffset === 0);
    resetBtn.disabled = (chartOffset === 0);
}

let tablePage = 1;
let rowsPerPage = 10;
let totalTableRows = 0;

function changeTablePage(dir) {
    const totalPages = Math.ceil(totalTableRows / rowsPerPage) || 1;
    const newPage = tablePage + dir;
    if (newPage >= 1 && newPage <= totalPages) {
        tablePage = newPage;
        fetchDataAndUpdate();
    }
}

function changeRowsPerPage() {
    rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value);
    tablePage = 1;
    fetchDataAndUpdate();
}

function updatePaginationInfo() {
    const totalPages = Math.ceil(totalTableRows / rowsPerPage) || 1;
    const pageInfoEl = document.getElementById('pageInfo');
    if (pageInfoEl) {
        pageInfoEl.textContent = `Halaman ${tablePage} dari ${totalPages}`;
    }
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    if (prevBtn) prevBtn.disabled = (tablePage <= 1);
    if (nextBtn) nextBtn.disabled = (tablePage >= totalPages);
}

const plantPresets = {
    sayuran: { r: 65, g: 15, b: 55, w: 25 },
    buah:    { r: 75, g: 10, b: 45, w: 35 },
    bunga:   { r: 60, g: 20, b: 65, w: 20 },
    semai:   { r: 50, g: 25, b: 70, w: 30 },
    herbal:  { r: 55, g: 30, b: 50, w: 40 },
    custom:  null,
};

function setActivePlant(type) {
    const buttons = document.querySelectorAll('.plant-btn');
    for (let btn of buttons) {
        const btnType = btn.getAttribute('data-plant');
        if (btnType === type) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    }
}

function setPlant(type, btn) {
    document.querySelectorAll('.plant-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const p = plantPresets[type];
    if (!p) return;
    ['r','g','b','w'].forEach(ch => {
        document.getElementById('sl-' + ch).value = p[ch];
        updateChannel(ch, p[ch], 'sl', true);
    });
}

function updateCompositionText() {
    const r = document.getElementById('sl-r').value;
    const g = document.getElementById('sl-g').value;
    const b = document.getElementById('sl-b').value;
    const w = document.getElementById('sl-w').value;
    document.getElementById('rgbwComposition').innerHTML =
        `R=${r}% &nbsp; G=${g}% &nbsp; B=${b}% &nbsp; W=${w}%`;
}

function updateChannel(ch, val, source, fromPreset = false) {
    val = parseInt(val);
    if (isNaN(val)) val = 0;
    if (source === 'abs') {
        val = Math.min(255, Math.max(0, val));
        let pct = Math.round((val / 255) * 100);
        pct = Math.min(100, Math.max(0, pct));
        document.getElementById('sl-' + ch).value = pct;
        document.getElementById('abs-' + ch).value = val;
        val = pct;
    } else {
        let abs = Math.round((val / 100) * 255);
        document.getElementById('abs-' + ch).value = abs;
    }
    document.getElementById('pct-' + ch).textContent = val + '%';
    document.getElementById('bar-' + ch).style.width = val + '%';
    const sl = document.getElementById('sl-' + ch);
    sl.style.setProperty('--val', val + '%');
    updateCompositionText();
    if (!fromPreset) {
        setActivePlant('custom');
    }
}

function applyRGBW() {
    const r = document.getElementById('sl-r').value;
    const g = document.getElementById('sl-g').value;
    const b = document.getElementById('sl-b').value;
    const w = document.getElementById('sl-w').value;
    fetch('set_rgbw.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ red: r, green: g, blue: b, white: w })
    })
    .then(response => response.json())
    .then(data => {
        const toast = document.getElementById('applyToast');
        if (data.success) {
            toast.textContent = 'Pengaturan berhasil diterapkan ke sistem';
        } else {
            toast.textContent = 'Gagal: ' + (data.message || 'Kesalahan tidak diketahui');
        }
        if (toastTimeout) clearTimeout(toastTimeout);
        toast.style.display = 'block';
        toastTimeout = setTimeout(() => { toast.style.display = 'none'; }, 3000);
    })
    .catch(() => {
        const toast = document.getElementById('applyToast');
        toast.textContent = 'Gagal mengirim pengaturan (jaringan atau server tidak terjangkau)';
        if (toastTimeout) clearTimeout(toastTimeout);
        toast.style.display = 'block';
        toastTimeout = setTimeout(() => { toast.style.display = 'none'; }, 3000);
    });
}

function syncPlantButtonWithSliders() {
    const rVal = parseInt(document.getElementById('sl-r').value);
    const gVal = parseInt(document.getElementById('sl-g').value);
    const bVal = parseInt(document.getElementById('sl-b').value);
    const wVal = parseInt(document.getElementById('sl-w').value);
    let matched = null;
    for (const [key, preset] of Object.entries(plantPresets)) {
        if (preset && preset.r === rVal && preset.g === gVal && preset.b === bVal && preset.w === wVal) {
            matched = key;
            break;
        }
    }
    setActivePlant(matched || 'custom');
}

function toggleAutoRefresh() {
    refreshEnabled = document.getElementById('autoRefreshToggle').checked;
    document.getElementById('refreshIntervalText').textContent = refreshEnabled ? '5s' : 'off';
    if (refreshEnabled) {
        if (!autoRefreshInterval) {
            autoRefreshInterval = setInterval(fetchDataAndUpdate, 5000);
        }
    } else {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
    }
}

syncPlantButtonWithSliders();
updateCompositionText();

function updateTime() {
    const now = new Date();
    const options = { timeZone: 'Asia/Jakarta', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
    document.getElementById('liveTime').textContent = now.toLocaleString('id-ID', options);
}
updateTime();
setInterval(updateTime, 1000);

async function fetchDataAndUpdate() {
    const tableOffset = (tablePage - 1) * rowsPerPage;
    const params = new URLSearchParams({
        _: Date.now(),  // cache buster
        table_limit: rowsPerPage,
        table_offset: tableOffset,
        chart_limit: chartLimit,
        chart_offset: chartOffset
    });

    try {
        const response = await fetch('api_data.php?' + params.toString());
        if (!response.ok) {
            console.error('HTTP error', response.status);
            return;
        }
        const data = await response.json();

        if (data.latest) {
            document.getElementById('metric-lux').textContent = Number(data.latest.lux).toLocaleString('en');
            document.getElementById('metric-ppfd').textContent = Number(data.latest.ppfd).toFixed(1);
            document.getElementById('metric-dli').textContent = Number(data.latest.dli_daily).toFixed(2);
            document.getElementById('metric-source').textContent = data.latest.sensor_status || '-';
        }

        totalTableRows = data.table_total || 0;
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        if (data.table.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--muted);">Tidak ada data</td></tr>';
        } else {
            data.table.forEach(row => {
                const tr = document.createElement('tr');
                const isSensor = (row.sensor_status || '').toLowerCase().includes('sensor') || (row.sensor_status || '').toLowerCase().includes('auto');
                const badgeClass = isSensor ? 'badge-sensor' : 'badge-manual';
                tr.innerHTML = `
                    <td>${row.timestamp}</td>
                    <td class="num">${Number(row.lux).toLocaleString('en')}</td>
                    <td class="num">${Number(row.ppfd).toFixed(1)}</td>
                    <td class="num">${Number(row.dli_daily).toFixed(2)}</td>
                    <td class="num">${row.temperature != null ? row.temperature.toFixed(1) : '-'}</td>
                    <td class="num">${row.humidity != null ? row.humidity.toFixed(1) : '-'}</td>
                    <td><span class="badge ${badgeClass}"><span class="badge-dot"></span>${row.sensor_status || '-'}</span></td>
                `;
                tbody.appendChild(tr);
            });
        }
        updatePaginationInfo();

        window.cachedChartData = data.chart && data.chart.length > 0 ? data.chart : [];
        applyChartData(window.cachedChartData);
        updateChartNavButtons(data.chart ? data.chart.length : 0);
    } catch (err) {
        console.error('Gagal fetch data:', err);
    }
}

if (initialChartData.length > 0) {
    window.cachedChartData = initialChartData;
    applyChartData(initialChartData);
}
document.getElementById('chartTypeSelect').value = 'dli';

if (refreshEnabled) {
    autoRefreshInterval = setInterval(fetchDataAndUpdate, 5000);
}
fetchDataAndUpdate();
updateChartNavButtons(0);
</script>

</body>
</html>