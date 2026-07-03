<template>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500">Evidencia y documentación</h2>
                <p v-if="missingSummary" class="text-xs text-amber-600 mt-1">{{ missingSummary }}</p>
                <p v-else class="text-xs text-emerald-600 mt-1">Evidencia completa para esta etapa</p>
            </div>
            <Transition name="fade">
                <span v-if="savedFlash" class="saved-flash inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    {{ savedFlash }}
                </span>
            </Transition>
        </div>

        <!-- Fotos y documentos -->
        <section class="px-5 py-4 border-b border-gray-50">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-semibold text-gray-700">Fotos y documentos <span class="text-gray-400 font-normal">({{ attachments.length }})</span></h3>
                <button @click="openUpload()" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">+ Subir</button>
            </div>
            <div v-if="attachments.length" class="grid grid-cols-3 lg:grid-cols-6 gap-2.5">
                <div v-for="a in attachments" :key="a.id"
                    @click="isImage(a) ? (lightbox = a) : openInNewTab(a.url)"
                    @keydown.enter="isImage(a) ? (lightbox = a) : openInNewTab(a.url)"
                    tabindex="0"
                    role="button"
                    :aria-label="isImage(a) ? `Ver foto: ${a.caption ?? a.file_name}` : `Abrir archivo: ${a.file_name}`"
                    class="aspect-square rounded-xl overflow-hidden bg-slate-100 cursor-pointer hover:opacity-90 transition-opacity relative border border-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                    <img v-if="isImage(a)" :src="a.url" :alt="a.caption ?? a.file_name" loading="lazy" decoding="async" class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full flex flex-col items-center justify-center gap-1 p-1.5 text-center">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        <p class="text-[10px] text-gray-500 truncate w-full">{{ a.file_name }}</p>
                    </div>
                    <span class="absolute top-1 left-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-black/50 text-white">{{ attachmentTypeLabel[a.attachment_type] ?? a.attachment_type }}</span>
                </div>
            </div>
            <p v-else class="text-xs text-gray-400 italic py-3 text-center">Sin fotos ni documentos todavía</p>
        </section>

        <!-- Checklist (solo lectura — guía del plan, no un registro de tareas hechas) -->
        <section v-if="checklist.length" class="px-5 py-4 border-b border-gray-50">
            <h3 class="text-xs font-semibold text-gray-700 mb-3">Checklist del plan <span class="text-gray-400 font-normal">({{ checklist.length }})</span></h3>
            <ul class="space-y-1.5">
                <li v-for="task in checklist" :key="task.id" class="flex items-start gap-2 text-sm text-gray-700">
                    <svg class="w-4 h-4 text-gray-300 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/></svg>
                    <span>{{ task.title }}</span>
                </li>
            </ul>
            <p class="text-[11px] text-gray-400 mt-2">Guía de referencia — no se marca como hecho aquí; la evidencia real son las fotos y notas registradas.</p>
        </section>

        <!-- Notas técnicas -->
        <section class="px-5 py-4 border-b border-gray-50">
            <h3 class="text-xs font-semibold text-gray-700 mb-3">Notas técnicas <span class="text-gray-400 font-normal">({{ comments.length }})</span></h3>
            <div v-if="comments.length" class="space-y-2 mb-3">
                <div v-for="c in recentComments" :key="c.id" class="rounded-xl p-3 border"
                    :class="c.is_internal ? 'border-amber-200 bg-amber-50/50' : 'border-gray-100 bg-gray-50/50'">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-xs font-semibold text-gray-900">{{ c.user?.name ?? 'Usuario' }}</p>
                        <div class="flex items-center gap-1.5">
                            <span v-if="c.is_internal" class="text-[10px] border border-amber-300 text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded-full font-semibold">Interno</span>
                            <span class="text-[10px] text-gray-400">{{ relativeTime(c.created_at) }}</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-700 whitespace-pre-line">{{ c.body }}</p>
                </div>
                <button v-if="comments.length > visibleComments" @click="visibleComments += 5" class="text-xs text-gray-500 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">
                    Ver {{ comments.length - visibleComments }} más
                </button>
            </div>
            <p v-else class="text-xs text-gray-400 italic mb-3">Sin notas técnicas todavía</p>

            <div class="rounded-xl border border-gray-200 p-3 focus-within:ring-2 focus-within:ring-indigo-500 transition-shadow">
                <textarea
                    v-model="commentBody"
                    rows="2"
                    placeholder="Escribe una nota técnica..."
                    class="w-full text-sm border-0 p-0 resize-none focus:outline-none focus:ring-0 placeholder-gray-400"
                />
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-50">
                    <label class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer select-none">
                        <input type="checkbox" v-model="commentInternal" class="rounded border-gray-300 focus:ring-indigo-500" />
                        Nota interna
                    </label>
                    <button
                        @click="submitComment"
                        :disabled="!commentBody.trim() || submittingComment"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg disabled:opacity-50 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                    >
                        {{ submittingComment ? '…' : 'Enviar' }}
                    </button>
                </div>
                <p v-if="commentError" role="alert" aria-live="polite" class="mt-1.5 text-xs text-red-600">{{ commentError }}</p>
            </div>
        </section>

        <!-- Firmas -->
        <section class="px-5 py-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-semibold text-gray-700">Firmas <span class="text-gray-400 font-normal">({{ signatures.length }})</span></h3>
                <div class="flex items-center gap-2">
                    <button v-if="!technicianSignature" @click="openSignature('technician_completion')" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">+ Firmar (técnico)</button>
                    <button v-if="!supervisorSignature" @click="openSignature('supervisor_verification')" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">+ Firmar (supervisor)</button>
                </div>
            </div>
            <div v-if="signatures.length" class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                <div v-for="s in signatures" :key="s.id" class="rounded-xl border border-emerald-100 p-3 flex items-start gap-3">
                    <img v-if="s.image_url" :src="s.image_url" alt="Firma" loading="lazy" decoding="async" class="w-14 h-9 object-contain bg-gray-50 rounded border border-gray-100 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700">
                            {{ signatureTypeLabel[s.signature_type] ?? s.signature_type }}
                        </span>
                        <p class="text-sm font-semibold text-gray-900 mt-1">{{ s.user?.name ?? 'Usuario' }}</p>
                        <p class="text-xs text-gray-500">{{ formatDateTime(s.signed_at) }}</p>
                    </div>
                </div>
            </div>
            <p v-else class="text-xs text-gray-400 italic py-3 text-center">Sin firmas registradas</p>
        </section>
    </div>

    <!-- Upload modal -->
    <SlidePanel :open="uploadOpen" title="Subir evidencia" description="Fotos y documentos quedan asociados a esta orden de trabajo." @close="uploadOpen = false">
        <div class="p-5 space-y-4">
            <div>
                <label class="text-xs font-semibold text-gray-600 block mb-1.5">Tipo</label>
                <select v-model="uploadType" class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="evidence">Evidencia</option>
                    <option value="before_photo">Antes</option>
                    <option value="after_photo">Después</option>
                    <option value="document">Documento</option>
                    <option value="report">Informe</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 block mb-1.5">Archivo</label>
                <input type="file" ref="fileInputRef" @change="onFileSelected" accept="image/*,.pdf,.doc,.docx" class="w-full text-sm" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 block mb-1.5">Descripción (opcional)</label>
                <input v-model="uploadCaption" type="text" placeholder="Ej: vista general del daño" class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 placeholder-gray-400" />
            </div>
            <p v-if="uploadError" role="alert" aria-live="polite" class="text-xs text-red-600">{{ uploadError }}</p>
        </div>
        <template #footer>
            <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button @click="uploadOpen = false" class="px-4 py-2.5 text-xs font-semibold text-gray-600 hover:text-gray-900 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 rounded-lg">Cancelar</button>
                <button @click="submitUpload" :disabled="!uploadFile || uploadingFile" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl disabled:opacity-50 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                    {{ uploadingFile ? 'Subiendo…' : 'Subir' }}
                </button>
            </div>
        </template>
    </SlidePanel>

    <!-- Signature modal -->
    <SlidePanel :open="signatureOpen" :title="signatureType === 'supervisor_verification' ? 'Firma del supervisor' : 'Firma del técnico'" description="Dibuja la firma dentro del recuadro." @close="signatureOpen = false">
        <div class="p-5 space-y-4">
            <div class="relative border border-gray-200 rounded-xl overflow-hidden bg-gray-50" style="height:180px;">
                <canvas
                    ref="canvasRef"
                    class="w-full h-full touch-none"
                    :class="{ 'pointer-events-none': !padReady }"
                    style="cursor: crosshair;"
                    role="img"
                    aria-label="Área para dibujar la firma con el mouse, el dedo o un lápiz óptico"
                />
                <div v-if="!padReady" class="absolute inset-0 flex items-center justify-center text-xs text-gray-400 bg-gray-50">
                    Preparando lienzo de firma…
                </div>
            </div>
            <div class="flex justify-end">
                <button @click="clearSignature" class="text-xs text-gray-500 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 rounded">Limpiar</button>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 block mb-1.5">Notas (opcional)</label>
                <input v-model="signatureNotes" type="text" placeholder="Observaciones adicionales" class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 placeholder-gray-400" />
            </div>
            <p v-if="signatureError" role="alert" aria-live="polite" class="text-xs text-red-600">{{ signatureError }}</p>
        </div>
        <template #footer>
            <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button @click="signatureOpen = false" class="px-4 py-2.5 text-xs font-semibold text-gray-600 hover:text-gray-900 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 rounded-lg">Cancelar</button>
                <button @click="submitSignature" :disabled="submittingSignature" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl disabled:opacity-50 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                    {{ submittingSignature ? 'Guardando…' : 'Guardar firma' }}
                </button>
            </div>
        </template>
    </SlidePanel>

    <!-- Support (comentario urgente) modal -->
    <SlidePanel :open="supportOpen" title="Solicitar apoyo" description="Se registra como nota interna, visible para el equipo de mantenimiento." @close="supportOpen = false">
        <div class="p-5 space-y-4">
            <textarea
                v-model="supportBody"
                rows="4"
                placeholder="Describe qué apoyo necesitas..."
                class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5 resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500 placeholder-gray-400"
            />
            <p v-if="supportError" role="alert" aria-live="polite" class="text-xs text-red-600">{{ supportError }}</p>
        </div>
        <template #footer>
            <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button @click="supportOpen = false" class="px-4 py-2.5 text-xs font-semibold text-gray-600 hover:text-gray-900 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 rounded-lg">Cancelar</button>
                <button @click="submitSupport" :disabled="!supportBody.trim() || submittingSupport" class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-xl disabled:opacity-50 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2">
                    {{ submittingSupport ? 'Enviando…' : 'Solicitar apoyo' }}
                </button>
            </div>
        </template>
    </SlidePanel>

    <!-- Lightbox -->
    <Teleport to="body">
        <div
            v-if="lightbox"
            ref="lightboxRef"
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            aria-label="Foto ampliada"
            class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4 outline-none"
            @click.self="lightbox = null"
            @keydown.esc="lightbox = null"
        >
            <div class="relative max-w-3xl w-full">
                <img :src="lightbox.url" :alt="lightbox.caption ?? ''" class="w-full rounded-2xl object-contain max-h-[80vh]" />
                <p v-if="lightbox.caption" class="text-white text-sm mt-3 text-center">{{ lightbox.caption }}</p>
                <button @click="lightbox = null" class="absolute -top-3 -right-3 w-8 h-8 rounded-full bg-white text-gray-700 hover:text-gray-900 flex items-center justify-center shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                    <span class="sr-only">Cerrar</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue'
