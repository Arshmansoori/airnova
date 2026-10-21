DELHI NCR AQI-72 — HOSTINGER SHARED-HOSTING PHP VERSION
========================================================

FASTEST INSTALL
1) In Hostinger File Manager open: public_html
2) Create folder: aqi
3) Upload ALL files/folders from this package into public_html/aqi/
4) In hPanel set PHP version to PHP 8.1 or newer.
5) Make sure public_html/aqi/data/ is writable (normally 755 works; if cache cannot write, use 775).
6) Open: https://YOUR-DOMAIN.com/aqi/

CHECKS
- Backend health: https://YOUR-DOMAIN.com/aqi/api.php?action=health
- API connectivity: https://YOUR-DOMAIN.com/aqi/api.php?action=upstreams
- Forecast example:
  https://YOUR-DOMAIN.com/aqi/api.php?action=forecast&lat=28.5132&lon=77.2490&hours=72

API KEYS
- This PRIVATE package already has the supplied data.gov.in and NASA FIRMS keys in config.php.
- config.php is blocked from direct browser access by .htaccess.
- NEVER upload this private package to GitHub/public repositories.
- For public sharing use a copy based on config.example.php with keys removed.

WHAT RUNS ON HOSTINGER
- PHP backend (no VPS, Python, Node, Render or Docker required)
- CAMS Global air-quality forecast via Open-Meteo
- ECMWF IFS weather + PBL height + pressure-level temperature via Open-Meteo
- CPCB/data.gov.in station calibration
- NASA FIRMS VIIRS NOAA-20/NOAA-21 fire hotspots
- Indian CPCB AQI rolling windows
- Atmospheric inversion score
- Stubble plume risk proxy
- File cache + learned CPCB bias in data/bias.json

IMPORTANT SCIENTIFIC LIMITATION
This is the lightweight production hybrid engine. Shared hosting cannot run a real WRF-Chem coupled HPC simulation. The dashboard does NOT falsely claim that it does. For the final coupled-physics research version, WRF-Chem output can be added later as another baseline input.
