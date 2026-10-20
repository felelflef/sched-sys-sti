<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: /admin/dashboard.php');
    } else {
        header('Location: /faculty/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!validateCSRF()) {
        $error = 'Invalid request token.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            
            if ($user['role'] === 'admin') {
                header('Location: /admin/dashboard.php');
            } else {
                header('Location: /faculty/dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$csrf_token = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login &mdash; Scheduling System</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { 
            background: radial-gradient(circle at 10% 10%, rgba(255, 199, 44, 0.15) 0%, transparent 40%),
                        radial-gradient(circle at 90% 90%, rgba(11, 78, 162, 0.12) 0%, transparent 45%),
                        var(--bg-primary); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            margin: 0; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
        }
        .login-page { width: 100%; max-width: 420px; padding: 2rem 1.5rem; }
        .login-brand { text-align: center; margin-bottom: 2rem; }
        .login-badge { display: inline-block; background: var(--accent-yellow); color: #1E293B; font-weight: 700; font-size: 0.75rem; letter-spacing: 0.05em; text-transform: uppercase; padding: 4px 10px; border-radius: 20px; margin-bottom: 0.75rem; }
        .login-brand h1 { font-size: 1.65rem; font-weight: 800; color: var(--accent-primary); margin: 0 0 0.4rem 0; letter-spacing: -0.02em; }
        .login-brand p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }
        .login-card { 
            background: var(--bg-secondary); 
            padding: 2.25rem; 
            border-radius: 12px; 
            box-shadow: 0 12px 36px rgba(11, 78, 162, 0.09); 
            border: 1px solid var(--border-subtle); 
            border-top: 4px solid var(--accent-yellow);
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-primary); font-size: 0.875rem; }
        .form-input { width: 100%; padding: 0.8rem 1rem; border: 1.5px solid var(--border-subtle); border-radius: 8px; font-size: 0.95rem; box-sizing: border-box; transition: all 0.2s ease; background-color: #FAFAFD; }
        .form-input:focus { outline: none; border-color: var(--accent-primary); background-color: #FFFFFF; box-shadow: 0 0 0 3px rgba(11, 78, 162, 0.15); }
        .btn-full { width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 600; border-radius: 8px; box-shadow: 0 4px 14px rgba(11, 78, 162, 0.25); transition: all 0.2s ease; cursor: pointer; }
        .btn-full:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(11, 78, 162, 0.35); }
        .alert-error { background: var(--danger-bg); color: var(--danger); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.875rem; border: 1px solid rgba(220, 38, 38, 0.2); }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-brand">
            <span class="login-badge">Portal</span>
            <h1>Scheduling System</h1>
            <p>Classroom & Schedule Management</p>
        </div>
        
        <div class="login-card">
            <?php if($error): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="/login.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" required autofocus placeholder="juan.delacruz@marikina.sti.edu.ph">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-input" required placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>
