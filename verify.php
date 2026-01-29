<?php
$skipVerify = 1;
/* get the incoming ID and password hash */
$user = isset($_POST["email"]) ? $_POST["email"] : null;

if ($user === null || empty($user)) {
    // $_POST["userid"] is not valid; perform the redirect to the root URL
    header("Location: https://" . $_SERVER["HTTP_HOST"]);
    exit; // Ensure that no further code is executed after the redirect
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    // Invalid CSRF token - log this attempt and redirect
    error_log("CSRF validation failed on login attempt from IP: " . $_SERVER['REMOTE_ADDR']);
    header("Location: $server_name/login.php?error=csrf");
    exit;
}

/* establish a connection with the database */
include_once("admin/connect.php");


$email = $_POST["email"];
$password = $_POST["pswd"];
$time=time();


// Use prepared statements to prevent SQL injection
$query = "SELECT * FROM Accounts WHERE email = ?";
$stmt = mysqli_prepare($db, $query);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);


// Allow access if a matching record was found and cookies enabled, else deny access.
if (!is_null($result) && $account = mysqli_fetch_array($result))
{
    // Verify password - support both password_hash and legacy sha1
    $storedPassword = $account['password'];
    $validPassword = false;
    
    // Check if it's a modern password_hash (starts with $2y$ or $2a$)
    if (strpos($storedPassword, '$2y$') === 0 || strpos($storedPassword, '$2a$') === 0) {
        $validPassword = password_verify($password, $storedPassword);
    } else {
        // Legacy support for SHA1 hashed passwords
        $validPassword = (sha1($password) === $storedPassword);
    }
    
    if ($validPassword) {
        // Upgrade legacy SHA1 password to password_hash on successful login
        if (strpos($storedPassword, '$2y$') !== 0 && strpos($storedPassword, '$2a$') !== 0) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateQuery = "UPDATE Accounts SET password = ? WHERE email = ?";
            $updateStmt = mysqli_prepare($db, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "ss", $newHash, $email);
            mysqli_stmt_execute($updateStmt);
        }

        $mode = 0;
        if ($_POST["mode"]) $mode = mysqli_real_escape_string($db,$_POST["mode"]);

        // Generate secure session token
        $sessionToken = bin2hex(random_bytes(32));
        
        // Store session token in database
        $updateSessionQuery = "UPDATE Accounts SET session_token = ?, session_expires = ? WHERE email = ?";
        $updateSessionStmt = mysqli_prepare($db, $updateSessionQuery);
        $expires = time() + 3600; // 1 hour expiry
        mysqli_stmt_bind_param($updateSessionStmt, "sis", $sessionToken, $expires, $email);
        mysqli_stmt_execute($updateSessionStmt);
        
        // Set secure cookies with proper flags
        setcookie("session", $sessionToken, [
            'expires' => time() + 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        setcookie("mode", $mode, [
            'expires' => time() + 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);


        $query = "SELECT * FROM Users WHERE email = ?";
        $stmt = mysqli_prepare($db, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);


        if (mysqli_num_rows($result) > 0) {

            $char = mysqli_fetch_array($result);
            $id = $char['id'];
            $user = $char['name'];
            $lastname = $char['lastname'];
            setcookie("id", $id, [
                'expires' => time() + 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            setcookie("name", $user, [
                'expires' => time() + 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            setcookie("lastname", $lastname, [
                'expires' => time() + 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
                
            // Regenerate CSRF token for the new session
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            header("Location: $server_name/bio.php?time=$time");
            exit;
        } else {
            header("Location: $server_name/create.php");
            exit;
        }
    } else {
        // Invalid password - redirect to login with error
        header("Location: $server_name/login.php?error=1");
        exit;
    }
} else {
    // No account found
    header("Location: $server_name/login.php?error=1");
    exit;
}
