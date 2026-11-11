# AirNova

AirNova helps people explore air quality in Delhi NCR and estimate the Air Quality Index (AQI) for a location without installing a monitor there. It includes a station dashboard, a 72-hour forecast with inversion and smoke plume indicators, and an experimental photo-based particulate estimate.

**[Open the live demo](https://airnova.space/)** · [Run locally](#run-locally) · [Benchmark](#preliminary-benchmark) · [Contributing](#contributing)

[![AirNova dashboard showing its AQI gauge and weather summary](airnova-desktop.png)](https://airnova.space/)

*Dashboard preview with sample readings.*

## Run locally

For a static preview, run the following from the repository root with Python 3.

```sh
python -m http.server --bind 127.0.0.1 5000
```

Open [localhost:5000](http://localhost:5000). The static preview serves the interface. For live readings and forecasts, use the [live demo](https://airnova.space/), which runs the PHP backend.

## How it works

- The browser interface includes station AQI and weather views. The [PHP routes](airnova/routes.php) supply public data to the dashboards.
- The [forecast dashboard](prediction/index.html) requests a 72-hour forecast by latitude and longitude and charts AQI, pollutant estimates, and weather indicators. The [forecast service](prediction/src/ForecastService.php) combines Copernicus CAMS air-quality data with ECMWF weather via Open-Meteo, applies CPCB station corrections when available, and uses NASA FIRMS detections for plume diagnostics.
- The [photo estimator](prediction/assets/airvision-cam.js) uses visible haze and an optional humidity adjustment to estimate PM2.5 and PM10, then converts those estimates to particulate AQI. The feature is experimental.

## Preliminary benchmark

Both comparisons cover the same 50 Delhi NCR locations.

| Comparison | Mean absolute error (AQI points) | Reference coverage |
| --- | ---: | --- |
| Calibrated CSV and workbook tab | **8.84** | 50 matched locations |
| Workbook live comparison | **82.4** | 50 matched locations, 23 distinct station labels |

Independence from calibration has yet to be established. These comparisons provide a preliminary benchmark for the matched sample.

To reproduce the arithmetic from the benchmark files, use the paired estimate and reference AQI in each matched row. Average the absolute differences separately for the calibrated and live comparisons, and count distinct station labels in the live comparison. The CSV and workbook are needed to recompute the results. The benchmark files are outside this repository. The proposed field validation will hold out entire locations and time periods.

## Contributing

[Open an issue](https://github.com/Arshmansoori/airnova/issues) for a reproducible bug or a proposed change. For a forecast discrepancy, include the location, forecast issue time and target time, and the reference station and reading where available.

The planned Codex work is to automate evaluation against independent station readings matched by location and time, test how much each external feed improves forecasts, add fallbacks, and remove dependencies where the evaluation supports it. The evaluation target is mean absolute percentage error below 10% on the independent test set.

## License and credits

[MIT](LICENSE).

The original interface is preserved in [original-reference.html](original-reference.html). The landscape uses Unsplash image `photo-1470770841072-f978cf4d019e`. Typography uses Inter with a system fallback.
