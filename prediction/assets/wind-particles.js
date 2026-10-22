/**
 * AirNova Particle Stream Engine (Windy.com Style Vector Field & Smoke Advection)
 * 60 FPS HTML5 Canvas Fluid Simulation
 */
class WindParticleEngine {
  constructor(canvas, options = {}) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    
    // Configurable parameters
    this.particleCount = options.particleCount || 1800;
    this.speedMultiplier = options.speedMultiplier || 1.1;
    this.plumeMode = options.plumeMode !== undefined ? options.plumeMode : true;
    this.hour = options.hour || 0; // 0 to 72h
    
    // Bounds: Northern India / NCR Region
    // Lat: 27.2N to 32.2N, Lon: 74.0E to 79.5E
    this.bounds = {
      minLat: 27.2, maxLat: 32.2,
      minLon: 74.0, maxLon: 79.5
    };

    // NASA FIRMS VIIRS Stubble Burning Cluster Hotspots (Punjab & Haryana)
    this.fireClusters = [
      { name: 'Sangrur', lat: 30.24, lon: 75.84, intensity: 95 },
      { name: 'Ludhiana', lat: 30.90, lon: 75.85, intensity: 88 },
      { name: 'Bhatinda', lat: 30.21, lon: 74.95, intensity: 92 },
      { name: 'Firozpur', lat: 30.92, lon: 74.61, intensity: 80 },
      { name: 'Karnal', lat: 29.68, lon: 76.98, intensity: 75 },
      { name: 'Kaithal', lat: 29.80, lon: 76.40, intensity: 78 }
    ];

    // Delhi NCR Receptor Basin
    this.delhi = { lat: 28.6139, lon: 77.2090, name: 'Delhi NCR' };

    this.particles = [];
    this.animationId = null;
    this.isRunning = false;