import { useApi } from '../../composables/useApi.js'
import SlidePanel from '../SlidePanel.vue'

const props = defineProps({
    workOrder: { type: Object, required: true },
})

const emit = defineEmits(['refresh'])

const api = useApi()

const attachmentTypeLabel = {
    before_photo: 'Antes', after_photo: 'Después', evidence: 'Evidencia', report: 'Informe', document: 'Documento',
}
const signatureTypeLabel = {
    technician_completion: 'Técnico', supervisor_verification: 'Supervisor',
}

const attachments = computed(() => [...(props.workOrder.attachments ?? [])].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)))
const signatures = computed(() => props.workOrder.signatures ?? [])
const comments = computed(() => [...(props.workOrder.comments ?? [])].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)))
const checklist = computed(() => props.workOrder.mission?.checklist ?? [])
const technicianSignature = computed(() => signatures.value.find(s => s.signature_type === 'technician_completion'))
const supervisorSignature = computed(() => signatures.value.find(s => s.signature_type === 'supervisor_verification'))

const missingSummary = computed(() => {
    const missing = []
    if (!attachments.value.length) missing.push('fotos')
    if (!technicianSignature.value) missing.push('firma del técnico')
    if (!missing.length) return null
    return `Falta: ${missing.join(', ')}`
})

