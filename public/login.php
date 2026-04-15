<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

requireGuest();

$authMessage = getAuthPageMessage('login', $_GET);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ZenZone</title>
</head>
<body>
    <h1>Login</h1>

    <?php if ($authMessage !== ''): ?>
        <p><?php echo htmlspecialchars($authMessage); ?></p>
    <?php endif; ?>

    <form method="POST" action="../api/auth/login.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

        <label for="email">Email</label><br>
        <input type="email" id="email" name="email" required><br><br>

        <label for="password">Password</label><br>
        <input type="password" id="password" name="password" required><br><br>

        <button type="submit">Login</button>
    </form>

    <p><a href="signup.php">Need an account? Sign up</a></p>
</body>
</html>
