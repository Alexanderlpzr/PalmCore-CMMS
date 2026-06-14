/**
 * Canvas-based watermarking for photos and signatures.
 * Operates on already-resized blobs — never on original full-res camera files.
 *
 * Photos:  dark strip at bottom (13% of height), JPEG 0.92 output
 * Signatures: header + footer strips, PNG output (lossless for signature strokes)
 */
export function useWatermark() {
    // ── helpers ──────────────────────────────────────────────────────────────

    function loadImage(blob) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(blob)
            const img = new Image()
            img.onload = () => { URL.revokeObjectURL(url); resolve(img) }
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('No se pudo cargar la imagen para watermark')) }
            img.src = url
        })
    }

    function toBlob(canvas, type, quality) {
        return new Promise((resolve, reject) => {
            canvas.toBlob(
                b => (b ? resolve(b) : reject(new Error('Error al generar imagen con watermark'))),
                type,
                quality,
            )
        })
    }

    function formatDateTime(date) {
        return new Intl.DateTimeFormat('es', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            hour12: false,
        }).format(date)
    }

    function gpsLine(gps) {
        if (!gps) return null
        const lat = gps.latitude.toFixed(4)
        const lon = gps.longitude.toFixed(4)
        const acc = Math.round(gps.accuracy)
        const lowAcc = gps.accuracy > 100 ? '  ⚠ baja precisión' : ''
        return `${lat}°, ${lon}°  ±${acc}m${lowAcc}`
    }

    function scaledFont(px, weight = 'normal') {
        return `${weight} ${px}px system-ui, -apple-system, sans-serif`
    }

    // ── photo watermark ───────────────────────────────────────────────────────

    /**
     * Draws a dark identification strip at the bottom of the image.
     *
     * @param {Blob} blob - Resized image blob (JPEG or PNG)
     * @param {{ workOrderNumber: string, technicianName: string, capturedAt: Date, gps: object|null }} meta
     * @returns {Promise<Blob>} JPEG blob at quality 0.92
     */
    async function applyToPhoto(blob, { workOrderNumber, technicianName, capturedAt, gps }) {
        const img = await loadImage(blob)

        const canvas = document.createElement('canvas')
        canvas.width = img.naturalWidth
        canvas.height = img.naturalHeight
        const ctx = canvas.getContext('2d')

        ctx.drawImage(img, 0, 0)

        const W = canvas.width
        const H = canvas.height
        const stripH = Math.max(88, Math.round(H * 0.13))
        const stripY = H - stripH
        const pad = Math.max(8, Math.round(W * 0.012))

        // Semi-transparent dark strip
        ctx.fillStyle = 'rgba(0, 0, 0, 0.75)'
        ctx.fillRect(0, stripY, W, stripH)

        // Thin separator line at top of strip
        ctx.fillStyle = 'rgba(255, 255, 255, 0.12)'
        ctx.fillRect(0, stripY, W, 1)

        const sizeBase = Math.max(11, Math.round(W * 0.018))
        const sizeSm = Math.max(10, Math.round(W * 0.015))

        // Left column — brand, WO number, date
        const col1X = pad
        let lineY = stripY + pad + sizeBase

        ctx.fillStyle = '#F59E0B'
        ctx.font = scaledFont(sizeSm, 'bold')
        ctx.fillText('PalmCore Artemis', col1X, lineY)

        lineY += Math.round(sizeBase * 1.5)
        ctx.fillStyle = '#FFFFFF'
        ctx.font = scaledFont(sizeBase, 'bold')
        ctx.fillText(workOrderNumber, col1X, lineY)

        lineY += Math.round(sizeBase * 1.4)
        ctx.fillStyle = '#94A3B8'
        ctx.font = scaledFont(sizeSm)
        ctx.fillText(formatDateTime(capturedAt), col1X, lineY)

        // Right column — GPS, technician (right-aligned)
        const col2X = W - pad
        let line2Y = stripY + pad + sizeBase

        const gpsText = gpsLine(gps)
        if (gpsText) {
            ctx.fillStyle = '#94A3B8'
            ctx.font = scaledFont(sizeSm)
            ctx.textAlign = 'right'
            ctx.fillText(gpsText, col2X, line2Y)
            line2Y += Math.round(sizeBase * 1.5)
        } else {
            line2Y += Math.round(sizeBase * 1.5)
        }

        ctx.fillStyle = '#E2E8F0'
        ctx.font = scaledFont(sizeSm)
        ctx.textAlign = 'right'
        ctx.fillText(technicianName, col2X, line2Y)

        ctx.textAlign = 'left'

        return toBlob(canvas, 'image/jpeg', 0.92)
    }

    // ── signature watermark ───────────────────────────────────────────────────

    /**
     * Adds identification header and footer strips to a signature image.
     *
     * @param {Blob} blob - Signature canvas PNG blob
     * @param {{ workOrderNumber: string, technicianName: string, signedAt: Date, gps: object|null }} meta
     * @returns {Promise<Blob>} PNG blob (lossless)
     */
    async function applyToSignature(blob, { workOrderNumber, technicianName, signedAt, gps }) {
        const img = await loadImage(blob)

        const W = img.naturalWidth
        const sigH = img.naturalHeight
        const stripH = Math.max(36, Math.round(W * 0.06))
        const totalH = sigH + stripH * 2

        const canvas = document.createElement('canvas')
        canvas.width = W
        canvas.height = totalH
        const ctx = canvas.getContext('2d')

        // Background fill (match signature pad background)
        ctx.fillStyle = 'rgb(39, 39, 42)'
        ctx.fillRect(0, 0, W, totalH)

        // Header strip
        ctx.fillStyle = 'rgba(0, 0, 0, 0.55)'
        ctx.fillRect(0, 0, W, stripH)

        const pad = Math.max(6, Math.round(W * 0.01))
        const sz = Math.max(11, Math.round(W * 0.038))

        ctx.fillStyle = '#F59E0B'
        ctx.font = scaledFont(sz, 'bold')
        ctx.fillText('PalmCore Artemis', pad, Math.round(stripH * 0.65))

        ctx.fillStyle = '#FFFFFF'
        ctx.font = scaledFont(sz)
        ctx.textAlign = 'right'
        ctx.fillText(workOrderNumber, W - pad, Math.round(stripH * 0.65))
        ctx.textAlign = 'left'

        // Original signature
        ctx.drawImage(img, 0, stripH)

        // Footer strip
        const footerY = stripH + sigH
        ctx.fillStyle = 'rgba(0, 0, 0, 0.55)'
        ctx.fillRect(0, footerY, W, stripH)

        const footerMidY = footerY + Math.round(stripH * 0.65)

        ctx.fillStyle = '#E2E8F0'
        ctx.font = scaledFont(sz)
        ctx.fillText(`Firmado por: ${technicianName}`, pad, footerMidY)

        const gpsText = gps ? `±${Math.round(gps.accuracy)}m` : null
        const dateStr = formatDateTime(signedAt)
        const rightText = gpsText ? `${dateStr}  ${gpsText}` : dateStr

        ctx.fillStyle = '#94A3B8'
        ctx.font = scaledFont(sz)
        ctx.textAlign = 'right'
        ctx.fillText(rightText, W - pad, footerMidY)
        ctx.textAlign = 'left'

        return toBlob(canvas, 'image/png')
    }

    return { applyToPhoto, applyToSignature }
}