const visibleComments = ref(5)
const recentComments = computed(() => comments.value.slice(0, visibleComments.value))

// Brief confirmation after a save — the panel already closes and the data
// refreshes silently, so this is the only visible acknowledgment that the
// action actually landed.
const savedFlash = ref(null)
let savedFlashTimer = null
function flashSaved(label) {
    savedFlash.value = label
    clearTimeout(savedFlashTimer)
    savedFlashTimer = setTimeout(() => { savedFlash.value = null }, 2200)
}

function isImage(a) {
    return (a.mime_type ?? '').startsWith('image/')
}
function openInNewTab(url) {
    window.open(url, '_blank', 'noopener')
}
function formatDateTime(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString('es', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function relativeTime(iso) {
    if (!iso) return ''
    const diff = Date.now() - new Date(iso).getTime()
    const h = Math.floor(diff / 36e5)
    if (h < 1) return 'hace menos de 1h'
    if (h < 24) return `hace ${h}h`
    return `hace ${Math.floor(h / 24)}d`
}

const lightbox = ref(null)
const lightboxRef = ref(null)
watch(lightbox, async (open) => {
    if (!open) return
    await nextTick()
    lightboxRef.value?.focus()
})

// ── Upload ───────────────────────────────────────────────────────────────────

const uploadOpen = ref(false)
const uploadType = ref('evidence')
const uploadFile = ref(null)
const uploadCaption = ref('')
const uploadingFile = ref(false)
const uploadError = ref(null)
const fileInputRef = ref(null)

function openUpload() {
    uploadType.value = 'evidence'
    uploadFile.value = null
    uploadCaption.value = ''
    uploadError.value = null
    uploadOpen.value = true
}

function onFileSelected(e) {
    uploadFile.value = e.target.files?.[0] ?? null
}

async function submitUpload() {
    if (!uploadFile.value) return
    uploadingFile.value = true
    uploadError.value = null
    try {
        const form = new FormData()
        form.append('file', uploadFile.value)
        form.append('attachment_type', uploadType.value)
        if (uploadCaption.value) form.append('caption', uploadCaption.value)
        await api.upload(`work-orders/${props.workOrder.id}/media`, form)
        uploadOpen.value = false
        flashSaved('Evidencia subida')
        emit('refresh')
    } catch (err) {
        uploadError.value = err?.message ?? 'No se pudo subir el archivo.'
    } finally {
        uploadingFile.value = false
    }
}

// ── Signature ────────────────────────────────────────────────────────────────

const signatureOpen = ref(false)
const signatureType = ref('technician_completion')
const signatureNotes = ref('')
const submittingSignature = ref(false)
const signatureError = ref(null)
const canvasRef = ref(null)
const padReady = ref(false)
let pad = null

function openSignature(type) {
    signatureType.value = type
    signatureNotes.value = ''
    signatureError.value = null
    padReady.value = false
    signatureOpen.value = true
}

watch(signatureOpen, async (isOpen) => {
    if (!isOpen) { pad?.off(); pad = null; padReady.value = false; return }
    // Loaded only when a technician actually opens the signature pad, not on
    // every Evidence Zone mount — signature_pad is dead weight otherwise. The
    // canvas stays visually disabled (padReady) until this resolves, so an
    // early stroke on a slow connection can't be silently lost.
    const [{ default: SignaturePad }] = await Promise.all([import('signature_pad'), nextTick()])
    const canvas = canvasRef.value
    if (!canvas) return
    const ratio = Math.max(window.devicePixelRatio || 1, 1)
    canvas.width = canvas.offsetWidth * ratio
    canvas.height = canvas.offsetHeight * ratio
    canvas.getContext('2d').scale(ratio, ratio)
    pad = new SignaturePad(canvas, { backgroundColor: 'rgb(249, 250, 251)', penColor: 'rgb(30, 41, 59)' })
    padReady.value = true
})

function clearSignature() {
    pad?.clear()
}

async function submitSignature() {
    if (!pad || pad.isEmpty()) {
        signatureError.value = 'Dibuja la firma antes de guardar.'
        return
    }
    submittingSignature.value = true
    signatureError.value = null
    try {
        const blob = await new Promise((resolve, reject) => {
            canvasRef.value.toBlob(b => (b ? resolve(b) : reject(new Error('No se pudo generar la imagen de la firma.'))), 'image/png')
        })
        const form = new FormData()
        form.append('signature_image', blob, `signature_${Date.now()}.png`)
        form.append('signature_type', signatureType.value)
        if (signatureNotes.value) form.append('notes', signatureNotes.value)
        await api.upload(`work-orders/${props.workOrder.id}/signature`, form)
        signatureOpen.value = false
        flashSaved('Firma guardada')
        emit('refresh')
    } catch (err) {
        signatureError.value = err?.message ?? 'No se pudo guardar la firma.'
    } finally {
        submittingSignature.value = false
    }
}

// ── Comments ─────────────────────────────────────────────────────────────────

const commentBody = ref('')
const commentInternal = ref(false)
const submittingComment = ref(false)
const commentError = ref(null)

async function submitComment() {
    if (!commentBody.value.trim()) return
    submittingComment.value = true
    commentError.value = null
    try {
        await api.post(`work-orders/${props.workOrder.id}/comments`, {
            body: commentBody.value.trim(),
            is_internal: commentInternal.value,
        })
        commentBody.value = ''
        commentInternal.value = false
        flashSaved('Nota guardada')
        emit('refresh')
    } catch (err) {
        commentError.value = err?.message ?? 'No se pudo enviar la nota.'
    } finally {
        submittingComment.value = false
    }
}

// ── Support (comentario interno urgente) ──────────────────────────────────────

const supportOpen = ref(false)
const supportBody = ref('')
const submittingSupport = ref(false)
const supportError = ref(null)

function openSupport() {
    supportBody.value = ''
    supportError.value = null
    supportOpen.value = true
}

async function submitSupport() {
    if (!supportBody.value.trim()) return
    submittingSupport.value = true
    supportError.value = null
    try {
        await api.post(`work-orders/${props.workOrder.id}/comments`, {
            body: `[Apoyo solicitado] ${supportBody.value.trim()}`,
            is_internal: true,
        })
        supportOpen.value = false
        flashSaved('Apoyo solicitado')
        emit('refresh')
    } catch (err) {
        supportError.value = err?.message ?? 'No se pudo enviar la solicitud.'
    } finally {
        submittingSupport.value = false
    }
}

defineExpose({ openUpload, openSupport, openSignature })
</script>
