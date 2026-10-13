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
    <title>Login &mdash; STI Classroom System</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { background-color: var(--bg-primary); display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; font-family: sans-serif; }
        .login-page { width: 100%; max-width: 400px; padding: 2rem; }
        .login-brand { text-align: center; margin-bottom: 2rem; color: var(--accent-primary); }
        .login-brand h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .login-brand p { color: var(--text-secondary); font-size: 0.9rem; }
        .login-card { background: var(--bg-secondary); padding: 2rem; border-radius: 8px; box-shadow: var(--shadow-float); border: 1px solid var(--border-subtle); }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: bold; color: var(--text-primary); font-size: 0.9rem; }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid var(--border-subtle); border-radius: 4px; font-size: 1rem; box-sizing: border-box; }
        .form-input:focus { outline: none; border-color: var(--accent-primary); box-shadow: 0 0 0 3px rgba(37,68,122,0.1); }
        .btn-full { width: 100%; padding: 0.75rem; font-size: 1rem; }
        .alert-error { background: var(--danger-bg); color: var(--danger); padding: 0.75rem; border-radius: 4px; margin-bottom: 1.5rem; font-size: 0.9rem; border: 1px solid rgba(192,57,43,0.2); }
        .back-link { display: block; text-align: center; margin-top: 1.5rem; color: var(--text-secondary); text-decoration: none; font-size: 0.9rem; }
        .back-link:hover { color: var(--accent-primary); text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-brand">
            <h1>STI College Marikina</h1>
            <p>Classroom Utilization System</p>
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
        
        <a href="/index.php" class="back-link">&larr; Back to Home</a>
    </div>
</body>
</html>
