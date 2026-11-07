const pollutants = [
  ['pm25', 'PM₂.₅', 'Fine particulate matter', 'pm2_5', '24 h', '60 µg/m³'],
  ['pm10', 'PM₁₀', 'Coarse particulate matter', 'pm10', '24 h', '100 µg/m³'],
  ['co', 'CO', 'Carbon monoxide', 'co', '8 h', '2 mg/m³'],
  ['no2', 'NO₂', 'Nitrogen dioxide', 'no2', '24 h', '80 µg/m³'],
  ['so2', 'SO₂', 'Sulfur dioxide', 'so2', '24 h', '80 µg/m³'],
  ['o3', 'O₃', 'Ground-level ozone', 'o3', '8 h', '100 µg/m³'],
  ['nh3', 'NH₃', 'Ammonia', 'nh3', '24 h', '400 µg/m³']
];

const hour = new Intl.DateTimeFormat('en-IN', {
  timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
});
const timestamp = new Intl.DateTimeFormat('en-IN', {
  timeZone: 'Asia/Kolkata', day: 'numeric', month: 'short',
  hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
});
const isReading = value => typeof value === 'number' && Number.isFinite(value) && value >= 0;
const chartValue = new Intl.NumberFormat('en-IN', {maximumFractionDigits: 1});

const indiaBands = [
  ['Good', '0–50', 50, '#6af1bc'],
  ['Satisfactory', '51–100', 100, '#bce989'],
  ['Moderate', '101–200', 200, '#ffe47a'],
  ['Poor', '201–300', 300, '#ffad7a'],
  ['Very Poor', '301–400', 400, '#ff809a'],
  ['Severe', '401+', Infinity, '#d6a1ff']
];
const usBands = [
  ['Good', '0–50', 50, '#6af1bc'],
  ['Moderate', '51–100', 100, '#ffe47a'],
  ['Sensitive groups', '101–150', 150, '#ffad7a'],
  ['Unhealthy', '151–200', 200, '#ff809a'],
  ['Very Unhealthy', '201–300', 300, '#d6a1ff'],
  ['Hazardous', '301+', Infinity, '#ef839f']
];

export function overviewObservations(indices, concentrations, dominant) {
  return pollutants.map(([key, label, name, field, period, limit]) => {
    const concentration = concentrations?.[field];
    const index = indices?.[key]?.v;
    const hasConcentration = isReading(concentration);
    const hasIndex = isReading(index);
    return {
      key, label, name, period, limit,
      kind: hasConcentration ? 'Concentration' : hasIndex ? 'AQI sub-index' : 'Unavailable',
      value: hasConcentration ? concentration / (key === 'co' ? 1000 : 1) : hasIndex ? index : null,
      unit: hasConcentration ? (key === 'co' ? 'mg/m³' : 'µg/m³') : '',
      dominant: hasIndex && key === dominant,
      source: hasConcentration ? 'WeatherAPI' : hasIndex ? 'WAQI' : ''
    };
  });
}

