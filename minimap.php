<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Minimap &mdash; STI Classroom System</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { margin: 0; font-family: sans-serif; background-color: var(--bg-primary); }
        .minimap-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .floor-tabs { display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid var(--border-subtle); padding-bottom: 1rem; }
        .floor-tab { padding: 0.75rem 1.5rem; background: var(--bg-secondary); border: 1px solid var(--border-subtle); border-radius: 4px; cursor: pointer; font-weight: bold; color: var(--text-secondary); transition: all 0.2s ease; }
        .floor-tab.active { background: var(--accent-primary); color: var(--text-on-accent); border-color: var(--accent-primary); }
        .floor-canvas { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; display: none; }
        .floor-canvas.active { display: grid; }
        
        .room-cell { background: var(--bg-secondary); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 1.5rem; text-align: center; cursor: pointer; position: relative; transition: transform 0.2s ease, box-shadow 0.2s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .room-cell:hover { transform: translateY(-3px); box-shadow: var(--shadow-float); }
        
        .room-cell .room-name { font-size: 1.25rem; font-weight: bold; margin-bottom: 0.5rem; color: var(--text-primary); }
        .room-cell .room-meta { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem; }
        .status-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 0.5rem; }
        .room-status-label { font-weight: bold; font-size: 0.9rem; }
        
        .room-cell.vacant { border-color: var(--success); }
        .room-cell.vacant .status-dot { background-color: var(--success); }
        .room-cell.vacant .room-status-label { color: var(--success); }
        
        .room-cell.occupied { border-color: var(--danger); }
        .room-cell.occupied .status-dot { background-color: var(--danger); }
        .room-cell.occupied .room-status-label { color: var(--danger); }
        
        .room-cell.reserved { border-color: var(--warning); }
        .room-cell.reserved .status-dot { background-color: var(--warning); }
        .room-cell.reserved .room-status-label { color: var(--warning); }
        
        .legend-bar { display: flex; justify-content: center; gap: 2rem; margin-top: 3rem; padding: 1rem; background: var(--bg-secondary); border-radius: 8px; border: 1px solid var(--border-subtle); }
        .legend-item { display: flex; align-items: center; gap: 0.5rem; font-weight: bold; color: var(--text-secondary); }
        
        .stats-row { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        .stat-card { flex: 1; min-width: 150px; background: var(--bg-secondary); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-subtle); display: flex; align-items: center; gap: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .stat-card .stat-value { font-size: 1.5rem; font-weight: bold; }
        .stat-card .stat-label { font-size: 0.85rem; color: var(--text-secondary); }
        
        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
        .modal-overlay.show { opacity: 1; pointer-events: auto; }
        .modal { background: var(--bg-secondary); border-radius: 8px; width: 90%; max-width: 600px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); transform: translateY(-20px); transition: transform 0.3s ease; display: flex; flex-direction: column; max-height: 90vh; }
        .modal-overlay.show .modal { transform: translateY(0); }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 1.25rem; color: var(--text-primary); }
        .close-modal { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary); }
        .modal-body { padding: 1.5rem; overflow-y: auto; }
        
        .public-header { padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border-bottom: 1px solid var(--border-subtle); }
    </style>
