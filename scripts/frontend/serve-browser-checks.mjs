import { createServer } from 'node:http';
import { readFile, realpath } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { resolve, sep, extname } from 'node:path';

// Isolated UI checks: serve only the test fixture and public JS/CSS, never .env or the database.
const root = await realpath(fileURLToPath(new URL('../../', import.meta.url)));
const allowed = ['public/js/', 'public/css/'];
const assetTemplates = ['resources/views/app/modals/asset-detail.blade.php', 'resources/views/app/modals/asset-create.blade.php'];
const fixtures = new Set([
  ...assetTemplates,
  'tests/Frontend/asset-select.browser.html', 'tests/Frontend/asset-select.browser.mjs',
  'tests/Frontend/maintenance-history.browser.html', 'tests/Frontend/maintenance-history.browser.mjs',
  'tests/Frontend/floorplan.browser.html', 'tests/Frontend/floorplan.browser.mjs',
]);
const types = { '.html': 'text/html', '.js': 'text/javascript', '.mjs': 'text/javascript', '.css': 'text/css' };

createServer(async (request, response) => {
  try {
    const path = decodeURIComponent(new URL(request.url, 'http://127.0.0.1').pathname).slice(1);
    if (request.method !== 'GET' || (!fixtures.has(path) && !allowed.some(prefix => path.startsWith(prefix)))) {
      response.writeHead(404).end();
      return;
    }
    const file = await realpath(resolve(root, path));
    const permittedFile = fixtures.has(path) ? file === resolve(root, path)
      : allowed.some(prefix => file.startsWith(resolve(root, prefix) + sep));
    const contentType = assetTemplates.includes(path) ? 'text/html' : types[extname(file)];
    if (!permittedFile || !contentType) {
      response.writeHead(404).end();
      return;
    }
    const content = await readFile(file);
    response.writeHead(200, { 'Content-Type': `${contentType}; charset=utf-8`, 'Cache-Control': 'no-store' });
    response.end(content);
  } catch {
    response.writeHead(404).end();
  }
}).listen(8765, '127.0.0.1', () => {
  console.log('Pruebas del selector: http://127.0.0.1:8765/tests/Frontend/asset-select.browser.html');
  console.log('Pruebas del historial: http://127.0.0.1:8765/tests/Frontend/maintenance-history.browser.html');
  console.log('Pruebas del mapa: http://127.0.0.1:8765/tests/Frontend/floorplan.browser.html');
});
