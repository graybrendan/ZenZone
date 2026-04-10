<?php ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ZenZone</title>
</head>
<body>
    <h1>Login</h1>

    <form method="POST" action="../api/auth/login.php">
        <label for="email">Email</label><br>
        <input type="email" id="email" name="email" required><br><br>

        <label for="password">Password</label><br>
        <input type="password" id="password" name="password" required><br><br>

        <button type="submit">Login</button>
    </form>

    <p><a href="signup.php">Need an account? Sign up</a></p>
</body>
</html>