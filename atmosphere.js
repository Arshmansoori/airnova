/* Local, code-rendered volumetric cloud, zero-lag 3D scroll physics & silky spatial parallax. No dependencies. */
window.AN_PHYSICS = { mx: 0, my: 0, sp: 0, sv: 0 };

(() => {
  'use strict';
  const root = document.documentElement;
  const media = matchMedia('(prefers-reduced-motion: reduce)');
  const quiet = () => root.classList.contains('still') || media.matches;
  const fine = matchMedia('(pointer:fine)');

  let targetX = 0, targetY = 0;
  let currentX = 0, currentY = 0;
  let lastScrollY = window.scrollY;
  let targetVelocity = 0, scrollVelocity = 0;
  let animating = false;

  function updatePhysics() {
    if (quiet()) {
      window.AN_PHYSICS.mx = 0;
      window.AN_PHYSICS.my = 0;
      window.AN_PHYSICS.sp = 0;
      window.AN_PHYSICS.sv = 0;
      root.style.setProperty('--mx', '0');
      root.style.setProperty('--my', '0');
      root.style.setProperty('--scroll-p', '0');
      root.style.setProperty('--scroll-v', '0');
      animating = false;
      return;
    }

    // High-precision luxury damping (lerp factor 0.085 for instant liquid inertia)
    currentX += (targetX - currentX) * 0.085;
    currentY += (targetY - currentY) * 0.085;

    // Scroll progress & velocity damping
    const maxScroll = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
    const scrollProgress = Math.min(1, Math.max(0, window.scrollY / maxScroll));
    scrollVelocity += (targetVelocity - scrollVelocity) * 0.14;
    targetVelocity *= 0.85;

    const normalizedVelocity = scrollVelocity / 45;

    // Update shared memory state (ZERO DOM style recalculation lag for canvas)
    window.AN_PHYSICS.mx = currentX;
    window.AN_PHYSICS.my = currentY;
    window.AN_PHYSICS.sp = scrollProgress;
    window.AN_PHYSICS.sv = normalizedVelocity;

    root.style.setProperty('--mx', currentX.toFixed(4));
    root.style.setProperty('--my', currentY.toFixed(4));
    root.style.setProperty('--scroll-p', scrollProgress.toFixed(4));
    root.style.setProperty('--scroll-v', normalizedVelocity.toFixed(4));

    const needsMotion = 
      Math.abs(targetX - currentX) > 0.0003 || 
      Math.abs(targetY - currentY) > 0.0003 || 
      Math.abs(scrollVelocity) > 0.04 ||
      Math.abs(targetVelocity) > 0.04;

    if (needsMotion) {
      requestAnimationFrame(updatePhysics);
    } else {
      animating = false;
    }
  }

  function startPhysics() {
    if (!animating) {
      animating = true;
      requestAnimationFrame(updatePhysics);
    }
  }

  addEventListener('pointermove', e => {
    if (!fine.matches || quiet()) return;
    targetX = (e.clientX / innerWidth - 0.5) * 2;
    targetY = (e.clientY / innerHeight - 0.5) * 2;
    startPhysics();
  }, { passive: true });

  window.addEventListener('scroll', () => {
    if (quiet()) return;
    const currentY = window.scrollY;
    const delta = currentY - lastScrollY;
    lastScrollY = currentY;
    targetVelocity = Math.max(-50, Math.min(50, delta));
    startPhysics();
  }, { passive: true });

  // Mobile Gyroscope 3D Tilt
  if (window.DeviceOrientationEvent) {
    window.addEventListener('deviceorientation', e => {
      if (quiet() || e.gamma === null || e.beta === null) return;
      targetX = Math.max(-1, Math.min(1, e.gamma / 22));
      targetY = Math.max(-1, Math.min(1, (e.beta - 35) / 22));
      startPhysics();
    }, { passive: true });
  }

  document.addEventListener('pointerleave', () => {
    targetX = 0;
    targetY = 0;
    startPhysics();
  });

  // Dynamic specular light reflection inside cards
  document.querySelectorAll('.range, .spectrum, .meter, .weather').forEach(el => {
    el.addEventListener('pointermove', e => {
      if (quiet()) return;
      const r = el.getBoundingClientRect();
      el.style.setProperty('--gx', `${((e.clientX - r.left) / r.width * 100).toFixed(1)}%`);
      el.style.setProperty('--gy', `${((e.clientY - r.top) / r.height * 100).toFixed(1)}%`);
    }, { passive: true });
  });

  const host = document.querySelector('.weather-icon');
  if (!host) return;
  const canvas = document.createElement('canvas');
  canvas.className = 'weather-cloud';
  canvas.setAttribute('aria-hidden', 'true');
  const ctx = canvas.getContext('2d');
  if (!ctx) return;
  const width = 300, height = 184;
  canvas.width = width;
  canvas.height = height;
  const image = ctx.createImageData(width, height);

  // Repeatable 3D noise field interpolated smoothly
  const N = 32, field = new Float32Array(N * N * N);
  let seed = 713;
  for (let i = 0; i < field.length; i++) {
    seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0;
    field[i] = seed / 4294967296;
  }
  const smooth = v => v * v * (3 - 2 * v), mix = (a, b, t) => a + (b - a) * t;
  function noise(x, y, z) {
    const ix = Math.floor(x), iy = Math.floor(y), iz = Math.floor(z);
    const fx = smooth(x - ix), fy = smooth(y - iy), fz = smooth(z - iz);
    const x0 = ix & 31, x1 = (ix + 1) & 31;
    const y0 = iy & 31, y1 = (iy + 1) & 31;
    const z0 = iz & 31, z1 = (iz + 1) & 31;
    const a = z0 * 1024, b = z1 * 1024;
    return mix(
      mix(mix(field[a + y0 * 32 + x0], field[a + y0 * 32 + x1], fx), mix(field[a + y1 * 32 + x0], field[a + y1 * 32 + x1], fx), fy),
      mix(mix(field[b + y0 * 32 + x0], field[b + y0 * 32 + x1], fx), mix(field[b + y1 * 32 + x0], field[b + y1 * 32 + x1], fx), fy),
      fz
    );
  }
  const lobes = [[-.83, -.12, .04, .37], [-.44, .12, .03, .48], [.02, .36, .1, .57], [.46, .13, .05, .44], [.79, -.13, .03, .31], [-.12, -.12, .02, .59]];
  function density(x, y, z) {
    let shape = -9;
    for (let i = 0; i < lobes.length; i++) {
      const b = lobes[i], dx = x - b[0], dy = (y - b[1]) * 1.12, dz = (z - b[2]) * .95;
      shape = Math.max(shape, b[3] - Math.sqrt(dx * dx + dy * dy + dz * dz));
    }
    shape = Math.min(shape, (y + .38) * 1.8);
    if (shape < -.1) return 0;
    const n = noise(x * 5.3 + 10, y * 5.3 + 12, z * 5.3 + 9) * .7 + noise(x * 12 + 20, y * 12 + 16, z * 12 + 12) * .3;
    return Math.max(0, shape + (n - .5) * .16) * 6.2;
  }

  let row = 0;
  function renderChunk() {
    const end = Math.min(height, row + 14);
    for (; row < end; row++) {
      for (let col = 0; col < width; col++) {
        const x = (col / width - .5) * 2.75, y = (.59 - row / height) * 1.72;
        let alpha = 0, red = 0, green = 0, blue = 0;
        for (let step = 0; step < 28 && alpha < .995; step++) {
          const z = -.9 + step * .064, d = density(x, y, z);
          if (d <= 0) continue;
          const shade = density(x + .1, y + .18, z - .12) * .45 + density(x + .2, y + .36, z - .24) * .3 + density(x + .3, y + .54, z - .36) * .2;
          const transmission = Math.exp(-shade * 1.8);
          const elevation = Math.max(0, Math.min(1, (y + .35) * .85));
          const light = Math.min(1, .12 + transmission * .75 + elevation * .13);
          const absorb = 1 - Math.exp(-d * .23), weight = (1 - alpha) * absorb;
          red += (103 + light * 146) * weight;
          green += (130 + light * 120) * weight;
          blue += (159 + light * 92) * weight;
          alpha += weight;
        }
        const offset = (row * width + col) * 4;
        if (alpha > 0) {
          image.data[offset] = red / alpha;
          image.data[offset + 1] = green / alpha;
          image.data[offset + 2] = blue / alpha;
          image.data[offset + 3] = Math.min(255, alpha * 270);
        }
      }
    }
    if (row < height) {
      requestAnimationFrame(renderChunk);
      return;
    }
    ctx.putImageData(image, 0, 0);
    host.append(canvas);
    host.classList.add('volumetric');
  }
  requestAnimationFrame(renderChunk);
})();
