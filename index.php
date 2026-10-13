<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home &mdash; STI Classroom System</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .hero { text-align: center; padding: 4rem 2rem; background-color: var(--bg-primary); }
        .hero h1 { color: var(--accent-primary); font-size: 2.5rem; margin-bottom: 1rem; }
        .hero p { color: var(--text-secondary); max-width: 600px; margin: 0 auto; font-size: 1.1rem; line-height: 1.6; }
        .gateways { display: flex; gap: 2rem; justify-content: center; padding: 2rem; max-width: 900px; margin: 0 auto; flex-wrap: wrap; }
        .gateway-card { flex: 1; min-width: 300px; background: var(--bg-secondary); border-radius: 8px; padding: 2rem; text-align: center; box-shadow: var(--shadow-float); text-decoration: none; color: inherit; transition: transform 0.2s ease, box-shadow 0.2s ease; border: 1px solid var(--border-subtle); }
        .gateway-card:hover { transform: translateY(-5px); box-shadow: 0 12px 40px rgba(44,48,58,0.08); border-color: var(--accent-primary); }
        .gateway-icon { font-size: 3rem; margin-bottom: 1rem; color: var(--accent-primary); }
        .gateway-card h2 { margin-bottom: 1rem; font-size: 1.5rem; }
        .gateway-card p { color: var(--text-secondary); line-height: 1.5; }
        .public-footer { text-align: center; padding: 2rem; color: var(--text-secondary); font-size: 0.9rem; margin-top: auto; border-top: 1px solid var(--border-subtle); }
        body { display: flex; flex-direction: column; min-height: 100vh; margin: 0; font-family: sans-serif; }
    </style>
</head>
<body>
    <header class="public-header" style="padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border-bottom: 1px solid var(--border-subtle);">
        <div style="font-weight: bold; color: var(--accent-primary); font-size: 1.2rem;">STI College Marikina</div>
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="/<?php echo sanitize($_SESSION['role']); ?>/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
        <?php else: ?>
            <a href="/login.php" class="btn btn-outline">Staff Login</a>
        <?php endif; ?>
    </header>

    <main class="content" style="flex: 1; padding: 0;">
        <section class="hero">
            <h1>Web-Based AI-Assisted Classroom Utilization System</h1>
            <p>A smart monitoring platform for tracking real-time classroom occupancy, managing schedules, and optimizing campus space utilization.</p>
        </section>

        <section class="gateways">
            <a href="/minimap.php" class="gateway-card">
                <div class="gateway-icon">🗺️</div>
                <h2>View Campus Map</h2>
                <p>Check real-time room availability, view daily schedules, and navigate the campus floor plan &mdash; no login required.</p>
            </a>
            
            <a href="/login.php" class="gateway-card">
                <div class="gateway-icon">🔐</div>
                <h2>Staff Login</h2>
                <p>Faculty and administrators: manage sessions, submit room requests, and access the full dashboard.</p>
            </a>
        </section>
    </main>

    <footer class="public-footer">
        &copy; <?php echo date('Y'); ?> STI College Marikina. All rights reserved.
    </footer>
</body>
</html>
