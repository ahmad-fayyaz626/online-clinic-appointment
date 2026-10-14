/**
 * CarePulse Clinic Management System - Interactive Frontend Script
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Auto-dismiss flash alerts after 6 seconds
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(() => {
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-8px)';
                setTimeout(() => alert.remove(), 400);
            });
        }, 6000);
    }

    // 2. Real-time Doctor Slot Availability on Booking Form
    const bookingForm = document.getElementById('appointment-booking-form');
    const doctorInput = document.querySelector('input[name="doctor_id"]') || document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSelect = document.getElementById('appointment_time');

    if (bookingForm && doctorInput && dateInput && timeSelect) {
        // Save original options
        const originalOptions = Array.from(timeSelect.options).map(opt => ({
            value: opt.value,
            text: opt.text.replace(' (Booked - Unavailable)', '')
        }));

        async function updateAvailableSlots() {
            const doctorId = doctorInput.value;
            const dateVal = dateInput.value;

            if (!doctorId || !dateVal) return;

            try {
                const response = await fetch(`check_slots.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(dateVal)}`);
                if (!response.ok) return;

                const data = await response.json();
                const bookedSlots = data.booked_slots || [];

                // Update options in dropdown
                timeSelect.innerHTML = '';
                originalOptions.forEach(opt => {
                    const optionEl = document.createElement('option');
                    optionEl.value = opt.value;

                    if (!opt.value) {
                        optionEl.text = opt.text;
                        timeSelect.appendChild(optionEl);
                        return;
                    }

                    const isBooked = bookedSlots.includes(opt.value);
                    if (isBooked) {
                        optionEl.text = `${opt.text} (Booked - Unavailable)`;
                        optionEl.disabled = true;
                        optionEl.style.color = '#94a3b8';
                        optionEl.style.backgroundColor = '#f1f5f9';
                    } else {
                        optionEl.text = opt.text;
                        optionEl.disabled = false;
                    }

                    timeSelect.appendChild(optionEl);
                });

                // If currently selected slot is disabled, reset value
                if (timeSelect.selectedOptions[0]?.disabled) {
                    timeSelect.value = '';
                }
            } catch (err) {
                console.warn('Slot availability fetch notice:', err);
            }
        }

        dateInput.addEventListener('change', updateAvailableSlots);
        doctorInput.addEventListener('change', updateAvailableSlots);

        // Run once on load if values present
        if (doctorInput.value && dateInput.value) {
            updateAvailableSlots();
        }
    }

    // 3. Client-side Form Validation for Signup Form
    const signupForm = document.getElementById('signup-form');
    if (signupForm) {
        signupForm.addEventListener('submit', (e) => {
            const pwd = document.getElementById('password').value;
            const confirmPwd = document.getElementById('confirm_password').value;

            if (pwd.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return false;
            }

            if (pwd !== confirmPwd) {
                e.preventDefault();
                alert('Passwords do not match. Please re-enter.');
                return false;
            }
        });
    }

    // 4. Prevent double form submission on submit buttons
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                setTimeout(() => {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.7';
                }, 50);
            }
        });
    });
});