export function createOverviewComponents(React, colors) {
  const h = React.createElement;

  function OverviewHero({reading, place, station, loading}) {
    const value = reading?.aqi;
    const demo = reading?.dataKind === 'demo';
    const india = reading?.aqiStandard === 'India';
    const bands = india ? indiaBands : usBands;
    const active = isReading(value) ? bands.findIndex(([, , max]) => value <= max) : -1;
    const color = bands[active]?.[3] ?? '#a9a1ff';
    return h('section', {className: 'nova-overview-masthead', 'aria-labelledby': 'nova-air-title', style: {'--reading-accent': color}},
      h('div', {className: 'nova-air-stage'},
        h(OverviewArtwork),
        h('div', {className: 'nova-air-copy'},
          h('div', {className: 'nova-reading-chip'}, h('i'), loading ? 'Updating' : demo ? 'Demo reading' : 'Station reading'),
          h('h1', {id: 'nova-air-title'}, 'Air quality'),
          h('p', {className: 'nova-air-location'},
            h('svg', {viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, 'aria-hidden': 'true'},
              h('path', {d: 'M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z'}),
              h('circle', {cx: 12, cy: 10, r: 2.5})
            ),
            h('span', null, place || reading?.city || 'Selected location'),
            station && station !== place && h('span', {className: 'nova-air-station'}, station)
          ),
          h('div', {className: 'nova-air-reading'},
            h('strong', {key: value}, isReading(value) ? chartValue.format(value) : '—'),
            h('div', null, h('span', null, india ? 'India AQI' : 'US AQI'), active >= 0 && h('b', null, bands[active][0]))
          )
        )
      ),
      h('div', {className: 'nova-spectrum', 'aria-label': india ? 'India AQI spectrum' : 'US AQI spectrum'},
        bands.map(([label, range, , tint], index) => h('div', {
          key: label, className: 'nova-spectrum-band', style: {'--band': tint, '--band-delay': index * 55 + 'ms'},
          'aria-current': index === active ? 'true' : undefined
        }, h('i', {'aria-hidden': 'true'}), h('b', null, label), h('small', null, range)))
      )
    );
  }

  function OverviewArtwork() {
    const ribbons = Array.from({length: 23}, (_, index) => {
      const d = index - 11;
      const points = [-70, 256+d*9, 112+d*6, 383-d*4, 102-d*5, 57+d*7, 327+d*4, 136+d*5, 503+d*4, 229-d*5, 647+d*5, 15+d*4];
      return h('path', {
        key: index, d: 'M' + points.slice(0,2).join(' ') + ' C' + points.slice(2,8).join(' ') + ' S' + points.slice(8).join(' '),
        opacity: 1 - Math.abs(d) * .044
      });
    });
    return h('div', {className: 'nova-aura', 'aria-hidden': 'true'},
      h('div', {className: 'nova-aura-cloud'}),
      h('svg', {viewBox: '0 0 610 360', preserveAspectRatio: 'xMidYMid slice'},
        h('defs', null,
          h('linearGradient', {id: 'nova-ribbon', x1: '0', y1: '340', x2: '600', y2: '40', gradientUnits: 'userSpaceOnUse'},
            h('stop', {offset: '0%', stopColor: '#62ffe7', stopOpacity: .25}),
            h('stop', {offset: '25%', stopColor: '#72ffe5'}),
            h('stop', {offset: '48%', stopColor: '#a4a0ff'}),
            h('stop', {offset: '72%', stopColor: '#fe8fc4'}),
            h('stop', {offset: '100%', stopColor: '#ffcf8d'})
          )
        ),
        h('g', {className: 'nova-aura-ribbons', fill: 'none', stroke: 'url(#nova-ribbon)', strokeWidth: 1.4}, ribbons)
      )
    );
  }

  function OverviewPollutants({readings, loading, onDetails}) {
    const cards = readings ?? overviewObservations(null, null, null);
    return h('section', {className: 'pollutants-section overview-pollutants reveal', 'aria-labelledby': 'overview-pollutants'},
      h('div', {className: 'overview-panel-heading'},
        h('h2', {id: 'overview-pollutants'}, 'Air pollutants'),
        h('button', {className: 'overview-detail-link', type: 'button', onClick: onDetails}, 'Detailed analytics →')
      ),
      h('div', {className: 'compact-pollutants'}, cards.map(card => {
        const accent = colors[card.key];
        const display = card.value === null ? '—' : new Intl.NumberFormat('en-IN', {
          maximumFractionDigits: card.key === 'co' && card.unit ? 3 : 1
        }).format(card.value);
        return h('button', {
          key: card.key, type: 'button', onClick: onDetails,
          title: [card.name, card.source].filter(Boolean).join(' · '),
          className: 'compact-pollutant overview-pollutant' + (card.dominant ? ' is-primary' : ''),
          style: {'--card-color': accent.color, '--card-glow': accent.glow}
        },
          h('div', {className: 'pollutant-orb-wrapper'},
            h('div', {className: 'pollutant-orb-glow', style: {background: accent.glow}}),
            h('img', {src: '/pollutants/' + card.key + '.jpg', alt: '', className: 'pollutant-orb-img', loading: 'lazy'}),
            card.dominant && h('span', {className: 'primary-pill-tag'}, 'DOMINANT')
          ),
          h('div', {className: 'pollutant-info-head'},
            h('span', {className: 'pollutant-code-name'}, card.label),
            h('span', {className: 'overview-reading-kind'}, loading && !readings ? 'Loading' : card.kind)
          ),
          h('div', {className: 'pollutant-number-row'},
            h('strong', null, display),
            h('span', {className: 'pollutant-unit'}, card.unit)
          ),
          h('div', {className: 'overview-pollutant-accent', 'aria-hidden': 'true'}),
          h('div', {className: 'pollutant-limit-row'}, 'CPCB ' + card.period + ' ref. · ' + card.limit)
        );
      }))
    );
  }

  function OverviewTrend({latitude, longitude}) {
    const [attempt, retry] = React.useState(0);
    const [result, setResult] = React.useState(null);
    const [focus, setFocus] = React.useState(null);
    const location = latitude + ',' + longitude;
    React.useEffect(() => {
      if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
      const controller = new AbortController();
      const url = new URL('https://air-quality-api.open-meteo.com/v1/air-quality');
      url.search = new URLSearchParams({
        latitude, longitude, hourly: 'us_aqi', past_days: 1, forecast_days: 1, timeformat: 'unixtime'
      });
      fetch(url, {signal: AbortSignal.any([controller.signal, AbortSignal.timeout(12000)])})
        .then(response => {
          if (!response.ok) throw new Error('Model feed unavailable');
          return response.json();
        })
        .then(({hourly}) => {
          const now = Date.now();
          const points = (hourly?.time ?? []).map((time, index) => ({
            time: time * 1000,
            aqi: isReading(hourly?.us_aqi?.[index]) ? hourly.us_aqi[index] : null
          })).filter(point => Number.isFinite(point.time) && point.time <= now);
          if (!controller.signal.aborted) setResult({location, attempt, points});
        })
        .catch(() => {
          if (!controller.signal.aborted) setResult({location, attempt, points: []});
        });
      return () => controller.abort();
    }, [latitude, longitude, location, attempt]);

    const ready = result?.location === location && result.attempt === attempt;
    const points = ready ? result.points.slice(-24) : [];
    const valid = points.filter(point => point.aqi !== null);
    const ceiling = Math.ceil(Math.max(50, ...valid.map(point => point.aqi)) / 50) * 50;
    const latest = valid.at(-1);
    const selected = focus?.location === location ? points.findIndex(point => point.time === focus.time) : -1;
    const select = point => point && setFocus({location, time: point.time});
    const onChartKey = event => {
      if (event.key === 'Escape') { setFocus(null); return; }
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      const index = event.key === 'Home' ? 0 : event.key === 'End' ? points.length - 1
        : selected < 0 ? points.length - 1
        : Math.max(0, Math.min(points.length - 1, selected + (event.key === 'ArrowLeft' ? -1 : 1)));
      select(points[index]);
    };

    return h('section', {className: 'nova-hourly', 'aria-label': '24-hour air quality chart'},
      valid.length > 0 ? h('div', {
        className: 'nova-hourly-plot', tabIndex: 0, role: 'group',
        'aria-label': 'Hourly US AQI. Use the left and right arrows to inspect readings.',
        onKeyDown: onChartKey, onBlur: () => setFocus(null)
      },
        h('div', {className: 'nova-chart-grid', 'aria-hidden': 'true'},
          [ceiling, ceiling * .8, ceiling * .6, ceiling * .4, ceiling * .2, 0].map(tick =>
            h('div', {key: tick}, h('span', null, Math.round(tick))))
        ),
        h('div', {className: 'nova-chart-bars', style: {'--samples': points.length}, onPointerLeave: event => {
          if (event.pointerType === 'mouse') setFocus(null);
        }},
          points.map((point, index) => h('button', {
            key: point.time, type: 'button', tabIndex: -1,
            className: 'nova-chart-bar',
            'data-latest': point === latest || undefined,
            'data-selected': index === selected || undefined,
            'aria-label': timestamp.format(point.time) + ' IST: ' + (point.aqi === null ? 'No reading' : chartValue.format(point.aqi) + ' US AQI'),
            onPointerEnter: event => { if (event.pointerType === 'mouse') select(point); },
            onClick: () => select(point),
            style: {'--level': point.aqi === null ? '0%' : (point.aqi / ceiling * 100) + '%', '--tint': 172 + index * 5, '--delay': index * 20 + 'ms'}
          }, point.aqi !== null && h('i'), index % 6 === 0 && h('small', {'aria-hidden': 'true'}, hour.format(point.time)))),
          selected >= 0 && h('div', {
            className: 'nova-chart-value', role: 'status',
            style: {'--at': ((selected + .5) / points.length * 100) + '%'}
          }, h('span', null, timestamp.format(points[selected].time)),
          h('strong', null, points[selected].aqi === null ? 'No reading' : chartValue.format(points[selected].aqi)))
        )
      ) : h('div', {className: 'nova-hourly-empty', role: 'status'},
        ready ? 'Chart data is unavailable.' : 'Loading chart…',
        ready && h('button', {type: 'button', className: 'overview-detail-link', onClick: () => retry(attempt + 1)}, 'Retry')
      )
    );
  }

  function OverviewSources() {
    return h('section', {className: 'data-section nova-overview-sources', 'aria-labelledby': 'overview-source-heading'},
      h('h2', {id: 'overview-source-heading'}, 'Overview data sources'),
      h('p', null, 'Hourly US AQI model data from ',
        h('a', {href: 'https://open-meteo.com/en/docs/air-quality-api'}, 'Open-Meteo'),
        ' and ', h('a', {href: 'https://atmosphere.copernicus.eu/'}, 'Copernicus CAMS'), '.'
      ),
      h('p', null, 'WeatherAPI supplies pollutant concentrations and WAQI supplies sub-indices. Each CPCB reference shows its averaging period.')
    );
  }

  return {OverviewHero, OverviewPollutants, OverviewTrend, OverviewSources};
}
