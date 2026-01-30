<?php

$wait_post = 15;

/* establish a connection with the database */
$skipVerify = 0;

include_once("admin/connect.php");
include_once("admin/userdata.php");
include_once("admin/charFuncs.php");
include_once("admin/locFuncs.php");
include_once('map/mapdata/coordinates.inc');

if ($location_array[$char['location']]['2']) {
    $is_town = 1;
    $dw = $town_bonuses['dW'];
} else {
    $is_town = 0;
    $dw = 0;
}

$name = $_COOKIE['name'];
$lastname = $_COOKIE['lastname'];

$bioName = mysqli_real_escape_string($db, strval($_GET['name']));
$submitted = mysqli_real_escape_string($db, strval($_POST['submitted']));
$bioLastName = mysqli_real_escape_string($db, strval($_GET['last']));
$extra = mysqli_real_escape_string($db, strval($_POST['extra']));
$message = mysqli_real_escape_string($db, strval($_REQUEST['message']));
$tab = mysqli_real_escape_string($db, strval($_GET['tab']));
$time = time();
$ipaddy = $_SERVER['REMOTE_ADDR'];

$wikilink = "Profile";

// if no name, show own bio
if ($bioName == '' || $bioLastName == '') {
    $bioName = $name;
    $bioLastName = $lastname;
}
if (strtolower($name) == strtolower($bioName) && strtolower($lastname) == strtolower($bioLastName)) $is_same = 1; else $is_same = 0;

// copy own data to charother
$charother = $char;
$socnameother = $charother['society'];
$stmt = mysqli_prepare($db, "SELECT * FROM Soc WHERE name=?");
mysqli_stmt_bind_param($stmt, "s", $socnameother);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$societyo = mysqli_fetch_array($result);

$friends = json_decode($charother['friends'], true);

// get bio to be displayed
$stmt = mysqli_prepare($db, "SELECT * FROM Users LEFT JOIN Users_data ON Users.id=Users_data.id WHERE Users.name=? AND Users.lastname=?");
mysqli_stmt_bind_param($stmt, "ss", $bioName, $bioLastName);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$char = mysqli_fetch_array($result);
$cid = $char['id'];
$time = time();
$username = $char['name'] . "_" . $char['lastname'];

$socname = $char['society'];
$stmt = mysqli_prepare($db, "SELECT * FROM Soc WHERE name=?");
mysqli_stmt_bind_param($stmt, "s", $socname);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$society = mysqli_fetch_array($result);
$classes = json_decode($char['type'], true);

