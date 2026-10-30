<?php
require_once __DIR__ . '/config/db.php';

// Authentication required
require_login();

$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($appointment_id <= 0) {
    set_flash('error', 'Invalid appointment record requested.');
    header('Location: index.php');
    exit;
}

// Fetch appointment with patient and doctor details
$stmt = $pdo->prepare("
    SELECT 
        a.id AS appointment_id,
        a.patient_id,
        a.doctor_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.reason,
        a.doctor_notes,
        a.created_at,
        p.full_name AS patient_name,
        p.phone AS patient_phone,
        p.email AS patient_email,
        doc_u.full_name AS doctor_name,
        doc_u.phone AS doctor_phone,
        doc_u.email AS doctor_email,
        s.name AS specialty_name,
        d.qualification,
        d.fee,
        d.user_id AS doctor_user_id
    FROM appointments a
    INNER JOIN users p ON a.patient_id = p.id
    INNER JOIN doctors d ON a.doctor_id = d.id
    INNER JOIN users doc_u ON d.user_id = doc_u.id
    INNER JOIN specialties s ON d.specialty_id = s.id
    WHERE a.id = ?
    LIMIT 1
");
$stmt->execute([$appointment_id]);
$apt = $stmt->fetch();

if (!$apt) {
    set_flash('error', 'Appointment slip not found.');
    header('Location: index.php');
    exit;
}

// Access control check: Patient owns it, Doctor is assigned, or Admin
$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'patient';

$is_authorized = false;
if ($user_role === 'admin') {
    $is_authorized = true;
} elseif ($user_role === 'doctor' && (int)$apt['doctor_user_id'] === $user_id) {
    $is_authorized = true;
} elseif ($user_role === 'patient' && (int)$apt['patient_id'] === $user_id) {
    $is_authorized = true;
}

if (!$is_authorized) {
    set_flash('error', 'You are not authorized to view this appointment slip.');
    header('Location: index.php');
    exit;
}

$time_obj = DateTime::createFromFormat('H:i:s', $apt['appointment_time']);
$formatted_time = $time_obj ? $time_obj->format('g:i A') : $apt['appointment_time'];
$formatted_date = date('l, d F Y', strtotime($apt['appointment_date']));
$slip_token     = 'CP-' . str_pad($apt['appointment_id'], 6, '0', STR_PAD_LEFT);

$page_title = "Appointment Slip #{$slip_token}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?> - CarePulse Clinic</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Slip Specific Styles */
        body {
            background-color: #f1f5f9;
        }
        .slip-wrapper {
            max-width: 760px;
            margin: 30px auto;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 36px 40px;
        }
        .slip-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .slip-section-title {
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        .slip-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        .slip-info-box {
            background-color: #f8fafc;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 16px;
        }
        .no-print {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 760px;
            margin: 20px auto 0;
            padding: 0 10px;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .slip-wrapper {
                box-shadow: none !important;
                border: 1px solid #000000 !important;
                margin: 0 !important;
                max-width: 100% !important;
                padding: 24px !important;
                border-radius: 0 !important;
            }
        }
    </style>
</head>
<body>

<!-- Control Bar (Hidden during printing) -->
<div class="no-print">
    <a href="<?php echo ($user_role === 'doctor') ? 'doctor_dashboard.php' : (($user_role === 'admin') ? 'admin_dashboard.php' : 'dashboard.php'); ?>" class="btn btn-outline btn-sm">
        &larr; Back to Dashboard
    </a>
    <div style="display: flex; gap: 8px;">
        <button onclick="window.print();" class="btn btn-primary btn-sm">
            🖨️ Print Appointment Slip
        </button>
    </div>
</div>

<!-- Main Medical Receipt Slip -->
<div class="slip-wrapper">
    <!-- Clinic & Slip Header -->
    <div class="slip-header">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <div style="background: var(--primary); color: #fff; width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 800;">
                    CP
                </div>
                <div style="font-size: 1.45rem; font-weight: 800; color: var(--primary);">
                    CarePulse <span>Clinic</span>
                </div>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4;">
                12 Medical Complex, Healthcare Boulevard, Lahore<br>
                UAN: (042) 111-227-378 &middot; info@carepulse.com
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 0.85rem; font-weight: 800; color: var(--primary); letter-spacing: 0.05em; text-transform: uppercase;">
                Appointment Slip
            </div>
            <div style="font-size: 1.25rem; font-weight: 800; color: var(--accent); margin: 2px 0;">
                <?php echo e($slip_token); ?>
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted);">
                Issued: <?php echo date('d M Y, h:i A'); ?>
            </div>
        </div>
    </div>

    <!-- Patient & Doctor Information -->
    <div class="slip-grid">
        <!-- Patient Box -->
        <div class="slip-info-box">
            <div class="slip-section-title">Patient Details</div>
            <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
                <?php echo e($apt['patient_name']); ?>
            </div>
            <div style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.5;">
                <div>📞 Phone: <strong><?php echo e($apt['patient_phone']); ?></strong></div>
                <div>✉️ Email: <?php echo e($apt['patient_email']); ?></div>
            </div>
        </div>

        <!-- Doctor Box -->
        <div class="slip-info-box">
            <div class="slip-section-title">Assigned Specialist</div>
            <div style="font-size: 1.05rem; font-weight: 700; color: var(--primary); margin-bottom: 4px;">
                <?php echo e($apt['doctor_name']); ?>
            </div>
            <div style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.5;">
                <div>Department: <strong><?php echo e($apt['specialty_name']); ?></strong></div>
                <div>Qualifications: <?php echo e($apt['qualification']); ?></div>
            </div>
        </div>
    </div>

    <!-- Appointment Timing & Fee Details -->
    <div style="border: 1px solid var(--border); border-radius: var(--radius-md); overflow: hidden; margin-bottom: 24px;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.92rem;">
            <tr style="background: #f8fafc; border-bottom: 1px solid var(--border);">
                <th style="padding: 12px 16px; color: var(--text-secondary);">Consultation Date</th>
                <th style="padding: 12px 16px; color: var(--text-secondary);">Scheduled Time Slot</th>
                <th style="padding: 12px 16px; color: var(--text-secondary);">Booking Status</th>
                <th style="padding: 12px 16px; color: var(--text-secondary); text-align: right;">Consultation Fee</th>
            </tr>
            <tr>
                <td style="padding: 14px 16px; font-weight: 700;">
                    <?php echo $formatted_date; ?>
                </td>
                <td style="padding: 14px 16px; font-weight: 700; color: var(--accent);">
                    ⏰ <?php echo $formatted_time; ?>
                </td>
                <td style="padding: 14px 16px;">
                    <?php if ($apt['status'] === 'booked'): ?>
                        <span class="badge badge-booked">Confirmed</span>
                    <?php elseif ($apt['status'] === 'completed'): ?>
                        <span class="badge badge-completed">Completed</span>
                    <?php else: ?>
                        <span class="badge badge-cancelled">Cancelled</span>
                    <?php endif; ?>
                </td>
                <td style="padding: 14px 16px; text-align: right; font-size: 1.05rem; font-weight: 800; color: var(--primary);">
                    PKR <?php echo number_format($apt['fee'], 0); ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Visit Reason -->
    <?php if (!empty($apt['reason'])): ?>
        <div style="margin-bottom: 20px;">
            <div class="slip-section-title">Patient Reason for Visit</div>
            <div style="background: #ffffff; border: 1px dashed var(--border); padding: 12px 16px; border-radius: var(--radius-md); font-size: 0.9rem; color: var(--text-secondary);">
                <?php echo e($apt['reason']); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Clinical Prescription & Doctor Remarks (If completed) -->
    <?php if ($apt['status'] === 'completed' && !empty($apt['doctor_notes'])): ?>
        <div style="margin-bottom: 28px;">
            <div class="slip-section-title" style="color: #059669;">📋 Doctor's Clinical Diagnosis & Prescription</div>
            <div style="background: #f0fdf4; border: 1.5px solid #a7f3d0; padding: 16px; border-radius: var(--radius-md); font-size: 0.92rem; color: #166534; line-height: 1.6;">
                <?php echo nl2br(e($apt['doctor_notes'])); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Signature & Stamp Section -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 36px; padding-top: 24px; border-top: 1px solid var(--border);">
        <div style="font-size: 0.8rem; color: var(--text-muted); max-width: 380px;">
            * Please arrive 10 minutes prior to your designated consultation time.<br>
            * Present this slip or token number at the reception desk upon arrival.
        </div>
        <div style="text-align: center;">
            <div style="border-bottom: 1px solid #0f172a; width: 170px; margin-bottom: 6px;"></div>
            <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-secondary);">Doctor Signature / Stamp</div>
        </div>
    </div>
</div>

</body>
</html>
