/**
 * CarePulse Clinic Management System - Interactive Frontend Script
 */

document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('[data-clinic-header]');
    const menu = header?.querySelector('[data-menu-toggle]');
    if (menu) {
        header.dataset.enhanced = 'true';
        menu.hidden = false;
        const narrow = window.matchMedia('(max-width: 860px)');
        const nav = document.getElementById(menu.getAttribute('aria-controls'));
        const isOpen = () => menu.getAttribute('aria-expanded') === 'true';
        const setOpen = open => {
            menu.setAttribute('aria-expanded', String(open));
            header.dataset.menuOpen = String(open);
        };
        menu.addEventListener('click', () => setOpen(!isOpen()));
        header.addEventListener('keydown', event => {
            if (event.key === 'Escape' && isOpen()) {
                setOpen(false);
                menu.focus();
            }
        });
        nav.addEventListener('click', event => {
            if (event.target.closest('a') && isOpen()) {
                setOpen(false);
            }
        });
        document.addEventListener('pointerdown', event => {
            if (isOpen() && !header.contains(event.target)) setOpen(false);
        });
        narrow.addEventListener('change', event => {
            if (event.matches && nav.contains(document.activeElement)) menu.focus();
            setOpen(false);
        });
    }

    const bookingForm = document.getElementById('appointment-booking-form');
    if (bookingForm) {
        const doctor = bookingForm.elements.doctor_id;
        const date = bookingForm.elements.appointment_date;
        const time = bookingForm.elements.appointment_time;
        const feedback = document.getElementById('slot-feedback');
        let activeRequest;

        async function updateAvailableSlots() {
            activeRequest?.abort();
            activeRequest = null;
            Array.from(time.options).forEach(option => {
                option.textContent = option.dataset.label;
                option.disabled = false;
            });
            time.removeAttribute('aria-busy');
            if (!doctor.value || !date.value) {
                feedback.textContent = '';
                return;
            }

            const request = new AbortController();
            activeRequest = request;
            const timeout = setTimeout(() => request.abort(), 8000);
            time.setAttribute('aria-busy', 'true');
            feedback.textContent = 'Checking available times…';

            try {
                const query = new URLSearchParams({ doctor_id: doctor.value, date: date.value });
                const response = await fetch('check_slots.php?' + query, { signal: request.signal });
                if (!response.ok) throw new Error('Availability unavailable');
                const { booked_slots: booked, cutoff } = await response.json();
                if (request.signal.aborted) return;

                let available = 0;
                for (const option of time.options) {
                    if (!option.value) continue;
                    if (cutoff && option.value <= cutoff) {
                        option.disabled = true;
                        option.textContent += ' (past)';
                    } else if (booked.includes(option.value)) {
                        option.disabled = true;
                        option.textContent += ' (booked)';
                    } else {
                        available++;
                    }
                }
                if (time.selectedOptions[0]?.disabled) time.value = '';
                feedback.textContent = available ? '' : 'No times remain. Choose another day.';
            } catch {
                if (activeRequest === request) {
                    feedback.textContent = 'Live availability is temporarily unavailable. We’ll check again when you submit.';
                }
            } finally {
                clearTimeout(timeout);
                if (activeRequest === request) time.removeAttribute('aria-busy');
            }
        }

        for (const option of time.options) option.dataset.label = option.textContent;
        doctor.addEventListener('change', updateAvailableSlots);
        date.addEventListener('change', updateAvailableSlots);
        updateAvailableSlots();
    }

    // 3. Client-side Form Validation for Signup Form
    const signupForm = document.getElementById('signup-form');
    if (signupForm) {
        signupForm.addEventListener('submit', (e) => {
            const pwd = document.getElementById('password').value;
            const confirmPwd = document.getElementById('confirm_password').value;

            if (Array.from(pwd).length < 12 || new TextEncoder().encode(pwd).length > 72) {
                e.preventDefault();
                alert('Use at least 12 characters and at most 72 bytes for the password.');
                return false;
            }

            if (pwd !== confirmPwd) {
                e.preventDefault();
                alert('Passwords do not match. Please re-enter.');
                return false;
            }
        });
    }

    // 5. Password Show / Hide Toggle functionality
    const passwordToggleButtons = document.querySelectorAll('.password-toggle-btn');
    passwordToggleButtons.forEach(btn => {
        if (btn.hasAttribute('onclick')) return; // Let inline handler take precedence
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const targetId = btn.getAttribute('data-target');
            if (typeof togglePasswordVisibility === 'function') {
                togglePasswordVisibility(targetId, btn, e);
            }
        });
    });
});
