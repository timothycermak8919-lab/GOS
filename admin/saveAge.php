<?php 
 //Link to download file...
 //Link to download file...
 $url = "https://".$_SERVER['SERVER_NAME']."/history/hornSnapshot.php";


 //Code to get the file...
 $data = @file_get_contents($url);
 $date = date('Y-m-d');
 //save as?
 $dir = __DIR__."/../history/ages";
 $filename = $dir."/".$date.".html";

 // This runs from the hourly cron at the end of an Age. A missing directory or an
 // unreachable snapshot URL used to leave fopen()/file_get_contents() returning
 // false, and fwrite(false, ...) is a TypeError on PHP 8 - which killed the very
 // update that closes the Age out.
 if ($data === false)
 {
   error_log("saveAge: could not fetch the Age snapshot from ".$url);
 }
 else
 {
   if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
   $fh = @fopen($filename,"w");
   if ($fh === false)
   {
     error_log("saveAge: could not open ".$filename." for writing");
   }
   else
   {
     fwrite($fh,$data);
     fclose($fh);
     echo "<a href='".$filename."'>Click Here</a> to download the file...";
   }
 }
 ?>