// FIXED: SQL injection vulnerability - use prepared statement
$charId = $char['id'];
$stmt = mysqli_prepare($db, "SELECT * FROM Users_stats WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $charId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_array($result);

$query = "SELECT * FROM Users_stats WHERE id='10011'";
$result = mysqli_query($db, $query);
$heroes = mysqli_fetch_array($result);

$titles = [''];
$title_why = [''];
$x = 0;
for ($y = 0; $y < count($rank_data); $y++) {
    if ($heroes[$rank_data[$y]['0']] == $char['id'] && $stats[$rank_data[$y]['0']] > 0) {
        $titles[$x] = $rank_data[$y]['2'];
        $title_why[$x] = $rank_data[$y]['1'];
        $x++;
    }
}

// FIXED: SQL injection vulnerability - use prepared statement
$cNat = $char['nation'];
$stmt = mysqli_prepare($db, "SELECT id, name, lastname FROM Users WHERE nation=? ORDER BY exp DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $cNat);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$topNat = mysqli_fetch_array($result);
if ($topNat['id'] == $char['id']) {
    $titles[$x] = "Champion of the " . $nat_champs[$cNat];
    $title_why[$x] = "Experienced " . $nationalities[$cNat];
    $x++;
}

$resultb = mysqli_prepare($db, "SELECT * FROM IP_logs WHERE addy=?");
mysqli_stmt_bind_param($resultb, "s", $ipaddy);
mysqli_stmt_execute($resultb);
$resultb = mysqli_stmt_get_result($resultb);
$fullname = $charother['name'] . "_" . $charother['lastname'];

$charip = json_decode($charother['ip'], true);
$alts = getAlts($charip);

$user_ips = json_decode($charother['ip'], true);

$uips_count=0;
if(is_array($user_ips)){
	$uips_count = count($user_ips);
}
$found = 0;
for ($i = 0; $i < $uips_count; $i++) {
    if ($user_ips[$i] == $ipaddy) $found = 1;
}
if (!$found) {
    $user_ips[$uips_count] = $ipaddy;
    $user_ips2 = json_encode($user_ips);
    if ($charother['id']!=""){
        // FIXED: SQL injection vulnerability - use prepared statement
        $stmt = mysqli_prepare($db, "UPDATE Users SET ip=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "si", $user_ips2, $charother['id']);
        mysqli_stmt_execute($stmt);
    }
}


if ($char['id'] == $charother['id']) // only update IPs on your own profile
{
    if (mysqli_fetch_row($resultb)) {
        $result = mysqli_prepare($db, "SELECT * FROM IP_logs WHERE addy=?");
        mysqli_stmt_bind_param($result, "s", $ipaddy);
        mysqli_stmt_execute($result);
        $result = mysqli_stmt_get_result($result);
        $ip_log = mysqli_fetch_array($result);
        $ip_users = json_decode($ip_log['users'], true);
        $found = 0;
        $ip_count = count($ip_users);
        for ($i = 0; $i < $ip_count; $i++) {
            if ($ip_users[$i] == $fullname) {
                $found = 1;
            }
        }
        if (!$found) {
            $ip_users[$ip_count] = $fullname;
            $ip_users2 = json_encode($ip_users);
            $ip_count++;
            // FIXED: SQL injection vulnerability - use prepared statement
            $ip_test = $ip_users['test']++;
            $stmt = mysqli_prepare($db, "UPDATE IP_logs SET users=?, num=?, test=? WHERE addy=?");
            mysqli_stmt_bind_param($stmt, "siis", $ip_users2, $ip_count, $ip_test, $ipaddy);
            mysqli_stmt_execute($stmt);
        } else if ($ip_log['maxnum'] == 2 && $char['donor'] > 0)
            // FIXED: SQL injection vulnerability - use prepared statement
            $stmt = mysqli_prepare($db, "UPDATE IP_logs SET maxnum=? WHERE addy=?");
            mysqli_stmt_bind_param($stmt, "is", $maxalts, $ipaddy);
            mysqli_stmt_execute($stmt);
    } else {
        $ip_users['0'] = $fullname;
        $ip_users2 = json_encode($ip_users);
        // FIXED: SQL injection vulnerability - use prepared statement
        $stmt = mysqli_prepare($db, "INSERT INTO IP_logs (addy, users, num, maxnum, test) VALUES(?, ?, '1', ?, '1')");
        mysqli_stmt_bind_param($stmt, "ssi", $ipaddy, $ip_users2, $maxalts);
        mysqli_stmt_execute($stmt);
    }
}

// IF CREATOR

if ($classes['0'] == 0) $is_creator = 1; else $is_creator = 0;

// ADD / REMOVE FRIENDS - Only allow users to manage their own friends list
if ($_GET['set_s'] && $is_same) {
    function delete_ele($array, $ele)
    {
        $new_array = array();
        foreach ($array as $key => $value) {
            if ($key != $ele) $new_array[$key] = $value;
        }
        return $new_array;
    }

    if ($_GET['set_s'] == 3) {
        $friends = delete_ele($friends, $char['id']);
        $message = "$bioName $bioLastName removed";
    } elseif (count($friends) <= 25) {
        $friends[$char['id']] = array($char['name'], $char['lastname'], $_GET['set_s'] - 1);
        $message = "$bioName $bioLastName added";
    } else $message = "You have too many friends/enemies";

    // FIXED: SQL injection vulnerability - use prepared statement
    $sfriends = json_encode($friends);
    $stmt = mysqli_prepare($db, "UPDATE Users_data SET friends=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "si", $sfriends, $charother['id']);
    mysqli_stmt_execute($stmt);
}

// TRANSFER ORG LEADERSHIP - Only current leader can transfer to themselves
if ($_GET['transfer'] && $is_same && $char['society'] == $charother['society'] && strtolower($societyo['leader']) == strtolower($charother['name']) && strtolower($societyo['leaderlast']) == strtolower($charother['lastname'])) {
    // FIXED: SQL injection vulnerability - use prepared statement
    $stmt = mysqli_prepare($db, "UPDATE Soc SET leader=?, leaderlast=? WHERE name=?");
    mysqli_stmt_bind_param($stmt, "sss", $bioName, $bioLastName, $char['society']);
    mysqli_stmt_execute($stmt);
    $message = $char['society'] . " leadership transfered";
}

if (!$message && $is_same) $message = "Today's date is " . wotDate();
elseif (!$message && $char['born']) {
    $message = "Residing in " . str_replace('-ap-', "'", $char['location']);
}

$charb = $char;
$char = $charother;
if (!$tab) $tab = 1;
include('header.php');
$char = $charb;
if (!$char['born']) {
    echo "<center><br><br><font class='medtext'>" . htmlspecialchars($bioName . " " . $bioLastName, ENT_QUOTES) . " does not exist";
    include('footer.htm');
    exit;
}

?>
    <div class="row solid-back">
        <div class="col-sm-12">
            <div id="content">
                <ul id="biotabs" class="nav nav-tabs" data-tabs="tabs">
                    <li <?php if ($tab == 1) echo "class='active'"; ?>><a href="#bio_tab" data-toggle="tab">Bio</a></li>
                    <?php
                    if ($is_same || ($charother['name'] == 'The' && $charother['lastname'] == 'Creator')) {
                        ?>
                        <li <?php if ($tab == 2) echo "class='active'"; ?>><a href="#stats_tab"
                                                                              data-toggle="tab">Stats</a></li>
                        <li <?php if ($tab == 3) echo "class='active'"; ?>><a href="#list_tab" data-toggle="tab">Watch
                                List</a></li>
                        <?php
                        if ($char['donor'] == 1) {
                            ?>
                            <li <?php if ($tab == 4) echo "class='active'"; ?>><a href="#alt_tab" data-toggle="tab">Alt
                                    Status</a></li>
                            <?php
                        }
                    }
                    ?>
                    <li <?php if ($tab == 5) echo "class='active'"; ?>><a href="#achieve_tab" data-toggle="tab">Accomplishments</a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane <?php if ($tab == 1) echo "active"; ?>" id="bio_tab">

                        <!-- CHARACTER DISPLAY STUFF -->
                        <div class="col-sm-4 col-md-3">
                            <?php
                            $avatar = $char['avatar'];
                            if (!($avatar)) {
                                $nat = str_replace(" ", "_", $nationalities[$char['nation']]);
                                $sex = $char['sex'];
                                $sexChar = "M";
                                if ($sex) $sexChar = "F";
                                $w = 0;
                                for ($y = 1; $y < count($worth_ranks); $y++) {
                                    if ($stats['net_worth'] >= $worth_ranks[$y]['0']) $w = $y;
                                }
                                if ($is_creator) $w = 0;

                                $avatar = "char/" . $nat . $sexChar . $w . ".jpg";
                            }

                            $gold = ($char['gold']);
                            $lvl = number_format($char['level']);
                            $wins = number_format($stats['wins']);
                            $win_per = "(" . intval(100 * $stats['wins'] / ($stats['battles'] + 0.0001) + 0.5) . "%) ";
                            $dwins = number_format($stats['duel_wins']);
                            $dwin_per = "(" . intval(100 * $stats['duel_wins'] / ($stats['tot_duels'] + 0.0001) + 0.5) . "%) ";
                            $ewins = number_format($stats['enemy_wins']);
                            $ewin_per = "(" . intval(100 * $stats['enemy_wins'] / ($stats['enemy_duels'] + 0.0001) + 0.5) . "%) ";
                            $owins = number_format($stats['offwins']);
                            $owin_per = "(" . intval(100 * $stats['off_wins'] / ($stats['off_bats'] + 0.0001) + 0.5) . "%) ";
                            $nwins = number_format($stats['npc_wins']);
                            $nwin_per = "(" . intval(100 * $stats['npc_wins'] / ($stats['tot_npcs'] + 0.0001) + 0.5) . "%) ";
                            $bgold = $char['bankgold'];
                            $ggold = $stats['dice_earn'];
                            $dgold = $stats['duel_earn'];
                            $tgold = $stats['item_earn'];
                            $qgold = $stats['quest_earn'];
                            $pgold = $stats['prof_earn'];
                            $npc1_wins = number_format($stats['shadow_wins']);
                            $npc1_per = "(" . intval(100 * $stats['shadow_wins'] / ($stats['shadow_npcs'] + 0.0001) + 0.5) . "%) ";
                            $npc2_wins = number_format($stats['military_wins']);
                            $npc2_per = "(" . intval(100 * $stats['military_wins'] / ($stats['military_npcs'] + 0.0001) + 0.5) . "%) ";
                            $npc3_wins = number_format($stats['ruffian_wins']);
                            $npc3_per = "(" . intval(100 * $stats['ruffian_wins'] / ($stats['ruffian_npcs'] + 0.0001) + 0.5) . "%) ";
                            $npc4_wins = number_format($stats['channeler_wins']);
                            $npc4_per = "(" . intval(100 * $stats['channeler_wins'] / ($stats['channeler_npcs'] + 0.0001) + 0.5) . "%) ";
                            $npc5_wins = number_format($stats['animal_wins']);
                            $npc5_per = "(" . intval(100 * $stats['animal_wins'] / ($stats['animal_npcs'] + 0.0001) + 0.5) . "%) ";
                            $npc6_wins = number_format($stats['exotic_wins']);
                            $npc6_per = "(" . intval(100 * $stats['exotic_wins'] / ($stats['exotic_npcs'] + 0.0001) + 0.5) . "%) ";

                            $stance = json_decode($societyo['stance'], true);
                            $associated = "";
                            if ($stance[str_replace(" ", "_", $char['society'])] == 1 && $char['society'] != $charother['society'] && $char['society']) $associated = "ally ";
                            if ($stance[str_replace(" ", "_", $char['society'])] == 2 && !$is_same) $associated = "enemy ";

                            if ($char['society'] != '') {
                                if ($char['soc_rank'] == 1) {
                                    $soctit = "Leader";
                                    if ($society['leadertitle'] != "") $soctit = $society['leadertitle'];
                                    $societyn = "<img src='images/SocLead.gif' height=20 width=28> " . $soctit . " of ";
                                } elseif ($char['soc_rank'] == 2) {
                                    $soctit = "Subleader";
                                    if ($society['subtitle'] != "") $soctit = $society['subtitle'];
                                    $societyn = "<img src='images/SocSub.gif' height=20 width=28> " . $soctit . " of ";
                                } elseif ($char['soc_rank'] > 0) {
                                    $cranks = json_decode($society['ranks'], true);
                                    $societyn = $cranks[7 - $char['soc_rank']]['0'] . " of ";
                                } else {
                                    $societyn = "Member of ";
                                }
                                $societyn .= $associated;
                                if ($char['society'] != $charother['society']) $societyn .= "<a href='joinclan.php?name=" . $char['society'] . "'>" . str_replace(" ", "&nbsp;", $char['society']) . "</a><br><br>";
                                else $societyn .= "<a href='clan.php'>" . str_replace(" ", "&nbsp;", $char['society']) . "</a><br><br>";
                            } else $societyn = '';
                            $health = $char['vitality'];

                            if ($char['goodevil'] == 2) $classn = "color: #5483C9;";
                            elseif ($char['goodevil'] == 1) $classn = "color: #E14545;";
                            else $classn = "";
                            if ($char['sex']) $gender = "Female";
                            else $gender = "Male";
                            $align = $char['align'];

                            if ($is_creator) {
                                $gender = "Unknown";
                                $associated = "";
                                $classn = "";
                                $lvl = "Infinite";
                                $health = "Limitless";
                                $wins = "Irrelevent";
                                $align = "Guess";
                            }

                            if (strlen($bioName . " " . $bioLastName) > 16) {
                                $font_size = "class='medtext' style='font-size: 11px; $classn'";
                                $societyn = "<br>" . $societyn;
                            } else $font_size = "class='medtext' style='$classn'";

                            // FIXED: SQL injection vulnerability - use prepared statement
                            $stmt = mysqli_prepare($db, "SELECT * FROM Notes WHERE to_id=? AND del_to='0' AND type < 4 ORDER BY sent DESC");
                            mysqli_stmt_bind_param($stmt, "i", $id);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            $num_notes = mysqli_num_rows($result);

                            // FIXED: SQL injection vulnerability - use prepared statement
                            $stmt = mysqli_prepare($db, "SELECT * FROM Notes WHERE to_id=? AND del_to='0' AND type = 9 ORDER BY sent DESC");
                            mysqli_stmt_bind_param($stmt, "i", $id);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            $num_logs = mysqli_num_rows($result);

                            $smin = $char['stamina'];
                            $smax = $char['stamaxa'];
                            ?>
                            <div class="panel panel-success">
                                <div class="panel-heading">
                                    <h3 class="panel-title"><?php echo htmlspecialchars($bioName . " " . $bioLastName, ENT_QUOTES); ?></h3>
                                </div>
                                <div class="panel-body abox">
                                    <p>
                                        <i>Level:</i> <?php echo $lvl; ?><br/>
                                        <i>Nationality:</i> <?php echo $nationalities[$char['nation']]; ?><br/>
                                        <i>Class:</i>
                                        <?php
                                        for ($i = 0; $i < count($classes); $i++) {
                                            echo "<img src='images/classes/" . $classes[$i] . ".gif' alt='" . $classes[$i] . "' title='" . $char_class[$classes[$i]] . "' height='15' width='15'/>";
                                        }
                                        ?>
                                        <br/>
                                        <i>Sex:</i> <?php echo $gender; ?><br/>
                                        <i>Stamina:</i> <?php echo "$smin/$smax"; ?><br/>
                                        <i>Health:</i> <?php echo $health; ?><br/>
                                        <i>Alignment:</i> <?php echo $align; ?><br/>
                                        <i>Purse:</i><?php echo displayGold($gold); ?>
                                    </p>
                                    <?php
                                    if ($char['lastonline'] >= time() - 900)
                                        echo "<button class='btn btn-xs btn-success active'>Online</button>";
                                    else
                                        echo "<button class='btn btn-xs btn-default disabled'>Offline</button>";
                                    ?>
                                </div>
                            </div>

                            <form name='duelform' action='duel.php' method='post'>
                                <input type='hidden' name='enemyid' value='<?php echo $cid; ?>' id='enemyid'/>
                                <input type='hidden' name='ddd' value='0' id='ddd'/>
                            </form>

                            <div class="panel panel-warning">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Actions</h3>
                                </div>
                                <div class="panel-body abox">
                                    <div class="btn-group-vertical btn-block">
                                        <?php
                                        // OTHER CHARACTER ACTIONS
                                        if (!$is_same)
                                        {
                                        // BATTLE
                                        $find_battle = json_decode($charother['find_battle'], true);
                                        $numbahs = array("Zero", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten", "Ten");

                                        if ($char['location'] == $charother['location']) {
                                            if ($find_battle[$char['id']] <= time() - intval(300 * (100 + $dw) / 100)) // DUEL FIX
                                            {
                                                $duel_link = "javascript:submitFormBio(0)";
                                                $duel_link2 = "javascript:submitFormBio(1)";
                                                ?>
                                                <a href="<?php echo $duel_link; ?>" class="btn btn-danger btn-sm"><img
                                                            border='0' src='images/duel.gif'/> Duel <img border='0'
                                                                                                         src='images/duel.gif'/></a>
                                                <?php
                                                if ($charother['donor']) {
                                                    ?>
                                                    <a href="<?php echo $duel_link2; ?>"
                                                       class="btn btn-danger btn-sm"><img border='0'
                                                                                          src='images/duel.gif'/> Double
                                                        Duel <img border='0' src='images/duel.gif'/></a>
                                                    <?php
                                                }
                                            } elseif ($find_battle[$char['id']] > time() - intval(300 * (100 + $dw) / 100)) {
                                                $time_to_battle = intval((intval(300 * (100 + $dw) / 100) - (time() - $find_battle[$char['id']])) / 60 + 1);
                                                ?>
                                                <img src='images/noduel.gif'
                                                     alt='X'/> <?php echo $numbahs[$time_to_battle]; ?> minute <img
                                                    src='images/noduel.gif' alt='X'/>
                                                <?php
                                                if ($time_to_battle != 1) echo "s";
                                            }
                                        }
                                        ?>
                                        <a href="messages.php?id=<?php echo $char['id']; ?>&tab=3"
                                           class="btn btn-info btn-sm"><img border='0' src='images/barter.gif'/> Send
                                            message <img border='0' src='images/barter.gif'/></a>
                                        <?php
                                        // TRADE
                                        if (!$alts[$username]) {
                                            ?>
                                            <a href="messages.php?id=<?php echo $char['id']; ?>&tab=4"
                                               class="btn btn-warning btn-sm"><img border='0' src='images/scale.gif'/>
                                                Offer trade <img border='0' src='images/scale.gif'/></a>
                                            <?php
                                        }
                                        // INVITE
                                        if ($char['society'] != $charother['society'] &&
                                            ($societyo['invite'] == 1 ||
                                                ($societyo['invite'] == 2 && strtolower($societyo['leader']) == strtolower($charother['name']) && strtolower($societyo['leaderlast']) == strtolower($charother['lastname'])) &&
                                                $socnameother)) {
                                            ?>
                                            <a href="messages.php?<?php echo "name=$bioName&last=$bioLastName&time=$time&join=59320112"; ?>"
                                               class="btn btn-info btn-sm"><img border='0' src='images/invite.gif'/>
                                                Send invite <img border='0' src='images/invite.gif'/></a>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <?php
                                    // BEFRIEND
                                    if (!isset($friends[$char['id']]['0'])) {
                                        ?>
                                        <a href="bio.php?<?php echo "set_s=1&time=$time&name=$bioName&last=$bioLastName"; ?>"
                                           class="btn btn-success btn-sm btn-block"><img border='0'
                                                                                         src='images/handshake.gif'/>
                                            Friend <img border='0' src='images/handshake.gif'/></a>
                                        <a href="bio.php?<?php echo "set_s=2&time=$time&name=$bioName&last=$bioLastName"; ?>"
                                           class="btn btn-danger btn-sm btn-block"><img border='0'
                                                                                      src='images/duel.gif'/> Enemy
                                            <img border='0' src='images/duel.gif'/></a>
                                        <?php
                                    } else {
                                        ?>
                                        <a href="bio.php?<?php echo "set_s=3&time=$time&name=$bioName&last=$bioLastName"; ?>"
                                           class="btn btn-info btn-sm btn-block"><img border='0'
                                                                                      src='images/handshake.gif'/>
                                            Neutral <img border='0' src='images/handshake.gif'/></a>
                                        <?php
                                    }
                                    }
                                    // SAME CHARACTER ACTIONS
                                    else
                                    {
                                    if ($num_notes) {
                                        ?>
                                        <a href="messages.php" class="btn btn-primary btn-sm"><img border='0'
                                                                                                   src='images/barter.gif'/>
                                            Messages <span class="badge"><?php echo $num_notes; ?></span> <img
                                                border='0' src='images/barter.gif'/></a>
                                        <?php
                                    }
                                    if ($num_logs) {
                                        ?>
                                        <a href="battlelogs.php" class="btn btn-danger btn-sm"><img border='0'
                                                                                                     src='images/shield.gif'/>
                                            Def log <span class="badge"><?php echo $num_logs; ?></span> <img border='0'
                                                                                                              src='images/shield.gif'/></a>
                                        <?php
                                    }
                                    ?>
                                    <a href="myquests.php" class="btn btn-success btn-sm btn-block"><img border='0'
                                                                                                         src='images/loc.gif'/>
                                        My Quests <img border='0' src='images/loc.gif'/></a>
                                    <a href='avatar.php' class="btn btn-info btn-sm btn-block"><img border='0'
                                                                                                    src='images/anvil.gif'/>
                                        Settings <img border='0' src='images/anvil.gif'/></a>
                                </div>
                                <?php
                                }
                                ?>
                            </div>
                        </div> <!-- close action panel -->
                    </div> <!-- close 1st column -->
                    <div class="col-sm-4 col-md-6">
                        <center><img id="avi" class="img-responsive img-optional" border='0' bordercolor='#000000'
                                     src="<?php echo $avatar; ?>"/><br/>
                            <p>
                                <?php
                                $curnote = htmlspecialchars($char['about'], ENT_QUOTES);
                                $curnote = nl2br($curnote);
                                echo "<i>$curnote</i>";
                                ?>
                            </p></center>
                    </div>
                    <div class="col-sm-4 col-md-3">
                        <?php

                        if ($ageday == 1) $days = "";
                        $percent_up = 100 - intval(100 * ($char['exp_up'] - $char['exp']) / $char['exp_up_s']);
                        if ($percent_up > 99) $percent_up = 99;

                        if ($char['society'] != '') {
                            ?>
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h3 class="panel-title"><?php echo $societyn ?></h3>
                                </div>
                                <div class="panel-body abox">
                                    <?php
                                    if ($char['soc_rank'] == 1) {
                                        $leadersoc = "yes";
                                        ?>
                                        <a href="clanoffice.php"
                                           class="btn btn-primary btn-sm btn-block">Office</a>
                                        <?php
                                        if ($char['donor']) {
                                            ?>
                                            <a href="clansettings.php"
                                               class="btn btn-info btn-sm btn-block">Settings</a>
                                            <?php
                                        }
                                    } elseif ($char['soc_rank'] == 2) {
                                        $leadersoc = "yes";
                                        ?>
                                        <a href="clanoffice.php"
                                           class="btn btn-primary btn-sm btn-block">Office</a>
                                        <?php
                                    } elseif ($char['soc_rank'] > 0) {
                                        $leadersoc = "no";
                                    } else {
                                        $leadersoc = "no";
                                    }
                                    $charwars = json_decode($char['wars'], true);
                                    if (is_array($charwars)) {
                                        $wararray = $charwars;
                                        $nowars = 0;
                                    } else {
                                        $wararray = array();
                                        $nowars = 1;
                                    }
                                    $wars = 0;
                                    if (is_array($society['enemies'])) {
                                        foreach ($society['enemies'] as $clan => $war) {
                                            if ($war == 1) {
                                                $wars++;
                                                if (is_array($charwars) && in_array($clan, $charwars)) $nowar = 1;
                                                else $nowar = 0;
                                                ?>
                                                <button class="btn btn-sm" disabled="disabled"><?php echo $clan ?></button>
                                                <?php
                                            }
                                        }
                                    }
                                    if ($wars == 0) {
                                        echo "No wars";
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php
                        }

                        $freeskills = $char['skill_pts'] - $skillpts;
                        $skillpoints = "0";
                        $freeprof = $char['profs_pts'] - $profpts;
                        $profpoints = "0";
                        $titlecount = count($titles);
                        for ($i = 0; $i < $titlecount; $i++) {
                            ?>
                            <div class="panel panel-success">
                                <div class="panel-heading">
                                    <h3 class="panel-title"><?php echo $titles[$i] ?></h3>
                                </div>
                                <div class="panel-body abox">
                                    <?php echo $title_why[$i] ?>
                                </div>
                            </div>
                            <?php
                        }

                        $onlineList = "";
                        $pmonth = 0;
                        $charip = json_decode($charother['ip'], true);
                        $alts = getAlts($charip);
                        foreach ($alts as $username => $alt) {
                            $stmt = mysqli_prepare($db, "SELECT id, name, lastname, level, exp, gold, location, stamina, stamaxa, battlestoday, newmsg, newlog, donor FROM Users WHERE name = ?");
                            $explode = explode("_", $username);
                            $altName = $explode[0];
                            $altLast = $explode[1];
                            mysqli_stmt_bind_param($stmt, "ss", $altName, $altLast);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            $listchar = mysqli_fetch_array($result);
                            if ($listchar['id'] != "" && $listchar['id'] != $char['id']) {
                                ?>
                                <div class="panel panel-warning">
                                    <div class="panel-heading">
                                        <h3 class="panel-title"><a
                                                    href="bio.php?name=<?php echo $listchar['name'] ?>&last=<?php echo $listchar['lastname'] ?>"><?php echo $listchar['name'] . " " . $listchar['lastname'] ?></a>
                                        </h3>
                                    </div>
                                    <div class="panel-body abox">
                                        <i>Level:</i> <?php echo $listchar['level'] ?><br/>
                                        <i>Stamina:</i> <?php echo $listchar['stamina'] . "/" . $listchar['stamaxa'] ?>
                                        <br/>
                                        <?php
                                        if ($listchar['newmsg']) {
                                            ?>
                                            <span class="label label-info">New Message</span>
                                            <?php
                                        }
                                        if ($listchar['newlog']) {
                                            ?>
                                            <span class="label label-danger">New Battle</span>
                                            <?php
                                        }
                                        if ($listchar['donor']) {
                                            ?>
                                            <span class="label label-success">Donor</span>
                                            <?php
                                        }
                                        if ($listchar['lastonline'] >= time() - 900) {
                                            echo "<br/><span class='label label-success'>Online</span>";
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        if ($char['society'] != '') {
                            ?>
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Society Info</h3>
                                </div>
                                <div class="panel-body abox">
                                    <i>Alignment:</i> <?php echo $society['align'] ?><br/>
                                    <i>Size:</i> <?php echo $society['size'] ?><div class="progress">
                                        <div class="progress-bar" role="progressbar"
                                             aria-valuenow="<?php echo $society['size'] ?>"
                                             aria-valuemin="0" aria-valuemax="100"
                                             style="width: <?php echo $society['size'] ?>%">
                                        </div>
                                    </div>
                                    <i>Wars Won:</i> <?php echo $society['wars'] ?><br/>
                                    <i>Wars Lost:</i> <?php echo $society['losses'] ?><br/>
                                    <?php
                                    if ($society['alignment']) {
                                        if ($society['alignment'] == 1) {
                                            echo "<i>Alignment:</i> Good<br/>";
                                        } else {
                                            echo "<i>Alignment:</i> Evil<br/>";
                                        }
                                    }
                                    $members = json_decode($society['members'], true);
                                    if (is_array($members)) {
                                        $membercount = count($members);
                                    } else {
                                        $membercount = 0;
                                    }
                                    echo "<i>Members:</i> " . $membercount . "<br/>";
                                    if ($leadersoc == "yes") {
                                        ?>
                                        <a href="clansettings.php" class="btn btn-primary btn-sm">Edit</a>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php
                        }
                        if ($char['location'] == '22' && $char['society'] == '') {
                            ?>
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Create Society</h3>
                                </div>
                                <div class="panel-body abox">
                                    You are in Tar Valon, why not start your own society?
                                    <a href="makeclan.php">Click Here to start a Society</a>
                                </div>
                            </div>
                            <?php
                        }
                        if ($char['donor']) {
                            ?>
                            <div class="panel panel-success">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Donor Perks</h3>
                                </div>
                                <div class="panel-body abox">
                                    <i>Battles per Day:</i> <?php echo $battlelimit ?><br/>
                                    <i>Max Quests:</i> <?php echo $maxquests ?><br/>
                                    <i>Max Alts:</i> <?php echo $maxalts ?><br/>
                                </div>
                            </div>
                            <?php
                        }
                        if ($char['donor'] == 1) {
                            ?>
                            <div class="panel panel-success">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Alt Status</h3>
                                </div>
                                <div class="panel-body abox">
                                    <?php
                                    $lists = 0;
                                    if (is_array($friends)) {
                                        foreach ($friends as $fid => $fval) {
                                            $listchar = mysqli_fetch_array(mysqli_query($db, "SELECT * FROM Users WHERE id='$fid'"));
                                            if ($fval['2'] == $lists && $listchar['id']) {
                                                $btnstyle = "btn-default";
                                                $lastBattle = mysqli_fetch_array(mysqli_query($db, "SELECT id, starts, ends, winner FROM Contests WHERE type='99' "));
                                                if ($lastBattle != 0) {
                                                    ?>
                                                    <button class="btn btn-sm" disabled="disabled"><?php echo $listchar['name'] . " " . $listchar['lastname'] ?></button>
                                                    <?php
                                                } else {
                                                    ?>
                                                    <a href="duel.php?type=99&target=<?php echo $fid ?>"
                                                       class="btn btn-sm btn-danger">Duel <?php echo $listchar['name'] . " " . $listchar['lastname'] ?></a>
                                                    <?php
                                                }
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php
                        }
                        if ($char['society'] != '') {
                            ?>
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Society Wars</h3>
                                </div>
                                <div class="panel-body abox">
                                    <button class="btn btn-sm" disabled="disabled"><?php echo "Wars: " . $wars ?></button>
                                </div>
                            </div>
                            <?php
                        }
                        $list_profs = json_decode($char['professions'], true);
                        $lists = 1;
                        if (is_array($friends)) {
                            ?>
                            <div class="panel panel-success">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Watch List</h3>
                                </div>
                                <div class="panel-body abox">
                                    <?php
                                    $lists = 1;
                                    if (is_array($friends)) {
                                        foreach ($friends as $fid => $fval) {
                                            // FIXED: SQL injection vulnerability - use prepared statement
                                            $stmt = mysqli_prepare($db, "SELECT * FROM Users WHERE id=?");
                                            mysqli_stmt_bind_param($stmt, "i", $fid);
                                            mysqli_stmt_execute($stmt);
                                            $result = mysqli_stmt_get_result($stmt);
                                            $listchar = mysqli_fetch_array($result);
                                            if ($fval['2'] == $lists && $listchar['id']) {
                                                $btnstyle = "btn-default";
                                                // FIXED: SQL injection vulnerability - use prepared statement
                                                $stmt = mysqli_prepare($db, "SELECT id, starts, ends, winner FROM Contests WHERE type='99'");
                                                mysqli_stmt_execute($stmt);
                                                $result = mysqli_stmt_get_result($stmt);
                                                $lastBattle = mysqli_fetch_array($result);
                                                if ($lastBattle != 0) {
                                                    ?>
                                                    <button class="btn btn-sm btn-<?php echo $btnstyle ?>"
                                                            disabled="disabled"><?php echo $listchar['name'] . " " . $listchar['lastname'] ?></button>
                                                    <?php
                                                } else {
                                                    ?>
                                                    <a href="duel.php?type=99&target=<?php echo $fid ?>"
                                                       class="btn btn-sm btn-danger"><?php echo $listchar['name'] . " " . $listchar['lastname'] ?></a>
                                                    <?php
                                                }
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php
                        }
                        if ($char['society'] != '') {
                            ?>
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Society Members</h3>
                                </div>
                                <div class="panel-body abox">
                                    <?php
                                    $lists = 2;
                                    if (is_array($friends)) {
                                        foreach ($friends as $fid => $fval) {
                                            $stmt = mysqli_prepare($db, "SELECT * FROM Users WHERE id=?");
                                            mysqli_stmt_bind_param($stmt, "i", $fid);
                                            mysqli_stmt_execute($stmt);
                                            $result = mysqli_stmt_get_result($stmt);
                                            $listchar = mysqli_fetch_array($result);
                                            if ($fval['2'] == $lists && $listchar['id']) {
                                                ?>
                                                <a href="bio.php?name=<?php echo $listchar['name'] ?>&last=<?php echo $listchar['lastname'] ?>"
                                                   class="btn btn-sm btn-default btn-block"><?php echo $listchar['name'] . " " . $listchar['lastname'] ?></a>
                                                <?php
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php
                        }
                        if ($char['society'] != '') {
                            ?>
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Enemy List</h3>
                                </div>
                                <div class="panel-body abox">
                                    <?php
                                    $lists = 3;
                                    if (is_array($friends)) {
                                        foreach ($friends as $fid => $fval) {
                                            $stmt = mysqli_prepare($db, "SELECT * FROM Users WHERE id=?");
                                            mysqli_stmt_bind_param($stmt, "i", $fid);
                                            mysqli_stmt_execute($stmt);
                                            $result = mysqli_stmt_get_result($stmt);
                                            $listchar = mysqli_fetch_array($result);
                                            if ($fval['2'] == $lists && $listchar['id']) {
                                                $lastBattle = mysqli_fetch_array(mysqli_query($db, "SELECT id, starts, ends, winner FROM Contests WHERE type='99' "));
                                                if ($lastBattle != 0) {
                                                    ?>
                                                    <button class="btn btn-sm btn-default"
                                                            disabled="disabled"><?php echo $listchar['name'] . " " . $listchar['lastname'] ?></button>
                                                    <?php
                                                } else {
                                                    ?>
                                                    <a href="duel.php?type=99&target=<?php echo $fid ?>"
                                                       class="btn btn-sm btn-danger btn-block"><?php echo $listchar['name'] . " " . $listchar['lastname'] ?></a>
                                                    <?php
                                                }
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php
                        }
                        if ($char['society'] != '') {
                            ?>
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Society Bank</h3>
                                </div>
                                <div class="panel-body abox">
                                    <i>Gold:</i> <?php echo $society['bank'] ?><br/>
                                    <?php
                                    if ($leadersoc == "yes") {
                                        ?>
                                        <a href="bank.php" class="btn btn-primary btn-sm">Deposit/Withdraw</a>
                                        <?php
                                    } else {
                                        ?>
                                        <a href="bank.php" class="btn btn-primary btn-sm">Deposit</a>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php
                        }
                        if ($char['society'] != '') {
                            ?>
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Stats</h3>
                                </div>
                                <div class="panel-body abox">
                                    <i>Gold:</i> <?php echo $gold ?><br/>
                                    <i>Bank:</i> <?php echo displayGold($bgold) ?><br/>
                                    <i>Net Worth:</i> <?php echo displayGold($stats['net_worth']) ?><br/>
                                    <i>Ji:</i> <?php echo $stats['ji'] ?><br/>
                                    <i>Exp:</i> <?php echo number_format($char['exp']) ?><br/>
                                    <i>Exp to Level:</i> <?php echo number_format($char['exp_up'] - $char['exp']) ?>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar"
                                             aria-valuenow="<?php echo $percent_up ?>" aria-valuemin="0"
                                             aria-valuemax="100" style="width: <?php echo $percent_up ?>%">
                                        </div>
                                    </div>
                                    <i>Battles:</i> <?php echo number_format($stats['battles']) ?><br/>
                                    <i>Wins:</i> <?php echo $wins . $win_per ?><br/>
                                    <i>NPC Wins:</i> <?php echo $nwins . $nwin_per ?><br/>
                                    <i>Win Chance:</i> <?php echo intval(100 * ($stats['wins'] + 0.0001) / ($stats['battles'] + 0.0001) + 0.5) ?>
                                    %<br/>
                                    <i>Offensive Wins:</i> <?php echo $owins . $owin_per ?><br/>
                                    <i>Defensive Wins:</i> <?php echo $ewins . $ewin_per ?><br/>
                                    <i>Duels:</i> <?php echo number_format($stats['tot_duels']) ?><br/>
                                    <i>Duel Wins:</i> <?php echo $dwins . $dwin_per ?><br/>
                                    <i>Current Win Streak:</i> <?php echo $stats['win_streak'] ?>
                                    <br/>
                                    <i>Max Win Streak:</i> <?php echo $stats['max_win_streak'] ?><br/>
                                </div>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
include('footer.htm');
?>
