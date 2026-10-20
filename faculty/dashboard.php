<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requireRole('faculty');
$user = currentUser();
$db = getDB();

$csrf_token = generateCSRF();

$todayName = date('D'); // Mon, Tue, etc.
$currentDate = date('Y-m-d');

// Fetch today's schedules
$stmt = $db->prepare("SELECT s.*, c.room_name_number, c.current_status 
                      FROM schedules s
                      JOIN classrooms c ON s.room_id = c.room_id
                      WHERE s.faculty_id = ? AND s.day_of_week = ?
                      ORDER BY s.start_time");
$stmt->execute([$user['user_id'], $todayName]);
$todaySchedules = $stmt->fetchAll();

// Fetch active sessions
$stmt = $db->prepare("SELECT sl.*, c.room_name_number, c.current_status 
                      FROM session_logs sl
                      JOIN classrooms c ON sl.room_id = c.room_id
                      WHERE sl.faculty_id = ? AND sl.actual_end_time IS NULL");
$stmt->execute([$user['user_id']]);
$activeSessions = $stmt->fetchAll();

// Fetch room requests
$stmt = $db->prepare("SELECT rr.*, c.room_name_number 
                      FROM room_requests rr
                      JOIN classrooms c ON rr.room_id = c.room_id
                      WHERE rr.faculty_id = ?
                      ORDER BY rr.created_at DESC");
$stmt->execute([$user['user_id']]);
$requests = $stmt->fetchAll();

// Fetch all rooms for dropdown
$stmt = $db->query("SELECT room_id, room_name_number FROM classrooms ORDER BY floor_level, room_name_number");
$allRooms = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    <title>Faculty Dashboard — Scheduling System</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .module-page { display: none; }
        .module-page.active { display: block; }
        .sidebar { width: 250px; background-color: var(--bg-secondary); border-right: 1px solid var(--border-subtle); display: flex; flex-direction: column; height: 100vh; position: fixed; box-shadow: 2px 0 16px rgba(11, 78, 162, 0.03); z-index: 10; }
        .main { margin-left: 250px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; background-color: var(--bg-primary); }
        .header { height: 64px; background-color: var(--bg-secondary); border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; padding: 0 24px; box-shadow: 0 2px 10px rgba(11, 78, 162, 0.03); }
        .content { padding: 24px; overflow-y: auto; flex: 1; }
        .brand { padding: 20px 24px; text-align: center; font-weight: 800; font-size: 1.15rem; color: var(--accent-primary); border-bottom: 1px solid var(--border-subtle); background: linear-gradient(180deg, #FFFFFF 0%, #F8FAFD 100%); }
        .brand-badge { display: inline-block; font-size: 0.7rem; font-weight: 700; color: #1E293B; background: var(--accent-yellow); padding: 2px 8px; border-radius: 12px; margin-top: 4px; letter-spacing: 0.03em; }
        .nav-section { padding: 12px 0; }
        .nav-item { padding: 10px 20px; margin: 2px 12px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; color: var(--text-primary); text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: all 0.2s ease; }
        .nav-item:hover { background-color: rgba(11, 78, 162, 0.05); color: var(--accent-primary); }
        .nav-item.active { background-color: rgba(11, 78, 162, 0.09); color: var(--accent-primary); font-weight: 700; border-right: 4px solid var(--accent-yellow); }
        .sidebar-footer { margin-top: auto; padding: 20px 24px; border-top: 1px solid var(--border-subtle); display: flex; align-items: center; gap: 12px; background: #FAFAFD; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--accent-primary), var(--accent-hover)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; box-shadow: 0 0 0 2px var(--accent-yellow); }
        
        .card { background: white; border-radius: 12px; border: 1px solid var(--border-subtle); box-shadow: 0 4px 16px rgba(11, 78, 162, 0.04); margin-bottom: 24px; }
        .card-header { padding: 16px 24px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 24px; }
        
        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; font-size: 0.875rem; transition: all 0.2s ease; }
        .btn-primary { background-color: var(--accent-primary); color: white; box-shadow: 0 2px 8px rgba(11, 78, 162, 0.25); }
        .btn-primary:hover { background-color: var(--accent-hover); box-shadow: 0 4px 14px rgba(11, 78, 162, 0.35); transform: translateY(-1px); }
        .btn-outline { background-color: transparent; border: 1px solid var(--border-subtle); color: var(--text-primary); }
        .btn-outline:hover { border-color: var(--accent-primary); color: var(--accent-primary); background-color: rgba(11, 78, 162, 0.04); }
        
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .badge-success { background-color: var(--success-bg); color: var(--success); }
        .badge-warning { background-color: var(--accent-yellow-bg); color: #B45309; border: 1px solid var(--accent-yellow-border); }
        .badge-danger { background-color: var(--danger-bg); color: var(--danger); }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 14px 18px; text-align: left; border-bottom: 1px solid var(--border-subtle); }
        .data-table th { background-color: #F8FAFD; color: var(--text-secondary); font-weight: 700; font-size: 0.825rem; }
        .data-table tbody tr:hover { background-color: #F8FAFD; }
        
        .session-card { display: flex; align-items: center; justify-content: space-between; padding: 22px; border: 1.5px solid var(--border-subtle); border-radius: 12px; margin-bottom: 16px; transition: all 0.2s ease; background: #FFFFFF; }
        .session-card:hover { border-color: var(--accent-primary); box-shadow: 0 6px 20px rgba(11, 78, 162, 0.07); }
        .session-btn { padding: 12px 24px; font-size: 0.95rem; border-radius: 8px; cursor: pointer; font-weight: 700; transition: all 0.2s ease; border: 2px solid; }
        .session-btn.inactive { background: white; color: var(--success); border-color: var(--success); box-shadow: 0 2px 8px rgba(22, 163, 74, 0.15); }
        .session-btn.inactive:hover { background: var(--success-bg); transform: translateY(-1px); }
        .session-btn.active { background: var(--danger); color: white; border-color: var(--danger); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25); }
        .session-btn.active:hover { background: #B91C1C; border-color: #B91C1C; transform: translateY(-1px); }
        
        .timer-display { font-family: monospace; font-size: 1.5rem; font-weight: 800; color: var(--accent-primary); margin-top: 6px; }

        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(2px); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal { background: white; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); overflow: hidden; }
        .modal-header { padding: 18px 24px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; background: #F8FAFD; }
        .modal-body { padding: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; margin-bottom: 6px; font-size: 0.85rem; font-weight: 600; color: var(--text-primary); }
        .form-input { width: 100%; padding: 8px 12px; border: 1.5px solid var(--border-subtle); border-radius: 6px; font-size: 0.9rem; box-sizing: border-box; transition: all 0.2s; }
        .form-input:focus { outline: none; border-color: var(--accent-primary); box-shadow: 0 0 0 3px rgba(11, 78, 162, 0.12); }
        
        .sched-card { border: 1.5px solid var(--border-subtle); border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s ease; background: white; }
        .sched-card:hover { border-color: var(--accent-primary); transform: translateY(-2px); box-shadow: 0 6px 18px rgba(11, 78, 162, 0.08); }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                <div>Scheduling System</div>
                <span class="brand-badge">Faculty Portal</span>
            </div>
            
            <div class="nav-section">
                <div class="nav-item active" data-target="page-schedule">My Schedule Today</div>
                <div class="nav-item" data-target="page-sessions">Session Control</div>
                <div class="nav-item" data-target="page-requests">Room Requests</div>
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
                <h2 id="header-title" style="margin: 0; font-size: 1.25rem;">My Schedule Today</h2>
                <a href="/logout.php" class="btn btn-outline">Logout</a>
            </header>

            <div class="content">
                <!-- Section 1: My Schedule -->
                <div id="page-schedule" class="module-page active">
                    <div class="card">
                        <div class="card-header">
                            <h3 style="margin: 0; font-size: 1.1rem;"><?= date('l, F j, Y') ?></h3>
                        </div>
                        <div class="card-body">
                            <?php if(empty($todaySchedules)): ?>
                                <div style="text-align: center; color: var(--text-secondary); padding: 40px 0;">
                                    You have no scheduled classes today.
                                </div>
                            <?php else: ?>
                                <?php foreach($todaySchedules as $sched): ?>
                                <div class="sched-card">
                                    <div>
                                        <div style="font-size: 1.1rem; font-weight: bold; color: var(--accent-primary); margin-bottom: 4px;">
                                            <?= htmlspecialchars($sched['room_name_number']) ?>
                                        </div>
                                        <div style="color: var(--text-primary); font-weight: 500;">
                                            <?= htmlspecialchars($sched['subject_code']) ?> - <?= htmlspecialchars($sched['section_name']) ?>
                                        </div>
                                        <div style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 8px;">
                                            🕒 <?= date('h:i A', strtotime($sched['start_time'])) ?> - <?= date('h:i A', strtotime($sched['end_time'])) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php
                                            $badgeClass = 'badge-success';
                                            if($sched['current_status'] === 'Occupied') $badgeClass = 'badge-danger';
                                            if($sched['current_status'] === 'Reserved') $badgeClass = 'badge-warning';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>" style="font-size: 0.875rem; padding: 6px 12px;"><?= htmlspecialchars($sched['current_status']) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Session Control -->
                <div id="page-sessions" class="module-page">
                    <div class="card">
                        <div class="card-header">
                            <h3 style="margin: 0; font-size: 1.1rem;">Active & Upcoming Sessions</h3>
                            <div style="font-size: 0.875rem; color: var(--text-secondary);">Toggle session status when you enter or leave a room</div>
                        </div>
                        <div class="card-body">
                            <?php
                                // Combine rooms from today's schedule + active sessions to unique list
                                $assignedRooms = [];
                                foreach($todaySchedules as $ts) $assignedRooms[$ts['room_id']] = $ts;
                                foreach($activeSessions as $as) {
                                    if(!isset($assignedRooms[$as['room_id']])) {
                                        $assignedRooms[$as['room_id']] = ['room_id' => $as['room_id'], 'room_name_number' => $as['room_name_number']];
                                    }
                                }
                            ?>
                            
                            <?php if(empty($assignedRooms)): ?>
                                <div style="text-align: center; color: var(--text-secondary); padding: 40px 0;">
                                    No rooms assigned for today.
                                </div>
                            <?php else: ?>
                                <?php foreach($assignedRooms as $room_id => $roomData): ?>
                                    <?php 
                                        // Find if this room has an active session
                                        $activeSession = null;
                                        foreach($activeSessions as $as) {
                                            if($as['room_id'] == $room_id) {
                                                $activeSession = $as;
                                                break;
                                            }
                                        }
                                        $isBtnActive = $activeSession ? true : false;
                                        $btnText = $isBtnActive ? 'Stop Session' : 'Start Session';
                                        $btnClass = $isBtnActive ? 'active' : 'inactive';
                                        $startTimeStr = $activeSession ? $activeSession['actual_start_time'] : '';
                                    ?>
                                    <div class="session-card" data-room-id="<?= $room_id ?>">
                                        <div>
                                            <div style="font-size: 1.25rem; font-weight: bold; margin-bottom: 4px;"><?= htmlspecialchars($roomData['room_name_number']) ?></div>
                                            <div class="timer-container" style="<?= $isBtnActive ? '' : 'display:none;' ?>">
                                                Elapsed: <div class="timer-display" data-start="<?= $startTimeStr ?>">00:00:00</div>
                                            </div>
                                        </div>
                                        <div>
                                            <button class="session-btn <?= $btnClass ?>" onclick="toggleSession(<?= $room_id ?>, this)">
                                                <?= $btnText ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Room Requests -->
                <div id="page-requests" class="module-page">
                    <div class="card">
                        <div class="card-header">
                            <h3 style="margin: 0; font-size: 1.1rem;">My Room Requests</h3>
                            <button class="btn btn-primary" onclick="openRequestModal()">New Request</button>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <?php if(empty($requests)): ?>
                                <div style="padding: 24px; text-align: center; color: var(--text-secondary);">You have not made any room requests.</div>
                            <?php else: ?>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Room</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($requests as $req): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($req['room_name_number']) ?></td>
                                            <td><?= date('M d, Y', strtotime($req['request_date'])) ?></td>
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
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Modal -->
    <div class="modal-overlay" id="requestModal">
        <div class="modal">
            <div class="modal-header">
                <h3 style="margin: 0;">Request a Room</h3>
                <button class="btn btn-outline btn-sm" onclick="closeRequestModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Room</label>
                    <select id="req-room" class="form-input">
                        <option value="">-- Select Room --</option>
                        <?php foreach($allRooms as $r): ?>
                            <option value="<?= $r['room_id'] ?>"><?= htmlspecialchars($r['room_name_number']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" id="req-date" class="form-input" min="<?= $currentDate ?>">
                </div>
                <div style="display: flex; gap: 16px;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Start Time</label>
                        <input type="time" id="req-start" class="form-input">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">End Time</label>
                        <input type="time" id="req-end" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Purpose</label>
                    <textarea id="req-purpose" class="form-input" rows="3"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                    <button class="btn btn-outline" onclick="closeRequestModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="submitRequest()">Submit Request</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        // Navigation logic
        document.querySelectorAll('.nav-item[data-target]').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
                
                const targetId = this.getAttribute('data-target');
                document.querySelectorAll('.module-page').forEach(page => page.classList.remove('active'));
                document.getElementById(targetId).classList.add('active');
                
                document.getElementById('header-title').textContent = this.textContent;
            });
        });

        // Request Modal logic
        function openRequestModal() { document.getElementById('requestModal').style.display = 'flex'; }
        function closeRequestModal() { document.getElementById('requestModal').style.display = 'none'; }
        
        function submitRequest() {
            const roomId = document.getElementById('req-room').value;
            const rDate = document.getElementById('req-date').value;
            const rStart = document.getElementById('req-start').value;
            const rEnd = document.getElementById('req-end').value;
            const purpose = document.getElementById('req-purpose').value;

            if(!roomId || !rDate || !rStart || !rEnd || !purpose) {
                alert('Please fill all fields');
                return;
            }

            fetch('/api/room_requests.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'submit', 
                    room_id: roomId, 
                    request_date: rDate, 
                    start_time: rStart, 
                    end_time: rEnd, 
                    purpose: purpose, 
                    csrf_token: csrfToken 
                })
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

        // Timer and Session logic
        function updateTimers() {
            const now = new Date();
            document.querySelectorAll('.timer-display').forEach(display => {
                const startStr = display.getAttribute('data-start');
                if(!startStr) return;
                
                // Convert MySQL datetime string (e.g. "2023-10-25 08:00:00") to JS Date
                // Basic cross-browser robust parsing: replace - with /
                const startDate = new Date(startStr.replace(/-/g, '/'));
                if(isNaN(startDate.getTime())) return;

                const diff = Math.floor((now - startDate) / 1000);
                if(diff < 0) return;

                const hrs = Math.floor(diff / 3600);
                const mins = Math.floor((diff % 3600) / 60);
                const secs = diff % 60;

                display.textContent = 
                    String(hrs).padStart(2, '0') + ':' + 
                    String(mins).padStart(2, '0') + ':' + 
                    String(secs).padStart(2, '0');
            });
        }

        setInterval(updateTimers, 1000);
        updateTimers();

        function toggleSession(roomId, btn) {
            const isCurrentlyActive = btn.classList.contains('active');
            const action = isCurrentlyActive ? 'stop' : 'start';
            
            btn.disabled = true;
            btn.textContent = 'Processing...';

            fetch('/api/toggle_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ room_id: roomId, action: action, csrf_token: csrfToken })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const card = btn.closest('.session-card');
                    const timerContainer = card.querySelector('.timer-container');
                    const timerDisplay = card.querySelector('.timer-display');

                    if(action === 'start') {
                        btn.classList.remove('inactive');
                        btn.classList.add('active');
                        btn.textContent = 'Stop Session';
                        
                        // Set start time from response
                        timerDisplay.setAttribute('data-start', data.data.actual_start_time);
                        timerContainer.style.display = 'block';
                    } else {
                        btn.classList.remove('active');
                        btn.classList.add('inactive');
                        btn.textContent = 'Start Session';
                        
                        timerDisplay.removeAttribute('data-start');
                        timerDisplay.textContent = '00:00:00';
                        timerContainer.style.display = 'none';
                    }
                } else {
                    alert('Error: ' + data.message);
                    // Revert button text
                    btn.textContent = isCurrentlyActive ? 'Stop Session' : 'Start Session';
                }
            })
            .catch(err => {
                alert('Network error occurred.');
                btn.textContent = isCurrentlyActive ? 'Stop Session' : 'Start Session';
            })
            .finally(() => {
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>
