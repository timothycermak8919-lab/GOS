<?php
// Enable error display
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if char directory and logo exist before including
if (file_exists('char/logo.png')) {
    include('char/logo.png');
}

echo "<!-- DEBUG: index.php started --><br>";

include("admin/connect.php");

echo "<!-- DEBUG: after connect.php --><br>";

$id = $_COOKIE['id'] ?? null;
$session = $_COOKIE['session'] ?? null;
$time = time();

echo "<!-- DEBUG: session=$session, id=$id --><br>";

// If we have a valid session token
if ($session) {
    // Validate session token using prepared statements
    $stmt = mysqli_prepare($db, "SELECT * From Accounts WHERE session_token=? AND session_expires > ?");
    $currentTime = time();
    mysqli_stmt_bind_param($stmt, "si", $session, $currentTime);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $account = mysqli_fetch_assoc($result);
    
    // If we are logged into an account
    if ($account) {
        $email = $account['email'];
        
        $stmt = mysqli_prepare($db, "SELECT * From Users WHERE email=? AND id=?");
        mysqli_stmt_bind_param($stmt, "si", $email, $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        // If we are logged into a character
        if (mysqli_num_rows($result) != 0) {
            header("Location: $server_name/bio.php?autologin=$time");
        } else {
            $stmt = mysqli_prepare($db, "SELECT * From Users WHERE email=?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            // If we don't have any character create one
            if (mysqli_num_rows($result) == 0) {
                header("Location: $server_name/create.php");
            // If we have some character we just aren't selected select it and go to bio
            } else {
                $user = mysqli_fetch_assoc($result);
                $id = $user['id'];
                $username = $user['name'];
                $name = $user['name'];
                $lastname = $user['lastname'];
                
                setcookie("id", $id, time()+3600, "/");
                setcookie("name", $username, time()+3600, "/");
                setcookie("lastname", $lastname, time()+3600, "/");
                header("Location: $server_name/bio.php");
            }
        }
    } else {
        // Invalid or expired session
        header("Location: $server_name/index2.php?time=$time");
    }
} else {
    header("Location: $server_name/index2.php?time=$time");
}
?>
