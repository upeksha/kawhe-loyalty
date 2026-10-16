const formats = ['image/png', 'image/jpeg', 'image/webp'];

function dimensions(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => { URL.revokeObjectURL(url); resolve([image.naturalWidth, image.naturalHeight]); };
        image.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Use a valid, readable PNG, JPG or WebP image.')); };
        image.src = url;
    });
}

export async function validateCardUpload(file, field, decode = dimensions) {
    if (!formats.includes(file.type)) return 'Choose a PNG, JPG or WebP image.';
    if (file.size > 2 * 1024 * 1024) return 'This image is too large. Choose an image under 2 MB.';
    try {
        const [width, height] = await decode(file);
        if (!width || !height || width * height > 40000000) return 'This image has invalid or oversized dimensions.';
        if (['card_photo', 'stamp_icon_upload'].includes(field) && (width > 3000 || height > 3000)) {
            return 'Choose an image no larger than 3000 × 3000 pixels.';
        }
    } catch { return 'This image cannot be read. Choose another PNG, JPG or WebP image.'; }
    return '';
}

if (typeof document !== 'undefined') {
    const approvedEvents = new WeakSet();
    const versions = new WeakMap();
    document.addEventListener('change', async (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'file'
            || !['card-design-form', 'loyalty-card-form'].includes(input.form?.id)
            || approvedEvents.has(event)) return;
        event.stopImmediatePropagation();
        const version = (versions.get(input) || 0) + 1;
        versions.set(input, version);
        let message = document.getElementById(`${input.id}-upload-feedback`);
        if (!message) {
            message = document.createElement('p');
            message.id = `${input.id}-upload-feedback`;
            message.className = 'mt-2 text-sm text-red-700';
            message.setAttribute('role', 'status');
            (input.closest('label') || input).insertAdjacentElement('afterend', message);
            input.setAttribute('aria-describedby', `${input.getAttribute('aria-describedby') || ''} ${message.id}`.trim());
        }
        const file = input.files[0];
        input.setCustomValidity(file ? 'Please wait while this image is checked.' : '');
        message.textContent = file ? 'Checking image…' : '';
        const error = file ? await validateCardUpload(file, input.name) : '';
        if (versions.get(input) !== version) return;
        input.setCustomValidity(error);
        input.setAttribute('aria-invalid', error ? 'true' : 'false');
        message.textContent = error;
        if (error) return;
        // Only valid selections reach the artwork and logo preview listeners.
        const checked = new Event('change', { bubbles: true });
        approvedEvents.add(checked);
        input.dispatchEvent(checked);
    }, true);
}
