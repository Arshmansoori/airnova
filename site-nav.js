(() => {
  const pages = [
    ['/', 'Home', 'home'],
    ['/aqi', 'AQI', 'aqi'],
    ['/weather.html', 'Weather', 'weather'],
    ['/airvision.html', 'AirVision', 'airvision'],
    ['/prediction/', 'Forecast', 'forecast']
  ];
  const current = location.pathname.startsWith('/admin') ? 'admin' : document.body.dataset.product;
  const header = document.createElement('header');
  header.className = 'product-header';
  header.innerHTML = `<a href="/" class="product-logo" aria-label="Airnova home">
      <img src="/assets/airnova-logo.png" alt="Airnova">
    </a>
    <nav class="product-nav" aria-label="Main navigation">
      ${pages.map(([href, label, id]) => `<a href="${href}"${id === current ? ' aria-current="page"' : ''}>${label}</a>`).join('')}
    </nav>`;
  document.body.prepend(header);
  if (current === 'admin' && ['localhost', '127.0.0.1'].includes(location.hostname)) {
    const note = document.createElement('div');
    note.className = 'product-admin-note';
    note.innerHTML = 'Account access is available on the <a href="https://airnova.space/admin">live Airnova admin page</a>.';
    header.after(note);
  }
})();
