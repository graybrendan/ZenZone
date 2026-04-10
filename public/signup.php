<?php ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - ZenZone</title>
</head>
<body>
    <h1>Create Account</h1>

    <form method="POST" action="../api/auth/register.php">
        <label for="full_name">Full Name</label><br>
        <input type="text" id="full_name" name="full_name" required><br><br>

        <label for="email">Email</label><br>
        <input type="email" id="email" name="email" required><br><br>

        <label for="password">Password</label><br>
        <input type="password" id="password" name="password" required><br><br>

        <button type="submit">Create Account</button>
    </form>
</body>
</html>