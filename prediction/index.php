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
  <script src="assets/app.js?v=1"></script>
</body>
</html>
