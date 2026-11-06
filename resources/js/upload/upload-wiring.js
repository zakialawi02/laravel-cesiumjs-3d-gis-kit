function wireUploadControls(activeUploadsList, uploadStates, {
    cancelBtnClass = 'cancel-upload-btn',
    onCancel,
} = {}) {
    activeUploadsList.addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;

        const progressItem = btn.closest('[id^="upload_"]');
        if (!progressItem) return;

        const uploadId = progressItem.id;

        if (btn.classList.contains('dismiss-btn')) {
            uploadStates.delete(uploadId);
            progressItem.classList.add('opacity-0', 'transition-opacity', 'duration-300');
            setTimeout(() => progressItem.remove(), 300);
            return;
        }

        const state = uploadStates.get(uploadId);
        if (!state) return;

        if (btn.classList.contains('pause-resume-btn')) {
            state.paused = !state.paused;
            const icon = btn.querySelector('i');
            const label = progressItem.querySelector('.progress-label');

            if (state.paused) {
                if (icon) icon.className = 'ri-play-line text-sm';
                if (label) label.textContent = 'Paused...';
                btn.title = 'Resume Upload';
            } else {
                if (icon) icon.className = 'ri-pause-line text-sm';
                if (label) label.textContent = 'Uploading...';
                btn.title = 'Pause Upload';
            }
        } else if (btn.classList.contains(cancelBtnClass)) {
            if (confirm('Cancel this upload?')) {
                btn.disabled = true;
                try {
                    await state.cancel();
                    uploadStates.delete(uploadId);
                    if (onCancel) onCancel(uploadId, progressItem, state);
                    else progressItem.remove();
                } catch (error) {
                    const label = progressItem.querySelector('.progress-label');
                    if (label) label.textContent = 'Cancellation could not be confirmed. Click cancel to retry.';
                } finally {
                    btn.disabled = false;
                }
            }
        }
    });
}

export default wireUploadControls;

if (typeof window !== 'undefined') {
    window.wireUploadControls = wireUploadControls;
}
