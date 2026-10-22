/**
 * AirNova AirVision Cam — Computer Vision Haze & AQI Estimation Engine
 * Based on Dark Channel Prior (He et al.) & Koschmieder Extinction Physics
 */
class AirVisionEngine {
  constructor() {
    this.patchSize = 11; // 11x11 local neighborhood for dark channel
    this.omega = 0.92;    // Aerial perspective retaining factor
    this.t0 = 0.10;       // Minimum transmission threshold
  }

  /**
   * Analyze an HTMLImageElement or HTMLCanvasElement
   * @param {HTMLImageElement|HTMLCanvasElement} source
   * @param {Object} options - { relativeHumidity: 60 }
   * @returns {Object} Comprehensive optical & AQI analytics
   */
  analyze(source, options = {}) {
    const rh = options.relativeHumidity || 55;
    
    // Scale image to a standardized processing dimension for 60fps responsiveness
    const maxDim = 480;
    let w = source.naturalWidth || source.width;
    let h = source.naturalHeight || source.height;
    if (w > maxDim || h > maxDim) {
      if (w > h) { h = Math.round((h * maxDim) / w); w = maxDim; }
      else { w = Math.round((w * maxDim) / h); h = maxDim; }
    }

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(source, 0, 0, w, h);
    const imgData = ctx.getImageData(0, 0, w, h);
    const pixels = imgData.data;
    const N = w * h;

    // 1. Compute pixel-wise min channel M(x) = min(R, G, B)
    const minChannel = new Float32Array(N);
    for (let i = 0, p = 0; i < N; i++, p += 4) {
      minChannel[i] = Math.min(pixels[p], pixels[p + 1], pixels[p + 2]);
    }

    // 2. Local patch min-filter -> Dark Channel J_dark
    const darkChannel = this._minFilter2D(minChannel, w, h, this.patchSize);

    // 3. Estimate Atmospheric Light (Airlight vector A = [Ar, Ag, Ab])
    // Pick top 0.1% brightest pixels in dark channel, then take max intensity among them
    const topCount = Math.max(10, Math.floor(N * 0.001));
    const indexedDark = new Array(N);
    for (let i = 0; i < N; i++) indexedDark[i] = { val: darkChannel[i], idx: i };
    indexedDark.sort((a, b) => b.val - a.val);

    let maxIntensity = -1;
    let airlight = [220, 220, 225]; // Safe default fallback
    for (let i = 0; i < topCount; i++) {
      const idx = indexedDark[i].idx;
      const p = idx * 4;
      const intensity = pixels[p] * 0.299 + pixels[p + 1] * 0.587 + pixels[p + 2] * 0.114;
      if (intensity > maxIntensity) {
        maxIntensity = intensity;
        airlight = [
          Math.max(10, pixels[p]),
          Math.max(10, pixels[p + 1]),
          Math.max(10, pixels[p + 2])
        ];
      }
    }

    // 4. Transmission Map t(x) = 1 - omega * minFilter(min_c(I_c / A_c))
    const normMin = new Float32Array(N);
    for (let i = 0, p = 0; i < N; i++, p += 4) {
      normMin[i] = Math.min(
        pixels[p] / airlight[0],
        pixels[p + 1] / airlight[1],
        pixels[p + 2] / airlight[2]
      );
    }
    const normDark = this._minFilter2D(normMin, w, h, this.patchSize);
    const transmission = new Float32Array(N);
    let sumTransmission = 0;

    for (let i = 0; i < N; i++) {
      const t = Math.max(this.t0, 1.0 - this.omega * normDark[i]);
      transmission[i] = t;
      sumTransmission += t;
    }
    const avgTransmission = sumTransmission / N;

    // 5. Dehazed Image Reconstruction
    // J(x) = (I(x) - A) / max(t(x), t0) + A
    const dehazedCanvas = document.createElement('canvas');
    dehazedCanvas.width = w;
    dehazedCanvas.height = h;
    const dCtx = dehazedCanvas.getContext('2d');
    const dehazedImgData = dCtx.createImageData(w, h);
    const dPixels = dehazedImgData.data;

    for (let i = 0, p = 0; i < N; i++, p += 4) {
      const t = transmission[i];
      dPixels[p]     = Math.min(255, Math.max(0, (pixels[p]     - airlight[0]) / t + airlight[0]));
      dPixels[p + 1] = Math.min(255, Math.max(0, (pixels[p + 1] - airlight[1]) / t + airlight[1]));
      dPixels[p + 2] = Math.min(255, Math.max(0, (pixels[p + 2] - airlight[2]) / t + airlight[2]));
      dPixels[p + 3] = 255;
    }
    dCtx.putImageData(dehazedImgData, 0, 0);

    // 6. Atmospheric Physics & AQI Mapping
    // Haze Optical Density: inverse of transmission
    const hazeDensity = Math.min(100, Math.max(0, Math.round((1.0 - avgTransmission) * 100)));
    
    // Humidity correction factor: high RH swells aerosol particles (hygroscopic effect)
    let fRh = 1.0;
    if (rh > 60) {
      fRh = 1.0 + 0.007 * Math.pow((rh - 60) / 10, 2);
    }

    // Calibrated urban Delhi aerosol optical depth to PM2.5 formula
    const rawSmogFactor = Math.pow(Math.max(0, 1.0 - avgTransmission), 1.55);
    const estimatedPm25 = Math.round(Math.min(650, Math.max(12, (20 + 580 * rawSmogFactor) / fRh)));
    const estimatedPm10 = Math.round(estimatedPm25 * 1.65);
    const aqi = this._calculateCpcbAqi(estimatedPm25, estimatedPm10);
    const categoryInfo = this._getAqiCategory(aqi);

    return {
      dimensions: { width: w, height: h },
      avgTransmission: Number(avgTransmission.toFixed(3)),
      hazeDensityPercent: hazeDensity,
      airlight: { r: airlight[0], g: airlight[1], b: airlight[2] },
      estimatedPm25,
      estimatedPm10,
      aqi,
      category: categoryInfo.category,
      color: categoryInfo.color,
      healthGuidance: categoryInfo.health,
      dehazedCanvas,
      originalCanvas: canvas
    };
  }

