// Simple Node.js reverse-proxy that replaces the React dev server. Supervisor
// runs `yarn start` in /app/frontend, which now runs this file. Everything is
// forwarded to the PHP application at PHP_UPSTREAM.
const http = require('http');
const url = require('url');

const UPSTREAM = process.env.PHP_UPSTREAM || 'http://127.0.0.1:9000';
const upstreamUrl = url.parse(UPSTREAM);
const port = parseInt(process.env.PORT || '3000', 10);
const host = process.env.HOST || '0.0.0.0';

const server = http.createServer((req, res) => {
  const fwdHost = req.headers['x-forwarded-host'] || req.headers['host'] || '';
  const fwdProto = req.headers['x-forwarded-proto'] || (req.connection && req.connection.encrypted ? 'https' : 'http');
  const options = {
    hostname: upstreamUrl.hostname,
    port: upstreamUrl.port,
    path: req.url,
    method: req.method,
    headers: Object.assign({}, req.headers, { host: upstreamUrl.host, 'x-forwarded-host': fwdHost, 'x-forwarded-proto': fwdProto }),
  };
  const proxyReq = http.request(options, (proxyRes) => {
    res.writeHead(proxyRes.statusCode, proxyRes.headers);
    proxyRes.pipe(res);
  });
  proxyReq.on('error', (err) => {
    res.writeHead(502, { 'Content-Type': 'text/plain' });
    res.end('Upstream error: ' + err.message);
  });
  req.pipe(proxyReq);
});

server.listen(port, host, () => {
  console.log(`[proxy] listening on http://${host}:${port} -> ${UPSTREAM}`);
});
