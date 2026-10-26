<?php
require_once __DIR__ . '/config/db.php';

// Access control: Admin only
require_admin();

$errors = [];
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_name = '';

// Handle Add / Edit Specialty
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            $errors[] = 'Specialty name cannot be empty.';
        } else {
            // Check duplicate
            $check = $pdo->prepare("SELECT id FROM specialties WHERE LOWER(name) = LOWER(?) LIMIT 1");
            $check->execute([$name]);
            if ($check->fetch()) {
                $errors[] = 'A specialty with this name already exists.';
            } else {
                try {
                    $insert = $pdo->prepare("INSERT INTO specialties (name) VALUES (?)");
                    $insert->execute([$name]);
                    set_flash('success', "Specialty '{$name}' created successfully.");
                    header('Location: admin_specialties.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($id <= 0 || empty($name)) {
            $errors[] = 'Valid ID and specialty name are required.';
        } else {
            // Check duplicate excluding self
            $check = $pdo->prepare("SELECT id FROM specialties WHERE LOWER(name) = LOWER(?) AND id != ? LIMIT 1");
            $check->execute([$name, $id]);
            if ($check->fetch()) {
                $errors[] = 'Another specialty with this name already exists.';
            } else {
                try {
                    $update = $pdo->prepare("UPDATE specialties SET name = ? WHERE id = ?");
                    $update->execute([$name, $id]);
                    set_flash('success', "Specialty updated to '{$name}'.");
                    header('Location: admin_specialties.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Invalid specialty selected.';
        } else {
            // Check if any doctors assigned
            $check_docs = $pdo->prepare("SELECT COUNT(*) FROM doctors WHERE specialty_id = ?");
            $check_docs->execute([$id]);
            $assigned_count = (int)$check_docs->fetchColumn();

            if ($assigned_count > 0) {
                set_flash('error', "Cannot delete this specialty because {$assigned_count} doctor(s) are currently assigned to it.");
                header('Location: admin_specialties.php');
                exit;
            } else {
                try {
                    $del = $pdo->prepare("DELETE FROM specialties WHERE id = ?");
                    $del->execute([$id]);
                    set_flash('success', 'Specialty deleted successfully.');
                    header('Location: admin_specialties.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch edit target if requested
if ($edit_id > 0) {
    $edit_stmt = $pdo->prepare("SELECT id, name FROM specialties WHERE id = ? LIMIT 1");
    $edit_stmt->execute([$edit_id]);
    $target = $edit_stmt->fetch();
    if ($target) {
        $edit_name = $target['name'];
    }
}

// Fetch all specialties with doctor counts
$stmt = $pdo->query("
    SELECT s.id, s.name, COUNT(d.id) AS total_doctors
    FROM specialties s
    LEFT JOIN doctors d ON s.id = d.specialty_id
    GROUP BY s.id, s.name
    ORDER BY s.name ASC
");
$specialties = $stmt->fetchAll();

$page_title = 'Manage Medical Specialties';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 900px; margin: 10px auto;">
    <div style="margin-bottom: 20px;">
        <a href="admin_dashboard.php" class="btn btn-outline btn-sm">
            &larr; Back to Admin Dashboard
        </a>
    </div>

    <div class="page-header" style="margin-bottom: 24px;">
        <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--primary); margin: 0 0 6px 0;">
            Medical Specialties & Departments
        </h1>
        <p style="color: var(--text-secondary); margin: 0;">
            Add and manage clinic clinical categories, medical departments, and specialty listings.
        </p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo e($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start;">
        <!-- Specialties List Table -->
        <div class="card">
            <div class="card-header">
                <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--primary); margin: 0;">
                    Existing Specialties (<?php echo count($specialties); ?>)
                </h2>
            </div>
            <div class="card-body" style="padding: 0; overflow-x: auto;">
                <table class="table" style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border); background-color: #f8fafc;">
                            <th style="padding: 12px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary);">Specialty Name</th>
                            <th style="padding: 12px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary); text-align: center;">Doctors</th>
                            <th style="padding: 12px 18px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary); text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($specialties as $sp): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 14px 18px; font-weight: 600; color: var(--text-primary);">
                                    <?php echo e($sp['name']); ?>
                                </td>
                                <td style="padding: 14px 18px; text-align: center;">
                                    <span class="badge badge-specialty" style="padding: 2px 8px;">
                                        <?php echo (int)$sp['total_doctors']; ?> Doctor<?php echo (int)$sp['total_doctors'] === 1 ? '' : 's'; ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 18px; text-align: right; white-space: nowrap;">
                                    <a href="admin_specialties.php?edit=<?php echo (int)$sp['id']; ?>" class="btn btn-outline btn-sm" style="margin-right: 4px;">
                                        Edit
                                    </a>
                                    <?php if ((int)$sp['total_doctors'] === 0): ?>
                                        <form action="admin_specialties.php" method="POST" style="display: inline;" onsubmit="return confirm('Delete specialty \'<?php echo addslashes($sp['name']); ?>\'?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$sp['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline" disabled style="opacity: 0.5; cursor: not-allowed;" title="Cannot delete: doctors assigned">
                                            In Use
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add / Edit Form Card -->
        <div class="card">
            <div class="card-header">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--primary); margin: 0;">
                    <?php echo $edit_id > 0 ? 'Edit Specialty' : 'Add New Specialty'; ?>
                </h3>
            </div>
            <div class="card-body">
                <form action="admin_specialties.php" method="POST">
                    <input type="hidden" name="action" value="<?php echo $edit_id > 0 ? 'update' : 'add'; ?>">
                    <?php if ($edit_id > 0): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label" for="name">Department Name</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="form-control" 
                            placeholder="e.g. Pulmonology, ENT" 
                            value="<?php echo e($edit_name); ?>" 
                            required
                        >
                        <div class="form-text">
                            Specialties categorize doctors on the patient booking directory.
                        </div>
                    </div>

                    <div style="display: flex; gap: 8px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary btn-block">
                            <?php echo $edit_id > 0 ? 'Update Specialty' : 'Add Specialty'; ?>
                        </button>
                        <?php if ($edit_id > 0): ?>
                            <a href="admin_specialties.php" class="btn btn-outline">
                                Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

