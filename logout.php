<?php
include('admin/connect.php');

$id = null;
$email = null;
$session = null;
$name = null;
$lastname = null;

// Invalidate session token in database
if (isset($_COOKIE['session'])) {
    $sessionToken = $_COOKIE['session'];
    $clearSessionQuery = "UPDATE Accounts SET session_token = NULL, session_expires = NULL WHERE session_token = ?";
    $clearSessionStmt = mysqli_prepare($db, $clearSessionQuery);
    mysqli_stmt_bind_param($clearSessionStmt, "s", $sessionToken);
    mysqli_stmt_execute($clearSessionStmt);
}

setcookie("id", "", time()-3600, "/");
setcookie("name", "", time()-3600, "/");
setcookie("lastname", "", time()-3600, "/");
setcookie("session", "", time()-3600, "/");
setcookie("mode", "", time()-3600, "/");


header("Location: $server_name/index2.php"); exit;
?>