#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const manifestPath = path.join(root, 'resources/js/lib/theme-presets.manifest.json');
const cssPath = path.join(root, 'resources/css/theme-palettes.css');
const fontsPath = path.join(root, 'resources/js/lib/theme-preset-fonts.json');

function emitVars(vars) {
  return Object.entries(vars)
    .map(([key, value]) => `    --${key}: ${value};`)
    .join('\n');
}

function mergeTheme(cssVars) {
  const shared = { ...(cssVars.theme ?? {}) };
  const light = { ...shared, ...(cssVars.light ?? {}) };
  const dark = { ...shared, ...(cssVars.dark ?? {}) };
  return { light, dark, shared };
}

async function fetchTheme(id) {
  const res = await fetch(`https://tweakcn.com/r/themes/${id}`);
  if (!res.ok) throw new Error(`Failed to fetch theme ${id}: ${res.status}`);
  return res.json();
}

function extractGoogleFonts(cssVars) {
  const families = new Set();
  const skip = new Set([
    'ui-sans-serif', 'ui-serif', 'ui-monospace', 'system-ui', 'sans-serif', 'serif', 'monospace',
    'Geist Mono', 'Geist', 'Cambria', 'Times New Roman', 'Times', 'Georgia',
  ]);

  for (const block of [cssVars.theme, cssVars.light, cssVars.dark]) {
    if (!block) continue;
    for (const key of ['font-sans', 'font-serif', 'font-mono']) {
      const value = block[key];
      if (!value) continue;
      for (const match of value.matchAll(/'([^']+)'/g)) {
        if (!skip.has(match[1])) families.add(match[1]);
      }
      const first = value.split(',')[0]?.trim().replace(/^['"]|['"]$/g, '');
      if (first && !skip.has(first)) families.add(first);
    }
  }

  return [...families].sort();
}

async function main() {
  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  const existing = fs.readFileSync(cssPath, 'utf8');
  const basicSection = existing.split('/* Popular all-time community themes')[0].trimEnd();

  const blocks = ['/* Popular all-time community themes from tweakcn.com/community (full tokens) */'];
  const fontMap = {};

  for (const preset of manifest) {
    const data = await fetchTheme(preset.id);
    const name = data.name ?? preset.label;
    const { light, dark } = mergeTheme(data.cssVars);
    fontMap[preset.slug] = extractGoogleFonts(data.cssVars);

    blocks.push(`/* tweakcn community: ${name} */`);
    blocks.push(`:root[data-palette="${preset.slug}"] {`);
    blocks.push(emitVars(light));
    blocks.push('}');
    blocks.push('');
    blocks.push(`.dark[data-palette="${preset.slug}"] {`);
    blocks.push(emitVars(dark));
    blocks.push('}');
    blocks.push('');
  }

  fs.writeFileSync(cssPath, `${basicSection}\n\n${blocks.join('\n').trimEnd()}\n`);
  fs.writeFileSync(fontsPath, `${JSON.stringify(fontMap, null, 2)}\n`);
  console.log(`Wrote ${manifest.length} full presets to theme-palettes.css`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
