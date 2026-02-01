<?php
// Aapka database connection file include karein
include('config.php'); 

$msg = "";

if(isset($_POST['add_admin'])){
    // Mobile number ko hi hum username ki tarah treat karenge aapke DB ke hisaab se
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    $password = mysqli_real_escape_string($conn, $_POST['password']); 
    // Agar aapka system hashed password use karta hai toh: password_hash($password, PASSWORD_DEFAULT);
    
    // Permissions ka data JSON mein
    $permissions = [
        "crash" => isset($_POST['p_crash']) ? 1 : 0,
        "users" => isset($_POST['p_users']) ? 1 : 0,
        "kyc" => isset($_POST['p_kyc']) ? 1 : 0,
        "trans" => isset($_POST['p_trans']) ? 1 : 0,
        "settings" => isset($_POST['p_settings']) ? 1 : 0
    ];
    $json_perms = json_encode($permissions);

    // SQL Query: Naya admin insert karne ke liye
    // Maan lete hain column names 'mobile', 'password', 'role', 'permissions' hain
    $sql = "INSERT INTO users (mobile, password, role, permissions, status) 
            VALUES ('$mobile', '$password', 'admin', '$json_perms', 'active')";

    if(mysqli_query($conn, $sql)){
        $msg = "<div class='success-msg'>✅ Naya Admin (Staff) Add Ho Gaya!</div>";
    } else {
        $msg = "<div class='error-msg'>❌ Error: " . mysqli_error($conn) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Staff Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #0d1117; font-family: 'Rajdhani', sans-serif; color: #fff; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .admin-card { background: #161b22; width: 380px; padding: 30px; border-radius: 15px; border: 1px solid #30363d; box-shadow: 0 8px 32px rgba(0,0,0,0.5); animation: slideIn 0.5s ease; }
        @keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        h2 { text-align: center; color: #58a6ff; margin-bottom: 25px; text-transform: uppercase; letter-spacing: 2px; }
        .input-box { margin-bottom: 20px; }
        .input-box label { display: block; margin-bottom: 8px; color: #8b949e; font-size: 14px; }
        .input-box input { width: 100%; padding: 12px; background: #0d1117; border: 1px solid #30363d; border-radius: 6px; color: #fff; font-size: 16px; outline: none; box-sizing: border-box; }
        .input-box input:focus { border-color: #58a6ff; }
        
        .perm-section { background: #0d1117; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #21262d; }
        .perm-section h4 { margin: 0 0 10px 0; font-size: 14px; color: #58a6ff; border-bottom: 1px solid #30363d; padding-bottom: 5px; }
        .perm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .perm-item { font-size: 13px; display: flex; align-items: center; cursor: pointer; }
        .perm-item input { margin-right: 8px; accent-color: #58a6ff; }
        
        .btn-add { width: 100%; padding: 15px; background: #238636; border: none; border-radius: 6px; color: #fff; font-weight: bold; font-size: 16px; cursor: pointer; transition: 0.3s; }
        .btn-add:hover { background: #2ea043; transform: scale(1.02); }
        
        .success-msg { background: rgba(35, 134, 54, 0.2); color: #3fb950; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; border: 1px solid #238636; }
        .error-msg { background: rgba(248, 81, 73, 0.2); color: #f85149; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px; border: 1px solid #f85149; }
        .upi-lock { color: #f85149; font-size: 12px; text-align: center; margin-top: 15px; display: block; font-weight: bold; }
    </style>
</head>
<body>

<div class="admin-card">
    <h2>Add New Admin</h2>
    <?php echo $msg; ?>
    
    <form method="POST">
        <div class="input-box">
            <label>Mobile Number (ID)</label>
            <input type="text" name="mobile" placeholder="Enter Mobile Number" required maxlength="10">
        </div>

        <div class="input-box">
            <label>Admin Password</label>
            <input type="password" name="password" placeholder="Create Password" required>
        </div>

        <div class="perm-section">
            <h4>Access Permissions</h4>
            <div class="perm-grid">
                <label class="perm-item"><input type="checkbox" name="p_crash"> Crash Control</label>
                <label class="perm-item"><input type="checkbox" name="p_users"> Manage Users</label>
                <label class="perm-item"><input type="checkbox" name="p_kyc"> KYC Verify</label>
                <label class="perm-item"><input type="checkbox" name="p_trans"> Transactions</label>
                <label class="perm-item"><input type="checkbox" name="p_settings"> Site Settings</label>
            </div>
        </div>

        <button type="submit" name="add_admin" class="btn-add">SAVE NEW ADMIN</button>
        
        <span class="upi-lock">🔒 SECURITY: Staff Admins cannot edit UPI IDs.</span>
    </form>
</div>

</body>
</html>