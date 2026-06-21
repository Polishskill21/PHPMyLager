// Shared feedback helpers (loaded on every page via layouts/app.blade.php).
//
// toast()      – show a transient message in #toast-area (auto-removes after 3.5s).
// flashToast() – queue a message in sessionStorage so it survives a full page
//                reload. Pages that mutate data reload right after success, which
//                would otherwise wipe an immediately-shown toast before it can be
//                read. Queue it, reload, and it is displayed on the next load.
// esc()        – minimal HTML escaping for values interpolated into innerHTML.

function esc(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function toast(msg, type = 'info') {
    const area = document.getElementById('toast-area');
    if (!area) return;

    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `<span>${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span> ${esc(msg)}`;
    area.appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

const FLASH_TOAST_KEY = 'flashToast';

// Queue a toast to be shown after the next page (re)load.
function flashToast(msg, type = 'info') {
    try {
        sessionStorage.setItem(FLASH_TOAST_KEY, JSON.stringify({ msg, type }));
    } catch (e) {
        // sessionStorage unavailable (private mode / disabled) — fall back to an
        // immediate toast so the message is at least shown before any reload.
        toast(msg, type);
    }
}

// On load, drain any queued flash toast left by the previous page.
document.addEventListener('DOMContentLoaded', () => {
    let raw = null;
    try {
        raw = sessionStorage.getItem(FLASH_TOAST_KEY);
        if (raw) sessionStorage.removeItem(FLASH_TOAST_KEY);
    } catch (e) {
        return;
    }
    if (!raw) return;

    try {
        const { msg, type } = JSON.parse(raw);
        if (msg) toast(msg, type || 'info');
    } catch (e) {
        // Ignore malformed payloads.
    }
});
