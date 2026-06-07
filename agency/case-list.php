<?php

/**
 * ARMAS Agency Case List
 */
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_auth('agency');

$page_title = 'Cases';
$use_dashboard_css = true;

$stmt = $pdo->prepare("SELECT id FROM agencies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$agency = $stmt->fetch();
$agency_id = $agency['id'];

// Handle view
$view_case = null;
$case_updates = [];
if (isset($_GET['view'])) {
    $view_id = intval($_GET['view']);
    $view_stmt = $pdo->prepare("SELECT c.*, o.first_name, o.last_name, o.middle_name 
                                FROM cases c JOIN ofws o ON c.ofw_id = o.id 
                                WHERE c.id = ? AND c.agency_id = ?");
    $view_stmt->execute([$view_id, $agency_id]);
    $view_case = $view_stmt->fetch();

    if ($view_case) {
        $upd_stmt = $pdo->prepare("SELECT cu.*, u.email 
                                   FROM case_updates cu 
                                   JOIN users u ON cu.updated_by = u.id 
                                   WHERE cu.case_id = ? 
                                   ORDER BY cu.created_at ASC");
        $upd_stmt->execute([$view_id]);
        $case_updates = $upd_stmt->fetchAll();
    }
}

// Handle edit status
if (isset($_POST['update_status'])) {
    $case_id = intval($_POST['case_id']);
    $new_status = $_POST['status'];
    $allowed = ['pending', 'in_process', 'resolved', 'closed'];

    // Get current status
    $cur_stmt = $pdo->prepare("SELECT status FROM cases WHERE id = ? AND agency_id = ?");
    $cur_stmt->execute([$case_id, $agency_id]);
    $current_status = $cur_stmt->fetchColumn();

    // Define allowed transitions
    $transitions = [
        'pending'    => ['in_process'],
        'in_process' => ['resolved', 'closed'],
        'resolved'   => ['closed'],
        'closed'     => [],
    ];

    if (in_array($new_status, $allowed) && in_array($new_status, $transitions[$current_status])) {
        $pdo->prepare("UPDATE cases SET status = ?, updated_at = NOW() WHERE id = ? AND agency_id = ?")
            ->execute([$new_status, $case_id, $agency_id]);

        // Log to case_updates
        $pdo->prepare("INSERT INTO case_updates (case_id, note, updated_by, created_at) VALUES (?, ?, ?, NOW())")
            ->execute([$case_id, "Status updated to $new_status.", $_SESSION['user_id']]);

        // Notify OFW
        $ofw_stmt = $pdo->prepare("SELECT o.user_id, c.case_number FROM cases c JOIN ofws o ON c.ofw_id = o.id WHERE c.id = ?");
        $ofw_stmt->execute([$case_id]);
        $ofw_case = $ofw_stmt->fetch();
        if ($ofw_case) {
            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?,?,?)")
                ->execute([$ofw_case['user_id'], "Your case {$ofw_case['case_number']} status has been updated to $new_status.", 'status_update']);
        }
    }
    header('Location: case-list.php');
    exit;
}

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = "WHERE c.agency_id = ?";
$params = [$agency_id];

if ($search) {
    $where .= " AND (c.case_number LIKE ? OR CONCAT(o.first_name, ' ', o.last_name) LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where .= " AND c.status = ?";
    $params[] = $status_filter;
}

$stmt = $pdo->prepare("SELECT c.*, o.first_name, o.last_name FROM cases c JOIN ofws o ON c.ofw_id = o.id $where ORDER BY c.created_at DESC");
$stmt->execute($params);
$cases = $stmt->fetchAll();
?>
<?php
$hide_navbar = true;
include '../includes/header.php'; ?>

<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="/armas/assets/img/armas.jpg" alt="ARMAS" class="sidebar-logo">
            <div class="sidebar-brand-text">
                <span class="logo-text">ARMAS</span>
                <span class="brand-subtitle">Agency Portal</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-section">
                <div class="sidebar-section-title">Main Menu</div>
                <a href="/armas/agency/dashboard.php" class="sidebar-link">
                    <span class="sidebar-link-icon">📊</span>
                    <span class="sidebar-link-text">Dashboard</span>
                </a>
                <a href="/armas/agency/ofw-list.php" class="sidebar-link">
                    <span class="sidebar-link-icon">👥</span>
                    <span class="sidebar-link-text">awaw</span>
                </a>
                <a href="/armas/agency/add-ofw.php" class="sidebar-link">
                    <span class="sidebar-link-icon">➕</span>
                    <span class="sidebar-link-text">Add OFW</span>
                </a>
                <a href="/armas/agency/case-list.php" class="sidebar-link active">
                    <span class="sidebar-link-icon">📋</span>
                    <span class="sidebar-link-text">Cases</span>
                </a>
                <a href="/armas/agency/reports.php" class="sidebar-link">
                    <span class="sidebar-link-icon">📈</span>
                    <span class="sidebar-link-text">Reports</span>
                </a>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">Account</div>
                <a href="/armas/agency/profile.php" class="sidebar-link">
                    <span class="sidebar-link-icon">👤</span>
                    <span class="sidebar-link-text">Profile</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <a href="/armas/pages/logout.php" class="btn btn-outline btn-sm w-100">Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <div class="main-header-title">
                <h1>Case Management</h1>
            </div>
        </header>

        <div class="main-body">
            <form method="GET" class="search-filter-bar">
                <div class="search-box">
                    <input type="text" name="search" class="form-control"
                        placeholder="Search by case number or OFW name..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending
                        </option>
                        <option value="in_process" <?php echo $status_filter === 'in_process' ? 'selected' : ''; ?>>In
                            Process</option>
                        <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved
                        </option>
                        <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed
                        </option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            </form>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($cases)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📋</div>
                            <h3>No Cases Found</h3>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Case Number</th>
                                        <th>OFW Name</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cases as $case): ?>
                                        <tr>
                                            <td><span
                                                    class="case-id"><?php echo htmlspecialchars($case['case_number']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($case['type']); ?></td>
                                            <td><?php echo get_status_badge($case['status']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($case['created_at'])); ?></td>
                                            <td>
                                                <a href="?view=<?php echo $case['id']; ?>" title="View Case">🔍</a>
                                                <a href="?edit=<?php echo $case['id']; ?>" title="Edit Case">✏️</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Edit Status Modal -->
<?php if (isset($_GET['edit'])):
    $edit_id = intval($_GET['edit']);
    $edit_stmt = $pdo->prepare("SELECT * FROM cases WHERE id = ? AND agency_id = ?");
    $edit_stmt->execute([$edit_id, $agency_id]);
    $edit_case = $edit_stmt->fetch();
?>
    <?php if ($edit_case): ?>
        <div class="modal" style="display:flex;">
            <div class="modal-content" style="max-width:400px;">
                <h3>Update Case Status</h3>
                <p><strong>Case:</strong> <?php echo htmlspecialchars($edit_case['case_number']); ?></p>
                <form method="POST">
                    <input type="hidden" name="case_id" value="<?php echo $edit_case['id']; ?>">
                    <div class="form-group" style="margin-top:16px;">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <?php
                            $transitions = [
                                'pending'    => ['in_process'],
                                'in_process' => ['resolved', 'closed'],
                                'resolved'   => ['closed'],
                                'closed'     => [],
                            ];
                            $labels = [
                                'pending'    => 'Pending',
                                'in_process' => 'In Process',
                                'resolved'   => 'Resolved',
                                'closed'     => 'Closed',
                            ];
                            // Always show current status as selected
                            echo "<option value='{$edit_case['status']}' selected>{$labels[$edit_case['status']]} (current)</option>";
                            foreach ($transitions[$edit_case['status']] as $next) {
                                echo "<option value='$next'>{$labels[$next]}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="modal-actions" style="margin-top:20px; display:flex; gap:12px;">
                        <button type="submit" name="update_status" class="btn btn-primary">Save</button>
                        <a href="case-list.php" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>


<!-- View Case Modal -->
<?php if ($view_case): ?>
    <div class="modal" style="display:flex;">
        <div class="modal-content" style="max-width:700px;">
            <h3>Case Details: <span class="case-id"><?php echo htmlspecialchars($view_case['case_number']); ?></span></h3>

            <div style="margin: 20px 0;">
                <p><strong>OFW Name:</strong> <?php echo htmlspecialchars($view_case['first_name'] . ' ' . $view_case['last_name']); ?></p>
                <p><strong>Type:</strong> <?php echo htmlspecialchars($view_case['type']); ?></p>
                <p><strong>Status:</strong> <?php echo get_status_badge($view_case['status']); ?></p>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($view_case['location_abroad']); ?></p>
                <p><strong>Employer:</strong> <?php echo htmlspecialchars($view_case['employer_name']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($view_case['description']); ?></p>
                <p><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($view_case['emergency_contact_name']); ?> — <?php echo htmlspecialchars($view_case['emergency_contact_number']); ?></p>
                <p><strong>Date of Departure:</strong> <?php echo date('M d, Y', strtotime($view_case['date_of_departure'])); ?></p>
                <p><strong>Submitted:</strong> <?php echo date('M d, Y h:i A', strtotime($view_case['created_at'])); ?></p>
            </div>

            <h4>Case Timeline</h4>
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-date"><?php echo date('M d, Y h:i A', strtotime($view_case['created_at'])); ?></div>
                    <div class="timeline-content">Case submitted</div>
                </div>
                <?php foreach ($case_updates as $update): ?>
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('M d, Y h:i A', strtotime($update['created_at'])); ?></div>
                        <div class="timeline-content"><?php echo htmlspecialchars($update['note']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="modal-actions" style="margin-top:20px;">
                <a href="case-list.php" class="btn btn-primary">Close</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>