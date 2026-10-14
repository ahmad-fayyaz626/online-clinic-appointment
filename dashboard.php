<?php
require_once __DIR__ . '/config/db.php';

// Authentication required
require_login();

$patient_id = $_SESSION['user_id'];
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// Build SQL with optional status filter
$sql = "
    SELECT 
        a.id AS appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.reason,
        a.created_at,
        u.full_name AS doctor_name,
        u.phone AS doctor_phone,
        s.name AS specialty_name,
        d.qualification,
        d.fee
    FROM appointments a
    INNER JOIN doctors d ON a.doctor_id = d.id
    INNER JOIN users u ON d.user_id = u.id
    INNER JOIN specialties s ON d.specialty_id = s.id
    WHERE a.patient_id = ?
";

$params = [$patient_id];

if ($status_filter === 'booked' || $status_filter === 'cancelled' || $status_filter === 'completed') {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Count stats for patient
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_count,
        SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) AS booked_count,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
    FROM appointments 
    WHERE patient_id = ?
");
$stats_stmt->execute([$patient_id]);
$stats = $stats_stmt->fetch();

$total_count     = (int)($stats['total_count'] ?? 0);
$booked_count    = (int)($stats['booked_count'] ?? 0);
$cancelled_count = (int)($stats['cancelled_count'] ?? 0);

$page_title = 'My Appointments Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h1>My Appointments</h1>
        <p>Manage, track, or cancel your scheduled clinic consultations</p>
    </div>
    <div>
        <a href="index.php" class="btn btn-primary" id="btn-book-new">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Book New Appointment
        </a>
    </div>
</div>

<!-- Quick Stats Summary -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px;">
    <div class="card" style="padding: 18px 20px;">
        <span class="stat-label">Total Bookings</span>
        <div style="font-size: 1.6rem; font-weight: 800; color: var(--primary); margin-top: 4px;"><?php echo $total_count; ?></div>
    </div>
    <div class="card" style="padding: 18px 20px;">
        <span class="stat-label">Active / Scheduled</span>
        <div style="font-size: 1.6rem; font-weight: 800; color: var(--success); margin-top: 4px;"><?php echo $booked_count; ?></div>
    </div>
    <div class="card" style="padding: 18px 20px;">
        <span class="stat-label">Cancelled</span>
        <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-muted); margin-top: 4px;"><?php echo $cancelled_count; ?></div>
    </div>
</div>

<!-- Filter Tabs -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 12px; flex-wrap: wrap;">
    <a href="dashboard.php?status=all" class="btn btn-sm <?php echo ($status_filter === 'all') ? 'btn-dark' : 'btn-outline'; ?>" id="filter-all">
        All Appointments (<?php echo $total_count; ?>)
    </a>
    <a href="dashboard.php?status=booked" class="btn btn-sm <?php echo ($status_filter === 'booked') ? 'btn-dark' : 'btn-outline'; ?>" id="filter-booked">
        Booked / Upcoming (<?php echo $booked_count; ?>)
    </a>
    <a href="dashboard.php?status=cancelled" class="btn btn-sm <?php echo ($status_filter === 'cancelled') ? 'btn-dark' : 'btn-outline'; ?>" id="filter-cancelled">
        Cancelled (<?php echo $cancelled_count; ?>)
    </a>
</div>

<?php if (empty($appointments)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">📅</div>
            <h3>No Appointments Found</h3>
            <p>
                <?php if ($status_filter !== 'all'): ?>
                    You don't have any appointments matching the "<?php echo e(ucfirst($status_filter)); ?>" status.
                <?php else: ?>
                    You haven't scheduled any medical appointments yet. Explore our top doctors and book your first consultation!
                <?php endif; ?>
            </p>
            <a href="index.php" class="btn btn-primary" id="btn-empty-book">Browse Available Doctors</a>
        </div>
    </div>
<?php else: ?>
    <div class="appointments-table-card">
        <table class="table" id="appointments-table">
            <thead>
                <tr>
                    <th>Doctor & Specialty</th>
                    <th>Date & Time</th>
                    <th>Fee</th>
                    <th>Visit Reason</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $apt): ?>
                    <?php 
                        $time_obj = DateTime::createFromFormat('H:i:s', $apt['appointment_time']);
                        $formatted_time = $time_obj ? $time_obj->format('g:i A') : $apt['appointment_time'];
                        $date_obj = new DateTime($apt['appointment_date']);
                        $formatted_date = $date_obj->format('M d, Y');
                    ?>
                    <tr id="appointment-row-<?php echo $apt['appointment_id']; ?>">
                        <td>
                            <div style="font-weight: 700; color: var(--primary);"><?php echo e($apt['doctor_name']); ?></div>
                            <div style="font-size: 0.85rem; color: var(--text-muted);">
                                <span class="badge badge-specialty" style="padding: 2px 8px;"><?php echo e($apt['specialty_name']); ?></span>
                                &middot; <?php echo e($apt['qualification']); ?>
                            </div>
                        </td>

                        <td>
                            <div style="font-weight: 600; color: var(--text-primary);"><?php echo $formatted_date; ?></div>
                            <div style="font-size: 0.85rem; color: var(--accent); font-weight: 600;">
                                <?php echo $formatted_time; ?>
                            </div>
                        </td>

                        <td>
                            <span style="font-weight: 700; color: var(--text-primary);">
                                Rs. <?php echo number_format($apt['fee'], 0); ?>
                            </span>
                        </td>

                        <td>
                            <span style="font-size: 0.88rem; color: var(--text-secondary);">
                                <?php echo !empty($apt['reason']) ? e($apt['reason']) : '<span style="color: #94a3b8;">None specified</span>'; ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($apt['status'] === 'booked'): ?>
                                <span class="badge badge-booked">Booked</span>
                            <?php elseif ($apt['status'] === 'cancelled'): ?>
                                <span class="badge badge-cancelled">Cancelled</span>
                            <?php else: ?>
                                <span class="badge badge-completed"><?php echo e(ucfirst($apt['status'])); ?></span>
                            <?php endif; ?>
                        </td>

                        <td style="text-align: right;">
                            <?php if ($apt['status'] === 'booked'): ?>
                                <form action="cancel_appointment.php" method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to cancel this appointment with <?php echo addslashes($apt['doctor_name']); ?>? The slot will be released.');">
                                    <input type="hidden" name="appointment_id" value="<?php echo (int)$apt['appointment_id']; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: #fca5a5;" id="btn-cancel-<?php echo $apt['appointment_id']; ?>">
                                        Cancel
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">
                                    Slot Released
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
