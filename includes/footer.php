    </div><!-- /.container -->
</main><!-- /.main-content -->

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> CarePulse Clinic Management System. All rights reserved.</p>
        <p style="margin-top: 4px; font-size: 0.8rem; color: #94a3b8;">Secure Online Patient Appointment Booking &middot; 3-Tier Architecture</p>
    </div>
</footer>

<script>
// Universal Password Show/Hide Toggle available across all pages
function togglePasswordVisibility(targetId, btn) {
    var input = document.getElementById(targetId);
    if (!input) return;

    var isPassword = input.getAttribute('type') === 'password';
    input.setAttribute('type', isPassword ? 'text' : 'password');

    if (btn) {
        var eyeIcon = btn.querySelector('.icon-eye');
        var eyeOffIcon = btn.querySelector('.icon-eye-off');
        if (eyeIcon && eyeOffIcon) {
            eyeIcon.style.display = isPassword ? 'none' : 'block';
            eyeOffIcon.style.display = isPassword ? 'block' : 'none';
        }
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        btn.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
    }
}

// Global delegated event listener so dynamic and static toggle buttons work reliably
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.password-toggle-btn');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    var targetId = btn.getAttribute('data-target');
    if (targetId) {
        togglePasswordVisibility(targetId, btn);
    }
});
</script>
<script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
</body>
</html>
