const TARGET_BYTES = 1_900_000;

// Resize and re-encode before transmission; never upload the original camera file.
export async function prepareReportPhoto(file) {
    const url = URL.createObjectURL(file);
    const image = new Image();
    try {
        image.src = url;
        await image.decode();
        const scale = Math.min(1, 1600 / Math.max(image.naturalWidth, image.naturalHeight));
        const canvas = document.createElement('canvas');
        let width = Math.max(1, Math.round(image.naturalWidth * scale));
        let height = Math.max(1, Math.round(image.naturalHeight * scale));
        for (let attempt = 0; attempt < 8; attempt++) {
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext('2d');
            if (!context) throw new Error('Pemrosesan foto tidak didukung browser ini.');
            context.fillStyle = '#fff';
            context.fillRect(0, 0, width, height);
            context.drawImage(image, 0, 0, width, height);
            const quality = Math.max(0.45, 0.84 - attempt * 0.07);
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality));
            if (blob && blob.size <= TARGET_BYTES) {
                return new File([blob], `kegiatan-${Date.now()}.jpg`, { type: 'image/jpeg' });
            }
            width = Math.max(1, Math.round(width * 0.8));
            height = Math.max(1, Math.round(height * 0.8));
        }
        throw new Error('Foto belum bisa diperkecil di bawah 2 MB. Silakan ambil ulang.');
    } catch (error) {
        if (error instanceof Error && error.message.includes('2 MB')) throw error;
        throw new Error('Foto tidak dapat diproses. Silakan ambil ulang dengan kamera perangkat.');
    } finally {
        URL.revokeObjectURL(url);
    }
}

export async function uploadReportCameraPhoto(file, wire, progress) {
    const photo = await prepareReportPhoto(file);
    await new Promise((resolve, reject) => {
        wire.upload('photos', photo, resolve,
            () => reject(new Error('Foto gagal diunggah. Periksa koneksi lalu ambil ulang.')),
            event => progress(event.detail.progress),
            () => reject(new Error('Unggahan foto dibatalkan.')));
    });
}
