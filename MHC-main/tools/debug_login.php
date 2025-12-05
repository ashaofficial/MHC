<?php
/**
 * Debug script to test login flow step by step
 * Check database, credentials, token generation, and cookies
 */

ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);

include_once __DIR__ . '/../secure/config.php';
include_once __DIR__ . '/../secure/db.php';
include_once __DIR__ . '/../secure/jwt.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f9f9f9; }
        .section { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff; border-radius: 4px; }
        .section h3 { margin-top: 0; color: #007bff; }
        .success { color: green; background: #e8f5e9; padding: 10px; border-radius: 3px; }
        .error { color: red; background: #ffebee; padding: 10px; border-radius: 3px; }
        .warning { color: orange; background: #fff3e0; padding: 10px; border-radius: 3px; }
        .info { color: #1976d2; background: #e3f2fd; padding: 10px; border-radius: 3px; }
        code { background: #f5f5f5; padding: 2px 5px; border-radius: 3px; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>

<h1>🔍 Login System Debug</h1>

<div class="section">
    <h3>1. Database Connection</h3>
    <?php
    if ($conn) {
        echo "<div class='success'>✓ Connected to database: <code>" . $conn->select_db("pms") . "</code></div>";
        echo "<p>Host: <code>localhost</code>, User: <code>root</code>, Database: <code>pms</code></p>";
    } else {
        echo "<div class='error'>✗ Connection failed: " . mysqli_connect_error() . "</div>";
    }
    ?>
</div>

<div class="section">
    <h3>2. Configuration Check</h3>
    <?php
    echo "<table>";
    echo "<tr><th>Setting</th><th>Value</th></tr>";
    echo "<tr><td>JWT_SECRET</td><td><code>" . substr($JWT_SECRET, 0, 30) . "...</code></td></tr>";
    echo "<tr><td>use_https</td><td><code>" . ($use_https ? 'true' : 'false') . "</code></td></tr>";
    echo "</table>";
    ?>
</div>

<div class="section">
    <h3>3. Users & Credentials in Database</h3>
    <?php
    $query = "SELECT u.id, u.name, c.username, c.password_hash, r.role_name
              FROM users u
              LEFT JOIN credential c ON u.id = c.user_id
              LEFT JOIN roles r ON u.role_id = r.id
              ORDER BY u.id";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Username</th><th>Password Hash (first 40 chars)</th><th>Role</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $hash_preview = isset($row['password_hash']) ? substr($row['password_hash'], 0, 40) . '...' : 'N/A';
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
            echo "<td><code>" . $hash_preview . "</code></td>";
            echo "<td>" . htmlspecialchars($row['role_name']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='error'>✗ No users found or query error: " . $conn->error . "</div>";
    }
    ?>
</div>

<div class="section">
    <h3>4. Test Login Simulation</h3>
    <?php
    // Simulate login attempt
    $test_username = 'admin';
    $test_password = 'admin@123';
    
    echo "<p>Testing login with: <code>$test_username</code> / <code>$test_password</code></p>";
    
    $sql = "SELECT c.id AS cred_id, c.user_id, c.password_hash, u.name AS user_name, u.id AS user_pk, r.role_name
            FROM credential c
            JOIN users u ON c.user_id = u.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE c.username = ? LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo "<div class='error'>✗ Query prepare failed: " . $conn->error . "</div>";
    } else {
        $stmt->bind_param("s", $test_username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            echo "<div class='error'>✗ User not found in database</div>";
        } else {
            echo "<div class='success'>✓ User found: " . htmlspecialchars($user['user_name']) . " (" . $user['role_name'] . ")</div>";
            
            // Test password verification
            $stored_hash = $user['password_hash'];
            echo "<p><strong>Password Hash Check:</strong></p>";
            echo "<code>" . $stored_hash . "</code><br>";
            
            if (preg_match('/^\$2[ayb]\$|^\$argon2/', $stored_hash)) {
                echo "<div class='info'>Hash format: Bcrypt/Argon (valid)</div>";
                $verified = password_verify($test_password, $stored_hash);
                if ($verified) {
                    echo "<div class='success'>✓ Password verification: PASSED</div>";
                    
                    // Test JWT generation
                    echo "<p><strong>JWT Token Generation:</strong></p>";
                    $payload = [
                        "user_id" => (int)$user["user_id"],
                        "username" => $test_username,
                        "role" => $user["role_name"] ?? null,
                        "exp" => time() + 3600
                    ];
                    $token = generateJWT($payload);
                    echo "<div class='success'>✓ Token generated</div>";
                    echo "<p><strong>Token:</strong></p>";
                    echo "<code style='word-break: break-all;'>" . $token . "</code>";
                    
                } else {
                    echo "<div class='error'>✗ Password verification: FAILED</div>";
                    echo "<p>The stored hash doesn't match the test password. Please run <code>create_test_user.php</code>.</p>";
                }
            } else {
                echo "<div class='warning'>⚠ Hash format: Not bcrypt/argon (may be plain text)</div>";
                if ($stored_hash === $test_password) {
                    echo "<div class='warning'>⚠ Hash matches plain password - will be hashed on next login</div>";
                } else {
                    echo "<div class='error'>✗ Hash does not match plain password</div>";
                }
            }
        }
    }
    ?>
</div>

<div class="section">
    <h3>5. Next Steps</h3>
    <ol>
        <li>If users are missing: <a href="create_test_user.php">Run create_test_user.php</a></li>
        <li>After fixing passwords, try logging in: <a href="/auth/login.html">Go to Login Page</a></li>
        <li>Check browser cookies in DevTools (F12 → Application → Cookies)</li>
        <li>If still stuck, run this debug page again to verify the fix</li>
    </ol>
</div>

</body>
</html>
<?php
?>
