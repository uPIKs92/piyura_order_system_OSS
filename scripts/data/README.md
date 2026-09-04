# scripts/data

Committed upstream data artifacts; regenerate only by re-running the pinned
conversion, never by hand-editing values.

- `shadcnpreset-themes.json` — `THEMES` from
  `morganfeeney/shadcnpreset` `apps/shadcnpreset/vendor/v4/registry/themes.ts`,
  pinned at commit `646c9a93e2bf83a43a5be095335f7e2d28c68ed9` (fetched 2026-08-30).
  Shape: theme name → `{ light, dark }` cssVars (oklch strings).
  24 entries: `PRESET_THEMES` minus `gray`, which has no THEMES entry upstream
  (decoding `gray` falls back to `neutral` during normalization).
- `shadcnpreset-fonts.json` — `FONT_DEFINITIONS` from
  `morganfeeney/shadcnpreset` `apps/shadcnpreset/lib/font-definitions.ts` at the
  same pin. Shape: font slug → `{ family, googleFontQuery }`; `googleFontQuery`
  is the ready-to-use Google Fonts `family=` parameter (per-family weights).

Both upstream files are MIT-licensed. The preset code codec ported in
`scripts/lib/preset-codec.mjs` comes from `shadcn-ui/ui`
(`packages/shadcn/src/preset/preset.ts`, MIT) — see its header for the pin.
