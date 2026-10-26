<?php
require_once __DIR__ . '/config/db.php';

// Access control: Admin only
require_admin();

// 1. Fetch clinic statistics
$doctor_count_stmt = $pdo->query("SELECT COUNT(*) FROM doctors");
$total_doctors = (int)$doctor_count_stmt->fetchColumn();

$patient_count_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'");
$total_patients = (int)$patient_count_stmt->fetchColumn();

$apt_stats_stmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_appointments,
        SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) AS booked_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
    FROM appointments
");
$apt_stats = $apt_stats_stmt->fetch();

$total_appointments = (int)($apt_stats['total_appointments'] ?? 0);
$booked_count       = (int)($apt_stats['booked_count'] ?? 0);
$completed_count    = (int)($apt_stats['completed_count'] ?? 0);
$cancelled_count    = (int)($apt_stats['cancelled_count'] ?? 0);

// 2. Fetch all doctors with specialty and appointment count
$doctors_stmt = $pdo->query("
    SELECT 
        d.id AS doctor_id,
        d.user_id,
        d.qualification,
        d.experience_years,
        d.fee,
        u.full_name,
        u.email,
        u.phone,
        s.name AS specialty_name,
        (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id) AS total_doctor_appointments
    FROM doctors d
    INNER JOIN users u ON d.user_id = u.id
    INNER JOIN specialties s ON d.specialty_id = s.id
    ORDER BY u.full_name ASC
");
$doctors = $doctors_stmt->fetchAll();

// 3. Fetch recent appointments across the clinic
$recent_apt_stmt = $pdo->query("
    SELECT 
        a.id AS appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.reason,
        p.full_name AS patient_name,
        p.phone AS patient_phone,
        doc_u.full_name AS doctor_name,
        s.name AS specialty_name
    FROM appointments a
    INNER JOIN users p ON a.patient_id = p.id
    INNER JOIN doctors d ON a.doctor_id = d.id
    INNER JOIN users doc_u ON d.user_id = doc_u.id
    INNER JOIN specialties s ON d.specialty_id = s.id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 10
");
$recent_appointments = $recent_apt_stmt->fetchAll();

$page_title = 'Admin Management Panel';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Admin Header -->
<div class="page-header" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--primary); margin: 0 0 4px 0;">
                Clinic Administration Panel
            </h1>
            <p style="color: var(--text-secondary); margin: 0;">
                Manage clinic doctors, monitor patient appointments, and oversee daily operations.
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="admin_specialties.php" class="btn btn-outline" id="btn-manage-specialties">
                Manage Specialties
            </a>
            <a href="admin_doctor_add.php" class="btn btn-primary" id="btn-add-doctor">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add New Doctor
            </a>
        </div>
    </div>
</div>

<!-- Stats Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 32px;">
    <div class="card" style="padding: 20px;">
        <span class="stat-label">Active Doctors</span>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary); margin-top: 4px;"><?php echo $total_doctors; ?></div>
    </div>
    <div class="card" style="padding: 20px;">
        <span class="stat-label">Registered Patients</span>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--accent); margin-top: 4px;"><?php echo $total_patients; ?></div>
    </div>
    <div class="card" style="padding: 20px;">
        <span class="stat-label">Total Appointments</span>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--text-primary); margin-top: 4px;"><?php echo $total_appointments; ?></div>
    </div>
    <div class="card" style="padding: 20px; border-left: 4px solid var(--success);">
        <span class="stat-label">Active Booked</span>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--success); margin-top: 4px;"><?php echo $booked_count; ?></div>
    </div>
</div>

