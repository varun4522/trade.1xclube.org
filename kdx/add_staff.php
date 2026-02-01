<?php
// 1. Error debugging on karein
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// 2. DATABASE CONNECTION (Details check karlein)
$db_host = "localhost";
$db_user = "chikenof_chick"; 
$db_pass = "chikenof_chick"; 
$db_name = "chikenof_chick"; 

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// 3. SECURITY CHECK: Login aur Super Admin check
if (!isset($_SESSION['user_id'])) {
    die("<div style='color:white; background:red; padding:20px; text-align:center;'>
            <h2>Pehle Game mein Login karein!</h2>
            <a href='login.php' style='color:yellow;'>Login Link</a>
         </div>");
}

$session_user_id = $_SESSION['user_id'];
$admin_query = mysqli_query($conn, "SELECT role FROM users WHERE id = '$session_user_id'");
$admin_row = mysqli_fetch_assoc($admin_query);

if (!$admin_row || $admin_row['role'] !== 'super_admin') {
    die("<h2 style='color:red; text-align:center;'>Access Denied! Aap Super Admin nahi hain.</h2>");
}

$msg = "";

// 4. ADMIN ADD LOGIC
if (isset($_POST['add_admin'])) {
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $raw_password = $_POST['password']; 
    
    // PASSWORD HASHING ($2y$10$ format generate karega)
    $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
    
    // Unique Referral Code
    $referral_code = "ADM" . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));

    // Permissions JSON
    $perms = json_encode([
        "users" => isset($_POST['p_users']) ? 1 : 0,
        "kyc" => isset($_POST['p_kyc']) ? 1 : 0,
        "trans" => isset($_POST['p_trans']) ? 1 : 0
    ]);

    // SQL Query
    $sql = "INSERT INTO users (username, phone, password, role, permissions, is_admin, status, referral_code, balance) 
            VALUES ('$username', '$phone', '$hashed_password', 'admin', '$perms', 1, 'active', '$referral_code', 0.00)";
    
    if (mysqli_query($conn, $sql)) {
        $msg = "<div class='alert success'>✅ Naya Admin Ban Gaya!<br>Password Hash Ho Gaya.<br>Referral: $referral_code</div>";
    } else {
        $msg = "<div class='alert error'>❌ SQL Error: " . mysqli_error($conn) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Manual Admin Add</title>
    <style>
        body { background: #0b0e14; color: #fff; font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .box { background: #161b22; width: 350px; padding: 30px; border-radius: 12px; border: 1px solid #30363d; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h2 { text-align: center; color: #58a6ff; margin-top: 0; }
        label { font-size: 14px; color: #8b949e; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; margin: 8px 0 15px 0; background: #0d1117; border: 1px solid #30363d; border-radius: 5px; color: #fff; box-sizing: border-box; }
        .btn { width: 100%; padding: 12px; background: #238636; border: none; color: #fff; font-weight: bold; cursor: pointer; border-radius: 5px; transition: 0.3s; }
        .btn:hover { background: #2ea043; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; font-size: 13px; text-align: center; }
        .success { background: rgba(46, 160, 67, 0.15); color: #3fb950; border: 1px solid #238636; }
        .error { background: rgba(248, 81, 73, 0.15); color: #f85149; border: 1px solid #f85149; }
        .perms { background: #0d1117; padding: 12px; border-radius: 5px; font-size: 12px; margin-bottom: 15px; border: 1px solid #30363d; }
        .perms strong { display: block; margin-bottom: 5px; color: #58a6ff; }
    </style>
</head>
<body>

<div class="box">
    <h2>Add New Admin</h2>
    <?php echo $msg; ?>
    <form method="POST" action="">
        <label>Username</label>
        <input type="text" name="username" required placeholder="Admin Name">
        
        <label>Phone Number</label>
        <input type="text" name="phone" required maxlength="10" placeholder="10 Digit Number">
        
        <label>Password</label>
        <input type="password" name="password" required placeholder="Create Password">

        <div class="perms">
            <strong>Permissions:</strong>
            <input type="checkbox" name="p_users" checked> Users <br>
            <input type="checkbox" name="p_kyc" checked> KYC &nbsp;
            <input type="checkbox" name="p_trans" checked> Transactions
        </div>

        <button type="submit" name="add_admin" class="btn">CREATE ADMIN ACCOUNT</button>
    </form>
</div>

</body>
</html>