<html>
<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>GoS Reset</title>

</head>
<body>
<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
$skipVerify = 1;
include("connect.php");
// if check to prevent others from using this accidentally. Best to remove drop
// permissions from database user after a reset just to be safe
//if (strtolower($name) != "the" || strtolower($lastname) != "creator" )
//if (strtolower($name) != "the" || strtolower($lastname) != "creator" )
echo "DEBUG: name=$name, lastname=$lastname, debug_mode=$debug_mode<br>";
if(!$debug_mode && (strtolower($name) != "the" || strtolower($lastname) != "creator"))
{
  echo $name." ".$lastname;
  echo "  Only the Creator has such powers!";
} else {
echo "Running reset...<br>";
$head = 1;
// Order matters on a fresh install. resetloc.php and generate_connections.php
// pull in userdata.php/locFuncs.php, which run queries against Locations, Hordes
// and Soc at include time, so every plain table-creating reset has to run first.
include("reset.php");
include("resetsoc.php");
include("resetquests.php");
include("resethordes.php");
include("resetestates.php");
include("resetips.php");
include("resetprofs.php");
include("resetcontests.php");
include("resetnotes.php");
include("resetmarket.php");
include("resetloc.php");
// resettrade.php seeds one row per location, so it runs after resetloc.php.
// It only rebuilds the Trade table when asked (r=1); a master reset wants that.
$_GET['r'] = 1;
include("resettrade.php");
unset($_GET['r']);
include("generate_connections.php");
  
if (mysqli_num_rows(mysqli_query($db,"SELECT id FROM Quests WHERE 1")) ==0)
{
  // Setup Quests
  include("create_tut_quests.php");
}
} 

// Close Database
mysqli_close($db);
?>

</body>
</html>