const http = require('node:http');
const fs = require('node:fs/promises');
const path = require('node:path');

const root = __dirname;
const pageRoutes = new Map([
  ['/', '/index.html'],
  ['/aqi', '/station.html'],
  ['/2', '/station.html'],
  ['/admin', '/station.html'],
  ['/weather', '/weather.html'],
  ['/airvision', '/airvision.html'],
  ['/prediction', '/prediction/index.html'],
  ['/prediction/index.php', '/prediction/index.html']
]);
const publicApis = new Set([
  '/api/stations', '/api/aqi', '/api/environment',
  '/api/coupled', '/api/history', '/api/network'
]);
const predictionActions = new Set(['health', 'presets', 'forecast', 'hotspots']);
const contentTypes = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.png': 'image/png',
  '.svg': 'image/svg+xml',
  '.json': 'application/json'
};

function sendJson(res, status, value) {
  res.writeHead(status, {'Content-Type': 'application/json', 'Cache-Control': 'no-store'});
  res.end(JSON.stringify(value));
}

async function proxyReading(url, res) {
  try {
    const upstream = new URL('https://airnova.space');
    upstream.pathname = url.pathname;
    upstream.search = url.search;
    const response = await fetch(upstream, {
      headers: {Accept: 'application/json'},
      redirect: 'error',
      signal: AbortSignal.timeout(25000)
    });
    const type = response.headers.get('content-type') || '';
    if (!type.includes('application/json')) {
      sendJson(res, 502, {error: 'Airnova returned an unexpected response.'});
      return;
    }
    sendJson(res, response.status, await response.json());
  } catch {
    sendJson(res, 502, {error: 'Airnova readings are temporarily unavailable.'});
  }
}

async function serveStatic(pathname, res) {
  const route = pathname === '/' ? '/' : pathname.replace(/\/$/, '');
  const file = path.resolve(root, '.' + (pageRoutes.get(route) || pathname));
  const type = contentTypes[path.extname(file)];
  if (!file.startsWith(root + path.sep) || !type || pathname.split('/').some(part => part.startsWith('.'))) {
    await send404(res);
    return;
  }
  try {
    const data = await fs.readFile(file);
    res.writeHead(200, {'Content-Type': type, 'Cache-Control': 'no-cache'}).end(data);
  } catch {
    await send404(res);
  }
}

async function send404(res) {
  try {
    const notFoundHtml = await fs.readFile(path.join(root, '404.html'));
    res.writeHead(404, {'Content-Type': 'text/html; charset=utf-8'}).end(notFoundHtml);
  } catch {
    res.writeHead(404).end('Not found');
  }
}

function handleRequest(req, res) {
  let url, pathname;
  try {
    url = new URL(req.url, 'http://localhost');
    pathname = decodeURIComponent(url.pathname);
  } catch {
    res.writeHead(400).end();
    return;
  }
  if (pathname.startsWith('/api/') || pathname === '/prediction/api.php') {
    const allowed = publicApis.has(pathname) ||
      (pathname === '/prediction/api.php' && predictionActions.has(url.searchParams.get('action')));
    if (req.method !== 'GET' || !allowed) {
      sendJson(res, 403, {error: 'This endpoint is available on the hosted site.'});
      return;
    }
    proxyReading(url, res);
    return;
  }
  serveStatic(pathname, res);
}

const DEFAULT_PORT = parseInt(process.env.PORT, 10) || 5000;

function startServer(port) {
  const server = http.createServer(handleRequest);
  server.on('error', (err) => {
    if (err.code === 'EADDRINUSE') {
      console.warn(`[Airnova] Port ${port} is occupied. Attempting port ${port + 1}...`);
      startServer(port + 1);
    } else {
      console.error('[Airnova] Server error:', err);
    }
  });
  server.listen(port, '0.0.0.0', () => {
    console.log(`\n======================================================`);
    console.log(`  Airnova is live and ready at: http://localhost:${port}`);
    console.log(`======================================================\n`);
  });
}

startServer(DEFAULT_PORT);
