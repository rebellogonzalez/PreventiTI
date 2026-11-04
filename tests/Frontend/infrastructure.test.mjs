import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { backupMarkup, formatBytes } from '../../public/js/features/infrastructure.js';

test('backup download requires a verified result and a constrained local URL', () => {
  assert.doesNotMatch(backupMarkup(null), /href=/);
  assert.doesNotMatch(backupMarkup({ verified: false, error: '<script>alert(1)</script>' }), /<script>|href=/);
  const backup = { verified: true, filename: '<img>', bytes: 1024, created_at: '2026-10-22T12:00:00Z', storage_files: 2, sha256: 'abc', download_url: '/api/infraestructura/backups/preventiti_20261022_120000_abcdef123456.zip/download' };
  assert.match(backupMarkup(backup), /href="\/api\/infraestructura\/backups\//);
  assert.doesNotMatch(backupMarkup(backup), /<img>/);
  assert.doesNotMatch(backupMarkup({ ...backup, download_url: 'https://external.example/backup' }), /href=/);
  assert.equal(formatBytes(1024), '1 KB');
  assert.equal(formatBytes(null), 'No disponible');
});

test('all vendored assets match the recorded checksums', () => {
  const manifest = JSON.parse(readFileSync(new URL('../../public/vendor-manifest.json', import.meta.url)));
  for (const [file, expected] of Object.entries(manifest.files)) {
    assert.equal(createHash('sha256').update(readFileSync(new URL(`../../public/${file}`, import.meta.url))).digest('hex'), expected, file);
  }
});
