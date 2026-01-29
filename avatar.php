<?php
/**
 * Avatar and Character Settings Management
 * 
 * Handles:
 * - Avatar uploads (file and URL)
 * - Password changes
 * - Character bio updates
 * - Character deletion
 * 
 * @version 2.0 - Refactored with security improvements
 */

// Constants
define('AVATAR_UPLOAD_DIR', 'avatar_uploads/');
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif']);
define('MAX_AVATAR_LENGTH', 10000);
define('MAX_INFO_LENGTH', 500);
define('MAX_FILE_SIZE', 5242880); // 5MB
define('MIN_PASSWORD_LENGTH', 5);
define('MAX_PASSWORD_LENGTH', 10);

// Include dependencies
require_once 'admin/connect.php';
require_once 'admin/userdata.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize
$wikilink = 'Game+Settings';
$message = 'Edit character settings';

/**
 * Delete element from array and reindex
 * @deprecated Use array_splice instead
 */
function array_delel(array $array, int $index): array
{
    array_splice($array, $index, 1);
    return $array;
}

/**
 * Remove blank/null entries from array
 */
function delete_blank(array $array): array
{
    return array_filter($array, fn($value) => !empty($value));
}

/**
 * Get character alternate accounts based on IP addresses
 * @param array $ips IP addresses to check
 * @return array Array of alternate character names
 */
function getAlts(array $ips): array
{
    global $db;
    $alts = [];
    
    if (!is_array($ips)) {
        return $alts;
    }
    
    foreach ($ips as $ip) {
        $stmt = mysqli_prepare($db, "SELECT users FROM IP_logs WHERE addy = ?");
        mysqli_stmt_bind_param($stmt, 's', $ip);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_array($result)) {
            $users = json_decode($row['users'], true);
            if (is_array($users)) {
                foreach ($users as $user) {
                    $alts[$user] = true;
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    return array_keys($alts);
}

/**
 * Handle avatar file upload with validation
 * @return array{success: bool, message: string, url: string|null}
 */
function handleAvatarUpload(): array
{
    global $char;
    
    if (!isset($_FILES['newavupload']) || !is_uploaded_file($_FILES['newavupload']['tmp_name'])) {
        return ['success' => false, 'message' => '', 'url' => null];
    }
    
    $file = $_FILES['newavupload'];
    
    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds 5MB limit', 'url' => null];
    }
    
    // Validate file type
    $imageFileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($imageFileType, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'message' => 'Only JPG, JPEG, PNG, GIF, WEBP, and JFIF files are allowed', 'url' => null];
    }
    
    // Validate it's actually an image
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['success' => false, 'message' => 'File is not a valid image', 'url' => null];
    }
    
    // Create directory if needed
    if (!is_dir(AVATAR_UPLOAD_DIR)) {
        mkdir(AVATAR_UPLOAD_DIR, 0755, true);
    }
    
    // Generate safe filename
    $firstName = preg_replace('/[^a-zA-Z0-9]/', '', $char['name']);
    $lastName = preg_replace('/[^a-zA-Z0-9]/', '', $char['lastname']);
    $newFileName = $firstName . '_' . $lastName . '.' . $imageFileType;
    $targetFile = AVATAR_UPLOAD_DIR . $newFileName;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        $baseURL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                   '://' . $_SERVER['HTTP_HOST'];
        return ['success' => true, 'message' => 'Uploaded successfully!', 'url' => $baseURL . '/' . $targetFile];
    }
    
    return ['success' => false, 'message' => 'Error uploading file', 'url' => null];
}

/**
 * Update character avatar in database
 * @param string $avatarURL Avatar URL or empty to clear
 * @return bool Success status
 */
