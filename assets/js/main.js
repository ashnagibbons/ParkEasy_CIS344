/**
 * These are all the frontend helper functions with client-side checks that are sent to php pages
 */
document.addEventListener('DOMContentLoaded', () => {

    // ---- Mobile nav toggle ----
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.querySelector('.main-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open);
        });
    }

    // ---- Live price preview on the reserve form ----
    const preview = document.getElementById('price-preview');
    const start = document.getElementById('start');
    const end = document.getElementById('end');
    if (preview && start && end) {
        const rate = parseFloat(preview.dataset.rate);
        const update = () => {
            if (!start.value || !end.value) { preview.textContent = ''; return; }
            const [sh, sm] = start.value.split(':').map(Number);
            const [eh, em] = end.value.split(':').map(Number);
            const hours = ((eh * 60 + em) - (sh * 60 + sm)) / 60;
            preview.textContent = hours > 0
                ? `${hours.toFixed(2).replace(/\.00$/, '')} hour(s) × $${rate.toFixed(2)} = $${(hours * rate).toFixed(2)}`
                : 'End time must be after start time.';
        };
        // Only allow end times after the chosen start time
        const limitEndTimes = () => {
            Array.from(end.options).forEach((opt) => {
                if (opt.value) opt.disabled = start.value !== '' && opt.value <= start.value;
            });
            if (end.value && end.selectedOptions[0].disabled) end.value = '';
        };

        start.addEventListener('change', () => { limitEndTimes(); update(); });
        end.addEventListener('change', update);
        limitEndTimes();
        update();
    }

    // ---- Card number: group digits in 4s ----
    const card = document.getElementById('card_number');
    if (card) {
        card.addEventListener('input', () => {
            const digits = card.value.replace(/\D/g, '').slice(0, 19);
            card.value = digits.replace(/(.{4})/g, '$1 ').trim();
        });
    }

    // ---- Expiry: auto-insert slash (MM/YY) ----
    const expiry = document.getElementById('expiry');
    if (expiry) {
        expiry.addEventListener('input', (e) => {
            let v = expiry.value.replace(/\D/g, '').slice(0, 4);
            if (v.length >= 3) v = v.slice(0, 2) + '/' + v.slice(2);
            else if (v.length === 2 && e.inputType !== 'deleteContentBackward') v += '/';
            expiry.value = v;
        });
    }

    // ---- CVV: digits only ----
    const cvv = document.getElementById('cvv');
    if (cvv) {
        cvv.addEventListener('input', () => { cvv.value = cvv.value.replace(/\D/g, '').slice(0, 4); });
    }

    // ---- Highlight empty required fields before submitting ----
    document.querySelectorAll('form[data-validate]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            // "Check availability" doesn't need every field
            if (e.submitter && e.submitter.hasAttribute('formnovalidate')) return;
            let ok = true;
            form.querySelectorAll('input[required]:not([type="radio"]), select[required]').forEach((input) => {
                const bad = !input.value.trim() || (input.type === 'email' && !input.checkValidity());
                input.classList.toggle('invalid', bad);
                if (bad) ok = false;
            });
            if (!ok) e.preventDefault();
        });
    });

    // ---- Confirm before cancelling ----
    document.querySelectorAll('.cancel-form').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (!confirm('Cancel this reservation? This cannot be undone.')) e.preventDefault();
        });
    });
});
