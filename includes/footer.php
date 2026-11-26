    </div><!-- /.container -->
</main><!-- /.main-content -->

<footer class="clinic-footer">
    <div class="container">
        <div class="clinic-footer-grid">
            <div class="clinic-footer-brand">
                <a href="index.php" class="brand-logo">
                    <span class="logo-badge" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    </span>
                    <span class="brand-text">CarePulse <span>Clinic</span></span>
                </a>
                <p>Appointments, reception check-in and live queue updates.</p>
            </div>
            <nav aria-label="Footer navigation">
                <h2><?= $role === 'admin' || $role === 'doctor' ? 'Clinic workspace' : 'Appointments' ?></h2>
                <ul>
                    <?php foreach ($portalLinks as $href => $label): ?>
                        <li><a href="<?= e($href) ?>"><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <div class="clinic-footer-help">
                <h2>On the day of your visit</h2>
                <p>Bring your appointment token and give it to reception when you arrive.</p>
                <a href="index.php#visiting">How check-in works</a>
            </div>
        </div>
        <div class="clinic-footer-base">
            <span>&copy; <?= date('Y') ?> CarePulse Clinic</span>
            <a href="#main-content">Back to top <span aria-hidden="true">&uarr;</span></a>
        </div>
    </div>
</footer>

<script>
// Universal Password Show/Hide Toggle available across all pages
function togglePasswordVisibility(targetId, btn, e) {
    if (e) {
        if (e.preventDefault) e.preventDefault();
        if (e.stopPropagation) e.stopPropagation();
        e.cancelBubble = true;
    }
    var input = document.getElementById(targetId);
    if (!input) return;

    var isPassword = (input.type === 'password' || input.getAttribute('type') === 'password');
    var nextType = isPassword ? 'text' : 'password';

    input.type = nextType;
    input.setAttribute('type', nextType);

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

// Global delegated event listener (safely avoids double-toggle if inline onclick exists)
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.password-toggle-btn');
    if (!btn) return;
    if (btn.hasAttribute('onclick')) return; // Let inline onclick handle it safely

    e.preventDefault();
    e.stopPropagation();
    var targetId = btn.getAttribute('data-target');
    if (targetId) {
        togglePasswordVisibility(targetId, btn, e);
    }
});
</script>
<script src="assets/js/main.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/main.js'); ?>"></script>
</body>
</html>
