<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();
$skipVerify = 1;
echo "Starting test<br>";
include('header.php');
echo "After include<br>";
?>
<h1>Test Page</h1>
