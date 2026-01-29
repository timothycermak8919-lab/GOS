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
$email  = $_COOKIE['email'] ?? null;
$password = $_COOKIE['password'] ?? null;
$mode = $_COOKIE['mode'] ?? null;


if ($email && $password) {
    $result = mysqli_query($db,"SELECT * From Accounts WHERE email='$email' AND password='$password'");
    if (mysqli_num_rows($result) != 0) {
        if ($id){
            $result = mysqli_query($db,"SELECT * From Users WHERE email='$email' AND id='$id'");
            if (mysqli_num_rows($result) == 0) {
                $id = null;
                $email = null;
                $password = null;
                $name = null;
                $lastname = null;
                setcookie("id", "", time()-3600, "/");
                setcookie("name", "", time()-3600, "/");
                setcookie("lastname", "", time()-3600, "/");
                setcookie("email", "", time()-3600, "/");
                setcookie("password", "", time()-3600, "/");
                if (!headers_sent()) {
                    $time = time();
                    header("Location: $server_name/index2.php?time=$time"); exit;
                }
            }
        }else{
            if ($skipVerify != 1){


                $result = mysqli_query($db,"SELECT * From Users WHERE email='$email'");
                //If we don't have any character create one
                if (mysqli_num_rows($result) == 0) {
                    header("Location: $server_name/create.php");
                //If we have some character we just aren't selected select it and go to bio
                }else{
                    $user = mysqli_fetch_array($result);
                    $id = $user['id'];
                    $username = $user['name'];
                    $name = $user['name'];
                    $lastname = $user['lastname'];
    
                    setcookie("id", "$id", time()+99999999, "/");
                    setcookie("name", "$username", time()+99999999, "/");
                    setcookie("lastname", "$lastname", time()+99999999, "/");
                    header("Location: $server_name/bio.php");
                }
            }
        }
    }else{
        $id = null;
        $email = null;
        $password = null;
        $name = null;
        $lastname = null;
        setcookie("id", "", time()-3600, "/");
        setcookie("name", "", time()-3600, "/");
        setcookie("lastname", "", time()-3600, "/");
        setcookie("email", "", time()-3600, "/");
        setcookie("password", "", time()-3600, "/");
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