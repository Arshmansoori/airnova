const $ = id => document.getElementById(id);
const api = (action, params={}) => {
  const q = new URLSearchParams({action, ...params});
  return fetch(`api.php?${q.toString()}`, {cache:'no-store'});
};
const fmt=(v,d=0)=>(v===null||v===undefined||Number.isNaN(Number(v)))?'—':Number(v).toFixed(d);
const timeFmt=s=>{ if(!s)return'—'; const raw=/[zZ]|[+-]\d{2}:?\d{2}$/.test(s)?s:`${s}+05:30`; const d=new Date(raw); return Number.isNaN(d.getTime())?s:d.toLocaleString('en-IN',{timeZone:'Asia/Kolkata',day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}); };
function aqiClass(aqi){if(aqi<=50)return'good';if(aqi<=100)return'sat';if(aqi<=200)return'moderate';if(aqi<=300)return'poor';if(aqi<=400)return'verypoor';return'severe';}

async function init(){
  try{
    const health=await api('health').then(r=>r.json());
    $('statusBadge').textContent=health.status==='ok'?'PHP backend ready':'Backend issue';
    const presets=await api('presets').then(r=>r.json());
    $('preset').innerHTML=presets.map((p,i)=>`<option value="${i}">${p.name}</option>`).join('');
    $('preset').onchange=()=>{const p=presets[+$('preset').value];$('lat').value=p.lat;$('lon').value=p.lon;};
    $('loadBtn').onclick=loadForecast;
    await loadForecast();
  }catch(e){$('statusBadge').textContent='Setup error'; showError(e.message);}
}

function showError(message){
  $('inversionBox').innerHTML=`<div class="error-box">${escapeHtml(message)}</div>`;
  $('plumeBox').innerHTML=`<div class="error-box">Open <code>api.php?action=upstreams</code> to check API connectivity.</div>`;
}
function escapeHtml(s){return String(s).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}

async function loadForecast(){
  const lat=Number($('lat').value),lon=Number($('lon').value);
  $('statusBadge').textContent='Loading live forecast…';$('loadBtn').disabled=true;
  try{
    const r=await api('forecast',{lat,lon,hours:72});
    const data=await r.json();
    if(!r.ok)throw new Error(data.detail||data.error||'Forecast failed');
    render(data);$('statusBadge').textContent='Live forecast loaded';
  }catch(e){$('statusBadge').textContent='Forecast error';showError(e.message);}
  finally{$('loadBtn').disabled=false;}
}

function render(data){
  const s=data.summary,hours=data.hours;
  $('currentAqi').textContent=s.current_aqi;$('currentAqi').className=`big ${aqiClass(s.current_aqi)}`;$('currentCat').textContent=s.current_category;
  $('peakAqi').textContent=s.peak_aqi;$('peakAqi').className=`big ${aqiClass(s.peak_aqi)}`;$('peakTime').textContent=timeFmt(s.peak_aqi_time);
  $('invScore').textContent=s.max_inversion_score;$('invTime').textContent=timeFmt(s.max_inversion_time);
  $('plumeScore').textContent=s.max_plume_score;$('plumeTime').textContent=timeFmt(s.max_plume_time);
  $('sourceMode').textContent=data.metadata.ground_calibration;
  drawChart(hours);
  const peakInv=hours.reduce((a,b)=>a.inversion.score>b.inversion.score?a:b);
  $('inversionBox').innerHTML=`
    <div class="metric-row"><span>Strength</span><b>${peakInv.inversion.level} • ${peakInv.inversion.score}/100</b></div>
    <div class="metric-row"><span>PBL height</span><b>${fmt(peakInv.weather.pbl_height_m)} m</b></div>
    <div class="metric-row"><span>ΔT (925 hPa − surface)</span><b>${fmt(peakInv.inversion.temperature_inversion_c,1)} °C</b></div>
    <div class="metric-row"><span>Mixing risk</span><b>${peakInv.inversion.mixing_risk}</b></div>`;
  const peakPlume=hours.reduce((a,b)=>a.stubble_plume.score>b.stubble_plume.score?a:b);
  $('plumeBox').innerHTML=`
    <div class="metric-row"><span>Risk</span><b>${peakPlume.stubble_plume.level} • ${peakPlume.stubble_plume.score}/100</b></div>
    <div class="metric-row"><span>Aligned hotspots</span><b>${peakPlume.stubble_plume.aligned_hotspots}</b></div>
    <div class="metric-row"><span>Detected hotspots</span><b>${peakPlume.stubble_plume.total_hotspots}</b></div>
    <div class="metric-row"><span>Source</span><b>${data.metadata.fire_source}</b></div>`;
  $('forecastRows').innerHTML=hours.map((h,i)=>`<tr>
    <td>${timeFmt(h.time)} <span class="pill">+${i}h</span></td>
    <td><strong class="${aqiClass(h.aqi)}">${h.aqi}</strong><br><small>${h.category}</small></td>
    <td>${fmt(h.pollutants_ugm3.pm2_5,1)}</td><td>${fmt(h.pollutants_ugm3.pm10,1)}</td><td>${fmt(h.pollutants_ugm3.o3,1)}</td><td>${fmt(h.pollutants_ugm3.no2,1)}</td>
    <td>${fmt(h.weather.pbl_height_m)} m</td><td>${fmt(h.weather.wind_speed_10m_kmh,1)} km/h<br><small>${fmt(h.weather.wind_direction_10m_deg)}°</small></td>
    <td>${h.inversion.level} ${h.inversion.score}</td><td>${h.stubble_plume.level} ${h.stubble_plume.score}</td>
  </tr>`).join('');
}

