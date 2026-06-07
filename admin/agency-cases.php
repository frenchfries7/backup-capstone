<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_auth('admin');
$page_title = 'Agency Cases';
$use_dashboard_css = true;
$agencies = $pdo->query("SELECT id, name FROM agencies ORDER BY name")->fetchAll();
$agency_id = $_GET['agency_id'] ?? ($agencies[0]['id'] ?? null);
$cases = $agency_id ? $pdo->prepare("SELECT c.*, o.first_name, o.last_name FROM cases c JOIN ofws o ON c.ofw_id = o.id WHERE c.agency_id = ? ORDER BY c.created_at DESC")->execute([$agency_id]) : [];
$cases = $agency_id ? $pdo->prepare("SELECT c.*, o.first_name, o.last_name FROM cases c JOIN ofws o ON c.ofw_id = o.id WHERE c.agency_id = ? ORDER BY c.created_at DESC")->fetchAll() : [];
?><?php
$hide_navbar = true;
include '../includes/header.php'; ?>
<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><img src="/armas/assets/img/armas.png" alt="ARMAS" class="sidebar-logo">
            <div class="sidebar-brand-text"><span class="logo-text">ARMAS</span><span class="brand-subtitle">Admin
                    Portal</span></div>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section">
                <div class="sidebar-section-title">Main Menu</div>
                <a href="/armas/admin/dashboard.php" class="sidebar-link"><span class="sidebar-link-icon">📊</span><span
                        class="sidebar-link-text">Dashboard</span></a>
                <a href="/armas/admin/create-ofw.php" class="sidebar-link"><span class="sidebar-link-icon">➕</span><span
                        class="sidebar-link-text">Create OFW</span></a>
                <a href="/armas/admin/create-agency.php" class="sidebar-link"><span
                        class="sidebar-link-icon">🏢</span><span class="sidebar-link-text">Create Agency</span></a>
                <a href="/armas/admin/agency-list.php" class="sidebar-link"><span
                        class="sidebar-link-icon">🏛</span><span class="sidebar-link-text">Agencies</span></a>
                <a href="/armas/admin/agency-cases.php" class="sidebar-link active"><span
                        class="sidebar-link-icon">📋</span><span class="sidebar-link-text">Agency Cases</span></a>
                <a href="/armas/admin/reports.php" class="sidebar-link"><span class="sidebar-link-icon">📈</span><span
                        class="sidebar-link-text">Reports</span></a>
                <a href="/armas/admin/manage-accounts.php" class="sidebar-link"><span
                        class="sidebar-link-icon">👥</span><span class="sidebar-link-text">Accounts</span></a>
        </nav>
        <div class="sidebar-footer"><a href="/armas/pages/logout.php" class="btn btn-outline btn-sm w-100">Logout</a>
        </div>
    </aside>
    <main class="main-content">
        <header class="main-header">
            <div class="main-header-title">
                <h1>Agency Cases</h1>
            </div>
        </header>
        <div class="main-body">
            <div class="agency-selector">
                <label>Select Agency:</label>
                <select id="agency-select" class="form-control" onchange="location.href='?agency_id='+this.value">
                    <?php foreach ($agencies as $a): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $agency_id == $a['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($a['name']); ?>
                        </option><?php endforeach; ?>
                </select>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Case Number</th>
                                    <th>OFW</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cases as $c): ?>
                                    <tr>
                                        <td><span class="case-id"><?php echo $c['case_number']; ?></span></td>
                                        <td><?php echo $c['first_name'] . ' ' . $c['last_name']; ?></td>
                                        <td><?php echo $c['type']; ?></td>
                                        <td><?php echo get_status_badge($c['status']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>    