</head>
<body>
    <header class="public-header">
        <a href="/index.php" style="text-decoration: none; font-weight: bold; color: var(--accent-primary); font-size: 1.2rem;">STI College Marikina &mdash; Campus Map</a>
        <a href="/login.php" class="btn btn-outline">Staff Login</a>
    </header>

    <div class="minimap-container">
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value" id="stat-vacant" style="color: var(--success);">0</div>
                <div class="stat-label">Vacant Rooms</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-occupied" style="color: var(--danger);">0</div>
                <div class="stat-label">Occupied Rooms</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-reserved" style="color: var(--warning);">0</div>
                <div class="stat-label">Reserved Rooms</div>
            </div>
        </div>

        <div class="floor-tabs">
            <div class="floor-tab active" data-floor="1">Floor 1</div>
            <div class="floor-tab" data-floor="2">Floor 2</div>
            <div class="floor-tab" data-floor="3">Floor 3</div>
        </div>

        <div id="floor-1" class="floor-canvas active"></div>
        <div id="floor-2" class="floor-canvas"></div>
        <div id="floor-3" class="floor-canvas"></div>

        <div class="legend-bar">
            <div class="legend-item"><span class="status-dot" style="background: var(--success);"></span> Vacant</div>
            <div class="legend-item"><span class="status-dot" style="background: var(--danger);"></span> Occupied</div>
            <div class="legend-item"><span class="status-dot" style="background: var(--warning);"></span> Reserved</div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal-overlay" id="roomModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalRoomTitle">Room Schedule</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <h4 style="margin-top:0; margin-bottom:1rem;" id="modalDateLabel"></h4>
                <div id="modalScheduleContent">Loading schedule...</div>
            </div>
        </div>
    </div>

    <script>
        const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        const todayStr = days[new Date().getDay()];

        // Tab switching
        document.querySelectorAll('.floor-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.floor-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.floor-canvas').forEach(c => c.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById('floor-' + tab.dataset.floor).classList.add('active');
            });
        });

        function fetchRoomStatus() {
            fetch('/api/room_status.php')
                .then(res => res.json())
                .then(res => {
                    if(res.success) {
                        updateMap(res.data);
                    }
                })
                .catch(err => console.error('Error fetching room status:', err));
        }

        function updateMap(groupedData) {
            let counts = { Vacant: 0, Occupied: 0, Reserved: 0 };
            
            for(let floor = 1; floor <= 3; floor++) {
                const canvas = document.getElementById('floor-' + floor);
                if(!canvas) continue;
                
                const rooms = groupedData[floor] || [];
                let html = '';
                
                rooms.forEach(room => {
                    counts[room.current_status] = (counts[room.current_status] || 0) + 1;
                    const statusClass = room.current_status.toLowerCase();
                    
                    html += \`
                        <div class="room-cell \${statusClass}" onclick="openModal(\${room.room_id}, '\${room.room_name_number}')">
                            <div class="room-name">\${room.room_name_number}</div>
                            <div class="room-meta">\${room.room_type} &bull; Cap: \${room.capacity}</div>
                            <div>
                                <span class="status-dot"></span>
                                <span class="room-status-label">\${room.current_status}</span>
                            </div>
                        </div>
                    \`;
                });
                
                canvas.innerHTML = html;
            }
            
            document.getElementById('stat-vacant').textContent = counts.Vacant || 0;
            document.getElementById('stat-occupied').textContent = counts.Occupied || 0;
            document.getElementById('stat-reserved').textContent = counts.Reserved || 0;
        }

        function openModal(roomId, roomName) {
            document.getElementById('modalRoomTitle').textContent = \`Schedule: \${roomName}\`;
            document.getElementById('modalDateLabel').textContent = \`Today (\${todayStr})\`;
            document.getElementById('modalScheduleContent').innerHTML = '<p>Loading...</p>';
            document.getElementById('roomModal').classList.add('show');
            
            fetch(\`/api/schedules.php?day=\${todayStr}&room_id=\${roomId}\`)
                .then(res => res.json())
                .then(res => {
                    if(res.success) {
                        renderScheduleTable(res.data);
                    } else {
                        document.getElementById('modalScheduleContent').innerHTML = \`<p style="color:var(--danger)">Error: \${res.error}</p>\`;
                    }
                })
                .catch(err => {
                    document.getElementById('modalScheduleContent').innerHTML = '<p style="color:var(--danger)">Failed to load schedule.</p>';
                });
        }

        function renderScheduleTable(schedules) {
            const content = document.getElementById('modalScheduleContent');
            if(schedules.length === 0) {
                content.innerHTML = '<p style="color:var(--text-secondary); text-align:center; padding: 2rem 0;">No classes scheduled for today.</p>';
                return;
            }
            
            let html = \`
                <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-subtle);">
                            <th style="padding: 0.75rem 0.5rem; color: var(--text-secondary);">Time</th>
                            <th style="padding: 0.75rem 0.5rem; color: var(--text-secondary);">Subject / Section</th>
                            <th style="padding: 0.75rem 0.5rem; color: var(--text-secondary);">Faculty</th>
                        </tr>
                    </thead>
                    <tbody>
            \`;
            
            schedules.forEach(s => {
                const timeStart = s.start_time.substring(0,5);
                const timeEnd = s.end_time.substring(0,5);
                html += \`
                    <tr style="border-bottom: 1px solid var(--border-subtle);">
                        <td style="padding: 0.75rem 0.5rem; white-space: nowrap;">\${timeStart} - \${timeEnd}</td>
                        <td style="padding: 0.75rem 0.5rem;"><strong>\${s.subject_code}</strong><br><small style="color:var(--text-secondary)">\${s.section_name}</small></td>
                        <td style="padding: 0.75rem 0.5rem;">\${s.first_name} \${s.last_name}</td>
                    </tr>
                \`;
            });
            
            html += \`</tbody></table>\`;
            content.innerHTML = html;
        }

        function closeModal() {
            document.getElementById('roomModal').classList.remove('show');
        }

        // Close modal on outside click
        document.getElementById('roomModal').addEventListener('click', (e) => {
            if(e.target === document.getElementById('roomModal')) {
                closeModal();
            }
        });

        // Init and Poll
        fetchRoomStatus();
        setInterval(fetchRoomStatus, 10000); // Poll every 10 seconds
    </script>
</body>
</html>
