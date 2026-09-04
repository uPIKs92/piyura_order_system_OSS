#!/usr/bin/env node
// Generates resources/css/theme-palettes.css and resources/js/lib/theme-preset-fonts.json
// from resources/js/lib/theme-presets.manifest.json (top-12 shadcnpreset.com community
// presets, pinned 2026-08-30) plus the vendored upstream data in scripts/data/.
//
// Preset codes are decoded with the official codec port (scripts/lib/preset-codec.mjs)
// and composed exactly like shadcnpreset.com does:
//   light = baseColor tokens ⊕ theme tokens ⊕ chart-1..5 from chartColor theme
//           ⊕ menuAccent override ⊕ radius override ⊕ font vars (light only)
// Data pins and shapes: see scripts/data/README.md.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { decodePreset, encodePreset, isPresetCode, V1_CHART_COLOR_MAP } from './lib/preset-codec.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const manifestPath = path.join(root, 'resources/js/lib/theme-presets.manifest.json');
const cssPath = path.join(root, 'resources/css/theme-palettes.css');
const fontsPath = path.join(root, 'resources/js/lib/theme-preset-fonts.json');
const themesDataPath = path.join(root, 'scripts/data/shadcnpreset-themes.json');
const fontsDataPath = path.join(root, 'scripts/data/shadcnpreset-fonts.json');

// morganfeeney/shadcnpreset vendor/v4/registry/config.ts RADII (radius name → CSS value).
const RADII = {
  none: '0',
  small: '0.45rem',
  medium: '0.625rem',
  large: '0.875rem',
};

// Base colors are THEMES entries; upstream BASE_COLORS = neutral/stone/zinc/mauve/olive/mist/taupe
// (PRESET_BASE_COLORS also lists gray, which has no THEMES entry and falls back to neutral).
const BASE_COLORS = new Set(['neutral', 'stone', 'zinc', 'mauve', 'olive', 'mist', 'taupe']);

const themes = JSON.parse(fs.readFileSync(themesDataPath, 'utf8'));
const fontDefinitions = JSON.parse(fs.readFileSync(fontsDataPath, 'utf8'));
const themeNames = Object.keys(themes);

function fail(message) {
  console.error(message);
  process.exit(1);
}

// getThemesForBaseColor(): a base-color-named theme is only valid on its own base.
function isThemeAvailableOnBase(baseColor, themeName) {
  return themeName === baseColor || !BASE_COLORS.has(themeName);
}

function isTranslucentMenuColor(menuColor) {
  return menuColor === 'default-translucent' || menuColor === 'inverted-translucent';
}

// Port of shadcnpreset lib/preset.ts resolvePresetFromCode + normalizeResolvedPreset
// (getThemesForBaseColor/getBaseColor inlined against the vendored THEMES data).
function resolvePresetFromCode(code) {
  if (!isPresetCode(code)) return null;
  const decoded = decodePreset(code);
  if (!decoded) return null;
  // encodePreset always emits v2 codes, so the canonical roundtrip check only
  // applies to "b" codes (legacy "a" codes decode fine but never re-encode).
  if (code.startsWith('b') && encodePreset(decoded) !== code) return null;

  const baseColor = BASE_COLORS.has(decoded.baseColor) ? decoded.baseColor : 'neutral';
  const availableThemeNames = new Set(themeNames.filter((name) => isThemeAvailableOnBase(baseColor, name)));
  const fallbackTheme = themeNames.find((name) => isThemeAvailableOnBase(baseColor, name)) ?? baseColor;
  const rawChartColor = decoded.chartColor ?? V1_CHART_COLOR_MAP[decoded.theme] ?? decoded.theme;

  return {
    ...decoded,
    baseColor,
    theme: availableThemeNames.has(decoded.theme) ? decoded.theme : fallbackTheme,
    effectiveChartColor: availableThemeNames.has(rawChartColor) ? rawChartColor : fallbackTheme,
    menuAccent:
      decoded.menuAccent === 'bold' && isTranslucentMenuColor(decoded.menuColor)
        ? 'subtle'
        : decoded.menuAccent,
    effectiveRadius: decoded.style === 'lyra' ? 'none' : decoded.radius,
  };
}

