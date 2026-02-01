<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$db = new mysqli("localhost","chikenof_chick","chikenof_chick","chikenof_chick");
$user_id = $_SESSION['user_id'];

// Fetch claimed investments only
$q = $db->prepare("
    SELECT *
    FROM user_investments
    WHERE user_id = ? AND status = 'claimed'
    ORDER BY id DESC
");
$q->bind_param("i", $user_id);
$q->execute();
$result = $q->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<title>Investment History</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-white p-4">

<h1 class="text-xl font-bold mb-4">📜 Closed / Investment History</h1>

<?php if($result->num_rows == 0): ?>
    <div class="bg-slate-800 p-4 rounded-xl text-center text-gray-400">
        No completed investments yet
    </div>
<?php endif; ?>

<div class="space-y-4">
<?php while($row = $result->fetch_assoc()): ?>
<div class="bg-slate-800 p-4 rounded-xl border border-white/10">
    <div class="flex justify-between items-center mb-2">
        <h2 class="font-bold text-lg">₹<?= number_format($row['amount']) ?></h2>
        <span class="text-xs bg-green-500 px-2 py-1 rounded-full">COMPLETED</span>
    </div>

    <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <p class="text-gray-400">Start</p>
            <p><?= date("d M Y h:i A", $row['start_time']) ?></p>
        </div>
        <div>
            <p class="text-gray-400">End</p>
            <p><?= date("d M Y h:i A", $row['end_time']) ?></p>
        </div>
        <div>
            <p class="text-gray-400">Profit</p>
            <p class="text-green-400 font-bold">
                ₹<?= number_format($row['return_amount'] - $row['amount']) ?>
            </p>
        </div>
        <div>
            <p class="text-gray-400">Total Return</p>
            <p class="text-green-400 font-bold">
                ₹<?= number_format($row['return_amount']) ?>
            </p>
        </div>
    </div>
</div>
<?php endwhile; ?>
</div>

<a href="index.php" class="block mt-6 text-center text-indigo-400">
    ⬅ Back to Home
</a>

</body>
</html>
