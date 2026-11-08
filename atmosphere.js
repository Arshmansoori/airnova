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
  document.querySelectorAll('.range, .spectrum, .meter, .weather, .showcase-card, .matrix-card, .w-metric-card, .hourly-preview').forEach(el => {
    el.addEventListener('pointermove', e => {
      if (quiet()) return;
      const r = el.getBoundingClientRect();
      el.style.setProperty('--gx', `${((e.clientX - r.left) / r.width * 100).toFixed(1)}%`);
      el.style.setProperty('--gy', `${((e.clientY - r.top) / r.height * 100).toFixed(1)}%`);
    }, { passive: true });
  });

  })();