function drawChart(hours){
  const svg=$('aqiChart'),W=1200,H=360,pad=42,vals=hours.map(h=>Number(h.aqi)||0),max=Math.max(500,...vals),min=0;
  const x=i=>pad+i*(W-pad*2)/Math.max(1,hours.length-1),y=v=>H-pad-(v-min)*(H-pad*2)/(max-min);
  const points=vals.map((v,i)=>`${x(i)},${y(v)}`).join(' '),bands=[50,100,200,300,400,500];
  const grid=bands.map(v=>`<line x1="${pad}" x2="${W-pad}" y1="${y(v)}" y2="${y(v)}" stroke="rgba(255,255,255,.08)"/><text x="8" y="${y(v)+4}" fill="#7794a8" font-size="11">${v}</text>`).join('');
  const idx=[0,12,24,36,48,60,71].filter(i=>i<hours.length),ticks=idx.map(i=>`<text x="${x(i)}" y="${H-10}" fill="#7794a8" font-size="11" text-anchor="middle">+${i}h</text>`).join('');
  svg.innerHTML=`${grid}${ticks}<polyline points="${points}" fill="none" stroke="#52e3c2" stroke-width="4" vector-effect="non-scaling-stroke"/><circle cx="${x(0)}" cy="${y(vals[0])}" r="7" fill="#fff"/>`;
}

/* ========================================================
   AIRVISION CAM & WIND PARTICLES INTEGRATION
   ======================================================== */
let airVisionEngine = null;
let currentVisionResult = null;
let isShowingDehazed = false;

