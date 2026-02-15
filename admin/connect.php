<?php
// Helper function for PHP 8.2+ count() compatibility
if (!function_exists('safe_count')) {
    function safe_count($var): int {
        return is_countable($var) ? count($var) : 0;
    }
}

// Enable output buffering
ob_start();

// Show any include errors
error_reporting(E_ALL);

// Try to include config.php and catch any errors
$includeError = '';
try {
    include_once('config.php');
} catch (Throwable $e) {
    $includeError = $e->getMessage();
}

global $db;

// Debug output to browser
$debugOutput = '';

// Debug logging
error_log('[DEBUG] admin/connect.php - Attempting database connection');
$debugOutput .= '[DEBUG] admin/connect.php - Attempting database connection<br>';

if ($includeError) {
    $debugOutput .= '[ERROR] Include error: ' . $includeError . '<br>';
}

$debugOutput .= '[DEBUG] Server: ' . ($database_server ?? 'UNSET') . '<br>';
$debugOutput .= '[DEBUG] Database: ' . ($database_name ?? 'UNSET') . '<br>';
$debugOutput .= '[DEBUG] Username: ' . ($database_username ?? 'UNSET') . '<br>';
$debugOutput .= '[DEBUG] Server Name: ' . ($server_name ?? 'UNSET') . '<br>';

// Try to connect
$db = @mysqli_connect($database_server ?? 'localhost', $database_username ?? '', $database_password ?? '', $database_name ?? '');
if (!$db) {
    $error = mysqli_connect_error();
    error_log('[DEBUG] Database connection FAILED: ' . $error);
    $debugOutput .= '[DEBUG] Database connection FAILED: ' . $error . '<br>';
    echo $debugOutput;
    exit;
}
error_log('[DEBUG] Database connection SUCCESS');
$debugOutput .= '[DEBUG] Database connection SUCCESS<br>';

// Output debug info
echo $debugOutput;

// Exit to show debug - REMOVE THIS AFTER DEBUGGING
exit;

// Continue with normal code below...
$time = time();
$div_img = "<table border='0' cellpadding='0' cellspacing='0' width='550' height='1' background=\"images/divider.gif\"><tr><td></td></tr></table>";

// CONSTANTS
$battlelimit = 50;
$max_gold = 10000000000;
$enable_producers = 0;
$base_inv_max = 15;
$is_firefox=substr_count($_SERVER["HTTP_USER_AGENT"],"Firefox");
$maxalts = 2;
$maxquests = 2;
$maxsocalts = 99;

date_default_timezone_set('America/Los_Angeles');


$id = $_COOKIE['id'] ?? null;
$name = $_COOKIE['name'] ?? null;
$lastname = $_COOKIE['lastname'] ?? null;
$session = $_COOKIE['session'] ?? null;
$mode = $_COOKIE['mode'] ?? null;


if ($session) {
    // Validate session token
    $stmt = mysqli_prepare($db, "SELECT * From Accounts WHERE session_token=? AND session_expires > ?");
    $currentTime = time();
    mysqli_stmt_bind_param($stmt, "si", $session, $currentTime);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $account = mysqli_fetch_assoc($result);
    
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
}else{
    if($skipVerify != 1){
        if (!headers_sent()) {
            $time = time();
            header("Location: $server_name/index2.php?time=$time"); exit;
        }       
    }
}


?>