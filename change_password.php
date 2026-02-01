<?php
include 'db.php';
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$message = "";
$status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // 1. Check karein ki new passwords match ho rahe hain
    if ($new_pass !== $confirm_pass) {
        $message = "New passwords do not match!";
        $status = "error";
    } else {
        // 2. Database se purana password check karein
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // Note: Agar aapne password hash kiya hai toh password_verify() use karein
        if ($old_pass == $user['password']) {
            // 3. Password update karein
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $upd_stmt = $conn->prepare($update_sql);
            $upd_stmt->bind_param("si", $new_pass, $user_id);
            
            if ($upd_stmt->execute()) {
                $message = "Password updated successfully!";
                $status = "success";
            } else {
                $message = "Something went wrong!";
                $status = "error";
            }
        } else {
            $message = "Old password is incorrect!";
            $status = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Trade Club</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; background: #0f172a; color: white; }
        .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <div class="px-6 py-8 flex items-center justify-between">
        <a href="profile.php" class="w-10 h-10 glass rounded-full flex items-center justify-center">
            <i class="fas fa-chevron-left text-blue-400"></i>
        </a>
        <h1 class="text-xl font-bold">Security</h1>
        <div class="w-10"></div>
    </div>

    <div class="container mx-auto px-6 max-w-md">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-blue-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-key text-3xl text-blue-400"></i>
            </div>
            <h2 class="text-2xl font-bold">Change Password</h2>
            <p class="text-gray-400 text-sm">Keep your account secure</p>
        </div>

        <?php if($message): ?>
            <div class="p-4 rounded-2xl mb-6 text-sm font-medium <?php echo $status == 'success' ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-red-500/20 text-red-400 border border-red-500/30'; ?>">
                <i class="fas <?php echo $status == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4">
            <div class="space-y-2">
                <label class="text-xs text-gray-400 ml-2 uppercase tracking-widest font-semibold">Current Password</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-500"></i>
                    <input type="password" name="old_password" required placeholder="••••••••" 
                           class="w-full bg-slate-800 border border-white/10 p-4 pl-12 rounded-2xl outline-none focus:border-blue-500 transition-all">
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-xs text-gray-400 ml-2 uppercase tracking-widest font-semibold">New Password</label>
                <div class="relative">
                    <i class="fas fa-shield-alt absolute left-4 top-1/2 -translate-y-1/2 text-gray-500"></i>
                    <input type="password" name="new_password" required placeholder="••••••••" 
                           class="w-full bg-slate-800 border border-white/10 p-4 pl-12 rounded-2xl outline-none focus:border-blue-500 transition-all">
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-xs text-gray-400 ml-2 uppercase tracking-widest font-semibold">Confirm New Password</label>
                <div class="relative">
                    <i class="fas fa-check-double absolute left-4 top-1/2 -translate-y-1/2 text-gray-500"></i>
                    <input type="password" name="confirm_password" required placeholder="••••••••" 
                           class="w-full bg-slate-800 border border-white/10 p-4 pl-12 rounded-2xl outline-none focus:border-blue-500 transition-all">
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 py-4 rounded-2xl font-bold shadow-lg shadow-blue-600/30 transition-all mt-6">
                Update Password
            </button>
        </form>
    </div>

</body>
</html>