function initAirVision() {
  if (!window.AirVisionEngine) return;
  airVisionEngine = new window.AirVisionEngine();

  const fileInput = $('camFileInput');
  const placeholder = $('camPlaceholder');
  const canvas = $('camCanvas');
  const btnToggle = $('btnToggleDehaze');
  const btnSample = $('btnSampleDelhi');

  const processImage = (img) => {
    $('camStatusBadge').textContent = 'Analyzing optics…';
    setTimeout(() => {
      currentVisionResult = airVisionEngine.analyze(img, { relativeHumidity: 65 });
      placeholder.style.display = 'none';
      canvas.style.display = 'block';

      // Draw original by default
      const ctx = canvas.getContext('2d');
      canvas.width = currentVisionResult.dimensions.width;
      canvas.height = currentVisionResult.dimensions.height;
      ctx.drawImage(currentVisionResult.originalCanvas, 0, 0);
      isShowingDehazed = false;

      // Update UI readouts
      $('vStatAqi').textContent = currentVisionResult.aqi;
      $('vStatAqi').style.color = currentVisionResult.color;
      $('vStatPm25').textContent = `${currentVisionResult.estimatedPm25} µg/m³`;
      $('vStatHaze').textContent = `${currentVisionResult.hazeDensityPercent}%`;
      $('camStatusBadge').textContent = `${currentVisionResult.category} (AQI ${currentVisionResult.aqi})`;
      $('camStatusBadge').style.borderColor = currentVisionResult.color;
      $('camStatusBadge').style.color = currentVisionResult.color;

      $('vHealthAlert').innerHTML = `
        <strong style="color:${currentVisionResult.color}">● ${currentVisionResult.category} Air Quality Detected:</strong>
        ${currentVisionResult.healthGuidance}
        <br><small style="color:var(--muted)">Airlight vector A=(${currentVisionResult.airlight.r}, ${currentVisionResult.airlight.g}, ${currentVisionResult.airlight.b}) | Mean Transmission t=${currentVisionResult.avgTransmission}</small>
      `;

      btnToggle.style.display = 'inline-block';
      btnToggle.textContent = '✨ View Dehazed Reconstruction';
    }, 40);
  };

  fileInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (event) => {
      const img = new Image();
      img.onload = () => processImage(img);
      img.src = event.target.result;
    };
    reader.readAsDataURL(file);
  });

  btnToggle.addEventListener('click', () => {
    if (!currentVisionResult) return;
    const ctx = canvas.getContext('2d');
    if (isShowingDehazed) {
      ctx.drawImage(currentVisionResult.originalCanvas, 0, 0);
      btnToggle.textContent = '✨ View Dehazed Reconstruction';
      isShowingDehazed = false;
    } else {
      ctx.drawImage(currentVisionResult.dehazedCanvas, 0, 0);
      btnToggle.textContent = '🔄 View Original Hazy Scene';
      isShowingDehazed = true;
    }
  });

  // Built-in synthetic procedural Delhi smog test generator
  btnSample.addEventListener('click', () => {
    const tCanvas = document.createElement('canvas');
    tCanvas.width = 460;
    tCanvas.height = 300;
    const tCtx = tCanvas.getContext('2d');

    // Sky with heavy smog gradient
    const skyGrad = tCtx.createLinearGradient(0, 0, 0, 200);
    skyGrad.addColorStop(0, '#c7bfae');
    skyGrad.addColorStop(1, '#dfd5c4');
    tCtx.fillStyle = skyGrad;
    tCtx.fillRect(0, 0, 460, 200);

    // Far skyline silhouette (washed out by smog)
    tCtx.fillStyle = 'rgba(150, 140, 130, 0.45)';
    tCtx.fillRect(40, 120, 50, 80);
    tCtx.fillRect(110, 90, 70, 110);
    tCtx.fillRect(200, 130, 90, 70);
    tCtx.fillRect(320, 80, 80, 120);

    // Near monuments / buildings (higher contrast but hazy)
    tCtx.fillStyle = '#6e5e52';
    tCtx.fillRect(70, 150, 60, 50);
    tCtx.fillRect(230, 140, 75, 60);

    // Ground road
    tCtx.fillStyle = '#4a433d';
    tCtx.fillRect(0, 200, 460, 100);

    // Apply atmospheric haze blanket
    tCtx.fillStyle = 'rgba(215, 205, 195, 0.55)';
    tCtx.fillRect(0, 0, 460, 300);

    const testImg = new Image();
    testImg.onload = () => processImage(testImg);
    testImg.src = tCanvas.toDataURL();
  });
}

let windEngine = null;
function initWindSimulation() {
  const canvas = $('windCanvas');
  if (!canvas || !window.WindParticleEngine) return;

  windEngine = new window.WindParticleEngine(canvas, {
    particleCount: 1800,
    speedMultiplier: 1.1,
    plumeMode: true
  });
  windEngine.start();

  window.addEventListener('resize', () => {
    if (windEngine) windEngine.resize();
  });

  const slider = $('windHourSlider');
  const hourLbl = $('windHourLbl');
  slider.addEventListener('input', (e) => {
    const val = +e.target.value;
    hourLbl.textContent = `+${val} Hours Forecast`;
    windEngine.setHour(val);
  });

  const btnPlume = $('btnPlumeToggle');
  const plumeBadge = $('plumeStatusBadge');
  btnPlume.addEventListener('click', () => {
    const isPlume = !windEngine.plumeMode;
    windEngine.setPlumeMode(isPlume);
    if (isPlume) {
      plumeBadge.textContent = '🔥 Plume Mode Active';
      plumeBadge.className = 'toggle-badge toggle-active';
      btnPlume.textContent = 'Disable Stubble Plumes';
    } else {
      plumeBadge.textContent = '💨 Pure Wind Vectors';
      plumeBadge.className = 'toggle-badge toggle-inactive';
      btnPlume.textContent = 'Enable Stubble Plumes';
    }
  });

  const btnSpeed = $('btnSpeedToggle');
  const speeds = [1.0, 1.6, 2.2, 0.6];
  let speedIdx = 0;
  btnSpeed.addEventListener('click', () => {
    speedIdx = (speedIdx + 1) % speeds.length;
    const spd = speeds[speedIdx];
    windEngine.setSpeed(spd);
    btnSpeed.textContent = `Speed: ${spd.toFixed(1)}x`;
  });
}

// Enhance standard init to launch deep-tech modules
const originalInit = init;
init = async function() {
  await originalInit();
  initAirVision();
  initWindSimulation();
};