    this.resize();
    this.initParticles();
  }

  resize() {
    const rect = this.canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    this.width = rect.width || this.canvas.parentElement.clientWidth || 800;
    this.height = rect.height || 460;
    this.canvas.width = this.width * dpr;
    this.canvas.height = this.height * dpr;
    this.ctx.scale(dpr, dpr);
  }

  initParticles() {
    this.particles = [];
    for (let i = 0; i < this.particleCount; i++) {
      this.particles.push(this.createParticle(true));
    }
  }

  createParticle(randomAge = false) {
    const x = Math.random() * this.width;
    const y = Math.random() * this.height;
    const maxAge = 80 + Math.random() * 90;
    return {
      x,
      y,
      prevX: x,
      prevY: y,
      age: randomAge ? Math.floor(Math.random() * maxAge) : 0,
      maxAge,
      speed: 0.8 + Math.random() * 0.8,
      plumeIntensity: 0
    };
  }

  // Convert (lat, lon) to canvas (x, y)
  latLonToCanvas(lat, lon) {
    const xNorm = (lon - this.bounds.minLon) / (this.bounds.maxLon - this.bounds.minLon);
    const yNorm = (this.bounds.maxLat - lat) / (this.bounds.maxLat - this.bounds.minLat);
    return {
      x: xNorm * this.width,
      y: yNorm * this.height
    };
  }

  // Convert canvas (x, y) to (lat, lon)
  canvasToLatLon(x, y) {
    const lon = this.bounds.minLon + (x / this.width) * (this.bounds.maxLon - this.bounds.minLon);
    const lat = this.bounds.maxLat - (y / this.height) * (this.bounds.maxLat - this.bounds.minLat);
    return { lat, lon };
  }

  /**
   * Sample 2D Wind Velocity Field (u, v) at coordinates
   * North-westerly winter wind system characteristic of Indo-Gangetic Plains
   */
  sampleWind(lat, lon) {
    // Nocturnal boundary layer dampening (night hours have sluggish trapped winds)
    const diurnalFactor = 0.65 + 0.35 * Math.sin(((this.hour % 24) - 6) * Math.PI / 12);
    
    // Primary flow: North-Westerly (towards South-East)
    // u > 0 (heading East), v < 0 (heading South)
    let u = 2.8 * diurnalFactor;
    let v = 3.6 * diurnalFactor; // In screen space, down is +y (South)

    // Delhi Bowl Stagnation: Topography & urban roughness slows wind down
    const distToDelhi = Math.hypot(lat - this.delhi.lat, lon - this.delhi.lon);
    if (distToDelhi < 0.6) {
      // Wind stagnation zone in Delhi NCR
      const stagnation = Math.max(0.25, distToDelhi / 0.6);
      u *= stagnation;
      v *= stagnation;
    }

    // Gentle realistic turbulence perturbation
    const wave = Math.sin(lon * 2.2 + this.hour * 0.1) * 0.45;
    u += wave;
    v += Math.cos(lat * 2.5) * 0.35;

    return { u, v };
  }

  /**
   * Calculate proximity to stubble fire emitters
   */
  sampleFirePlume(lat, lon) {
    let maxPlume = 0;
    for (const fire of this.fireClusters) {
      const d = Math.hypot(lat - fire.lat, lon - fire.lon);
      if (d < 0.85) {
        const influence = (1.0 - d / 0.85) * (fire.intensity / 100);
        if (influence > maxPlume) maxPlume = influence;
      }
    }
    return maxPlume;
  }

  start() {
    if (this.isRunning) return;
    this.isRunning = true;
    const animate = () => {
      if (!this.isRunning) return;
      this.step();
      this.animationId = requestAnimationFrame(animate);
    };
    this.animationId = requestAnimationFrame(animate);
  }

  stop() {
    this.isRunning = false;
    if (this.animationId) cancelAnimationFrame(this.animationId);
  }

  setPlumeMode(enabled) {
    this.plumeMode = enabled;
  }

  setSpeed(mult) {
    this.speedMultiplier = mult;
  }

  setParticleCount(count) {
    this.particleCount = count;
    this.initParticles();
  }

  setHour(h) {
    this.hour = h;
  }

  step() {
    const ctx = this.ctx;
    
    // Trail persistence fade effect: draw translucent dark rectangle
    ctx.fillStyle = 'rgba(7, 16, 24, 0.18)';
    ctx.fillRect(0, 0, this.width, this.height);

    // Draw reference cities and fire hotspots
    this.drawLandmarks();

    // Advance particles
    ctx.lineWidth = 1.6;
    ctx.lineCap = 'round';

    for (let i = 0; i < this.particles.length; i++) {
      const p = this.particles[i];
      p.prevX = p.x;
      p.prevY = p.y;

      const { lat, lon } = this.canvasToLatLon(p.x, p.y);
      const wind = this.sampleWind(lat, lon);

      // Advection step
      p.x += wind.u * p.speed * this.speedMultiplier;
      p.y += wind.v * p.speed * this.speedMultiplier;
      p.age++;

      // Check stubble fire plume accumulation
      if (this.plumeMode) {
        const fireInfluence = this.sampleFirePlume(lat, lon);
        if (fireInfluence > 0.15) {
          p.plumeIntensity = Math.min(1.0, p.plumeIntensity + fireInfluence * 0.4);
        } else {
          // Slow plume dispersion over distance
          p.plumeIntensity *= 0.985;
        }
      } else {
        p.plumeIntensity = 0;
      }

      // Render particle trail
      const progress = p.age / p.maxAge;
      const alpha = Math.sin(progress * Math.PI) * 0.85;

      ctx.beginPath();
      ctx.moveTo(p.prevX, p.prevY);
      ctx.lineTo(p.x, p.y);

      if (p.plumeIntensity > 0.25) {
        // High stubble smoke plume: intense fiery amber/crimson
        const g = Math.floor(68 + (1.0 - p.plumeIntensity) * 110);
        ctx.strokeStyle = `rgba(239, ${g}, 68, ${alpha})`;
      } else {
        // Clean air stream: clear electric cyan
        ctx.strokeStyle = `rgba(56, 189, 248, ${alpha * 0.65})`;
      }
      ctx.stroke();

      // Respawn out of bounds or expired particles
      if (p.age >= p.maxAge || p.x < 0 || p.x > this.width || p.y < 0 || p.y > this.height) {
        this.particles[i] = this.createParticle(false);
      }
    }
  }

  drawLandmarks() {
    const ctx = this.ctx;

    // 1. Draw Active Stubble Fire Hotspots
    if (this.plumeMode) {
      for (const fire of this.fireClusters) {
        const { x, y } = this.latLonToCanvas(fire.lat, fire.lon);
        // Fire halo pulse
        ctx.beginPath();
        ctx.arc(x, y, 9 + Math.sin(Date.now() / 200) * 2, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(239, 68, 68, 0.22)';
        ctx.fill();

        ctx.beginPath();
        ctx.arc(x, y, 4, 0, Math.PI * 2);
        ctx.fillStyle = '#ef4444';
        ctx.fill();

        ctx.fillStyle = '#fca5a5';
        ctx.font = '10px monospace';
        ctx.fillText(`🔥 ${fire.name}`, x + 7, y + 3);
      }
    }

    // 2. Draw Delhi NCR Receptor Station
    const dPos = this.latLonToCanvas(this.delhi.lat, this.delhi.lon);
    ctx.beginPath();
    ctx.arc(dPos.x, dPos.y, 14, 0, Math.PI * 2);
    ctx.strokeStyle = '#10b981';
    ctx.lineWidth = 2;
    ctx.stroke();

    ctx.beginPath();
    ctx.arc(dPos.x, dPos.y, 5, 0, Math.PI * 2);
    ctx.fillStyle = '#10b981';
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 11px sans-serif';
    ctx.fillText('🎯 Delhi NCR Command', dPos.x + 12, dPos.y + 4);
  }
}

window.WindParticleEngine = WindParticleEngine;
