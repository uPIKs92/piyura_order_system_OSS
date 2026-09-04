// Preset code encoding/decoding, vendored verbatim (types stripped) from:
// https://github.com/shadcn-ui/ui/blob/main/packages/shadcn/src/preset/preset.ts
// shadcn-ui/ui is MIT licensed (c) shadcn. Fetched 2026-08-30; decode + encode
// roundtrip verified against the installed shadcn@4.13.1 dist.
//
// Bit-packs design system params into a single integer, then encodes as base62
// with a version prefix character ("a" = v1, "b" = v2 with chartColor).
//
// Rules for backward compat (upstream):
//   1. Never reorder existing value arrays — only append.
//   2. New fields must have their default at index 0.
//   3. Only append new fields to the end of PRESET_FIELDS.
//   4. Stay under 53 bits total (JS safe integer limit).

export const PRESET_STYLES = [
  "nova",
  "vega",
  "maia",
  "lyra",
  "mira",
  "luma",
  "sera",
  "rhea",
]

export const PRESET_BASE_COLORS = [
  "neutral",
  "stone",
  "zinc",
  "gray",
  "mauve",
  "olive",
  "mist",
  "taupe",
]

export const PRESET_THEMES = [
  "neutral",
  "stone",
  "zinc",
  "gray",
  "amber",
  "blue",
  "cyan",
  "emerald",
  "fuchsia",
  "green",
  "indigo",
  "lime",
  "orange",
  "pink",
  "purple",
  "red",
  "rose",
  "sky",
  "teal",
  "violet",
  "yellow",
  "mauve",
  "olive",
  "mist",
  "taupe",
]

export const PRESET_CHART_COLORS = PRESET_THEMES

// Before v2, base-color themes had colored chart palettes
// borrowed from these colored themes. Used by consumers to
// restore the original chart colors when decoding v1 presets.
export const V1_CHART_COLOR_MAP = {
  neutral: "blue",
  stone: "lime",
  zinc: "amber",
  mauve: "emerald",
  olive: "violet",
  mist: "rose",
  taupe: "cyan",
}

export const PRESET_ICON_LIBRARIES = [
  "lucide",
  "hugeicons",
  "tabler",
  "phosphor",
  "remixicon",
]

export const PRESET_FONTS = [
  "inter",
  "noto-sans",
  "nunito-sans",
  "figtree",
  "roboto",
  "raleway",
  "dm-sans",
  "public-sans",
  "outfit",
  "jetbrains-mono",
  "geist",
  "geist-mono",
  "lora",
  "merriweather",
  "playfair-display",
  "noto-serif",
  "roboto-slab",
  "oxanium",
  "manrope",
  "space-grotesk",
  "montserrat",
  "ibm-plex-sans",
  "source-sans-3",
  "instrument-sans",
  "eb-garamond",
  "instrument-serif",
]
export const PRESET_FONT_HEADINGS = ["inherit", ...PRESET_FONTS]

export const PRESET_RADII = [
  "default",
  "none",
  "small",
  "medium",
  "large",
]

export const PRESET_MENU_ACCENTS = ["subtle", "bold"]
export const PRESET_MENU_COLORS = [
  "default",
  "inverted",
  "default-translucent",
  "inverted-translucent",
]

// V1 fields (version "a"): 40 bits. No chartColor.
const PRESET_FIELDS_V1 = [
  { key: "menuColor", values: PRESET_MENU_COLORS, bits: 3 },
  { key: "menuAccent", values: PRESET_MENU_ACCENTS, bits: 3 },
  { key: "radius", values: PRESET_RADII, bits: 4 },
  { key: "font", values: PRESET_FONTS, bits: 6 },
  { key: "iconLibrary", values: PRESET_ICON_LIBRARIES, bits: 6 },
  { key: "theme", values: PRESET_THEMES, bits: 6 },
  { key: "baseColor", values: PRESET_BASE_COLORS, bits: 6 },
  { key: "style", values: PRESET_STYLES, bits: 6 },
]

// V2 fields (version "b"): 51 bits. Adds chartColor and fontHeading.
const PRESET_FIELDS_V2 = [
  ...PRESET_FIELDS_V1,
  { key: "chartColor", values: PRESET_CHART_COLORS, bits: 6 },
  { key: "fontHeading", values: PRESET_FONT_HEADINGS, bits: 5 },
]

// Base62 alphabet.
const BASE62 = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz"

// Version prefixes — "a" = v1 (no chartColor), "b" = v2 (with chartColor).
const CURRENT_VERSION = "b"
const VALID_VERSIONS = ["a", "b"]

export function toBase62(num) {
  if (num === 0) return "0"
  let result = ""
  let n = num
  while (n > 0) {
    result = BASE62[n % 62] + result
    n = Math.floor(n / 62)
  }
  return result
}

export function fromBase62(str) {
  let result = 0
  for (let i = 0; i < str.length; i++) {
    const idx = BASE62.indexOf(str[i])
    if (idx === -1) return -1
    result = result * 62 + idx
  }
  return result
}

// Encode a PresetConfig into a short alphanumeric code.
// Always produces v2 ("b") codes.
export function encodePreset(config) {
  const merged = {
    ...Object.fromEntries(PRESET_FIELDS_V2.map((f) => [f.key, f.values[0]])),
    ...config,
  }

  // Uses multiplication instead of bitwise ops (JS bitwise truncates to 32 bits).
  let bits = 0
  let offset = 0
  for (const field of PRESET_FIELDS_V2) {
    const idx = field.values.indexOf(merged[field.key])
    bits += (idx === -1 ? 0 : idx) * 2 ** offset
    offset += field.bits
  }

  return CURRENT_VERSION + toBase62(bits)
}

// Decode a preset code back into a PresetConfig.
// "a"-prefixed codes use v1 fields (no chartColor).
// "b"-prefixed codes use v2 fields (with chartColor).
export function decodePreset(code) {
  if (!code || code.length < 2) {
    return null
  }

  const version = code[0]
  if (!VALID_VERSIONS.includes(version)) {
    return null
  }

  const fields = version === "a" ? PRESET_FIELDS_V1 : PRESET_FIELDS_V2

  const bits = fromBase62(code.slice(1))
  if (bits < 0) return null

  const result = {}
  let offset = 0
  for (const field of fields) {
    const idx = Math.floor(bits / 2 ** offset) % 2 ** field.bits
    result[field.key] =
      idx < field.values.length ? field.values[idx] : field.values[0]
    offset += field.bits
  }

  if (version === "a") {
    result.fontHeading = "inherit"
  }

  return result
}

// Check if a string looks like a preset code (version char + base62).
export function isPresetCode(value) {
  if (!value || value.length < 2 || value.length > 10) {
    return false
  }

  if (!VALID_VERSIONS.includes(value[0])) {
    return false
  }

  for (let i = 1; i < value.length; i++) {
    if (BASE62.indexOf(value[i]) === -1) {
      return false
    }
  }

  return true
}
