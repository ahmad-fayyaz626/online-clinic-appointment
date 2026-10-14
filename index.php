<?php
require_once __DIR__ . '/config/db.php';

// Fetch all specialties for the filter dropdown
$specialties_stmt = $pdo->query("SELECT id, name FROM specialties ORDER BY name ASC");
$all_specialties = $specialties_stmt->fetchAll();

// Handle search and filtering
$selected_specialty = isset($_GET['specialty']) ? (int)$_GET['specialty'] : 0;
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "
    SELECT 
        d.id AS doctor_id,
        u.full_name AS doctor_name,
        u.email AS doctor_email,
        u.phone AS doctor_phone,
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

$page_title = 'Find Specialists & Book Appointment';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="page-hero">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">Quality Medical Care, Scheduled at Your Convenience</h1>
            <p class="hero-subtitle">
                Browse our experienced medical specialists, choose an available time slot, and book your verified appointment without any waiting queues.
            </p>
            <?php if (!is_logged_in()): ?>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <a href="signup.php" class="btn btn-primary" id="hero-register-btn">Create Patient Account</a>
                    <a href="login.php" class="btn btn-outline" style="color: white; border-color: rgba(255,255,255,0.3);" id="hero-login-btn">Sign In</a>
                </div>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn-primary" id="hero-dashboard-btn">
                    View My Appointments
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Filter and Search Bar -->
<div class="filter-bar">
    <form action="index.php" method="GET" class="filter-group" style="width: 100%; display: flex; flex-wrap: wrap; gap: 14px;">
        <div style="flex: 2; min-width: 220px;">
            <input 
                type="text" 
                name="q" 
                class="form-control" 
                placeholder="Search by doctor name, specialty, or qualification..." 
                value="<?php echo e($search_query); ?>"
                id="search-input"
            >
        </div>

        <div style="flex: 1; min-width: 180px;">
            <select name="specialty" class="form-select" id="specialty-filter">
                <option value="0">All Medical Specialties</option>
                <?php foreach ($all_specialties as $sp): ?>
                    <option value="<?php echo $sp['id']; ?>" <?php echo ($selected_specialty === (int)$sp['id']) ? 'selected' : ''; ?>>
                        <?php echo e($sp['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary" id="btn-filter">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                Search
            </button>

            <?php if (!empty($search_query) || $selected_specialty > 0): ?>
                <a href="index.php" class="btn btn-outline" id="btn-reset-filter">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Doctors List -->
<div class="section-header" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 700; color: var(--primary);">Available Doctors & Specialists</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Showing <?php echo count($doctors); ?> certified physician<?php echo count($doctors) === 1 ? '' : 's'; ?>
        </p>
    </div>
</div>

<?php if (empty($doctors)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">🩺</div>
            <h3>No Doctors Found</h3>
            <p>We couldn't find any doctors matching your search criteria. Try selecting another specialty or reset filters.</p>
            <a href="index.php" class="btn btn-outline">View All Doctors</a>
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

                <div class="card-footer" style="display: flex; gap: 10px;">
                    <?php if (is_logged_in()): ?>
                        <a href="book.php?doctor_id=<?php echo $doc['doctor_id']; ?>" class="btn btn-primary btn-block" id="btn-book-<?php echo $doc['doctor_id']; ?>">
                            Book Appointment
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary btn-block" id="btn-book-guest-<?php echo $doc['doctor_id']; ?>">
                            Login to Book
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
