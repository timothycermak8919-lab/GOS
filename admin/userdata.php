<?php
// load basic data
include_once("config.php");
include_once('displayFuncs.php');
include_once('busiFuncs.php');
include_once('charFuncs.php');
$stmt = mysqli_prepare($db, "SELECT * FROM Users LEFT JOIN Users_data ON Users.id=Users_data.id WHERE Users.id=?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$char = mysqli_fetch_array($result);
$curtime=time();
$jobs_data = $char['jobs'] ?? null; // Use null coalescing operator to handle potential non-existence
$jobs = $jobs_data ? unserialize($jobs_data) : []; // Unserialize only if not null/empty, otherwise default to empty array
$pro_stats=cparse(getAllJobBonuses($jobs));
$check=intval(time()/3600);

// MAX INVENTORY SIZE (ANY CHANGES ALSO NEED TO BE CHANGED IN THE 'map/places/blacksmith.php' SCRIPT)
$inv_max=$base_inv_max+5*$char['travelmode2']+$pro_stats['eS'];
$pouch_max = 4+2*$char['pouch_type']+$pro_stats['cS'];

$char['lastonline']= time();
$stmt = mysqli_prepare($db, "UPDATE Users SET lastonline=? WHERE id=?");
mysqli_stmt_bind_param($stmt, "ii", $char['lastonline'], $id);
mysqli_stmt_execute($stmt);

// Darkfriends cannot have a positive alignment.
$types_data = $char['type'] ?? null;
$types = $types_data ? unserialize($types_data) : [];

if (!empty($types) && $types[0] == 5 && $char['align'] > 0) 
{
  $char['align'] = 0;
  $stmt = mysqli_prepare($db, "UPDATE Users SET align=? WHERE id=?");
  mysqli_stmt_bind_param($stmt, "ii", $char['align'], $id);
  mysqli_stmt_execute($stmt);
}

$lastBattleDone = mysqli_num_rows(mysqli_query($db,"SELECT id FROM Contests WHERE type='99' AND done='1'"));

$funtime = 1;
if ($char['donor']) 
{
  $battlelimit = $battlelimit*2*$funtime;
  $maxquests = $maxquests*2;
  $maxalts = $maxalts*2;
}
if ($funtime == 2) // Ironman rules
{
  $battlelimit = 400;
  $maxquests = $maxquests*2;
  $maxalts = 1;
  $maxsocalts += 1;
}

mysqli_query($db,"LOCK TABLES Users WRITE;");
if ($char['gold'] < 0 || $char['gold'] > $max_gold) // KEEP GOLD BELOW MAX
{
  if ($char['gold'] < 0) {
    $stmt = mysqli_prepare($db, "UPDATE Users SET gold='0' WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
  }
  else  {
    $stmt = mysqli_prepare($db, "UPDATE Users SET gold=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "ii", $max_gold, $id);
    mysqli_stmt_execute($stmt);
  }
}

if ($char['arrival']<=time() && $char['arrival']!=0) // ARRIVE AT TRAVEL TO PLACE
{
  // FIX: Path traversal prevention - whitelist allowed files
  $allowed_files = ['coordinates.inc'];
  $safe_subfile = basename($subfile);
  if (in_array($safe_subfile, $allowed_files)) {
    include($_SERVER['DOCUMENT_ROOT']."/".$safe_subfile."/map/mapdata/coordinates.inc");
  }
  $char['location']=$char['travelto'];
  $stmt = mysqli_prepare($db, "UPDATE Users SET location=?, arrival='0' WHERE id=?");
  mysqli_stmt_bind_param($stmt, "si", $char['travelto'], $id);
  mysqli_stmt_execute($stmt);
}
mysqli_query($db,"UNLOCK TABLES;");

// UPDATE USER ACHIEVEMENTS
mysqli_query($db,"LOCK TABLES Users WRITE, Users_data WRITE, Users_stats WRITE, Notes WRITE;");
$myAchieve_data = $char['achieve'] ?? null;
$myAchieve = $myAchieve_data ? unserialize($myAchieve_data) : [];
$stmt = mysqli_prepare($db, "SELECT * FROM Users_stats WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $char['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_array($result);
if ($char['align'] > $stats['align_high']) 
{
  $stmt = mysqli_prepare($db, "UPDATE Users_stats SET align_high=? WHERE id=?");
  mysqli_stmt_bind_param($stmt, "ii", $char['align'], $id);
  mysqli_stmt_execute($stmt);
}
if ((0-$char['align']) > $stats['align_low']) 
{
  $align_low = 0 - $char['align'];
  $stmt = mysqli_prepare($db, "UPDATE Users_stats SET align_low=? WHERE id=?");
  mysqli_stmt_bind_param($stmt, "ii", $align_low, $id);
  mysqli_stmt_execute($stmt);
}
$achieved=0;
$maGold=0;
$maJi=0;
$maSp=0;
$maPp=0;
$maAchieved=0;
$amsg = "You have completed an Achievement!<br/>";
$stmt = mysqli_prepare($db, "SELECT id, name, lastname FROM Users WHERE name = 'The' AND lastname = 'Creator'");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($result);
$creator = mysqli_fetch_array($result);
$cid = $creator['id'];
foreach ($achievements as $branch => $ainfo)
{
  $a= $myAchieve[$branch];
  if (!$a) $a=0;
  if ($a > count($ainfo[3])) 
  {
    $myAchieve[$branch] = count($ainfo[3]);
    $a=$myAchieve[$branch];
  }
  $maAchieved+=$a;
  if ($a < count($ainfo[3]))
  {
    $x = $ainfo[3][$a];
    if ($stats[$ainfo[0]] >= $x) 
    {
      $achieved++;
      $myAchieve[$branch]++;
      if ($ainfo[5] == 'cp')
      {
        $maGold += $ainfo[4][$a];
      }
      elseif ($ainfo[5] == 'ji') $maJi += $ainfo[4][$a];
      elseif ($ainfo[5] == 'sp') $maSp += $ainfo[4][$a];
      elseif ($ainfo[5] == 'pp') $maPp += $ainfo[4][$a];

      $notesub= "Completed: ".$ainfo[2][0];
      if (count($ainfo[2]) > 1)
      {
        if ($ainfo[2][2])
        {
          $notesub.= displayGold($x,1);
        }
        else
        {
          $notesub.= $x;
        }
        $notesub.= $ainfo[2][1];
      }      
      $amsg .= "<br/>You recieved: ";
      if ($maGold) $amsg.= displayGold($maGold,1).", ";
      if ($maJi) $amsg.= $maJi." Ji, ";
      if ($maSp) $amsg.= $maSp." Skill Points, ";
      if ($maPp) $amsg.= $maPp." Profession Points, ";
      $amsg.= "<br/><br/>Keep up the good work!";   

      $note=$amsg;
      $note_extra="";

      $result = mysqli_query($db,"INSERT INTO Notes (from_id,   to_id,    del_from,del_to,type,root,sent,        cc,subject,       body,       special) 
                                        VALUES ('".$cid."','".$id."','0',     '0',   '5', '0', '".time()."','','".$notesub."','".$note."','".$note_extra."')");
    }
  }
}
if ($maAchieved != $stats['achieved'])
{
  $stats['achieved'] = $maAchieved;
  $stmt = mysqli_prepare($db, "UPDATE Users_stats SET achieved=? WHERE id=?");
  mysqli_stmt_bind_param($stmt, "ii", $stats['achieved'], $id);
  mysqli_stmt_execute($stmt);
}
if ($achieved)
{ 
  $char['gold']+=$maGold;
  $stats['ji']+=$maJi;
  $char['points'] += $maSp;
  $char['propoints'] += $maPp;
  $sma=serialize($myAchieve);

  $stmt = mysqli_prepare($db, "UPDATE Users SET newachieve=newachieve+1, gold=?, points=?, propoints=? WHERE id=?");
  mysqli_stmt_bind_param($stmt, "iddi", $char['gold'], $char['points'], $char['propoints'], $id);
  mysqli_stmt_execute($stmt);

  $stmt = mysqli_prepare($db, "UPDATE Users_data SET achieve=? WHERE id=?");
  mysqli_stmt_bind_param($stmt, "si", $sma, $id);
  mysqli_stmt_execute($stmt);

  $stmt = mysqli_prepare($db, "UPDATE Users_stats SET ji=? WHERE id=?");
  mysqli_stmt_bind_param($stmt, "ii", $stats['ji'], $id);
  mysqli_stmt_execute($stmt);
}
mysqli_query($db,"UNLOCK TABLES;");

// UPDATE DICE GAMES
include_once('dice_update.php');

?>
