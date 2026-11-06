function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

export default getCsrfToken;

if (typeof window !== 'undefined') {
    window.getCsrfToken = getCsrfToken;
}
