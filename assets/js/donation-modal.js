// assets/js/donation-modal.js - Donation modal (merged; simple)
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('donate-modal');
    const btn = document.getElementById('donate-modal-btn');
    const cancel = document.getElementById('modal-cancel');
    const confirm = document.getElementById('modal-confirm');

    btn?.addEventListener('click', () => modal.style.display = 'flex');
    cancel?.addEventListener('click', () => modal.style.display = 'none');
    confirm?.addEventListener('click', () => {
        document.querySelector('form').submit();  // Submit main form
    });
    modal?.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });
});