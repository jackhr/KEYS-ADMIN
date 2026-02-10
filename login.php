<?php

include_once 'includes/env.php';
include_once 'includes/admin-auth.php';
include_once 'includes/connection.php';

if (is_admin_logged_in()) {
    header('Location: /');
    exit;
}

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $login_error = 'Please enter your username and password.';
    } else {
        $stmt = $con->prepare("SELECT id, username, password_hash, role, active FROM admin_users WHERE username = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
            $stmt->close();

            $is_active = $admin && (int) $admin['active'] === 1;

            if ($is_active && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_user'] = [
                    'id' => (int) $admin['id'],
                    'username' => $admin['username'],
                    'role' => $admin['role']
                ];

                $update = $con->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?");
                if ($update) {
                    $update->bind_param('i', $admin['id']);
                    $update->execute();
                    $update->close();
                }

                header('Location: /');
                exit;
            }
        }

        $login_error = 'Invalid credentials.';
    }
}

$title_override = "Admin Login";
$page = "admin";
$description = "Admin login for $company_name.";
$extra_css = "admin";

include_once 'includes/header.php';

?>

<section class="general-header admin-header admin-login-header">
    <h1>Admin Login</h1>
    <p>Sign in to manage the Keys Car Rental fleet.</p>
</section>

<section id="admin-login">
    <div class="inner">
        <div class="admin-card login-card">
            <h2>Welcome Back</h2>
            <p class="login-subtitle">Use your admin credentials to continue.</p>

            <form method="POST" action="/login.php" autocomplete="off">
                <label class="input-container">
                    <h6>Username</h6>
                    <input type="text" name="username" required>
                </label>
                <label class="input-container">
                    <h6>Password</h6>
                    <input type="password" name="password" required>
                </label>

                <?php if ($login_error !== '') { ?>
                    <div class="login-error"><?php echo htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php } ?>

                <button class="continue-btn admin-login-btn" type="submit">Sign In</button>
            </form>
        </div>
    </div>
</section>

<?php include_once 'includes/footer.php'; ?>