// Port of shadcnpreset vendor/v4/registry/config.ts buildRegistryTheme.
function buildRegistryTheme(config) {
  const baseColorVars = themes[config.baseColor];
  const themeVars = themes[config.theme];
  if (!baseColorVars || !themeVars) {
    fail(`Base color "${config.baseColor}" or theme "${config.theme}" not found`);
  }

  const lightVars = { ...baseColorVars.light, ...themeVars.light };
  const darkVars = { ...baseColorVars.dark, ...themeVars.dark };

  const chartTheme = themes[config.chartColor];
  if (chartTheme) {
    for (let i = 1; i <= 5; i++) {
      const key = `chart-${i}`;
      if (chartTheme.light[key]) lightVars[key] = chartTheme.light[key];
      if (chartTheme.dark[key]) darkVars[key] = chartTheme.dark[key];
    }
  }

  if (config.menuAccent === 'bold') {
    lightVars.accent = lightVars.primary;
    lightVars['accent-foreground'] = lightVars['primary-foreground'];
    darkVars.accent = darkVars.primary;
    darkVars['accent-foreground'] = darkVars['primary-foreground'];
  }

  const resolvedRadius =
    config.radius && config.radius !== 'default'
      ? RADII[config.radius]
      : (lightVars.radius ?? darkVars.radius);

  if (resolvedRadius) {
    lightVars.radius = resolvedRadius;
    darkVars.radius = resolvedRadius;
  }

  return { light: lightVars, dark: darkVars };
}

function getFontDefinition(slug) {
  const definition = fontDefinitions[slug];
  if (!definition) fail(`Font "${slug}" missing from scripts/data/shadcnpreset-fonts.json`);
  return definition;
}

// Expected decoded configs for the pinned top-12 (2026-08-30 leaderboard,
// votes DESC → preset_code ASC tiebreak), verified against the community page.
const EXPECTED_CONFIGS = {
  'luma-lime': { style: 'luma', baseColor: 'mist', theme: 'lime', chartColor: 'indigo', iconLibrary: 'tabler', font: 'instrument-sans', fontHeading: 'merriweather' },
  'sera-taupe': { style: 'sera', baseColor: 'taupe', theme: 'taupe', chartColor: 'taupe', iconLibrary: 'lucide', font: 'noto-sans', fontHeading: 'playfair-display' },
  'lyra-zinc': { style: 'lyra', baseColor: 'zinc', theme: 'zinc', chartColor: 'cyan', iconLibrary: 'hugeicons', font: 'inter', fontHeading: 'inherit' },
  'vega-emerald': { style: 'vega', baseColor: 'olive', theme: 'emerald', chartColor: 'lime', iconLibrary: 'lucide', font: 'public-sans', fontHeading: 'geist' },
  'luma-teal': { style: 'luma', baseColor: 'mist', theme: 'teal', chartColor: 'teal', iconLibrary: 'lucide', font: 'figtree', fontHeading: 'inherit' },
  'luma-neutral': { style: 'luma', baseColor: 'neutral', theme: 'neutral', chartColor: 'neutral', iconLibrary: 'lucide', font: 'inter', fontHeading: 'inherit' },
  'nova-mauve': { style: 'nova', baseColor: 'mauve', theme: 'mauve', chartColor: 'purple', iconLibrary: 'tabler', font: 'oxanium', fontHeading: 'inherit' },
  'sera-amber': { style: 'sera', baseColor: 'taupe', theme: 'amber', chartColor: 'rose', iconLibrary: 'lucide', font: 'playfair-display', fontHeading: 'playfair-display' },
  'luma-yellow': { style: 'luma', baseColor: 'mauve', theme: 'yellow', chartColor: 'sky', iconLibrary: 'hugeicons', font: 'oxanium', fontHeading: 'inherit' },
  'vega-sky': { style: 'vega', baseColor: 'mauve', theme: 'sky', chartColor: 'purple', iconLibrary: 'tabler', font: 'geist-mono', fontHeading: 'manrope' },
  'nova-violet': { style: 'nova', baseColor: 'neutral', theme: 'violet', chartColor: 'yellow', iconLibrary: 'lucide', font: 'noto-serif', fontHeading: 'inherit' },
  'luma-red': { style: 'luma', baseColor: 'taupe', theme: 'red', chartColor: 'taupe', iconLibrary: 'hugeicons', font: 'geist', fontHeading: 'manrope' },
};

