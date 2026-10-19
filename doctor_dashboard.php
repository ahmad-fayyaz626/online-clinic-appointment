<?php
require_once __DIR__ . '/config/db.php';

// Access control: Doctor only
require_doctor();

$user_id = $_SESSION['user_id'];

// Fetch doctor profile details
$stmt = $pdo->prepare("
    SELECT 
        d.id AS doctor_id, 
        d.qualification, 
        d.experience_years, 
        d.fee, 
        s.name AS specialty_name, 
        u.full_name, 
        u.email, 
        u.phone
    FROM doctors d
    INNER JOIN users u ON d.user_id = u.id
    INNER JOIN specialties s ON d.specialty_id = s.id
    WHERE d.user_id = ?
");
$stmt->execute([$user_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    die("Doctor profile record not found. Please contact clinic administrator.");
}

$doctor_id = (int)$doctor['doctor_id'];
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// Query counts for doctor statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_count,
        SUM(CASE WHEN appointment_date = CURDATE() AND status = 'booked' THEN 1 ELSE 0 END) AS today_count,
        SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) AS booked_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
    FROM appointments
    WHERE doctor_id = ?
");
$stats_stmt->execute([$doctor_id]);
$stats = $stats_stmt->fetch();

$total_count     = (int)($stats['total_count'] ?? 0);
$today_count     = (int)($stats['today_count'] ?? 0);
$booked_count    = (int)($stats['booked_count'] ?? 0);
$completed_count = (int)($stats['completed_count'] ?? 0);
$cancelled_count = (int)($stats['cancelled_count'] ?? 0);

// Build query for patient appointments
$sql = "
    SELECT 
        a.id AS appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.reason,
        a.created_at,
        p.full_name AS patient_name,
        p.email AS patient_email,
        p.phone AS patient_phone
    FROM appointments a
    INNER JOIN users p ON a.patient_id = p.id
    WHERE a.doctor_id = ?
";
$params = [$doctor_id];

if ($status_filter === 'today') {
    $sql .= " AND a.appointment_date = CURDATE() AND a.status = 'booked'";
} elseif (in_array($status_filter, ['booked', 'completed', 'cancelled'], true)) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$page_title = 'Doctor Schedule & Patients';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Doctor Profile Banner -->
<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--primary); margin: 0;">
                    <?php echo e($doctor['full_name']); ?>
                </h1>
                <span class="badge badge-specialty"><?php echo e($doctor['specialty_name']); ?></span>
            </div>
            <p style="color: var(--text-secondary); margin: 0;">
                <?php echo e($doctor['qualification']); ?> &middot; <?php echo e($doctor['experience_years']); ?> Years Experience &middot; Fee: <strong>PKR <?php echo number_format($doctor['fee'], 0); ?></strong>
            </p>
        </div>
        <div style="font-size: 0.9rem; color: var(--text-muted); background: #ffffff; padding: 10px 16px; border: 1px solid var(--border); border-radius: var(--radius-md);">
            📅 Today is <strong><?php echo date('l, d M Y'); ?></strong>
        </div>
    </div>
</div>

<!-- Doctor Statistics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px;">
    <div class="card" style="padding: 18px 20px;">
        <span class="stat-label">Total Patients Seen</span>
        <div style="font-size: 1.7rem; font-weight: 800; color: var(--primary); margin-top: 4px;"><?php echo $total_count; ?></div>
    </div>
    <div class="card" style="padding: 18px 20px; border-left: 4px solid var(--accent);">
        <span class="stat-label">Today's Remaining</span>
        <div style="font-size: 1.7rem; font-weight: 800; color: var(--accent); margin-top: 4px;"><?php echo $today_count; ?></div>
    </div>
    <div class="card" style="padding: 18px 20px; border-left: 4px solid var(--warning);">
        <span class="stat-label">Upcoming / Booked</span>
        <div style="font-size: 1.7rem; font-weight: 800; color: var(--warning); margin-top: 4px;"><?php echo $booked_count; ?></div>
    </div>
    <div class="card" style="padding: 18px 20px; border-left: 4px solid var(--success);">
        <span class="stat-label">Completed Visits</span>
        <div style="font-size: 1.7rem; font-weight: 800; color: var(--success); margin-top: 4px;"><?php echo $completed_count; ?></div>
    </div>
</div>

