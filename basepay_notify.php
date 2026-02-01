<?php
// basepay_notify.php

// Error reporting band rakhein taaki output "success" hi rahe
error_reporting(0);
ini_set('display_errors', 0);

// 1. Database Connection
$servername = "localhost";
$username = "chikenof_chick"; 
$password = "chikenof_chick"; 
$dbname = "chikenof_chick"; 

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed"); // Connection fail hone par script ruk jayegi
}

// 2. Basepay Payment Key (Tumhari key)
$key = "eebffc308408408dba442e41808a2a61";

// 3. Data Receive Karo
$data = $_POST;

// Agar koi data nahi aaya, toh ruk jao
if (empty($data)) {
    die("No data");
}

// 4. Signature Verification (Security Check)
$received_sign = $data['sign'];
unset($data['sign']); 
unset($data['signType']); // Docs ke mutabik signType signature mein nahi hota

ksort($data); // Data ko A-Z sort karte hain

$str = "";
foreach ($data as $k => $v) {
    if ($v !== "" && $v !== null) {
        $str .= $k . "=" . $v . "&";
    }
}
$str .= "key=" . $key; // Key append karte hain
$my_sign = md5($str); // Apna MD5 hash banate hain

// 5. Check agar Signature Match hua
if ($my_sign === $received_sign) {
    
    // Check agar Payment Successful hai (tradeResult = 1)
    if ($data['tradeResult'] == "1") {
        
        $mch_order_no = $data['mchOrderNo']; // Order ID jo humne bheji thi
        $amount = floatval($data['amount']); // Amount jo user ne pay kiya
        
        // Step A: Transaction dhoondo
        $stmt = $conn->prepare("SELECT id, user_id, status FROM transactions WHERE mch_order_no = ?");
        $stmt->bind_param("s", $mch_order_no);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            
            // Sirf tab update karo agar wo pehle se 'approved' NA ho (Double add se bachne ke liye)
            if ($row['status'] != 'approved') {
                
                // 1. Transaction Status ko 'approved' karo
                $updateTxn = $conn->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?");
                $updateTxn->bind_param("i", $row['id']);
                $updateTxn->execute();
                $updateTxn->close();
                
                // 2. User ke Wallet (balance) mein paise add karo
                // Tumhari table mein column ka naam 'balance' hai
                $user_id = $row['user_id'];
                $updateUser = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $updateUser->bind_param("di", $amount, $user_id);
                $updateUser->execute();
                $updateUser->close();
                
            }
        }
        $stmt->close();
    }
    
    // Basepay ko batao ki humne data receive kar liya
    echo "success";
    
} else {
    // Agar signature match nahi hua (Fake request)
    echo "fail";
}

$conn->close();
?>