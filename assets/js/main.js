/**
 * CarePulse Clinic - Client-Side Interactive Scripts
 */

document.addEventListener('DOMContentLoaded', () => {
    // Auto-dismiss alerts after 5 seconds if desired
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(() => {
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 400);
            });
        }, 5000);
    }
});
