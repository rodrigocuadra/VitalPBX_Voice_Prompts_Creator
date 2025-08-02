<?php
// generate_password_hash.php
// Simple script to generate a password hash and an SQL INSERT statement.
// Usage: Upload to your server, open it in a browser, enter a password.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (name,email,password_hash,role) VALUES ('Administrador','admin@demo.com','" . addslashes($hash) . "','admin');";

        echo "<h3>Generated hash:</h3><textarea style='width:100%;height:100px;'>" . htmlspecialchars($hash) . "</textarea>";
        echo "<h3>SQL Insert Statement:</h3><textarea style='width:100%;height:120px;'>" . htmlspecialchars($sql) . "</textarea>";
        echo "<p>Copy and paste this SQL into your database to create the admin user.</p>";
    } else {
        echo "<p style='color:red;'>Please enter a password.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Generate Password Hash</title>
</head>
<body>
<h2>Generate Password Hash and SQL Insert</h2>
<form method="post">
    <label>Enter password to hash:</label><br>
    <input type="text" name="password" style="width:300px;" required>
    <button type="submit">Generate</button>
</form>
</body>
</html>