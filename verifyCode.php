<?php

/* get the incoming ID and password hash */
$user = $_POST["userid"];
$pwod = ($_POST["pwod"]);
$confirm =  ($_POST["confirm"]);


$skipVerify = 1;
/* establish a connection with the database */
include_once("admin/connect.php");
 

//Find code
$query = "SELECT * FROM Reset WHERE code = ?";
$stmt = mysqli_prepare($db, $query);
mysqli_stmt_bind_param($stmt, "s", $user);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$codeResult = mysqli_fetch_array( $result );

/* Allow access if a matching record was found and cookies enabled, else deny access. */
if ($codeResult)
{

	include('header.php');
	//Make sure passwords match
	if(strcmp($pwod,$confirm) == 0 ){
	
	$pwod = password_hash($pwod, PASSWORD_DEFAULT);
	
	// Use prepared statement to prevent SQL injection
	$deleteQuery = "DELETE FROM Reset WHERE code = ?";
	$deleteStmt = mysqli_prepare($db, $deleteQuery);
	mysqli_stmt_bind_param($deleteStmt, "s", $user);
	mysqli_stmt_execute($deleteStmt);
	
	$updateQuery = "UPDATE Accounts SET password = ? WHERE email = ?";
	$updateStmt = mysqli_prepare($db, $updateQuery);
	mysqli_stmt_bind_param($updateStmt, "ss", $pwod, $codeResult['email']);
	mysqli_stmt_execute($updateStmt);
	
	echo "<center><b>Password Updated:</b> Please relog.";
	}else{
		echo "<center><b>Password Mismatch:</b> Please type your confirmation password correctly. ";
	}
}
