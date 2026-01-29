<?php
include_once('config.php');
global $db;
$db = @mysqli_connect($database_server, $database_username, $database_password, $database_name);
if (!$db) {
    echo "<br><b>Could not connect to the MySQL database. Please try again in a few minutes.</b>";
    exit;
}
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