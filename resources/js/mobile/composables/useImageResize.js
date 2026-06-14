export function useImageResize() {
    function resizeImage(file, maxPx = 1600, quality = 0.8) {
        return new Promise((resolve, reject) => {
            const img = new Image()
            const url = URL.createObjectURL(file)

            img.onload = () => {
                URL.revokeObjectURL(url)
                let w = img.naturalWidth
                let h = img.naturalHeight

                if (w > maxPx || h > maxPx) {
                    if (w >= h) {
                        h = Math.round((h * maxPx) / w)
                        w = maxPx
                    } else {
                        w = Math.round((w * maxPx) / h)
                        h = maxPx
                    }
                }

                const canvas = document.createElement('canvas')
                canvas.width = w
                canvas.height = h
                canvas.getContext('2d').drawImage(img, 0, 0, w, h)
                canvas.toBlob(
                    blob => (blob ? resolve(blob) : reject(new Error('No se pudo procesar la imagen'))),
                    'image/jpeg',
                    quality,
                )
            }

            img.onerror = () => {
                URL.revokeObjectURL(url)
                reject(new Error('No se pudo cargar la imagen'))
            }

            img.src = url
        })
    }

    return { resizeImage }
}
