import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { basename, dirname } from 'node:path';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';

const outputs = [];
const copy = (source, target) => {
  mkdirSync(dirname(target), { recursive: true });
  copyFileSync(source, target);
  outputs.push(target);
};
const libraries = [
  ['lucide', 'dist/umd/lucide.min.js', 'LICENSE', 'js/vendor/lucide/lucide-1.61.0.min.js'],
  ['chart.js', 'dist/chart.umd.min.js', 'LICENSE.md', 'js/vendor/chart/chart-4.6.1.min.js'],
];
for (const [name, source, license, target] of libraries) {
  copy(`node_modules/${name}/${source}`, `public/${target}`);
  copy(`node_modules/${name}/${license}`, `public/${dirname(target)}/LICENSE`);
}
let fontCSS = '/* Fuentes locales: licencias OFL junto a sus archivos. */\n';
for (const [font, family] of [['plus-jakarta-sans', 'Plus Jakarta Sans'], ['jetbrains-mono', 'JetBrains Mono']]) {
  const base = `node_modules/@fontsource-variable/${font}`;
  let css = readFileSync(`${base}/wght.css`, 'utf8').replaceAll(`${family} Variable`, family);
  css = css.replace(/url\(['"]?\.\/files\/([^)'"\s]+)['"]?\)/g, (_, file) => {
    copy(`${base}/files/${file}`, `public/fonts/vendor/${font}/${file}`);
    return `url('../fonts/vendor/${font}/${file}')`;
  });
  copy(`${base}/LICENSE`, `public/fonts/vendor/${font}/LICENSE`);
  fontCSS += css + '\n';
}
writeFileSync('public/css/fonts.css', fontCSS);
outputs.push('public/css/fonts.css');
copy('node_modules/tailwindcss/LICENSE', 'public/css/vendor/TAILWIND-LICENSE');
execFileSync(process.execPath, ['node_modules/tailwindcss/lib/cli.js', '-c', 'tailwind.config.cjs', '-i', 'resources/css/tailwind.css', '-o', 'public/css/tailwind.css', '--minify'], { stdio: 'inherit' });
outputs.push('public/css/tailwind.css');
const versions = JSON.parse(readFileSync('package.json', 'utf8')).devDependencies;
const files = Object.fromEntries(outputs.sort().map(file => [file.replace(/^public\//, ''), createHash('sha256').update(readFileSync(file)).digest('hex')]));
writeFileSync('public/vendor-manifest.json', JSON.stringify({ versions, files }, null, 2) + '\n');
console.log(`Recursos locales generados: ${outputs.length} archivos, versiones fijadas y SHA-256 registrados.`);
