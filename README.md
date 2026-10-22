<div align="center">

<img src="air-nova-logo.png" alt="AirNova Logo" width="220" />

# AirNova: Hyperlocal AQI & 72-Hour Atmospheric Smog Inversion Engine

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge)](LICENSE)
[![PHP: 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Physics: Copernicus CAMS](https://img.shields.io/badge/Physics-Copernicus%20CAMS-0284c7?style=for-the-badge)](https://atmosphere.copernicus.eu/)
[![Satellite: NASA FIRMS](https://img.shields.io/badge/Satellite-NASA%20FIRMS-E03C31?style=for-the-badge&logo=nasa&logoColor=white)](https://firms.modaps.eosdis.nasa.gov/)
[![Live Platform](https://img.shields.io/badge/Live%20Platform-airnova.space-059669?style=for-the-badge)](https://airnova.space)

**AirNova** is an end-to-end civic atmospheric intelligence engine designed to predict, analyze, and alert on urban air quality anomalies, stubble fire transport, and nocturnal smog chambers **up to 72 hours in advance**.

[Live Demo](https://airnova.space) • [Architecture](#system-architecture) • [Key Features](#core-capabilities) • [API Reference](#api-reference) • [Deployment](#quickstart--deployment)

</div>

---

## 📌 The Problem vs. The AirNova Solution

Most commercial AQI dashboards only show **historical or current sensor readings** (lagging indicators). When air quality turns severe in urban basins like Delhi NCR, residents and authorities are caught off guard because ground sensors only record pollution *after* it has already accumulated at the surface.

**AirNova shifts from reactive monitoring to predictive atmospheric physics:**
- **The Culprit:** Thermal inversion (warmer air trapping cold air near the ground) combined with sudden collapses in Planetary Boundary Layer (PBL) height (<250m) during cold autumn/winter nights.
- **The Solution:** AirNova couples **Copernicus CAMS atmospheric chemistry models**, **ECMWF IFS meteorology**, **NASA satellite fire detections**, and **real-time DPCC/CPCB station sensors** to detect upcoming atmospheric "smog chambers" up to 3 days before ground monitors spike.

---

## 🏛 System Architecture

AirNova runs an ultra-lean, decoupled hybrid architecture optimized for sub-150ms edge response times without requiring expensive high-performance computing (HPC) infrastructure for civic serving.

```
┌────────────────────────────────────────────────────────────────────────┐
│                          EXTERNAL DATA FEEDS                           │
├──────────────────┬──────────────────────┬──────────────────────────────┤
│ DPCC/CPCB Ground │ Copernicus CAMS /    │ NASA FIRMS VIIRS Satellites  │
│ 15+ Stations     │ ECMWF IFS 72h Model  │ (Active Fire Hotspots)       │
└────────┬─────────┴──────────┬───────────┴──────────────┬───────────────┘
         │                    │                          │
         ▼                    ▼                          ▼
┌────────────────────────────────────────────────────────────────────────┐
│                     AIRNOVA HYBRID COMPUTATION CORE                     │
│  - Indian CPCB 7-Pollutant Sub-Index Engine (PM2.5, PM10, NO2, etc.)   │
│  - Boundary Layer Inversion Score: ΔT(925 hPa - Surface) + PBL Height │
│  - Regional Plume Trajectory Risk Assessment Proxy                     │
│  - Dynamic Ground Observation Bias Correction (data/bias.json)         │
└──────────────────────────────────┬─────────────────────────────────────┘
                                   │
                                   ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        DELIVERY & INTERFACE                            │
│  - High-Speed Edge REST API (< 150ms response)                         │
│  - Geospatial Heatmaps & Street-Level AQI Mapping (Leaflet)            │
│  - Differential CADR Indoor Air Purifier Mass-Balance Calculator       │
└────────────────────────────────────────────────────────────────────────┘
```

---

## ⚡ Core Capabilities

### 1. 72-Hour Smog Inversion Predictor
Detects nocturnal temperature inversion where cold air containing particulate matter is trapped below warmer air layers. When boundary layer height drops below 250 meters and wind speed stagnates, the system raises proactive smog alerts.

### 2. Multi-Pollutant CPCB Sub-Index Engine
Fully compliant with official Indian Central Pollution Control Board (CPCB) rolling-window sub-indices for:
- $\text{PM}_{2.5}$ & $\text{PM}_{10}$
- Nitrogen Dioxide ($\text{NO}_2$) & Sulphur Dioxide ($\text{SO}_2$)
- Carbon Monoxide ($\text{CO}$), Ozone ($\text{O}_3$), and Ammonia ($\text{NH}_3$)

### 3. Satellite Agricultural Fire & Plume Tracker
Integrates NASA FIRMS VIIRS (NOAA-20 / NOAA-21) fire radiometry across northwestern agricultural corridors to project stubble plume influx probability.

### 4. Differential CADR Purifier Calculator
Uses clean-air delivery rate mass-balance fluid dynamics equations to calculate the exact room purification time and filtration speed requirements based on indoor dimensions and ambient outdoor pollution.

### 5. 📷 AirVision Cam: Computer Vision AQI & Dehazer
Uses He et al.'s Dark Channel Prior (DCP) and the Koschmieder Atmospheric Extinction Law ($I(x) = J(x)t(x) + A(1-t(x))$) to calculate aerosol optical scattering across 11×11 local patches. Directly estimates street-level PM2.5 and CPCB AQI from any smartphone sky/building photo, accompanied by real-time dehazed clean-scene reconstruction.

### 6. 💨 60 FPS Stubble Plume & Wind Vector Stream Engine (Windy.com Style)
Models 2D atmospheric fluid advection ($\frac{\partial C}{\partial t} + \mathbf{u}\cdot\nabla C = S$) over Northern India on HTML5 Canvas. Integrates active NASA FIRMS VIIRS stubble fire clusters across Punjab/Haryana, rendering dynamic fiery red/amber smoke trails that trace plume transport directly into the Delhi-NCR basin with interactive wind speed and 72-hour forecast timeline controls.

### 7. Micro-Architecture & Shared-Hosting Ready
Built with zero framework bloat. The core PHP micro-engine runs comfortably on standard Linux/Apache shared hosting or edge instances with memory-efficient flat-file caching. Dedicated standalone client `airvision.html` runs offline with zero server dependencies.

---

## 📁 Repository Structure

```text
├── airnova/
│   ├── core.php                # Core math, distance calculations, CPCB & EPA logic
│   ├── routes.php              # REST endpoint dispatcher
│   └── storage.php             # JSON file & token session management
├── assets/                     # Frontend UI assets, compiled script bundles & styling
├── flowcharts/                 # High-definition architectural & scientific diagrams
├── pollutants/                 # High-resolution pollutant identification assets
├── prediction/                 # AQI-72 Atmospheric forecasting module
│   ├── src/
│   │   ├── Engine.php          # Inversion scoring, CPCB subindices & rolling math
│   │   ├── ForecastService.php # Open-Meteo & NASA FIRMS orchestrator
│   │   └── Storage.php         # Cache handling & bias weight storage
│   ├── config.example.php      # Environment configuration template
│   ├── api.php                 # Direct forecast API endpoint
│   └── index.php               # Standalone 72h forecast dashboard
├── .gitignore                  # Production exclusion rules
├── api.php                     # Root API gateway for station data
├── index.html                  # Main application interface
└── README.md                   # Platform documentation
```

---

## 🚀 Quickstart & Deployment

### Prerequisites
- PHP 8.1 or higher
- cURL and JSON extensions enabled
- Web server: Apache (mod_rewrite enabled) or Nginx

### 1. Clone the Repository
```bash
git clone https://github.com/YOUR_USERNAME/airnova.git
cd airnova
```

### 2. Configure Environment Keys
Copy the example configuration to active config:
```bash
cp prediction/config.example.php prediction/config.php
```
Open `prediction/config.php` and supply your keys:
- `data_gov_api_key`: Central Government Open Data API key (data.gov.in)
- `firms_map_key`: NASA Earthdata FIRMS API key

### 3. Set Permissions
Ensure the cache directory is writable by your web server:
```bash
chmod -R 775 prediction/data
```

### 4. Serve Locally
```bash
php -S localhost:8000
```
Visit `http://localhost:8000` to access the primary portal or `http://localhost:8000/prediction/` for the dedicated 72-hour forecast engine.

---

## 📡 API Reference

### Health Check
```http
GET /prediction/api.php?action=health
```
**Response:**
```json
{
  "status": "healthy",
  "engine": "AirNova-AQI72",
  "timestamp": 1774160000
}
```

### 72-Hour Forecast
```http
GET /prediction/api.php?action=forecast&lat=28.6139&lon=77.2090&hours=72
```
**Parameters:**
- `lat` *(float)*: Latitude of the target location.
- `lon` *(float)*: Longitude of the target location.
- `hours` *(int, default: 72)*: Forecast horizon in hours (24 to 72).

### Live Station Readings
```http
GET /api.php?action=stations
```
Returns real-time data from all mapped NCR command stations including Mandir Marg, Anand Vihar, RK Puram, and Punjabi Bagh.

---

## 🛡️ License & Attributions

- Licensed under the **MIT License**.
- Meteorological & atmospheric chemistry forecasts provided via **Copernicus Atmosphere Monitoring Service (CAMS)** & **ECMWF**.
- Satellite fire observations courtesy of **NASA FIRMS (EOSDIS)**.
- Station ground monitoring data aligned with **CPCB / DPCC standards**.

---

<div align="center">
  <b>Developed by Team AirVision</b><br />
  <i>Empowering citizens and cities with actionable clean-air intelligence.</i>
</div>
