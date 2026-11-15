<div align="center">

# 🌬️ AirNova

### *What's the AQI on **your** street?*

**Hyperlocal Real-Time AQI & 72-Hour Atmospheric Smog Inversion Engine**
<br>for Delhi NCR — powered by Copernicus CAMS 🛰️ & NASA Satellite Telemetry 🌍

[![Live Demo](https://img.shields.io/badge/🌐_Live_Demo-airnova.space-00C853?style=for-the-badge)](https://airnova.space)
[![Version](https://img.shields.io/badge/🚀_Version-v1.0.0-7C4DFF?style=for-the-badge)](https://github.com/Arshmansoori/airnova/releases/tag/v1.0.0)
[![License: MIT](https://img.shields.io/badge/📜_License-MIT-FF6D00?style=for-the-badge)](LICENSE)
[![Health Guide](https://img.shields.io/badge/❤️_Health_Advisor-Read_the_Guide-E91E63?style=for-the-badge)](health.html)

**[🌐 Open the Live Demo](https://airnova.space)** &nbsp;·&nbsp; **[📈 72-Hour Forecast](https://airnova.space)** &nbsp;·&nbsp; **[❤️ Health Advisor](health.html)** &nbsp;·&nbsp; **[🚀 Latest Release](https://github.com/Arshmansoori/airnova/releases/tag/v1.0.0)**

</div>

---

## ⚠️ The Problem

> Delhi NCR is one of the **most polluted regions on Earth** — yet monitoring stations report only **fixed points**, while pollution changes **street to street**.

You can't plan your morning run, your child's school trip, or your elderly parent's walk on a station 5 km away.

## ✨ The Solution

**AirNova estimates the Air Quality Index (AQI) at *any* GPS point in Delhi NCR** — not just where a station happens to be.

| 🎯 Feature | 📋 What it does |
|---|---|
| 📍 **Hyperlocal AQI** | Enter any latitude & longitude → AQI estimate for that exact spot |
| 🔮 **72-Hour Forecast** | AQI, pollutants & weather indicators, charted for the next 3 days |
| 🛰️ **Copernicus CAMS** | European air-quality model data, fused with ECMWF weather via Open-Meteo |
| 🏭 **CPCB Corrections** | Official Indian station readings correct the model where available |
| 🔥 **NASA FIRMS** | Live fire detections for smoke-plume diagnostics |
| 📸 **Photo Estimator** | Estimate PM2.5 / PM10 from visible haze *(experimental)* |
| ❤️ **Health Advisor** | Mask, exercise & safety guidance for your AQI — [read it](health.html) |

---

## 🚀 Quick Start

### 🌐 Use it live — no setup

👉 **[airnova.space](https://airnova.space)** — open it, enter your location, done.

### 💻 Run locally

```bash
# Static preview (Python 3)
python -m http.server --bind 127.0.0.1 5000
```

Open [localhost:5000](http://localhost:5000). The static preview serves the interface — for live readings and forecasts, use the [live demo](https://airnova.space), which runs the PHP backend.

---

## 🧠 How It Works

```mermaid
flowchart LR
    A[📍 Your GPS Point] --> B[🛰️ Copernicus CAMS]
    B --> C[🌦️ ECMWF Weather]
    C --> D[🏭 CPCB Stations]
    D --> E[🔥 NASA FIRMS]
    E --> F[📊 AirNova Engine]
    F --> G[✅ Your Street-Level AQI]
```

1. **You pick a point** — any latitude/longitude in Delhi NCR.
2. **The forecast service** combines Copernicus CAMS air-quality data with ECMWF weather via Open-Meteo.
3. **CPCB station corrections** are applied when available, and **NASA FIRMS** detections flag smoke plumes.
4. **You get** station AQI, weather views, and a 72-hour forecast chart.

> *Street-level* refers to the point you query. The estimate's accuracy and spatial resolution at street scale still need validation — see the benchmark below.

---

## 📊 Preliminary Benchmark

Both comparisons cover the **same 50 Delhi NCR locations**.

| Comparison | Mean Absolute Error (AQI points) | Coverage |
|---|---:|---|
| 📄 Calibrated CSV & workbook tab | **8.84** | 50 matched locations |
| 🔴 Workbook live comparison | **82.4** | 50 matched locations, 23 distinct station labels |

⚠️ **Honest note:** independence from calibration has yet to be established. These are preliminary benchmarks on a matched sample — the original benchmark files are outside this repository, and the proposed field validation will hold out entire locations and time periods.

### ▶️ Run the scorer on a synthetic sample

```bash
# Python 3.11+
python benchmark/score.py benchmark/synthetic-aqi.csv
```

Expected output: **4 matched pairs** and **7.50 AQI points MAE**. The synthetic sample uses invented values to demonstrate the calculation.

To score your own data, use the same CSV header — one row per location, with the reference observation for the same location ID and forecast valid time (ISO 8601 UTC, e.g. `2026-01-01T06:00:00Z`).

---

## 🛠️ Tech Stack

<p align="center">
  <img src="https://img.shields.io/badge/HTML-263445?style=for-the-badge&logo=html5&logoColor=E34F26" alt="HTML" />
  <img src="https://img.shields.io/badge/PHP-263445?style=for-the-badge&logo=php&logoColor=777BB4" alt="PHP" />
  <img src="https://img.shields.io/badge/CSS-263445?style=for-the-badge&logo=css3&logoColor=1572B6" alt="CSS" />
  <img src="https://img.shields.io/badge/JavaScript-263445?style=for-the-badge&logo=javascript&logoColor=F7DF1E" alt="JavaScript" />
  <img src="https://img.shields.io/badge/Python-263445?style=for-the-badge&logo=python&logoColor=3776AB" alt="Python" />
</p>

---

## 🤝 Contributing

Found a bug? Have an idea? **[Open an issue](https://github.com/Arshmansoori/airnova/issues/new)** — for a forecast discrepancy, include the location, the forecast issue time and target time, and the reference station and reading where available.

**Planned work:** automate evaluation against independent station readings matched by location and time, test how much each external feed improves forecasts, add fallbacks, and remove dependencies where the evaluation supports it. The evaluation target is **mean absolute percentage error below 10%** on an independent test set.

---

## 📜 License & Credits

**[MIT License](LICENSE)** — free to use, modify and share.

- Original interface preserved in [`original-reference.html`](original-reference.html)
- Landscape uses Unsplash image `photo-1470770841072-f978cf4d019e`
- Typography uses Inter with a system fallback

---

<div align="center">

### 🌬️ Breathe Better. Live Healthier.

**[🌐 airnova.space](https://airnova.space)** &nbsp;·&nbsp; **[❤️ Health Advisor](health.html)** &nbsp;·&nbsp; **[🚀 v1.0.0](https://github.com/Arshmansoori/airnova/releases/tag/v1.0.0)**

</div>