<!-- Section 1: Doctor CRUD Management -->
<div style="margin-bottom: 40px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--primary); margin: 0;">
            Doctor Directory (CRUD)
        </h2>
        <span style="font-size: 0.88rem; color: var(--text-muted);">
            Showing <?php echo count($doctors); ?> registered specialist<?php echo count($doctors) === 1 ? '' : 's'; ?>
        </span>
    </div>

    <div class="card" style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse; text-align: left;" id="admin-doctors-table">
            <thead>
                <tr style="border-bottom: 2px solid var(--border); background-color: #f8fafc;">
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Doctor Name & Email</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Specialty</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Qualification & Exp.</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Phone</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Fee</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Bookings</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary); text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($doctors)): ?>
                    <tr>
                        <td colspan="7" style="padding: 24px; text-align: center; color: var(--text-muted);">
                            No doctors registered in the system yet. Click "Add New Doctor" above to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($doctors as $doc): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 14px 18px;">
                                <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">
                                    <?php echo e($doc['full_name']); ?>
                                </div>
                                <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">
                                    <?php echo e($doc['email']); ?>
                                </div>
                            </td>
                            <td style="padding: 14px 18px;">
                                <span class="badge badge-specialty"><?php echo e($doc['specialty_name']); ?></span>
                            </td>
                            <td style="padding: 14px 18px; font-size: 0.88rem; color: var(--text-secondary);">
                                <div><?php echo e($doc['qualification']); ?></div>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                                    <?php echo (int)$doc['experience_years']; ?> years experience
                                </div>
                            </td>
                            <td style="padding: 14px 18px; font-size: 0.88rem; color: var(--text-secondary);">
                                <?php echo e($doc['phone']); ?>
                            </td>
                            <td style="padding: 14px 18px; font-size: 0.92rem; font-weight: 700; color: var(--primary);">
                                PKR <?php echo number_format($doc['fee'], 0); ?>
                            </td>
                            <td style="padding: 14px 18px; font-size: 0.9rem; font-weight: 600;">
                                <?php echo (int)$doc['total_doctor_appointments']; ?>
                            </td>
                            <td style="padding: 14px 18px; text-align: right; white-space: nowrap;">
                                <a href="admin_doctor_edit.php?id=<?php echo (int)$doc['doctor_id']; ?>" class="btn btn-outline btn-sm" style="margin-right: 6px;">
                                    Edit
                                </a>
                                <form action="admin_doctor_delete.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete Dr. <?php echo addslashes($doc['full_name']); ?>? This will also remove their user account.');">
                                    <input type="hidden" name="doctor_id" value="<?php echo (int)$doc['doctor_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Section 2: Recent Clinic Appointments -->
<div style="margin-bottom: 20px;">
    <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--primary); margin: 0 0 16px 0;">
        Recent Clinic Appointments
    </h2>

    <div class="card" style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse; text-align: left;" id="admin-appointments-table">
            <thead>
                <tr style="border-bottom: 2px solid var(--border); background-color: #f8fafc;">
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Patient</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Doctor & Specialty</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Date & Time</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Reason</th>
                    <th style="padding: 14px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_appointments)): ?>
                    <tr>
                        <td colspan="5" style="padding: 24px; text-align: center; color: var(--text-muted);">
                            No appointments found in the system yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_appointments as $apt): ?>
                        <?php 
                            $time_obj = DateTime::createFromFormat('H:i:s', $apt['appointment_time']);
                            $formatted_time = $time_obj ? $time_obj->format('g:i A') : $apt['appointment_time'];
                            $formatted_date = date('D, d M Y', strtotime($apt['appointment_date']));
                        ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 14px 18px;">
                                <div style="font-weight: 700; color: var(--text-primary); font-size: 0.92rem;">
                                    <?php echo e($apt['patient_name']); ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?php echo e($apt['patient_phone']); ?>
                                </div>
                            </td>
                            <td style="padding: 14px 18px;">
                                <div style="font-weight: 600; color: var(--text-primary); font-size: 0.92rem;">
                                    <?php echo e($apt['doctor_name']); ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--accent);">
                                    <?php echo e($apt['specialty_name']); ?>
                                </div>
                            </td>
                            <td style="padding: 14px 18px; white-space: nowrap;">
                                <div style="font-weight: 600; font-size: 0.9rem;"><?php echo $formatted_date; ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">⏰ <?php echo $formatted_time; ?></div>
                            </td>
                            <td style="padding: 14px 18px; font-size: 0.88rem; color: var(--text-secondary);">
                                <?php echo !empty($apt['reason']) ? e($apt['reason']) : '<em>Routine Checkup</em>'; ?>
                            </td>
                            <td style="padding: 14px 18px; white-space: nowrap;">
                                <?php if ($apt['status'] === 'booked'): ?>
                                    <span class="badge badge-booked">Booked</span>
                                <?php elseif ($apt['status'] === 'completed'): ?>
                                    <span class="badge badge-completed">Completed</span>
                                <?php else: ?>
                                    <span class="badge badge-cancelled">Cancelled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
