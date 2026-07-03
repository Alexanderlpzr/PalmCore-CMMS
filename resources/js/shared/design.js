// Fronda CMMS — shared design vocabulary (single source of truth).
//
// Both the Ops panel (light theme) and the Mobile PWA (dark theme) import from
// here so a given status/priority always shows the SAME label and the SAME
// semantic color across the whole product. Labels mirror the PHP domain enums.
//
// The product has exactly five color roles. Do not introduce new ones.
//   success → operativo / completado        warning → advertencia / en proceso
//   danger  → crítico / cancelado / fallido  info    → informativo / planificado
//   neutral → inactivo / archivado
// `brand` is the Fronda green used for primary actions (same family as success).

export const TONES = ['success', 'warning', 'danger', 'info', 'neutral', 'brand']

const TONE_CLASSES = {
    // Solid pills on light surfaces (Ops panel, Filament-adjacent web).
    light: {
        success: 'bg-emerald-100 text-emerald-700',
        warning: 'bg-amber-100 text-amber-700',
        danger:  'bg-red-100 text-red-700',
        info:    'bg-blue-100 text-blue-700',
        neutral: 'bg-gray-100 text-gray-600',
        brand:   'bg-emerald-100 text-emerald-700',
    },
    // Translucent pills on the dark surfaces of the mobile PWA.
    dark: {
        success: 'bg-emerald-500/15 text-emerald-400',
        warning: 'bg-amber-500/15 text-amber-400',
        danger:  'bg-red-500/15 text-red-400',
        info:    'bg-blue-500/15 text-blue-400',
        neutral: 'bg-zinc-700/40 text-zinc-400',
        brand:   'bg-emerald-500/15 text-emerald-400',
    },
}

export function toneClasses(tone, theme = 'light') {
    return TONE_CLASSES[theme]?.[tone] ?? TONE_CLASSES[theme]?.neutral ?? ''
}

// Icon "chip" tints: a soft background + a stronger icon foreground for the
// rounded icon tiles used on cards and list rows.
const TONE_ICON = {
    light: {
        success: 'bg-emerald-100 text-emerald-600',
        warning: 'bg-amber-100 text-amber-600',
        danger:  'bg-red-100 text-red-600',
        info:    'bg-blue-100 text-blue-600',
        neutral: 'bg-gray-100 text-gray-500',
        brand:   'bg-emerald-100 text-emerald-600',
    },
    dark: {
        success: 'bg-emerald-500/15 text-emerald-400',
        warning: 'bg-amber-500/15 text-amber-400',
        danger:  'bg-red-500/15 text-red-400',
        info:    'bg-blue-500/15 text-blue-400',
        neutral: 'bg-zinc-700/40 text-zinc-400',
        brand:   'bg-emerald-500/15 text-emerald-400',
    },
}

export function toneIcon(tone, theme = 'light') {
    return TONE_ICON[theme]?.[tone] ?? TONE_ICON[theme]?.neutral ?? ''
}

// ── Domain vocabularies (labels aligned with the PHP enums) ────────────────────

export const WORK_ORDER_STATUS = {
    draft:       { label: 'Borrador',     tone: 'neutral' },
    planned:     { label: 'Planificada',  tone: 'info' },
    in_progress: { label: 'En Ejecución', tone: 'warning' },
    on_hold:     { label: 'En Espera',    tone: 'warning' },
    completed:   { label: 'Completada',   tone: 'success' },
    verified:    { label: 'Verificada',   tone: 'success' },
    closed:      { label: 'Cerrada',      tone: 'neutral' },
    cancelled:   { label: 'Cancelada',    tone: 'danger' },
}

export const MAINTENANCE_REQUEST_STATUS = {
    draft:        { label: 'Borrador',        tone: 'neutral' },
    submitted:    { label: 'Enviado',         tone: 'info' },
    under_review: { label: 'En Revisión',     tone: 'warning' },
    approved:     { label: 'Aprobado',        tone: 'success' },
    rejected:     { label: 'Rechazado',       tone: 'danger' },
    cancelled:    { label: 'Cancelado',       tone: 'neutral' },
    converted:    { label: 'Convertido a OT', tone: 'success' },
}

export const EQUIPMENT_STATUS = {
    active:            { label: 'Activo',           tone: 'success' },
    inactive:          { label: 'Inactivo',         tone: 'neutral' },
    under_maintenance: { label: 'En Mantenimiento', tone: 'warning' },
    retired:           { label: 'Retirado',         tone: 'danger' },
    disposed:          { label: 'Dado de Baja',     tone: 'neutral' },
}

export const PRIORITY = {
    p1_critical: { label: 'Crítica',     tone: 'danger' },
    p2_high:     { label: 'Alta',        tone: 'warning' },
    p3_medium:   { label: 'Media',       tone: 'info' },
    p4_low:      { label: 'Baja',        tone: 'neutral' },
    p5_planned:  { label: 'Planificada', tone: 'neutral' },
}

export const CRITICALITY = {
    critical: { label: 'Crítico', tone: 'danger' },
    high:     { label: 'Alto',    tone: 'warning' },
    medium:   { label: 'Medio',   tone: 'info' },
    low:      { label: 'Bajo',    tone: 'neutral' },
}

export const ALERT_SEVERITY = {
    critical: { label: 'Crítico',     tone: 'danger' },
    warning:  { label: 'Advertencia', tone: 'warning' },
    info:     { label: 'Informativo', tone: 'info' },
    // Numeric-style severities map onto the same five roles when present.
    high:     { label: 'Alto',         tone: 'warning' },
    medium:   { label: 'Medio',        tone: 'info' },
    low:      { label: 'Bajo',         tone: 'neutral' },
}

/**
 * Resolve a value within a vocabulary map into { label, tone, classes }.
 * Unknown values degrade gracefully to a neutral pill with the raw value.
 */
export function describe(map, value, theme = 'light') {
    const entry = map[value] ?? { label: value ?? '—', tone: 'neutral' }
    return { ...entry, classes: toneClasses(entry.tone, theme) }
}
