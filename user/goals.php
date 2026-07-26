<?php
/**
 * FinancePro - Financial Goals
 * Create, track, and manage personal financial goals with progress bars.
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];
$errors = [];

$csrf_token = generate_csrf_token();

// Handle Add Goal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_goal') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request.';
    } else {
        $goal_name     = clean_input($_POST['goal_name'] ?? '');
        $target_amount = (float)($_POST['target_amount'] ?? 0);
        $saved_amount  = (float)($_POST['saved_amount'] ?? 0);
        $deadline      = clean_input($_POST['deadline'] ?? '');
        $priority      = clean_input($_POST['priority'] ?? 'Medium');
        if (empty($goal_name)) $errors[] = 'Goal name is required.';
        if ($target_amount <= 0) $errors[] = 'Target amount must be > 0.';
        if (empty($deadline)) $errors[] = 'Deadline is required.';
        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO financial_goals (user_id, goal_name, target_amount, saved_amount, deadline, priority) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("isddss", $uid, $goal_name, $target_amount, $saved_amount, $deadline, $priority);
            $stmt->execute(); $stmt->close();
            set_flash('success', "Goal '$goal_name' created successfully!");
            header("Location: goals.php"); exit;
        }
    }
}

// Handle Update Saved Amount
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_saved') {
    if (verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $goal_id      = (int)$_POST['goal_id'];
        $saved_amount = (float)$_POST['saved_amount'];
        $stmt = $conn->prepare("UPDATE financial_goals SET saved_amount=? WHERE goal_id=? AND user_id=?");
        $stmt->bind_param("dii", $saved_amount, $goal_id, $uid);
        $stmt->execute(); $stmt->close();
        set_flash('success', 'Goal progress updated!');
        header("Location: goals.php"); exit;
    }
}

// Handle Delete Goal
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM financial_goals WHERE goal_id=? AND user_id=?");
    $stmt->bind_param("ii", (int)$_GET['delete'], $uid);
    $stmt->execute(); $stmt->close();
    set_flash('success', 'Goal removed.'); header("Location: goals.php"); exit;
}

// Fetch all goals
$stmt = $conn->prepare("SELECT * FROM financial_goals WHERE user_id=? ORDER BY priority DESC, deadline ASC");
$stmt->bind_param("i", $uid);
$stmt->execute();
$goals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$priority_colors = ['High' => 'var(--fp-danger)', 'Medium' => 'var(--fp-warning)', 'Low' => 'var(--fp-accent)'];
$active_page = 'goals'; $page_title = 'Financial Goals';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Financial Goals - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
<div class="fp-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="fp-main">
        <?php include '../includes/header.php'; ?>
        <div class="fp-content">
            <?php include '../includes/alerts.php'; ?>
            <?php if (!empty($errors)): ?>
            <div class="fp-alert fp-alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= implode(' &bull; ', array_map('e', $errors)) ?></div>
            <?php endif; ?>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 style="font-size:1.4rem;font-weight:700;color:var(--fp-text-dark);margin:0;">
                        <i class="fa-solid fa-star" style="color:var(--fp-warning)"></i> Financial Goals
                    </h1>
                    <p style="color:var(--fp-text-muted);margin:4px 0 0;">Track your savings goals and milestones</p>
                </div>
                <button class="btn-fp btn-fp-primary btn-fp-sm" onclick="document.getElementById('addGoalForm').scrollIntoView({behavior:'smooth'})">
                    <i class="fa-solid fa-plus"></i> New Goal
                </button>
            </div>

            <!-- Goals Grid -->
            <?php if (empty($goals)): ?>
            <div class="table-card" style="margin-bottom:24px;">
                <div class="empty-state">
                    <i class="fa-solid fa-star"></i>
                    <h3>No Goals Yet</h3>
                    <p>Create your first financial goal below to start tracking your savings progress.</p>
                </div>
            </div>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;margin-bottom:28px;">
                <?php foreach ($goals as $g):
                    $pct = $g['target_amount'] > 0 ? min(100, round($g['saved_amount'] / $g['target_amount'] * 100)) : 0;
                    $remaining = $g['target_amount'] - $g['saved_amount'];
                    $days_left = (strtotime($g['deadline']) - time()) / 86400;
                    $bar_color = $pct >= 100 ? 'var(--fp-accent)' : ($pct >= 75 ? 'var(--fp-primary)' : ($pct >= 40 ? 'var(--fp-warning)' : 'var(--fp-danger)'));
                    // Estimated completion
                    $daily_rate = $days_left > 0 && $g['saved_amount'] > 0
                        ? $g['saved_amount'] / (max(1, (time() - strtotime($g['created_at'])) / 86400))
                        : 0;
                    $days_to_complete = $daily_rate > 0 && $remaining > 0 ? ceil($remaining / $daily_rate) : null;
                ?>
                <div class="form-card" style="position:relative;overflow:hidden;">
                    <!-- Priority badge -->
                    <div style="position:absolute;top:14px;right:14px;">
                        <span style="background:<?= $priority_colors[$g['priority']] ?? 'var(--fp-primary)' ?>;color:#fff;padding:2px 10px;border-radius:20px;font-size:.7rem;font-weight:700;">
                            <?= e($g['priority']) ?>
                        </span>
                    </div>

                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
                        <div style="width:44px;height:44px;background:linear-gradient(135deg,var(--fp-primary),#7c3aed);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa-solid fa-bullseye" style="color:#fff;font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;color:var(--fp-text-dark);font-size:1rem;"><?= e($g['goal_name']) ?></div>
                            <div style="font-size:.78rem;color:var(--fp-text-muted);">Deadline: <?= date('d M Y', strtotime($g['deadline'])) ?></div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="fp-progress" style="height:10px;margin-bottom:10px;">
                        <div class="fp-progress-bar" style="width:<?= $pct ?>%;background:<?= $bar_color ?>;transition:width .5s ease;"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:.8rem;color:var(--fp-text-muted);margin-bottom:14px;">
                        <span><strong style="color:var(--fp-text-dark);"><?= $pct ?>%</strong> saved</span>
                        <span><?= format_currency($g['saved_amount']) ?> / <?= format_currency($g['target_amount']) ?></span>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:.8rem;margin-bottom:14px;">
                        <div style="background:var(--fp-bg);padding:8px;border-radius:8px;">
                            <div style="color:var(--fp-text-muted);">Remaining</div>
                            <div style="font-weight:700;color:<?= $remaining>0?'var(--fp-danger)':'var(--fp-accent)' ?>;"><?= $remaining <= 0 ? '✓ Achieved!' : format_currency($remaining) ?></div>
                        </div>
                        <div style="background:var(--fp-bg);padding:8px;border-radius:8px;">
                            <div style="color:var(--fp-text-muted);">Days Left</div>
                            <div style="font-weight:700;color:<?= $days_left<0?'var(--fp-danger)':($days_left<30?'var(--fp-warning)':'var(--fp-text-dark)') ?>;">
                                <?= $days_left < 0 ? 'Overdue' : round($days_left).' days' ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($days_to_complete !== null): ?>
                    <div style="font-size:.75rem;color:var(--fp-text-muted);margin-bottom:12px;">
                        <i class="fa-solid fa-clock"></i> Est. completion: ~<?= $days_to_complete ?> more days at current rate
                    </div>
                    <?php endif; ?>

                    <div style="display:flex;gap:6px;">
                        <button class="btn-fp btn-fp-outline btn-fp-sm" style="flex:1;"
                            onclick="document.getElementById('updateGoalId').value='<?= $g['goal_id'] ?>';
                                     document.getElementById('updateSavedAmt').value='<?= $g['saved_amount'] ?>';
                                     document.getElementById('updateModal').style.display='flex';">
                            <i class="fa-solid fa-edit"></i> Update
                        </button>
                        <a href="?delete=<?= $g['goal_id'] ?>" onclick="return confirm('Delete this goal?')"
                           class="btn-fp btn-fp-outline btn-fp-sm" style="color:var(--fp-danger);border-color:var(--fp-danger);">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Add Goal Form -->
            <div class="form-card" id="addGoalForm">
                <div class="form-section-title"><i class="fa-solid fa-plus" style="color:var(--fp-primary)"></i> Create New Goal</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <input type="hidden" name="action" value="add_goal">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
                        <div class="fp-form-group">
                            <label class="fp-label">Goal Name</label>
                            <input type="text" name="goal_name" class="fp-input" placeholder="e.g. Emergency Fund" required>
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">Target Amount (Rs.)</label>
                            <input type="number" name="target_amount" class="fp-input" min="1" step="0.01" placeholder="50000" required>
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">Already Saved (Rs.)</label>
                            <input type="number" name="saved_amount" class="fp-input" min="0" step="0.01" placeholder="0" value="0">
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">Deadline</label>
                            <input type="date" name="deadline" class="fp-input" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">Priority</label>
                            <select name="priority" class="fp-select">
                                <option value="High">High</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-fp btn-fp-primary" style="margin-top:8px;">
                        <i class="fa-solid fa-star"></i> Create Goal
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Update Saved Amount Modal -->
<div id="updateModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:var(--fp-card-bg,#fff);border-radius:16px;padding:28px;width:360px;max-width:90%;">
        <h3 style="margin:0 0 16px;color:var(--fp-text-dark);font-size:1rem;">Update Goal Progress</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
            <input type="hidden" name="action" value="update_saved">
            <input type="hidden" name="goal_id" id="updateGoalId">
            <div class="fp-form-group">
                <label class="fp-label">Amount Saved So Far (Rs.)</label>
                <input type="number" name="saved_amount" id="updateSavedAmt" class="fp-input" min="0" step="0.01" required>
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button type="submit" class="btn-fp btn-fp-primary" style="flex:1;"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <button type="button" onclick="document.getElementById('updateModal').style.display='none'" class="btn-fp btn-fp-outline" style="flex:1;">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
