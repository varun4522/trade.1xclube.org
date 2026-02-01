<?php
session_start();
require_once('../config.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle deposit approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $deposit_id = intval($_POST['deposit_id']);
    $admin_id = $_SESSION['admin_id'];
    
    if ($action === 'approve') {
        // Get deposit info
        $result = $conn->query("SELECT user_id, amount FROM deposits WHERE id = $deposit_id");
        if ($result->num_rows === 1) {
            $deposit = $result->fetch_assoc();
            $user_id = $deposit['user_id'];
            $amount = $deposit['amount'];
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Update deposit status
                $conn->query("UPDATE deposits SET status = 'approved', 
                              processed_at = NOW(), processed_by = $admin_id 
                              WHERE id = $deposit_id");
                
                // Update user balance
                $conn->query("UPDATE users SET balance = balance + $amount 
                              WHERE id = $user_id");
                
                // Mark notification as read
                $conn->query("UPDATE admin_notifications SET is_read = TRUE 
                              WHERE deposit_id = $deposit_id");
                
                $conn->commit();
                $message = "Deposit approved successfully";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error processing deposit: " . $e->getMessage();
            }
        }
    } elseif ($action === 'reject') {
        $conn->query("UPDATE deposits SET status = 'rejected', 
                      processed_at = NOW(), processed_by = $admin_id 
                      WHERE id = $deposit_id");
        $conn->query("UPDATE admin_notifications SET is_read = TRUE 
                      WHERE deposit_id = $deposit_id");
        $message = "Deposit rejected";
    }
}

// Get pending deposits
$pending_deposits = $conn->query("
    SELECT d.*, u.username 
    FROM deposits d
    JOIN users u ON d.user_id = u.id
    WHERE d.status = 'pending'
    ORDER BY d.created_at DESC
");

// Get recent processed deposits
$processed_deposits = $conn->query("
    SELECT d.*, u.username 
    FROM deposits d
    JOIN users u ON d.user_id = u.id
    WHERE d.status != 'pending'
    ORDER BY d.processed_at DESC
    LIMIT 20
");

// Get unread notifications
$notifications = $conn->query("
    SELECT n.*, d.amount, u.username
    FROM admin_notifications n
    JOIN deposits d ON n.deposit_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE n.is_read = FALSE
    ORDER BY n.created_at DESC
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Deposit Requests</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Admin Navbar -->
        <nav class="bg-blue-600 text-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-xl font-bold">Trade Club Admin</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="deposits.php" class="px-3 py-2 rounded-md text-sm font-medium bg-blue-700">
                            <i class="fas fa-coins mr-1"></i> Deposits
                        </a>
                        <a href="users.php" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-blue-700">
                            <i class="fas fa-users mr-1"></i> Users
                        </a>
                        <a href="logout.php" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-blue-700">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <?php if (isset($message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Notifications -->
                <div class="md:col-span-1">
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-4 py-5 sm:px-6 bg-blue-50 border-b border-gray-200">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                <i class="fas fa-bell mr-2 text-blue-600"></i>
                                Notifications
                            </h3>
                        </div>
                        <div class="bg-white px-4 py-5 sm:p-6">
                            <?php if ($notifications->num_rows > 0): ?>
                                <ul class="divide-y divide-gray-200">
                                    <?php while ($notification = $notifications->fetch_assoc()): ?>
                                        <li class="py-3">
                                            <div class="flex items-center space-x-4">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-coins text-yellow-500"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 truncate">
                                                        <?php echo $notification['message']; ?>
                                                    </p>
                                                    <p class="text-sm text-gray-500 truncate">
                                                        <?php echo $notification['username']; ?> - ₹<?php echo $notification['amount']; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-sm text-gray-500">No new notifications</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="md:col-span-2 space-y-6">
                    <!-- Pending Deposits -->
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-4 py-5 sm:px-6 bg-yellow-50 border-b border-gray-200">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                <i class="fas fa-clock mr-2 text-yellow-600"></i>
                                Pending Deposit Requests
                            </h3>
                        </div>
                        <div class="bg-white px-4 py-5 sm:p-6">
                            <?php if ($pending_deposits->num_rows > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php while ($deposit = $pending_deposits->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $deposit['id']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $deposit['username']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₹<?php echo number_format($deposit['amount'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo ucfirst($deposit['method']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y g:i A', strtotime($deposit['created_at'])); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="deposit_id" value="<?php echo $deposit['id']; ?>">
                                                            <button type="submit" name="action" value="approve" class="text-green-600 hover:text-green-900 mr-3">
                                                                <i class="fas fa-check"></i> Approve
                                                            </button>
                                                            <button type="submit" name="action" value="reject" class="text-red-600 hover:text-red-900">
                                                                <i class="fas fa-times"></i> Reject
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-gray-500">No pending deposit requests</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Processed Deposits -->
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                <i class="fas fa-history mr-2 text-gray-600"></i>
                                Recent Processed Deposits
                            </h3>
                        </div>
                        <div class="bg-white px-4 py-5 sm:p-6">
                            <?php if ($processed_deposits->num_rows > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php while ($deposit = $processed_deposits->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $deposit['id']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $deposit['username']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₹<?php echo number_format($deposit['amount'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                            <?php echo $deposit['status'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                            <?php echo ucfirst($deposit['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y g:i A', strtotime($deposit['processed_at'])); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-gray-500">No processed deposits yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>