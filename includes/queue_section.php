<?php
$queueTitle = ['patient' => 'Your live queue', 'doctor' => 'Consultation queue', 'admin' => 'Clinic queue'][$queueRole];
$queueSubtitle = [
    'patient' => 'Follow your turn after checking in at reception.',
    'doctor' => 'Call checked-in patients and finish visits from one place.',
    'admin' => 'Check in arrivals and monitor every doctor’s queue.',
][$queueRole];
?>
<section class="live-queue" id="live-queue" aria-labelledby="queue-title">
    <header class="queue-header">
        <div>
            <p class="queue-date" data-queue-date><?= e(date('l, j F')) ?></p>
            <h2 id="queue-title"><?= e($queueTitle) ?></h2>
            <p><?= e($queueSubtitle) ?></p>
        </div>
        <div class="queue-connection">
            <span class="queue-sync" data-queue-connection hidden role="status"></span>
            <a href="<?= e(['patient' => 'dashboard.php', 'doctor' => 'doctor_dashboard.php', 'admin' => 'admin_dashboard.php'][$queueRole]) ?>#live-queue" class="queue-refresh" data-queue-refresh>Refresh</a>
        </div>
    </header>
    <div class="queue-body">
        <?php if ($queueRole === 'admin'): ?>
            <form class="queue-reception" action="queue_action.php" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="check_in">
                <div>
                    <label for="queue-token">Check in a patient</label>
                    <p id="queue-token-help">Enter the token from today’s appointment slip.</p>
                </div>
                <input class="form-control" id="queue-token" name="token" placeholder="CP-000123"
                       pattern="[Cc][Pp]-[0-9]{6,10}" maxlength="13" autocomplete="off"
                       autocapitalize="characters" spellcheck="false" required aria-describedby="queue-token-help">
                <button type="submit" class="btn queue-primary">Check in</button>
            </form>
        <?php endif; ?>
        <div data-queue-content>
            <?php require __DIR__ . '/queue_cards.php'; ?>
        </div>
        <p class="queue-sr" data-queue-announcement role="status"></p>
        <?php if ($queueRole === 'admin' && $queueData): ?>
            <details class="queue-settings">
                <summary>Consultation timing defaults</summary>
                <p>These estimates apply until a doctor has enough recorded visits.</p>
                <form action="queue_action.php" method="post" class="queue-settings-form" data-queue-timing>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_timing">
                    <label>Doctor
                        <select class="form-select" name="doctor_id">
                            <?php foreach ($queueData as $row): ?>
                                <option value="<?= (int)$row['id'] ?>" data-min="<?= (int)$row['queue_min_minutes'] ?>" data-max="<?= (int)$row['queue_max_minutes'] ?>">
                                    <?= e($row['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Minimum (min)
                        <input class="form-control" type="number" name="min_minutes" required min="5" max="120" value="<?= (int)$queueData[0]['queue_min_minutes'] ?>">
                    </label>
                    <label>Maximum (min)
                        <input class="form-control" type="number" name="max_minutes" required min="5" max="120" value="<?= (int)$queueData[0]['queue_max_minutes'] ?>">
                    </label>
                    <button type="submit" class="btn btn-outline">Save timing</button>
                </form>
            </details>
        <?php endif; ?>
    </div>
</section>
<script src="assets/js/queue.js?v=<?= filemtime(__DIR__ . '/../assets/js/queue.js') ?>" defer></script>