  /**
   * Fast separable 2D min-filter approximation
   */
  _minFilter2D(arr, w, h, radius) {
    const r = Math.floor(radius / 2);
    const temp = new Float32Array(arr.length);
    const out = new Float32Array(arr.length);

    // Horizontal pass
    for (let y = 0; y < h; y++) {
      const rowOffset = y * w;
      for (let x = 0; x < w; x++) {
        let minVal = arr[rowOffset + x];
        const start = Math.max(0, x - r);
        const end = Math.min(w - 1, x + r);
        for (let k = start; k <= end; k++) {
          if (arr[rowOffset + k] < minVal) minVal = arr[rowOffset + k];
        }
        temp[rowOffset + x] = minVal;
      }
    }

    // Vertical pass
    for (let x = 0; x < w; x++) {
      for (let y = 0; y < h; y++) {
        let minVal = temp[y * w + x];
        const start = Math.max(0, y - r);
        const end = Math.min(h - 1, y + r);
        for (let k = start; k <= end; k++) {
          if (temp[k * w + x] < minVal) minVal = temp[k * w + x];
        }
        out[y * w + x] = minVal;
      }
    }
    return out;
  }

  _calculateCpcbAqi(pm25, pm10) {
    const pm25Bands = [
      [0, 30, 0, 50],
      [31, 60, 51, 100],
      [61, 90, 101, 200],
      [91, 120, 201, 300],
      [121, 250, 301, 400],
      [251, 380, 401, 500]
    ];
    let aqi25 = 500;
    for (const [cLo, cHi, iLo, iHi] of pm25Bands) {
      if (pm25 <= cHi) {
        aqi25 = Math.round(((iHi - iLo) / (cHi - cLo)) * (pm25 - cLo) + iLo);
        break;
      }
    }
    return Math.max(0, Math.min(500, aqi25));
  }

  _getAqiCategory(aqi) {
    if (aqi <= 50) return { category: 'Good', color: '#10b981', health: 'Air quality is clean. Outdoor activities are safe.' };
    if (aqi <= 100) return { category: 'Satisfactory', color: '#84cc16', health: 'Minor breathing discomfort possible for highly sensitive people.' };
    if (aqi <= 200) return { category: 'Moderate', color: '#eab308', health: 'Breathing discomfort to people with lungs, asthma, and heart conditions.' };
    if (aqi <= 300) return { category: 'Poor', color: '#f97316', health: 'Breathing discomfort to most people on prolonged exposure.' };
    if (aqi <= 400) return { category: 'Very Poor', color: '#ef4444', health: 'Respiratory illness on prolonged exposure. Limit outdoor exertion.' };
    return { category: 'Severe', color: '#b91c1c', health: 'Emergency health warning. Avoid all outdoor exposure, run air purification.' };
  }
}

window.AirVisionEngine = AirVisionEngine;
