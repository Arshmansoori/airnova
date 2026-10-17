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
init();