function emitVars(vars) {
  return Object.entries(vars)
    .map(([key, value]) => `    --${key}: ${value};`)
    .join('\n');
}

function main() {
  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  const existing = fs.readFileSync(cssPath, 'utf8');
  const basicSection = existing.split('/* Popular all-time community themes')[0].trimEnd();

  const blocks = ['/* Popular all-time community themes from shadcnpreset.com/community (full tokens) */'];
  const fontMap = {};

  for (const preset of manifest) {
    const resolved = resolvePresetFromCode(preset.code);
    if (!resolved) fail(`Preset ${preset.slug}: code "${preset.code}" failed to decode`);

    const expected = EXPECTED_CONFIGS[preset.slug];
    if (!expected) fail(`Preset ${preset.slug}: no expected config entry`);
    for (const key of ['style', 'baseColor', 'theme', 'chartColor', 'iconLibrary', 'font', 'fontHeading']) {
      const actual = key === 'chartColor' ? resolved.effectiveChartColor : resolved[key];
      if (actual !== expected[key]) {
        fail(`Preset ${preset.slug}: decoded ${key} "${actual}" != expected "${expected[key]}"`);
      }
    }

    const { light, dark } = buildRegistryTheme({
      baseColor: resolved.baseColor,
      theme: resolved.theme,
      chartColor: resolved.effectiveChartColor,
      menuAccent: resolved.menuAccent,
      radius: resolved.effectiveRadius,
    });

    // Font vars go in the light/root block only; dark inherits (matches
    // shadcnpreset lib/preset-theme-css.ts getFontVars).
    const bodyFont = getFontDefinition(resolved.font);
    light['font-sans'] = bodyFont.family;
    light['font-heading'] =
      resolved.fontHeading === 'inherit' ? 'var(--font-sans)' : getFontDefinition(resolved.fontHeading).family;

    // Google Fonts query strings: body font first, then heading if different.
    const queries = [bodyFont.googleFontQuery];
    if (resolved.fontHeading !== 'inherit') {
      const headingQuery = getFontDefinition(resolved.fontHeading).googleFontQuery;
      if (!queries.includes(headingQuery)) queries.push(headingQuery);
    }
    fontMap[preset.slug] = queries;

    blocks.push(`/* shadcnpreset.com community: ${preset.label} (${preset.code}) */`);
    blocks.push(`:root[data-palette="${preset.slug}"] {`);
    blocks.push(emitVars(light));
    blocks.push('}');
    blocks.push('');
    blocks.push(`.dark[data-palette="${preset.slug}"] {`);
    blocks.push(emitVars(dark));
    blocks.push('}');
    blocks.push('');

    console.log(`${preset.slug}: primary ${light.primary} background ${light.background}`);
  }

  fs.writeFileSync(cssPath, `${basicSection}\n\n${blocks.join('\n').trimEnd()}\n`);
  fs.writeFileSync(fontsPath, `${JSON.stringify(fontMap, null, 2)}\n`);
  console.log(`Wrote ${manifest.length} full presets to theme-palettes.css`);
}

main();