function updateAvatar(string $avatarURL): bool
{
    global $db, $char;
    
    if (empty($avatarURL)) {
        $stmt = mysqli_prepare($db, "UPDATE Users SET avatar = '' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $char['id']);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $char['avatar'] = '';
        return $result;
    }
    
    if (strlen($avatarURL) > MAX_AVATAR_LENGTH) {
        return false;
    }
    
    $stmt = mysqli_prepare($db, "UPDATE Users SET avatar = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $avatarURL, $char['id']);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($result) {
        $char['avatar'] = $avatarURL;
    }
    
    return $result;
}

/**
 * Update character bio/about information
 * @param string $info Character info text
 * @return array{success: bool, message: string}
 */
function updateCharacterInfo(string $info): array
{
    global $db, $char;
    
    if (strlen($info) > MAX_INFO_LENGTH) {
        return ['success' => false, 'message' => 'Info must be a max of 500 characters'];
    }
    
    $sanitizedInfo = htmlspecialchars(stripslashes($info), ENT_QUOTES);
    
    $stmt = mysqli_prepare($db, "UPDATE Users_data SET about = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $sanitizedInfo, $char['id']);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($result) {
        $char['about'] = $sanitizedInfo;
        return ['success' => true, 'message' => 'Character info updated successfully'];
    }
    
    return ['success' => false, 'message' => 'Failed to update character info'];
}

/**
 * Verify password and upgrade legacy SHA1 to bcrypt if needed
 * @param string $inputPassword Plain text password
 * @param string $storedPassword Hashed password from database
 * @param string $email User email for password upgrade (optional)
 * @return bool True if password matches
 */
function verifyPassword(string $inputPassword, string $storedPassword, ?string $email = null): bool
{
    global $db;
    
    // Check if password is bcrypt
    if (strpos($storedPassword, '$2y') === false) {
        // Legacy SHA1 password - could upgrade here if needed
        return false;
    }

    // Verify bcrypt password
    return password_verify($inputPassword, $storedPassword);
}

/**
 * Update account password
 * @param string $oldPass Old password (plain text)
 * @param string $newPass New password (plain text)
 * @param string $confirmPass Confirmation password (plain text)
 * @return array{success: bool, message: string}
 */
function updatePassword(string $oldPass, string $newPass, string $confirmPass): array
{
    global $db, $email, $password;
    
    // Validate passwords match
    if ($newPass !== $confirmPass) {
        return ['success' => false, 'message' => 'New passwords do not match'];
    }
    
    // Validate password length
    if (strlen($newPass) < MIN_PASSWORD_LENGTH || strlen($newPass) > MAX_PASSWORD_LENGTH) {
        return ['success' => false, 'message' => 'Password must be between 5 and 10 characters'];
    }
    
    // Verify old password
    if (!verifyPassword($oldPass, $password)) {
        return ['success' => false, 'message' => 'Old password is incorrect'];
    }
    
    // Hash new password with bcrypt
    $newPasswordHash = password_hash($newPass, PASSWORD_BCRYPT);
    
    $stmt = mysqli_prepare($db, "UPDATE Accounts SET password = ? WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 'ss', $newPasswordHash, $email);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($result) {
        setcookie('password', $newPasswordHash, time() + 99999999, '/');
        return ['success' => true, 'message' => 'Password updated successfully'];
    }
    
    return ['success' => false, 'message' => 'Failed to update password'];
}

/**
 * Delete character and all associated data
 * @param string $confirmEmail Email for confirmation
 * @param string $confirmPass Password for confirmation
 * @return void (redirects or sets error message)
 */
function deleteCharacter(string $confirmEmail, string $confirmPass): void
{
    global $db, $char, $email, $password, $server_name, $message;
    
    // Verify credentials
    if ($confirmEmail !== $email || !verifyPassword($confirmPass, $password)) {
        $message = 'Invalid information given';
        return;
    }
    
    $charId = $char['id'];
    $charName = $char['name'];
    $charLastname = $char['lastname'];
    $societyName = $char['society'];
    
    // Handle society leadership transfer
    if (!empty($societyName)) {
        $stmt = mysqli_prepare($db, "SELECT * FROM Soc WHERE name = ?");
        mysqli_stmt_bind_param($stmt, 's', $societyName);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $society = mysqli_fetch_array($result);
        mysqli_stmt_close($stmt);
        
        if ($society && $society['id']) {
            // Check if this character is the leader
            if (strtolower($charName) === strtolower($society['leader']) && 
                strtolower($charLastname) === strtolower($society['leaderlast'])) {
                
                // Try to transfer leadership to subleader
                if ($society['subs'] > 0) {
                    $subleaders = unserialize($society['subleaders']);
                    if (is_array($subleaders) && !empty($subleaders)) {
                        reset($subleaders);
                        $newLeaderId = key($subleaders);
                        $newLeader = $subleaders[$newLeaderId];
                        
                        // Update society with new leader
                        $stmt = mysqli_prepare($db, "UPDATE Soc SET leader = ?, leaderlast = ? WHERE name = ?");
                        mysqli_stmt_bind_param($stmt, 'sss', $newLeader[0], $newLeader[1], $societyName);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                        
                        // Remove new leader from subleaders
                        unset($subleaders[$newLeaderId]);
                        $subleaders = delete_blank($subleaders);
                        $subs = count($subleaders);
                        $subleadersSerialized = serialize($subleaders);
                        
                        $subQuery = $subs > 0 ? 
                            "UPDATE Soc SET subleaders = ?, subs = ? WHERE name = ?" :
                            "UPDATE Soc SET subleaders = '', subs = ? WHERE name = ?";
                        
                        $stmt = mysqli_prepare($db, $subQuery);
                        if ($subs > 0) {
                            mysqli_stmt_bind_param($stmt, 'sis', $subleadersSerialized, $subs, $societyName);
                        } else {
                            mysqli_stmt_bind_param($stmt, 'is', $subs, $societyName);
                        }
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                } else {
                    // No subleaders, find most experienced member
                    $stmt = mysqli_prepare($db, "SELECT name, lastname FROM Users WHERE society = ? ORDER BY exp DESC LIMIT 1");
                    mysqli_stmt_bind_param($stmt, 's', $societyName);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    if ($newLeader = mysqli_fetch_array($result)) {
                        $stmt2 = mysqli_prepare($db, "UPDATE Soc SET leader = ?, leaderlast = ? WHERE name = ?");
                        mysqli_stmt_bind_param($stmt2, 'sss', $newLeader['name'], $newLeader['lastname'], $societyName);
                        mysqli_stmt_execute($stmt2);
                        mysqli_stmt_close($stmt2);
                    }
                    mysqli_stmt_close($stmt);
                }
            }
            
            // Return vault items
            $vaultId = 10000 + $society['id'];
            $stmt = mysqli_prepare($db, "SELECT id FROM Items WHERE owner = ? AND society > 0 AND society < 10000");
            mysqli_stmt_bind_param($stmt, 'i', $charId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $currentTime = time();
            while ($item = mysqli_fetch_array($result)) {
                $stmt2 = mysqli_prepare($db, "UPDATE Items SET owner = ?, last_moved = ?, istatus = 0 WHERE id = ?");
                mysqli_stmt_bind_param($stmt2, 'iii', $vaultId, $currentTime, $item['id']);
                mysqli_stmt_execute($stmt2);
                mysqli_stmt_close($stmt2);
            }
            mysqli_stmt_close($stmt);
            
            // Update member count
            $memberCount = max(0, $society['members'] - 1);
            $stmt = mysqli_prepare($db, "UPDATE Soc SET members = ? WHERE name = ?");
            mysqli_stmt_bind_param($stmt, 'is', $memberCount, $societyName);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Delete society if no members left
            if ($memberCount <= 0) {
                $stance = unserialize($society['stance']);
                if (is_array($stance)) {
                    foreach ($stance as $otherSocName => $stanceValue) {
                        if ($stanceValue != 0) {
                            $otherSocNameSpaced = str_replace('_', ' ', $otherSocName);
                            $stmt = mysqli_prepare($db, "SELECT stance FROM Soc WHERE name = ?");
                            mysqli_stmt_bind_param($stmt, 's', $otherSocNameSpaced);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            
                            if ($otherSoc = mysqli_fetch_array($result)) {
                                $otherStance = unserialize($otherSoc['stance']);
                                $otherStance[str_replace(' ', '_', $societyName)] = 0;
                                $otherStanceSerialized = serialize($otherStance);
                                
                                $stmt2 = mysqli_prepare($db, "UPDATE Soc SET stance = ? WHERE name = ?");
                                mysqli_stmt_bind_param($stmt2, 'ss', $otherStanceSerialized, $otherSocNameSpaced);
                                mysqli_stmt_execute($stmt2);
                                mysqli_stmt_close($stmt2);
                            }
                            mysqli_stmt_close($stmt);
                        }
                    }
                }
                
                $stmt = mysqli_prepare($db, "DELETE FROM Soc WHERE name = ?");
                mysqli_stmt_bind_param($stmt, 's', $societyName);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Delete businesses and estates
    $stmt = mysqli_prepare($db, "DELETE FROM Profs WHERE owner = ?");
    mysqli_stmt_bind_param($stmt, 'i', $charId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    $stmt = mysqli_prepare($db, "DELETE FROM Estates WHERE owner = ?");
    mysqli_stmt_bind_param($stmt, 'i', $charId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Send notification to admin
    $stmt = mysqli_prepare($db, "SELECT id FROM Users WHERE name = 'The' AND lastname = 'Creator'");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($creator = mysqli_fetch_array($result)) {
        $creatorId = $creator['id'];
        $noteSubject = 'OB: ' . $charName . ' ' . $charLastname;
        $noteBody = 'Born: ' . ($char['born'] ?? 'Unknown') . '<br/>';
        $noteBody .= 'ID: ' . $charId . '<br/>';
        $noteBody .= 'Clan: ' . $societyName . '<br/>';
        $noteBody .= 'IPs:<br/>';
        
        $charIps = unserialize($char['ip']);
        if (is_array($charIps)) {
            foreach ($charIps as $ip) {
                $noteBody .= htmlspecialchars($ip) . '<br/>';
            }
            
            $noteBody .= 'Alts:<br/>';
            $alts = getAlts($charIps);
            foreach ($alts as $altName) {
                $noteBody .= htmlspecialchars($altName) . '<br/>';
            }
        }
        
        $currentTime = time();
        $stmt2 = mysqli_prepare($db, 
            "INSERT INTO Notes (from_id, to_id, del_from, del_to, type, root, sent, cc, subject, body, special) 
             VALUES (?, ?, 0, 0, 0, 0, ?, '', ?, ?, '')");
        mysqli_stmt_bind_param($stmt2, 'iiiss', $creatorId, $creatorId, $currentTime, $noteSubject, $noteBody);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
        
        $stmt2 = mysqli_prepare($db, "UPDATE Users SET msgcheck = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt2, 'ii', $currentTime, $creatorId);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
    }
    mysqli_stmt_close($stmt);
    
    // Clean up IP logs
    $ips = unserialize($char['ip']);
    if (is_array($ips)) {
        $fullname = $charName . '_' . $charLastname;
        
        foreach ($ips as $ip) {
            $stmt = mysqli_prepare($db, "SELECT users FROM IP_logs WHERE addy = ?");
            mysqli_stmt_bind_param($stmt, 's', $ip);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($ipLog = mysqli_fetch_array($result)) {
                $users = unserialize($ipLog['users']);
                if (is_array($users)) {
                    $users = array_filter($users, fn($user) => $user !== $fullname);
                    $users = array_values($users);
                    $usersSerialized = serialize($users);
                    $userCount = count($users);
                    
                    $stmt2 = mysqli_prepare($db, "UPDATE IP_logs SET users = ?, num = ? WHERE addy = ?");
                    mysqli_stmt_bind_param($stmt2, 'sis', $usersSerialized, $userCount, $ip);
                    mysqli_stmt_execute($stmt2);
                    mysqli_stmt_close($stmt2);
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Delete character data
    $stmt = mysqli_prepare($db, "DELETE FROM Users_data WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $charId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    $stmt = mysqli_prepare($db, "DELETE FROM Users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $charId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Clear cookies and redirect
    setcookie('id', '', time() - 3600, '/');
    setcookie('name', '', time() - 3600, '/');
    setcookie('lastname', '', time() - 3600, '/');
    header('Location: ' . $server_name . '/bio.php');
    exit;
}

// ===== MAIN PROCESSING =====

// Validate CSRF token for state-changing operations
$csrf_valid = true;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $csrf_valid = false;
        $message = "Invalid request. Please try again.";
        error_log("CSRF validation failed in avatar.php from IP: " . $_SERVER['REMOTE_ADDR']);
    }
}

// Handle avatar changes
if ($csrf_valid && isset($_POST['changer'])) {
    error_reporting(E_ALL);
    
    // Handle file upload
    $uploadResult = handleAvatarUpload();
    if ($uploadResult['success'] && $uploadResult['url']) {
        if (updateAvatar($uploadResult['url'])) {
            $message = $uploadResult['message'];
        } else {
            $message = 'Failed to save avatar';
        }
    } elseif ($uploadResult['message']) {
        $message = $uploadResult['message'];
    }
    
    // Handle URL-based avatar (if no file was uploaded)
    if (isset($_POST['newav']) && !$uploadResult['success']) {
        $avatarURL = trim($_POST['newav']);
        
        if (!empty($avatarURL)) {
            if (updateAvatar($avatarURL)) {
                $message = 'Character info updated successfully';
            } else {
                $message = 'Problem with chosen avatar';
            }
        } else {
            updateAvatar('');
            $message = 'Avatar cleared';
        }
    }
    
    // Update character bio
    if (isset($_POST['aboutchar'])) {
        $infoResult = updateCharacterInfo($_POST['aboutchar']);
        if ($infoResult['success']) {
            $message = $infoResult['message'];
        } else {
            $message = $infoResult['message'];
        }
    }
}

// Handle password update
if ($csrf_valid && isset($_POST['password'], $_POST['passworda'], $_POST['passwordb']) && 
    !empty($_POST['password']) && !empty($_POST['passworda']) && !empty($_POST['passwordb'])) {
    
    $passwordResult = updatePassword($_POST['password'], $_POST['passworda'], $_POST['passwordb']);
    $message = $passwordResult['message'];
}

// Handle character deletion
if ($csrf_valid && isset($_POST['killer'])) {
    deleteCharacter($_POST['killmail'] ?? '', $_POST['killpass'] ?? '');
}

// Include header
include('header.php');
?>

<div class="row solid-back">
    <div class="col-sm-12">
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class='col-sm-8'>
            <div class='panel panel-info'>
                <div class='panel-heading'>
                    <h3 class='panel-title'>Character Settings</h3>
                </div>
                <div class='panel-body abox'>
                    <form class='form-horizontal' action="avatar.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"/>
                        <!-- Avatar URL -->
                        <div class="form-group form-group-sm">
                            <label for='newav' class='control-label col-sm-4'>Offsite Avatar URL:</label>
                            <div class='col-sm-8'>
                                <input type="text" class="form-control gos-form" name="newav" 
                                       value="<?php echo htmlspecialchars($char['avatar'] ?? ''); ?>" 
                                       id="newav" maxlength="200" />
                                <i>No offensive or adult themed images<br/>
                                Leave input field blank for default avatar</i>
                            </div>
                        </div>

                        <!-- File Upload -->
                        <div class="form-group form-group-sm">
                            <label for='file-upload' class='control-label col-sm-4'>File Upload Avatar:</label>
                            <div class='col-sm-8'>
                                <input accept="image/png, image/gif, image/webp, image/jpeg, image/jfif" 
                                       class="form-control gos-form" name="newavupload" id="file-upload" type="file"/>
                            </div>
                        </div>

                        <input type="hidden" name="changer" value="1" />
                        
                        <!-- Password Section -->
                        <div class="form-group form-group-sm">
                            <label class='control-label col-sm-4'>Change Password:</label>
                        </div>
                        <div class="form-group form-group-sm">
                            <label for='oldpass' class='control-label col-sm-4'>Old Password:</label>
                            <div class='col-sm-8'>
                                <input id='oldpass' type="password" class="form-control gos-form" 
                                       name="password" maxlength="20" />
                            </div>
                        </div>
                        <div class="form-group form-group-sm">
                            <label for='newpass' class='control-label col-sm-4'>New Password:</label>
                            <div class='col-sm-8'>
                                <input id='newpass' type="password" class="form-control gos-form" 
                                       name="passworda" maxlength="20" />
                            </div>
                        </div>
                        <div class="form-group form-group-sm">
                            <label for='conpass' class='control-label col-sm-4'>Confirm Password:</label>
                            <div class='col-sm-8'>
                                <input id='conpass' type="password" class="form-control gos-form" 
                                       name="passwordb" maxlength="20" />
                            </div>
                        </div>
                        
                        <!-- Character Info -->
                        <div class="form-group">
                            <label for='aboutchar' class='control-label col-sm-4'>Character Information:</label>
                            <div class='col-sm-8'>
                                <textarea name="aboutchar" class="form-control gos-form" rows="4" wrap="soft"><?php echo htmlspecialchars($char['about'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <input type="submit" name="submit" value="Update Settings" class="btn btn-info"/>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Kill Character Panel -->
        <div class='col-sm-4'>
            <div class='panel panel-danger'>
                <div class='panel-heading'>
                    <h3 class='panel-title'>Kill Character</h3>
                </div>
                <div class='panel-body solid-back'>
                    <form action="avatar.php" name="killForm" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"/>
                        <p class='text-danger h5'>
                            <i>Character and all of their data will be deleted. Confirm your password and email to delete. 
                            Once done, it cannot be undone!</i>
                        </p>
                        <input type="hidden" name="killer" value="1" />
                        <div class="form-group form-group-sm">
                            <label for='killpass'>Confirm Password:</label>
                            <input type="password" class="form-control gos-form" name="killpass" id="killpass" />
                        </div>
                        <div class="form-group form-group-sm">
                            <label for='killmail'>Confirm E-mail:</label>
                            <input type="text" class="form-control gos-form" name="killmail" id="killmail" />
                        </div>
                        <a href="#" onclick="if(confirm('Warning! Once you do this, this character data will be lost forever! Are you sure?')){document.killForm.submit();} return false;" 
                           class="btn btn-danger btn-sm btn-wrap">Kill Character</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include('footer.htm');
?>