<!-- Filter Tabs -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 12px; flex-wrap: wrap;">
    <a href="doctor_dashboard.php?status=all" class="btn btn-sm <?php echo ($status_filter === 'all') ? 'btn-dark' : 'btn-outline'; ?>" id="filter-all">
        All Appointments (<?php echo $total_count; ?>)
    </a>
    <a href="doctor_dashboard.php?status=today" class="btn btn-sm <?php echo ($status_filter === 'today') ? 'btn-dark' : 'btn-outline'; ?>" id="filter-today">
        Today's Schedule (<?php echo $today_count; ?>)
    </a>
    <a href="doctor_dashboard.php?status=booked" class="btn btn-sm <?php echo ($status_filter === 'booked') ? 'btn-dark' : 'btn-outline'; ?>" id="filter-booked">
        Upcoming Booked (<?php echo $booked_count; ?>)
    </a>
    <a href="doctor_dashboard.php?status=completed" class="btn btn-sm <?php echo ($status_filter === 'completed') ? 'btn-dark' : 'btn-outline'; ?>" id="filter-completed">
        Completed (<?php echo $completed_count; ?>)
    </a>
    <a href="doctor_dashboard.php?status=cancelled" class="btn btn-sm <?php echo ($status_filter === 'cancelled') ? 'btn-dark' : 'btn-outline'; ?>" id="filter-cancelled">
        Cancelled (<?php echo $cancelled_count; ?>)
    </a>
</div>

<!-- Appointments Table Card -->
<?php if (empty($appointments)): ?>
    <div class="card">
        <div class="empty-state" style="text-align: center; padding: 48px 20px;">
            <div style="font-size: 3rem; margin-bottom: 12px;">👨‍⚕️</div>
            <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 8px;">No Patient Appointments Found</h3>
            <p style="color: var(--text-secondary); max-width: 450px; margin: 0 auto;">
                <?php if ($status_filter !== 'all'): ?>
                    There are no appointments matching the "<strong><?php echo e(ucfirst($status_filter)); ?></strong>" filter.
                <?php else: ?>
                    You currently have no patient appointments booked on your calendar.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="card" style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse; text-align: left;" id="doctor-appointments-table">
            <thead>
                <tr style="border-bottom: 2px solid var(--border); background-color: #f8fafc;">
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Patient Details</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Date & Time</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Reason / Symptoms</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Status</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary); text-align: right;">Manage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $apt): ?>
                    <?php 
                        $time_obj = DateTime::createFromFormat('H:i:s', $apt['appointment_time']);
                        $formatted_time = $time_obj ? $time_obj->format('g:i A') : $apt['appointment_time'];
                        $formatted_date = date('D, d M Y', strtotime($apt['appointment_date']));
                        $is_today = ($apt['appointment_date'] === date('Y-m-d'));
                    ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <!-- Patient Info -->
                        <td style="padding: 16px 18px;">
                            <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">
                                <?php echo e($apt['patient_name']); ?>
                            </div>
                            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 2px;">
                                📞 <?php echo e($apt['patient_phone']); ?> &middot; ✉️ <?php echo e($apt['patient_email']); ?>
                            </div>
                        </td>

                        <!-- Date & Time -->
                        <td style="padding: 16px 18px; white-space: nowrap;">
                            <div style="font-weight: 600; color: var(--text-primary); font-size: 0.92rem;">
                                <?php echo $formatted_date; ?>
                                <?php if ($is_today): ?>
                                    <span style="background: #e0f2fe; color: #0369a1; font-size: 0.72rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-left: 4px;">TODAY</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 2px;">
                                ⏰ <?php echo $formatted_time; ?>
                            </div>
                        </td>

                        <!-- Reason -->
                        <td style="padding: 16px 18px; max-width: 280px;">
                            <div style="font-size: 0.9rem; color: var(--text-primary);">
                                <?php echo !empty($apt['reason']) ? e($apt['reason']) : '<span style="color: #94a3b8; font-style: italic;">Routine Consultation</span>'; ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                                Booked on <?php echo date('d M Y', strtotime($apt['created_at'])); ?>
                            </div>
                        </td>

                        <!-- Status Badge -->
                        <td style="padding: 16px 18px; white-space: nowrap;">
                            <?php if ($apt['status'] === 'booked'): ?>
                                <span class="badge badge-booked">Booked</span>
                            <?php elseif ($apt['status'] === 'completed'): ?>
                                <span class="badge badge-completed">Completed</span>
                            <?php else: ?>
                                <span class="badge badge-cancelled">Cancelled</span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td style="padding: 16px 18px; text-align: right; white-space: nowrap;">
                            <?php if ($apt['status'] === 'booked'): ?>
                                <div style="display: inline-flex; gap: 6px;">
                                    <form action="doctor_update_status.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="appointment_id" value="<?php echo (int)$apt['appointment_id']; ?>">
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="btn btn-sm" style="background-color: var(--success); color: #ffffff;" title="Mark consultation completed">
                                            ✓ Complete
                                        </button>
                                    </form>

                                    <form action="doctor_update_status.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                        <input type="hidden" name="appointment_id" value="<?php echo (int)$apt['appointment_id']; ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: var(--border);" title="Cancel appointment">
                                            Cancel
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($apt['status'] === 'completed'): ?>
                                <span style="font-size: 0.85rem; color: var(--success); font-weight: 600;">
                                    ✓ Visited
                                </span>
                            <?php else: ?>
                                <span style="font-size: 0.85rem; color: var(--text-muted);">
                                    Cancelled
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
