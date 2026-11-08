(() => {
 'use strict';
 const $ = s => document.querySelector(s), $$ = s => [...document.querySelectorAll(s)];

 const cities = [
  {id:'delhi',name:'Delhi NCR',country:'India',aqi:162,low:94,high:182,temp:28,feels:31,condition:'Partly Cloudy',humidity:64,wind:12},
  {id:'mumbai',name:'Mumbai',country:'India',aqi:89,low:61,high:118,temp:30,feels:34,condition:'Warm & Breezy',humidity:78,wind:18},
  {id:'bengaluru',name:'Bengaluru',country:'India',aqi:54,low:32,high:76,temp:24,feels:25,condition:'Mostly Sunny',humidity:58,wind:9},
  {id:'london',name:'London',country:'United Kingdom',aqi:32,low:18,high:48,temp:12,feels:10,condition:'Overcast',humidity:82,wind:16},
  {id:'singapore',name:'Singapore',country:'Singapore',aqi:47,low:28,high:62,temp:31,feels:35,condition:'Partly Cloudy',humidity:80,wind:11}
 ];

 const storage = {
  get(k, f) { try { return localStorage.getItem(k) ?? f; } catch { return f; } },
  set(k, v) { try { localStorage.setItem(k, v); } catch {} }
 };

 let city = cities.find(c => c.id === storage.get('airnova-simple-city', 'delhi')) || cities[0];
 let unit = storage.get('airnova-simple-unit', 'c');
 let still = storage.get('airnova-simple-motion', '') === 'pause' || matchMedia('(prefers-reduced-motion: reduce)').matches;

 const status = v => {
  const isLight = document.documentElement.getAttribute('data-theme') === 'light';
  if (isLight) {
   return v <= 50 ? { text: 'Good', color: '#00875a', rgb: '0, 135, 90' } :
          v <= 100 ? { text: 'Moderate', color: '#b7791f', rgb: '183, 121, 31' } :
          v <= 150 ? { text: 'Sensitive groups', color: '#d97706', rgb: '217, 119, 6' } :
          v <= 200 ? { text: 'Unhealthy', color: '#dc2626', rgb: '220, 38, 38' } :
          v <= 300 ? { text: 'Very unhealthy', color: '#7c3aed', rgb: '124, 58, 237' } :
                     { text: 'Hazardous', color: '#be185d', rgb: '190, 24, 93' };
  }
  return v <= 50 ? { text: 'Good', color: '#00f5a0', rgb: '0, 245, 160' } :
         v <= 100 ? { text: 'Moderate', color: '#ffd166', rgb: '255, 209, 102' } :
         v <= 150 ? { text: 'Sensitive groups', color: '#ff9f43', rgb: '255, 159, 67' } :
         v <= 200 ? { text: 'Unhealthy', color: '#ff4757', rgb: '255, 71, 87' } :
         v <= 300 ? { text: 'Very unhealthy', color: '#a55eea', rgb: '165, 94, 234' } :
                    { text: 'Hazardous', color: '#ff007f', rgb: '255, 0, 127' };
 };

 const fraction = value => {
  const bands = [0, 50, 100, 150, 200, 300, 500];
  const i = Math.min(5, Math.max(0, bands.findIndex((v, idx) => idx < 6 && value >= v && value <= bands[idx + 1])));
  return (i + (value - bands[i]) / (bands[i + 1] - bands[i])) / 6;
 };

 const arc = $('.arc'), length = arc.getTotalLength();
 const spectrum = $('.spectrum');
 const spectrumPointer = $('#spectrum-pointer');
 const spectrumLabels = $$('.spectrum-labels > span');
 const requestedView = new URLSearchParams(location.search).get('view');
 const view = ['aqi', 'weather'].includes(requestedView) ? requestedView : 'home';

 // Follow arc geometry so the graduations stay aligned
 for (let i = 0; i <= 60; i++) {
  const p = arc.getPointAtLength(length * i / 60);
  const cx = 200, cy = 200;
  const dx = p.x - cx, dy = p.y - cy;
  const r = Math.hypot(dx, dy);
  const inner = i % 5 === 0 ? 12 : 7;
  const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
  line.setAttribute('x1', cx + dx / r * (r - 12));
  line.setAttribute('y1', cy + dy / r * (r - 12));
  line.setAttribute('x2', cx + dx / r * (r - 12 - inner));
  line.setAttribute('y2', cy + dy / r * (r - 12 - inner));
  if (i % 5 === 0) line.classList.add('major');
  $('#ticks').append(line);
 }

 let countFrame;
 function render() {
  $('#city-label').textContent = city.name;
  const st = status(city.aqi);
  $('#aqi-status').innerHTML = '<i></i>' + st.text;
  $('#aqi-status').style.color = st.color;
  $('#aqi-status').style.setProperty('--status-color', st.color);
  $('#aqi-status').style.setProperty('--status-rgb', st.rgb);
  document.documentElement.style.setProperty('--active-aqi-color', st.color);
  document.documentElement.style.setProperty('--active-aqi-rgb', st.rgb);

  const f = fraction(city.aqi);
  $$('.arc,.arc-glow').forEach(el => {
   el.style.strokeDasharray = length;
   el.style.strokeDashoffset = length * (1 - f);
  });
  const p = arc.getPointAtLength(length * f);
  $('#arc-point').setAttribute('cx', p.x);
  $('#arc-point').setAttribute('cy', p.y);

  // Smooth count-up animation
  cancelAnimationFrame(countFrame);
  const from = Number($('#aqi-value').textContent) || 0;
  const t = performance.now();
  function count(now) {
   const p = still ? 1 : Math.min(1, (now - t) / 1200);
   $('#aqi-value').textContent = Math.round(from + (city.aqi - from) * (1 - (1 - p) ** 3));
   if (p < 1) countFrame = requestAnimationFrame(count);
  }
  countFrame = requestAnimationFrame(count);

  $('#aqi-low').textContent = city.low;
  $('#aqi-high').textContent = city.high;

  // Spectrum updates
  if (spectrum) {
   spectrum.style.setProperty('--reading-color', st.color);
   spectrum.style.setProperty('--reading-rgb', st.rgb);
  }
  if (spectrumPointer) {
   spectrumPointer.style.left = Math.max(1, Math.min(99, f * 100)) + '%';
   const valEl = $('#spectrum-val') || spectrumPointer.firstElementChild;
   if (valEl) valEl.textContent = city.aqi;
  }
  const specStatusText = $('#spec-status-text');
  if (specStatusText) {
   specStatusText.textContent = `${st.text} · ${city.aqi} AQI`;
   specStatusText.style.color = st.color;
  }
  const specDot = $('#spectrum-badge .spec-dot');
  if (specDot) {
   specDot.style.background = st.color;
   specDot.style.boxShadow = `0 0 10px ${st.color}`;
  }

  const currentBand = Math.max(0, Math.ceil(f * spectrumLabels.length) - 1);
  spectrumLabels.forEach((label, index) => {
   if (index === currentBand) label.setAttribute('aria-current', 'true');
   else label.removeAttribute('aria-current');
  });

  const c = v => unit === 'f' ? Math.round(v * 9 / 5 + 32) : v;
  $('#temperature').textContent = c(city.temp);
  $('#unit-button').innerHTML = '°<small>' + unit.toUpperCase() + '</small>';
  $('#unit-button').setAttribute('aria-label', 'Switch temperature to ' + (unit === 'c' ? 'Fahrenheit' : 'Celsius'));
  $('#feels').textContent = c(city.feels) + '°' + unit.toUpperCase();
  $('#condition').textContent = city.condition;

  const sunEl = $('.sun-wrap') || $('.sun-sphere');
  if (sunEl) sunEl.style.opacity = city.id === 'london' ? '.45' : '1';

  if (view !== 'home') renderDetail(c);
 }

 function selectCity(id) {
  const selected = cities.find(candidate => candidate.id === id);
  if (!selected) return;
  city = selected;
  storage.set('airnova-simple-city', city.id);
  render();
 }

 function renderDetail(convertTemperature) {
  $('[data-current-city]').textContent = city.name;
  if (view === 'weather') {
   $('[data-detail-humidity]').textContent = city.humidity + '%';
   $('[data-detail-wind]').textContent = city.wind + ' km/h';
  }
  $$('[data-compare-city]').forEach(button => {
   const reading = cities.find(candidate => candidate.id === button.dataset.compareCity);
   button.setAttribute('aria-pressed', String(reading.id === city.id));
   const value = button.querySelector('.detail-city-value');
   if (view === 'aqi') {
    const category = status(reading.aqi);
    value.textContent = reading.aqi + ' AQI';
    value.style.color = category.color;
   } else {
    value.textContent = convertTemperature(reading.temp) + '°' + unit.toUpperCase();
   }
  });
 }

 if (view !== 'home') {
  const main = $('#home');
  main.append($('#' + view + '-view').content.cloneNode(true));
  $('[data-instrument]').append($('#' + view));
  if (view === 'aqi') $('[data-spectrum-slot]').append(spectrum);
  else spectrum.hidden = true;
  $('.hero').hidden = true;
  $$('.home-only').forEach(el => el.hidden = true);
  document.title = (view === 'aqi' ? 'Air quality' : 'Weather') + ' | Airnova';
  const list = $('[data-city-list]');
  cities.forEach(reading => {
   const button = document.createElement('button');
   button.type = 'button';
   button.className = 'detail-city';
   button.dataset.compareCity = reading.id;
   const name = document.createElement('span');
   name.textContent = reading.name;
   const value = document.createElement('strong');
   value.className = 'detail-city-value';
   button.append(name, value);
   button.addEventListener('click', () => selectCity(reading.id));
   list.append(button);
  });
 }

 $('#unit-button').addEventListener('click', () => {
  unit = unit === 'c' ? 'f' : 'c';
  storage.set('airnova-simple-unit', unit);
  render();
 });

 function renderCities() {
  const q = $('#city-search').value.toLowerCase().trim();
  const found = cities.filter(c => (c.name + ' ' + c.country).toLowerCase().includes(q));
  $('#city-results').innerHTML = found.length ? found.map(c =>
   `<button class="city-result" data-city="${c.id}">
     <span><strong>${c.name}${c.id === city.id ? ' · selected' : ''}</strong><small>${c.country}</small></span>
     <span style="--color:${status(c.aqi).color}">${c.aqi}<small>AQI</small></span>
   </button>`
  ).join('') : '<p class="empty">No sample city found. Try Delhi, Mumbai, Bengaluru, London or Singapore.</p>';

  $$('.city-result').forEach(b => b.addEventListener('click', () => {
   selectCity(b.dataset.city);
   $('#city-dialog').close();
  }));
 }

 $('#city-button').addEventListener('click', () => {
  $('#city-search').value = '';
  renderCities();
  $('#city-dialog').showModal();
  $('#city-search').focus();
 });
 $('#city-search').addEventListener('input', renderCities);
 $('#close-dialog').addEventListener('click', () => $('#city-dialog').close());
 $('#city-dialog').addEventListener('click', e => {
  if (e.target !== e.currentTarget) return;
  const r = e.currentTarget.getBoundingClientRect();
  if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
   e.currentTarget.close();
  }
 });

 function nav() {
  $$('[data-tab]').forEach(link => {
   const active = link.dataset.tab === view;
   link.classList.toggle('active', active);
   if (active) link.setAttribute('aria-current', 'page');
   else link.removeAttribute('aria-current');
  });
  const section = location.hash.slice(1);
  $('#aqi').classList.toggle('focus-glow', view === 'home' && section === 'aqi');
  $('#weather').classList.toggle('focus-glow', view === 'home' && section === 'weather');
 }
 addEventListener('hashchange', nav);
 nav();

 function motion() {
  document.documentElement.classList.toggle('still', still);
  const motionBtn = $('#motion-button');
  if (motionBtn) {
   motionBtn.setAttribute('aria-label', still ? 'Play background animations' : 'Pause background animations');
   motionBtn.title = still ? 'Play animations' : 'Pause animations';
   const pauseIcon = $('.pause-icon');
   if (pauseIcon) pauseIcon.textContent = still ? '▷' : 'Ⅱ';
   motionBtn.setAttribute('aria-pressed', String(still));
  }
 }
 motion();
 const motionBtn = $('#motion-button');
 if (motionBtn) {
  motionBtn.addEventListener('click', () => {
   still = !still;
   storage.set('airnova-simple-motion', still ? 'pause' : 'play');
   motion();
  });
 }

 // Theme Switcher: Dark (Signature Atmospheric Glass) & Light (Crisp White Mode)
 const themeKey = 'airnova-theme-mode';
 let currentTheme = storage.get(themeKey, 'dark');
 if (currentTheme !== 'light') currentTheme = 'dark';

 function applyTheme(theme) {
  if (!['light', 'dark'].includes(theme)) theme = 'dark';
  currentTheme = theme;
  document.documentElement.setAttribute('data-theme', theme);
  storage.set(themeKey, theme);
  $$('[data-theme-btn]').forEach(btn => {
   const isActive = btn.dataset.themeBtn === theme;
   btn.classList.toggle('active', isActive);
   btn.setAttribute('aria-checked', String(isActive));
  });
  if (typeof render === 'function') render();
 }
 applyTheme(currentTheme);

 $$('[data-theme-btn]').forEach(btn => {
  btn.addEventListener('click', () => applyTheme(btn.dataset.themeBtn));
 });

 addEventListener('pageshow', event => {
  if (!event.persisted) return;
  city = cities.find(candidate => candidate.id === storage.get('airnova-simple-city', 'delhi')) || cities[0];
  unit = storage.get('airnova-simple-unit', 'c');
  still = storage.get('airnova-simple-motion', '') === 'pause' || matchMedia('(prefers-reduced-motion: reduce)').matches;
  motion();
  currentTheme = storage.get(themeKey, 'dark');
  if (currentTheme !== 'light') currentTheme = 'dark';
  applyTheme(currentTheme);
  render();
 });

 // True 3D Particle Space in Canvas
 const canvas = $('#world'), ctx = canvas.getContext('2d');
 let w = 0, h = 0, last = 0;
 const stars = Array.from({length: 80}, () => ({
  x: Math.random(),
  y: Math.random() * 0.7,
  z: Math.random() * 0.8 + 0.2,
  r: Math.random() * 0.8 + 0.2,
  phase: Math.random() * 6
 }));
 const motes = Array.from({length: 56}, () => ({
  x: Math.random(),
  y: Math.random(),
  z: Math.random() * 2.4 + 0.3,
  speed: Math.random() * 0.008 + 0.003,
  phase: Math.random() * 6
 }));

 function resize() {
  w = innerWidth;
  h = innerHeight;
  const dpr = Math.min(devicePixelRatio || 1, 1.5);
  canvas.width = w * dpr;
  canvas.height = h * dpr;
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
 }
 addEventListener('resize', resize);
 resize();

 function draw(time) {
  requestAnimationFrame(draw);
  if (document.hidden || time - last < 30) return;
  const dt = Math.min(0.05, (time - last) / 1000);
  last = time;
  ctx.clearRect(0, 0, w, h);

  const phys = window.AN_PHYSICS || { mx: 0, my: 0, sv: 0 };
  const mx = phys.mx || 0;
  const my = phys.my || 0;
  const sv = phys.sv || 0;

  const isLight = currentTheme === 'light';
  stars.forEach(s => {
   const alpha = (isLight ? 0.08 : 0.16) + 0.18 * (0.5 + 0.5 * Math.sin((still ? 0 : time * 0.00045) + s.phase));
   ctx.fillStyle = isLight ? `rgba(40, 95, 160, ${alpha})` : `rgba(205, 235, 255, ${alpha})`;
   ctx.beginPath();
   const sx = s.x * w + (mx * -10 * s.z);
   const sy = s.y * h + (my * -8 * s.z) + (sv * -15 * s.z);
   ctx.arc(sx, sy, s.r, 0, Math.PI * 2);
   ctx.fill();
  });

  if (still) return;

  const currentSt = status(city.aqi);
  motes.forEach(p => {
   const warp = Math.abs(sv) * 0.02;
   p.y -= (p.speed + warp) * dt * (p.z * 0.75);
   if (p.y < -0.05) {
    p.y = 1.05;
    p.x = Math.random();
   }
   ctx.beginPath();
   const pulse = 0.15 + 0.35 * (0.5 + 0.5 * Math.sin(time * 0.0006 + p.phase));
   ctx.fillStyle = isLight ? `rgba(2, 132, 199, ${pulse * 0.6})` : `rgba(${currentSt.rgb}, ${pulse})`;
   ctx.shadowBlur = (6 + Math.abs(sv) * 12) * p.z;
   ctx.shadowColor = `rgba(${currentSt.rgb}, 0.55)`;

   const px = p.x * w + Math.sin(time * 0.00015 + p.phase) * (14 * p.z) + (mx * -28 * p.z);
   const py = p.y * h + (my * -18 * p.z) + (sv * -30 * p.z);
   const pr = (0.5 + (p.phase % 1) * 0.45) * Math.min(2.4, p.z + Math.abs(sv) * 0.8);

   ctx.arc(px, py, pr, 0, Math.PI * 2);
   ctx.fill();
  });
  ctx.shadowBlur = 0;
 }
 requestAnimationFrame(draw);

 $('#aqi-value').textContent = '0';
 render();
})();
