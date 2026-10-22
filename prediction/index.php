<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <meta name="theme-color" content="#071018" />
  <title>Delhi NCR AQI-72</title>
  <link rel="stylesheet" href="assets/style.css?v=1" />
</head>
<body>
  <main class="shell">
    <header class="hero">
      <div>
        <div class="eyebrow">AIRNOVA • DELHI NCR ATMOSPHERIC INTELLIGENCE</div>
        <h1>AQI-72 <span>Delhi NCR Forecast Dashboard</span></h1>
        <p>72-hour CAMS chemistry + ECMWF meteorology/PBL + CPCB adaptive ground calibration + NASA FIRMS VIIRS stubble-plume diagnostics.</p>
      </div>
      <div class="status" id="statusBadge">Checking…</div>
    </header>

    <section class="controls panel">
      <label>Location<select id="preset"></select></label>
      <label>Latitude<input id="lat" type="number" step="0.0001" value="28.5132" /></label>
      <label>Longitude<input id="lon" type="number" step="0.0001" value="77.2490" /></label>
      <button id="loadBtn">Load 72-hour forecast</button>
    </section>

    <section class="cards">
      <article class="card"><div class="label">Current AQI</div><div class="big" id="currentAqi">—</div><div id="currentCat">—</div></article>
      <article class="card"><div class="label">Peak 72h AQI</div><div class="big" id="peakAqi">—</div><div id="peakTime">—</div></article>
      <article class="card"><div class="label">Max Inversion</div><div class="big" id="invScore">—</div><div id="invTime">—</div></article>
      <article class="card"><div class="label">Max Plume Risk</div><div class="big" id="plumeScore">—</div><div id="plumeTime">—</div></article>
    </section>

    <!-- ========================================================
         DEEP TECH & AI SUITE: AIRVISION CAM & 60FPS WIND STREAMS
         ======================================================== -->
    <section class="deeptech-section">
      <div class="deeptech-header">
        <div>
          <div class="eyebrow">DEEP TECH &amp; ATMOSPHERIC COMPUTING</div>
          <h2>AirVision AI Cam &amp; Stubble Smoke Stream Engine</h2>
        </div>
        <div class="pill">Real-Time Optical Physics &amp; ECMWF Advection</div>
      </div>

      <div class="deeptech-grid">
        <!-- MODULE 1: AIRVISION CAM -->
        <article class="panel deeptech-card">
          <div>
            <div class="panel-head">
              <div>
                <div class="eyebrow">COMPUTER VISION OPTICS (DARK CHANNEL PRIOR)</div>
                <h2>📷 AirVision Cam: Image AQI &amp; Dehazer</h2>
              </div>
              <span id="camStatusBadge" class="pill">Ready to scan</span>
            </div>
            
            <p style="color:var(--muted);font-size:13px;line-height:1.5;margin-top:0">
              Uses Koschmieder atmospheric extinction law &amp; Dark Channel Prior (DCP) to calculate particulate haze density and estimate street AQI from any sky or landmark photo.
            </p>

            <div class="cam-controls">
              <input type="file" id="camFileInput" accept="image/*" style="display:none" />
              <button class="btn-action" onclick="document.getElementById('camFileInput').click()">
                📁 Upload / Capture Photo
              </button>
              <button class="btn-secondary" id="btnSampleDelhi">Load Delhi Smog Sample</button>
              <button class="btn-secondary" id="btnToggleDehaze" style="display:none">Toggle Dehazed View</button>
            </div>

            <!-- Preview Box / Canvas Wrap -->
            <div id="camDisplayArea" class="cam-canvas-wrap">
              <div id="camPlaceholder" style="padding:40px 20px;text-align:center;color:var(--muted)">
                <div style="font-size:38px;margin-bottom:8px">📸</div>
                <div style="font-weight:700;color:var(--text);font-size:14px">Drop a photo here or click upload</div>
                <div style="font-size:12px;margin-top:4px">Instant optical extinction &amp; PM2.5 estimation</div>
              </div>
              <canvas id="camCanvas" style="display:none"></canvas>
            </div>

            <!-- Optical Metrics Readout -->
            <div class="vision-stats-grid">
              <div class="v-stat">
                <div class="v-stat-lbl">Estimated AQI</div>
                <div id="vStatAqi" class="v-stat-val">—</div>
              </div>
              <div class="v-stat">
                <div class="v-stat-lbl">Est. PM2.5</div>
                <div id="vStatPm25" class="v-stat-val">—</div>
              </div>
              <div class="v-stat">
                <div class="v-stat-lbl">Haze Density</div>
                <div id="vStatHaze" class="v-stat-val">—</div>
              </div>
            </div>
          </div>

          <div id="vHealthAlert" style="margin-top:14px;padding:12px 14px;border-radius:12px;background:rgba(255,255,255,.03);border:1px solid var(--line);font-size:12px;color:var(--muted);line-height:1.4">
            Upload an outdoor image to generate instant optical air quality diagnostics.
          </div>
        </article>

        <!-- MODULE 2: WIND & STUBBLE STREAM ANIMATION -->
        <article class="panel deeptech-card">
          <div>
            <div class="panel-head">
              <div>
                <div class="eyebrow">2D FLUID ADVECTION (WINDY.COM STYLE)</div>
                <h2>💨 Stubble Fire Smoke Streams</h2>
              </div>
              <span id="plumeStatusBadge" class="toggle-badge toggle-active">🔥 Plume Mode Active</span>
            </div>

            <p style="color:var(--muted);font-size:13px;line-height:1.5;margin-top:0">
              Real-time 60 FPS vector simulation showing NASA FIRMS stubble fire plume trajectories from Punjab &amp; Haryana converging into the Delhi NCR basin.
            </p>

            <div class="wind-canvas-wrap">
              <canvas id="windCanvas"></canvas>
            </div>

            <div class="wind-controls-deck">
              <div class="wind-ctrl-row">
                <span style="font-weight:700">Forecast Horizon:</span>
                <span id="windHourLbl" style="font-family:monospace;color:var(--cyan);font-weight:800">+0 Hours (Current)</span>
                <input type="range" id="windHourSlider" class="wind-slider" min="0" max="72" value="0" />
              </div>

              <div class="wind-ctrl-row">
                <span>Vector Stream Mode:</span>
                <div style="display:flex;gap:8px">
                  <button id="btnPlumeToggle" class="btn-secondary" style="padding:5px 12px;font-size:11px">
                    Toggle Stubble Plumes
                  </button>
                  <button id="btnSpeedToggle" class="btn-secondary" style="padding:5px 12px;font-size:11px">
                    Speed: 1.0x
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--muted)">
            <span>📍 Trajectory Corridor: Punjab ➔ Haryana ➔ Delhi NCR</span>
            <span style="color:#ef4444;font-weight:700">● 6 Hotspot Clusters</span>
          </div>
        </article>
      </div>
    </section>

    <section class="panel chart-panel">
      <div class="panel-head"><div><div class="eyebrow">FORECAST CURVE</div><h2>Indian AQI • next 72 hours</h2></div><div class="legend">CPCB rolling-average method</div></div>
      <svg id="aqiChart" viewBox="0 0 1200 360" preserveAspectRatio="none"></svg>
    </section>

    <section class="grid2">
      <article class="panel"><div class="panel-head"><div><div class="eyebrow">ATMOSPHERIC STATE</div><h2>Inversion & PBL</h2></div></div><div id="inversionBox" class="metric-stack">Loading…</div></article>
      <article class="panel"><div class="panel-head"><div><div class="eyebrow">REGIONAL TRANSPORT</div><h2>Stubble plume</h2></div></div><div id="plumeBox" class="metric-stack">Loading…</div></article>
    </section>

    <section class="panel data-panel">
      <div class="panel-head"><div><div class="eyebrow">HOURLY DETAIL</div><h2>Forecast table</h2></div><div class="legend" id="sourceMode"></div></div>
      <div class="table-wrap">
        <table><thead><tr><th>Time</th><th>AQI</th><th>PM2.5</th><th>PM10</th><th>O₃</th><th>NO₂</th><th>PBL</th><th>Wind</th><th>Inversion</th><th>Plume</th></tr></thead><tbody id="forecastRows"></tbody></table>
      </div>
    </section>

    <section class="panel footnote"><strong>Platform architecture note:</strong> this shared-hosting version uses live CAMS/ECMWF/CPCB/FIRMS data and local bias correction. It does not pretend to run WRF-Chem on shared hosting; a coupled WRF-Chem output can be plugged into the same pipeline later for final scientific validation.</section>
  </main>
  <script src="assets/airvision-cam.js?v=1"></script>
  <script src="assets/wind-particles.js?v=1"></script>
  <script src="assets/app.js?v=2"></script>
</body>
</html>
