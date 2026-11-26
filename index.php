<?php
require_once __DIR__ . '/config/db.php';

// Fetch all specialties for the filter dropdown
$specialties_stmt = $pdo->query("SELECT id, name FROM specialties ORDER BY name ASC");
$all_specialties = $specialties_stmt->fetchAll();

// Handle search and filtering
$selected_specialty = filter_input(INPUT_GET, 'specialty', FILTER_VALIDATE_INT) ?: 0;
$search_query = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 100)) : '';

$sql = "
    SELECT 
        d.id AS doctor_id,
        u.full_name AS doctor_name,
        s.id AS specialty_id,
        s.name AS specialty_name,
        d.qualification,
        d.experience_years,
        d.fee
    FROM doctors d
    INNER JOIN users u ON d.user_id = u.id
    INNER JOIN specialties s ON d.specialty_id = s.id
    WHERE 1=1
";

$params = [];

if ($selected_specialty > 0) {
    $sql .= " AND s.id = ?";
    $params[] = $selected_specialty;
}

if (!empty($search_query)) {
    $sql .= " AND (u.full_name LIKE ? OR d.qualification LIKE ? OR s.name LIKE ?)";
    $wildcard = "%{$search_query}%";
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
}

$sql .= " ORDER BY s.name ASC, u.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

$page_title = 'Find Specialists & Book appointment';
require_once __DIR__ . '/includes/header.php';
?>

<section class="clinic-hero" aria-labelledby="clinic-hero-title">
    <div class="clinic-hero-copy">
        <h1 id="clinic-hero-title">Find a doctor.<br>Plan your visit.</h1>
        <p>Book a consultation and follow your place in the queue after checking in at reception.</p>
        <div class="clinic-hero-actions">
            <a href="#doctor-directory" class="btn btn-primary">Find a doctor <span aria-hidden="true">&darr;</span></a>
            <?php if ($role === 'patient'): ?>
                <a href="dashboard.php" class="btn btn-outline">My appointments</a>
            <?php elseif ($role === 'doctor' || $role === 'admin'): ?>
                <a href="<?= $role === 'doctor' ? 'doctor_dashboard.php' : 'admin_dashboard.php' ?>" class="btn btn-outline">Open workspace</a>
            <?php else: ?>
                <a href="#visiting" class="btn btn-outline">How visits work</a>
            <?php endif; ?>
        </div>
    </div>
    <img class="clinic-hero-photo" src="assets/images/clinic-reception-1200.jpg"
         srcset="assets/images/clinic-reception-640.jpg 640w, assets/images/clinic-reception-1200.jpg 1200w"
         sizes="(max-width: 720px) 100vw, (max-width: 1200px) 55vw, 655px"
         width="1200" height="675" alt="" fetchpriority="high">
</section>

<section class="clinic-directory" id="doctor-directory" aria-labelledby="directory-title">
    <div class="clinic-directory-heading">
        <div>
            <h2 id="directory-title">Doctor directory</h2>
            <p>Search by name or choose a specialty to find an appointment.</p>
        </div>
        <span><?= count($doctors) ?> <?= count($doctors) === 1 ? 'doctor' : 'doctors' ?></span>
    </div>
    <form action="index.php#doctor-directory" method="get" class="clinic-search" role="search" aria-label="Find a doctor">
        <label for="search-input">Doctor or specialty
            <input type="search" name="q" class="form-control" placeholder="e.g. Sara Khan or Cardiology"
                   maxlength="100" value="<?= e($search_query) ?>" id="search-input">
        </label>
        <label for="specialty-filter">Specialty
            <select name="specialty" class="form-select" id="specialty-filter">
                <option value="0">All specialties</option>
                <?php foreach ($all_specialties as $sp): ?>
                    <option value="<?= (int)$sp['id'] ?>"<?= $selected_specialty === (int)$sp['id'] ? ' selected' : '' ?>><?= e($sp['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="clinic-search-actions">
            <button type="submit" class="btn btn-primary" id="btn-filter">Search</button>
            <?php if ($search_query !== '' || $selected_specialty > 0): ?>
                <a href="index.php#doctor-directory" class="btn btn-outline" id="btn-reset-filter">Clear</a>
            <?php endif; ?>
        </div>
    </form>

<?php if (empty($doctors)): ?>
    <div class="card">
        <div class="empty-state">
            <h3>No Doctors Found</h3>
            <p>We couldn't find any doctors matching your search criteria. Try selecting another specialty or reset filters.</p>
            <a href="index.php#doctor-directory" class="btn btn-outline">View all doctors</a>
        </div>
    </div>
<?php else: ?>
    <div class="doctors-grid" id="doctors-list">
        <?php foreach ($doctors as $doc): ?>
            <div class="card doctor-card" id="doctor-card-<?php echo $doc['doctor_id']; ?>">
                <div class="card-body">
                    <div class="doctor-profile-header">
                        <div class="doctor-avatar">
                            Dr
                        </div>
                        <div class="doctor-meta">
                            <h3><?php echo e($doc['doctor_name']); ?></h3>
                            <span class="badge badge-specialty"><?php echo e($doc['specialty_name']); ?></span>
                        </div>
                    </div>

                    <div class="doctor-qualification">
                        <strong>Qualification:</strong> <?php echo e($doc['qualification']); ?>
                    </div>

                    <div class="doctor-stats">
                        <div class="stat-item">
                            <span class="stat-label">Experience</span>
                            <span class="stat-value"><?php echo (int)$doc['experience_years']; ?>+ Yrs</span>
                        </div>
                        <div class="stat-item" style="text-align: right;">
                            <span class="stat-label">Consultation Fee</span>
                            <span class="stat-value fee-value">Rs. <?php echo number_format($doc['fee'], 0); ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($role === 'patient' || $role === 'guest'): ?>
                <div class="card-footer">
                    <?php if ($role === 'patient'): ?>
                        <a href="book.php?doctor_id=<?php echo $doc['doctor_id']; ?>" class="btn btn-primary btn-block" id="btn-book-<?php echo $doc['doctor_id']; ?>">
                            Book appointment
                        </a>
                    <?php elseif ($role === 'guest'): ?>
                        <a href="login.php" class="btn btn-primary btn-block" id="btn-book-guest-<?php echo $doc['doctor_id']; ?>">
                            Sign in to book
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</section>
<section class="clinic-visit-guide" id="visiting" aria-labelledby="visit-guide-title">
    <h2 id="visit-guide-title">On the day of your visit</h2>
    <ol>
        <li><strong>Bring your token</strong><p>Open your appointment slip from My appointments.</p></li>
        <li><strong>Check in at reception</strong><p>Give the receptionist your token when you arrive.</p></li>
        <li><strong>Follow your turn</strong><p>See how many people are ahead and when the doctor may be ready.</p></li>
    </ol>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
