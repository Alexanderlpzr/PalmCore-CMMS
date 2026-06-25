/**
 * Plant area taxonomy for structured asset code generation.
 *
 * Code format: {area_prefix}-{type_code}-{seq}
 * Example: 05-BMB-001 = Extracción / Bomba / unit 001
 */

export const PLANT_AREAS = [
    { prefix: '01', name: 'Recepción' },
    { prefix: '02', name: 'Esterilización' },
    { prefix: '03', name: 'Desfrutado' },
    { prefix: '04', name: 'Raquis' },
    { prefix: '05', name: 'Extracción' },
    { prefix: '06', name: 'Clarificación' },
    { prefix: '07', name: 'Palmistería' },
    { prefix: '08', name: 'Desfibrado' },
    { prefix: '09', name: 'Cogeneración' },
]

/** Map of prefix → area name for fast lookup */
export const AREA_BY_PREFIX = Object.fromEntries(
    PLANT_AREAS.map(a => [a.prefix, a.name])
)

/**
 * Parse a structured asset code into its parts.
 * Returns null if the code does not match the expected format.
 *
 * @param {string} code — e.g. "05-BMB-001"
 * @returns {{ prefix: string, areaName: string|null, typeCode: string, seq: string }|null}
 */
export function parseAssetCode(code) {
    if (!code) { return null }
    const match = code.match(/^(\d{2})-([A-Z]{2,6})-(\d{3,})$/)
    if (!match) { return null }
    const [, prefix, typeCode, seq] = match
    return {
        prefix,
        areaName: AREA_BY_PREFIX[prefix] ?? null,
        typeCode,
        seq,
    }
}

/**
 * Format a structured code for display, enriching it with the area name.
 * Falls back to the raw code if it cannot be parsed.
 *
 * @param {string} code — e.g. "05-BMB-001"
 * @returns {string} — e.g. "05-BMB-001 (Extracción)"
 */
export function formatAssetCode(code) {
    const parsed = parseAssetCode(code)
    if (!parsed?.areaName) { return code ?? '' }
    return `${code} · ${parsed.areaName}`
}

/**
 * Get the area name for a given equipment code.
 * Returns null if the code is unstructured or area is unknown.
 *
 * @param {string} code
 * @returns {string|null}
 */
export function getAreaFromCode(code) {
    return parseAssetCode(code)?.areaName ?? null
}
