<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/clinic_queue.php';

// Authentication required
require_role('patient');

$patient_id = $_SESSION['user_id'];
$status_filter = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'all';

// Build SQL with optional status filter
$sql = "
    SELECT 
        a.id AS appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.checked_in_at,
        a.consultation_started_at,
        a.reason,
        a.doctor_notes,
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
    </div>
    <div>
        <a href="index.php" class="btn btn-primary" id="btn-book-new">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Book appointment
        </a>
    </div>
</div>

<?php
$queueRole = 'patient';
$queueData = (new ClinicQueue($pdo))->patientQueue((int)$patient_id);
require __DIR__ . '/includes/queue_section.php';
?>

<h2 class="queue-history-title">Appointment history</h2>
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
        <table role="table" class="table clinic-history-table" id="appointments-table">
            <thead>
                <tr role="row">
                    <th scope="col">Doctor & Specialty</th>
                    <th scope="col">Date & Time</th>
                    <th scope="col">Fee</th>
                    <th scope="col">Visit Reason</th>
                    <th scope="col">Status</th>
                    <th scope="col" style="text-align: right;">Action</th>
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
                    <tr role="row" id="appointment-row-<?= (int)$apt['appointment_id'] ?>">
                        <td role="cell" data-label="Doctor">
                            <div style="font-weight: 700; color: var(--primary);"><?php echo e($apt['doctor_name']); ?></div>
                            <div style="font-size: 0.85rem; color: var(--text-muted);">
                                <span class="badge badge-specialty" style="padding: 2px 8px;"><?php echo e($apt['specialty_name']); ?></span>
                                &middot; <?php echo e($apt['qualification']); ?>
                            </div>
                        </td>

                        <td role="cell" data-label="Date and time">
                            <div style="font-weight: 600; color: var(--text-primary);"><?php echo $formatted_date; ?></div>
                            <div style="font-size: 0.85rem; color: var(--accent); font-weight: 600;">
                                <?php echo $formatted_time; ?>
                            </div>
                        </td>

                        <td role="cell" data-label="Fee">
                            <span style="font-weight: 700; color: var(--text-primary);">
                                Rs. <?php echo number_format($apt['fee'], 0); ?>
                            </span>
                        </td>

                        <td role="cell" data-label="Visit details">
                            <div style="font-size: 0.88rem; color: var(--text-secondary);">
                                <?php echo !empty($apt['reason']) ? e($apt['reason']) : '<span style="color: #94a3b8;">None specified</span>'; ?>
                            </div>
                            <?php if (!empty($apt['doctor_notes'])): ?>
                                <div style="margin-top: 6px; font-size: 0.82rem; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 6px 10px; border-radius: 6px; line-height: 1.4;">
                                    <strong>📋 Doctor's Prescription:</strong><br>
                                    <?php echo nl2br(e($apt['doctor_notes'])); ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td role="cell" data-label="Status">
                            <?php if ($apt['status'] === 'booked'): ?>
                                <span class="badge badge-booked" data-history-status><?= e(ucfirst(ClinicQueue::visitState($apt))) ?></span>
                            <?php elseif ($apt['status'] === 'no_show'): ?>
                                <span class="badge badge-cancelled" data-history-status>Missed</span>
                            <?php elseif ($apt['status'] === 'cancelled'): ?>
                                <span class="badge badge-cancelled" data-history-status>Cancelled</span>
                            <?php else: ?>
                                <span class="badge badge-completed" data-history-status>Completed</span>
                            <?php endif; ?>
                        </td>

                        <td role="cell" data-label="Actions" style="text-align: right; white-space: nowrap;">
                            <a href="appointment_slip.php?id=<?php echo (int)$apt['appointment_id']; ?>" target="_blank" class="btn btn-outline btn-sm" style="margin-right: 6px;" title="View and print appointment receipt">
                                🖨️ Slip
                            </a>
                            <?php if (in_array($apt['status'], ['booked', 'no_show'], true) && $apt['consultation_started_at'] === null && $apt['appointment_date'] >= date('Y-m-d')): ?>
                                <form action="cancel_appointment.php" method="POST" class="history-cancel" data-history-cancel<?= $apt['status'] === 'no_show' ? ' hidden' : '' ?> onsubmit="return confirm('Cancel this appointment and release the slot?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="appointment_id" value="<?php echo (int)$apt['appointment_id']; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: #fca5a5;" id="btn-cancel-<?php echo $apt['appointment_id']; ?>">
                                        Cancel
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
