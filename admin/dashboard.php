<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requireRole('admin');
$user = currentUser();
$db = getDB();

$csrf_token = generateCSRF();

// Fetch stats
$stmt = $db->query("SELECT COUNT(*) as total FROM classrooms");
$totalRooms = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM classrooms WHERE current_status = 'Occupied'");
$occupiedRooms = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM room_requests WHERE status = 'Pending'");
$pendingRequestsCount = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM session_logs WHERE actual_end_time IS NULL");
$activeSessions = $stmt->fetch()['total'];

// Fetch all requests for requests tab
$stmt = $db->query("SELECT rr.*, c.room_name_number, u.first_name, u.last_name 
                    FROM room_requests rr 
                    JOIN classrooms c ON rr.room_id = c.room_id 
                    JOIN users u ON rr.faculty_id = u.user_id 
                    ORDER BY rr.created_at DESC");
$allRequests = $stmt->fetchAll();

// Fetch Users
$stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Fetch Rooms
$stmt = $db->query("SELECT * FROM classrooms ORDER BY floor_level, room_name_number");
$rooms = $stmt->fetchAll();

// Fetch recent activity
$stmt = $db->query("SELECT sl.*, c.room_name_number, u.first_name, u.last_name 
                    FROM session_logs sl
                    JOIN classrooms c ON sl.room_id = c.room_id
                    JOIN users u ON sl.faculty_id = u.user_id
                    ORDER BY sl.actual_start_time DESC LIMIT 5");
$recentActivity = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    <title>Admin Dashboard — STI Classroom System</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .module-page { display: none; }
        .module-page.active { display: block; }
        .sidebar { width: 250px; background-color: var(--bg-secondary); border-right: 1px solid var(--border-subtle); display: flex; flex-direction: column; height: 100vh; position: fixed; }
        .main { margin-left: 250px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; background-color: var(--bg-primary); }
        .header { height: 64px; background-color: var(--bg-secondary); border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; padding: 0 24px; }
        .content { padding: 24px; overflow-y: auto; flex: 1; }
        .brand { padding: 24px; text-align: center; font-weight: bold; font-size: 1.2rem; border-bottom: 1px solid var(--border-subtle); }
        .nav-section { padding: 12px 0; }
        .nav-section-label { font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); padding: 0 24px; margin-bottom: 8px; font-weight: 600; }
        .nav-item { padding: 10px 24px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; color: var(--text-primary); text-decoration: none; }
        .nav-item:hover, .nav-item.active { background-color: var(--bg-primary); color: var(--accent-primary); border-right: 3px solid var(--accent-primary); }
        .sidebar-footer { margin-top: auto; padding: 24px; border-top: 1px solid var(--border-subtle); display: flex; align-items: center; gap: 12px; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--accent-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: var(--shadow-float); display: flex; align-items: center; gap: 16px; border: 1px solid var(--border-subtle); }
        .stat-icon { width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-value { font-size: 1.5rem; font-weight: bold; margin-bottom: 4px; }
        .stat-label { color: var(--text-secondary); font-size: 0.875rem; }
        .card { background: white; border-radius: 8px; border: 1px solid var(--border-subtle); box-shadow: var(--shadow-float); margin-bottom: 24px; }
        .card-header { padding: 16px 24px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 24px; }
        .btn { padding: 8px 16px; border-radius: 4px; border: none; cursor: pointer; font-weight: 500; font-size: 0.875rem; }
        .btn-primary { background-color: var(--accent-primary); color: white; }
        .btn-outline { background-color: transparent; border: 1px solid var(--border-subtle); color: var(--text-primary); }
        .btn-danger { background-color: var(--danger); color: white; }
        .btn-sm { padding: 4px 8px; font-size: 0.75rem; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-subtle); }
        .data-table th { background-color: var(--bg-primary); color: var(--text-secondary); font-weight: 600; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .badge-success { background-color: var(--success-bg); color: var(--success); }
        .badge-warning { background-color: var(--warning-bg); color: var(--warning); }
        .badge-danger { background-color: var(--danger-bg); color: var(--danger); }
        .role-badge { padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; border: 1px solid currentColor; }
        .role-admin { color: var(--accent-primary); }
        .role-faculty { color: var(--success); }
        .schedule-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .schedule-grid th, .schedule-grid td { border: 1px solid var(--border-subtle); padding: 8px; text-align: center; height: 60px; }
        .schedule-grid th { background-color: var(--bg-primary); }
        .schedule-block { background-color: var(--success-bg); border-left: 4px solid var(--success); padding: 4px; border-radius: 4px; font-size: 0.75rem; text-align: left; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal { background: white; border-radius: 8px; width: 100%; max-width: 400px; }
        .modal-header { padding: 16px 24px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: flex-end; gap: 8px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; margin-bottom: 8px; font-size: 0.875rem; font-weight: 500; }
        .form-input { width: 100%; padding: 8px 12px; border: 1px solid var(--border-subtle); border-radius: 4px; }
        .floor-tabs { display: flex; gap: 8px; margin-bottom: 16px; }
        .floor-tab { padding: 8px 16px; border: 1px solid var(--border-subtle); border-radius: 4px; cursor: pointer; background: white; }
        .floor-tab.active { background: var(--accent-primary); color: white; border-color: var(--accent-primary); }
        #aiInsightsContent { line-height: 1.6; }
        #aiInsightsContent h3 { margin-top: 0; }
        .loading { display: flex; align-items: center; justify-content: center; padding: 40px; color: var(--text-secondary); }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                <div>STI Classroom System</div>
                <div style="font-size: 0.75rem; font-weight: normal; color: var(--text-secondary); margin-top: 4px;">STI College Marikina</div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-label">Monitoring</div>
                <div class="nav-item active" data-target="page-overview">Overview</div>
                <a href="/minimap.php" class="nav-item">Campus Map</a>
            </div>

            <div class="nav-section">
                <div class="nav-section-label">Management</div>
                <div class="nav-item" data-target="page-schedules">Schedules</div>
                <div class="nav-item" data-target="page-requests">
                    Room Requests
                    <?php if($pendingRequestsCount > 0): ?>
                        <span class="badge badge-warning"><?= $pendingRequestsCount ?></span>
                    <?php endif; ?>
                </div>
                <div class="nav-item" data-target="page-rooms">Rooms</div>
            </div>

            <div class="nav-section">
                <div class="nav-section-label">System</div>
                <div class="nav-item" data-target="page-users">Users</div>
                <div class="nav-item" data-target="page-ai">AI Insights</div>
            </div>

            <div class="sidebar-footer">
                <div class="avatar">
                    <?= htmlspecialchars(strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1))) ?>
                </div>
                <div style="overflow: hidden;">
                    <div style="font-size: 0.875rem; font-weight: 600; white-space: nowrap; text-overflow: ellipsis;"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: capitalize;"><?= htmlspecialchars($user['role']) ?></div>
                </div>
            </div>
        </aside>

        <div class="main">
            <header class="header">
                <h2 id="header-title" style="margin: 0; font-size: 1.25rem;">Overview</h2>
                <div style="display: flex; gap: 16px; align-items: center;">
                    <input type="text" placeholder="Search..." class="form-input" style="width: 200px;">
                    <a href="/logout.php" class="btn btn-outline">Logout</a>
                </div>
            </header>

            <div class="content">
                <!-- Section 1: Overview -->
                <div id="page-overview" class="module-page active">
                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(37, 68, 122, 0.1); color: var(--accent-primary);">R</div>
                            <div>
                                <div class="stat-value"><?= $totalRooms ?></div>
                                <div class="stat-label">Total Rooms</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--danger-bg); color: var(--danger);">O</div>
                            <div>
                                <div class="stat-value"><?= $occupiedRooms ?></div>
                                <div class="stat-label">Occupied Now</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--warning-bg); color: var(--warning);">P</div>
                            <div>
                                <div class="stat-value"><?= $pendingRequestsCount ?></div>
                                <div class="stat-label">Pending Requests</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--success-bg); color: var(--success);">A</div>
                            <div>
                                <div class="stat-value"><?= $activeSessions ?></div>
                                <div class="stat-label">Active Sessions</div>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                        <div class="card">
                            <div class="card-header">
                                <h3 style="margin: 0; font-size: 1.1rem;">AI Utilization Insights</h3>
                                <button class="btn btn-outline btn-sm" onclick="loadAIInsights(true)">Refresh</button>
                            </div>
                            <div class="card-body">
                                <div id="aiInsightsContent">
                                    <div class="loading">Loading insights...</div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 style="margin: 0; font-size: 1.1rem;">Recent Activity</h3>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <?php if(empty($recentActivity)): ?>
                                    <div style="padding: 24px; color: var(--text-secondary); text-align: center;">No recent activity</div>
                                <?php else: ?>
                                    <table class="data-table">
                                        <tbody>
                                            <?php foreach($recentActivity as $log): ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight: 500;"><?= htmlspecialchars($log['room_name_number']) ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-secondary);"><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></div>
                                                </td>
                                                <td style="text-align: right;">
                                                    <div style="font-size: 0.875rem;"><?= date('h:i A', strtotime($log['actual_start_time'])) ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                                        <?= $log['actual_end_time'] ? 'to ' . date('h:i A', strtotime($log['actual_end_time'])) : '<span class="badge badge-success">Ongoing</span>' ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Schedules -->
                <div id="page-schedules" class="module-page">
                    <div class="card">
                        <div class="card-header">
                            <div style="display: flex; gap: 16px;">
                                <select id="sched-day-filter" class="form-input" style="width: 150px;">
                                    <option value="Mon">Monday</option>
                                    <option value="Tue">Tuesday</option>
                                    <option value="Wed">Wednesday</option>
                                    <option value="Thu">Thursday</option>
                                    <option value="Fri">Friday</option>
                                    <option value="Sat">Saturday</option>
                                </select>
                                <select id="sched-floor-filter" class="form-input" style="width: 150px;">
                                    <option value="all">All Floors</option>
                                    <option value="1">Floor 1</option>
                                    <option value="2">Floor 2</option>
                                    <option value="3">Floor 3</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body" style="overflow-x: auto;">
                            <table class="schedule-grid" id="schedule-table">
                                <thead>
                                    <tr>
                                        <th style="width: 120px;">Room</th>
                                        <th>7:00 - 8:30</th>
                                        <th>8:30 - 10:00</th>
                                        <th>10:00 - 11:30</th>
                                        <th>11:30 - 1:00</th>
                                        <th>1:00 - 2:30</th>
                                        <th>2:30 - 4:00</th>
                                        <th>4:00 - 5:30</th>
                                        <th>5:30 - 7:00</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Populated by JS -->
                                    <tr><td colspan="9" class="loading">Select a day to view schedules...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Room Requests -->
                <div id="page-requests" class="module-page">
                    <div class="card">
                        <div class="card-header">
                            <div class="floor-tabs" id="request-tabs" style="margin: 0;">
                                <div class="floor-tab active" data-filter="Pending">Pending</div>
                                <div class="floor-tab" data-filter="Approved">Approved</div>
                                <div class="floor-tab" data-filter="Declined">Declined</div>
                            </div>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Requested By</th>
                                        <th>Room</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="requests-tbody">
                                    <?php foreach($allRequests as $req): ?>
                                    <tr class="req-row" data-status="<?= htmlspecialchars($req['status']) ?>" style="<?= $req['status'] !== 'Pending' ? 'display:none;' : '' ?>">
                                        <td><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></td>
                                        <td><?= htmlspecialchars($req['room_name_number']) ?></td>
                                        <td><?= htmlspecialchars($req['request_date']) ?></td>
                                        <td><?= date('h:i A', strtotime($req['start_time'])) ?> - <?= date('h:i A', strtotime($req['end_time'])) ?></td>
                                        <td><?= htmlspecialchars($req['purpose']) ?></td>
                                        <td>
                                            <?php
                                                $badgeClass = 'badge-warning';
                                                if($req['status'] === 'Approved') $badgeClass = 'badge-success';
                                                if($req['status'] === 'Declined') $badgeClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($req['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if($req['status'] === 'Pending'): ?>
                                                <button class="btn btn-primary btn-sm" onclick="handleRequest(<?= $req['request_id'] ?>, 'approve')">Approve</button>
                                                <button class="btn btn-danger btn-sm" onclick="handleRequest(<?= $req['request_id'] ?>, 'decline')">Decline</button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Rooms -->
                <div id="page-rooms" class="module-page">
                    <div class="card">
                        <div class="card-body" style="padding: 0;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Room Code</th>
                                        <th>Floor</th>
                                        <th>Type</th>
                                        <th>Capacity</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($rooms as $room): ?>
                                    <tr>
                                        <td style="font-weight: 500;"><?= htmlspecialchars($room['room_name_number']) ?></td>
                                        <td>Level <?= htmlspecialchars($room['floor_level']) ?></td>
                                        <td><?= htmlspecialchars($room['room_type']) ?></td>
                                        <td><?= htmlspecialchars($room['capacity']) ?> pax</td>
                                        <td>
                                            <?php
                                                $badgeClass = 'badge-success';
                                                if($room['current_status'] === 'Occupied') $badgeClass = 'badge-danger';
                                                if($room['current_status'] === 'Reserved') $badgeClass = 'badge-warning';
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($room['current_status']) ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Users -->
                <div id="page-users" class="module-page">
                    <div class="card">
                        <div class="card-header">
                            <h3 style="margin: 0; font-size: 1.1rem;">System Users</h3>
                            <button class="btn btn-primary" onclick="openAddUserModal()">Add Faculty</button>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <table class="data-table" id="users-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($users as $u): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td><span class="role-badge role-<?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars($u['role']) ?></span></td>
                                        <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section 6: AI Insights -->
                <div id="page-ai" class="module-page">
                    <div class="card">
                        <div class="card-header">
                            <h3 style="margin: 0; font-size: 1.1rem;">Full AI Analysis Report</h3>
                            <button class="btn btn-primary" onclick="loadAIInsights(true, 'aiInsightsFull')">Generate New Report</button>
                        </div>
                        <div class="card-body">
                            <div id="aiInsightsFull" style="line-height: 1.6; max-width: 800px;">
                                <div class="loading">Click "Generate New Report" to analyze current classroom utilization data.</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal-overlay" id="addUserModal">
        <div class="modal">
            <div class="modal-header">
                <h3 style="margin: 0;">Add Faculty</h3>
                <button class="btn btn-outline btn-sm" onclick="closeAddUserModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" id="add-fname" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" id="add-lname" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="add-email" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" id="add-pass" class="form-input">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeAddUserModal()">Cancel</button>
                <button class="btn btn-primary" onclick="submitAddUser()">Save Faculty</button>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        // Navigation logic
        document.querySelectorAll('.nav-item[data-target]').forEach(item => {
            item.addEventListener('click', function(e) {
                if(this.getAttribute('href')) return;
                
                document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
                
                const targetId = this.getAttribute('data-target');
                document.querySelectorAll('.module-page').forEach(page => page.classList.remove('active'));
                document.getElementById(targetId).classList.add('active');
                
                document.getElementById('header-title').textContent = this.textContent.replace(/[0-9]/g, '').trim();

                if(targetId === 'page-schedules') loadSchedules();
                if(targetId === 'page-overview' && document.getElementById('aiInsightsContent').innerHTML.includes('Loading')) {
                    loadAIInsights(false);
                }
            });
        });

        // Overview AI Insights
        function loadAIInsights(force = false, targetId = 'aiInsightsContent') {
            const container = document.getElementById(targetId);
            container.innerHTML = '<div class="loading">Loading insights from Gemini...</div>';
            
            fetch('/api/gemini_suggestions.php', { method: 'GET' })
            .then(res => res.text())
            .then(text => {
                // Basic markdown to html
                let html = text
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.*?)\*/g, '<em>$1</em>')
                    .replace(/\n\n/g, '</p><p>')
                    .replace(/\n- (.*?)(?=\n|$)/g, '<li>$1</li>')
                    .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
                
                if(!html.startsWith('<p>')) html = '<p>' + html + '</p>';
                container.innerHTML = html;
            })
            .catch(err => {
                container.innerHTML = '<div style="color: var(--danger);">Failed to load insights.</div>';
            });
        }

        // Initialize overview AI on load
        loadAIInsights(false);

        // Schedules logic
        document.getElementById('sched-day-filter').addEventListener('change', loadSchedules);
        document.getElementById('sched-floor-filter').addEventListener('change', loadSchedules);

        function loadSchedules() {
            const day = document.getElementById('sched-day-filter').value;
            const floor = document.getElementById('sched-floor-filter').value;
            const tbody = document.querySelector('#schedule-table tbody');
            tbody.innerHTML = '<tr><td colspan="9" class="loading">Loading schedules...</td></tr>';

            fetch(`/api/schedules.php?day=${day}&floor=${floor}`)
            .then(res => res.json())
            .then(data => {
                if(!data.success) {
                    tbody.innerHTML = `<tr><td colspan="9">Error loading schedules</td></tr>`;
                    return;
                }
                
                tbody.innerHTML = '';
                const timeSlots = [
                    '07:00:00-08:30:00', '08:30:00-10:00:00', '10:00:00-11:30:00', 
                    '11:30:00-13:00:00', '13:00:00-14:30:00', '14:30:00-16:00:00', 
                    '16:00:00-17:30:00', '17:30:00-19:00:00'
                ];

                if(data.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;">No rooms found for this floor.</td></tr>`;
                    return;
                }

                data.data.forEach(room => {
                    let tr = document.createElement('tr');
                    tr.innerHTML = `<td style="font-weight: 500;">${room.room_name_number}</td>`;
                    
                    timeSlots.forEach(slot => {
                        let cellData = room.schedules.find(s => {
                            return (s.start_time + '-' + s.end_time) === slot; // simplified match
                        });
                        
                        if(cellData) {
                            tr.innerHTML += `<td><div class="schedule-block" title="${cellData.subject_code} - ${cellData.section_name}\n${cellData.faculty_name}">
                                <strong>${cellData.subject_code}</strong><br>
                                ${cellData.section_name}<br>
                                ${cellData.faculty_name}
                            </div></td>`;
                        } else {
                            tr.innerHTML += `<td></td>`;
                        }
                    });
                    tbody.appendChild(tr);
                });
            });
        }

        // Requests logic
        document.querySelectorAll('#request-tabs .floor-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('#request-tabs .floor-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                document.querySelectorAll('.req-row').forEach(row => {
                    if(row.getAttribute('data-status') === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });

        function handleRequest(id, action) {
            if(!confirm(`Are you sure you want to ${action} this request?`)) return;
            
            fetch('/api/room_requests.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: action, request_id: id, csrf_token: csrfToken })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    location.reload(); // Simple reload to reflect changes
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        // Add User Modal
        function openAddUserModal() { document.getElementById('addUserModal').style.display = 'flex'; }
        function closeAddUserModal() { document.getElementById('addUserModal').style.display = 'none'; }
        
        function submitAddUser() {
            const fname = document.getElementById('add-fname').value;
            const lname = document.getElementById('add-lname').value;
            const email = document.getElementById('add-email').value;
            const pass = document.getElementById('add-pass').value;

            if(!fname || !lname || !email || !pass) {
                alert('Please fill all fields');
                return;
            }

            fetch('/api/users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', first_name: fname, last_name: lname, email: email, password: pass, role: 'faculty', csrf_token: csrfToken })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    </script>
</body>
</html>
