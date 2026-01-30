<?php
error_reporting(E_ERROR);

// URL
$subfile = "";
$server_name = "http://" . ($_SERVER['SERVER_NAME'] ?? 'localhost');

include("credentials.php");

// Admin password for updating donors
$admin_username = "";
$admin_password = "";


include("connect.php");
// User stuff stored in cookies
$id = $_COOKIE['id'] ?? null;
$name = $_COOKIE['name'] ?? null;
$lastname = $_COOKIE['lastname'] ?? null;
$email = $_COOKIE['email'] ?? null;
$session = $_COOKIE['session'] ?? null;
$mode = $_COOKIE['mode'] ?? null;


if ($session) {
    // Validate session token using prepared statements
    $stmt = mysqli_prepare($db, "SELECT * From Accounts WHERE session_token=? AND session_expires > ?");
    $currentTime = time();
    mysqli_stmt_bind_param($stmt, "si", $session, $currentTime);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $account = mysqli_fetch_array($result);
    
    if ($account) {
        $email = $account['email'];
        
        if ($id) {
            $stmt = mysqli_prepare($db, "SELECT * From Users WHERE email=? AND id=?");
            mysqli_stmt_bind_param($stmt, "si", $email, $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($result) == 0) {
                $id = null;
                $email = null;
                $session = null;
                $name = null;
                $lastname = null;
                setcookie("id", "", time()-3600, "/");
                setcookie("name", "", time()-3600, "/");
                setcookie("lastname", "", time()-3600, "/");
                setcookie("session", "", time()-3600, "/");
                if (!headers_sent()) {
                    $time = time();
                    header("Location: $server_name/index2.php?time=$time"); exit;
                }
            }
        } else {
            if ($skipVerify != 1) {
                $stmt = mysqli_prepare($db, "SELECT * From Users WHERE email=?");
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                // If we don't have any character create one
                if (mysqli_num_rows($result) == 0) {
                    header("Location: $server_name/create.php");
                // If we have some character we just aren't selected select it and go to bio
                } else {
                    $user = mysqli_fetch_array($result);
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
        }
    } else {
        // Invalid or expired session
        $id = null;
        $email = null;
        $session = null;
        $name = null;
        $lastname = null;
        setcookie("id", "", time()-3600, "/");
        setcookie("name", "", time()-3600, "/");
        setcookie("lastname", "", time()-3600, "/");
        setcookie("session", "", time()-3600, "/");
        if (!headers_sent()) {
            $time = time();
            header("Location: $server_name/index2.php?time=$time"); exit;
        }
    }
} else {
    if ($skipVerify != 1) {
        if (!headers_sent()) {
            $time = time();
            header("Location: $server_name/index2.php?time=$time"); exit;
        }       
    }
}
?>
