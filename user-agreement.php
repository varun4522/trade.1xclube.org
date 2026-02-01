<?php
// SESSION START (keep session open if present, but allow public access)
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>User Agreement</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

<style>
*{
  box-sizing:border-box;
}

html,body{
  margin:0;
  padding:0;
  width:100%;
  overflow-x:hidden;
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto;
  background:linear-gradient(135deg,#ffffff,#fff5f0,#ffe8dc);
  color:#1a1a1a;
}

/* HEADER */
.ua-header{
  position:sticky;
  top:0;
  z-index:1000;
  background:rgba(255,255,255,.98);
  backdrop-filter:blur(12px);
  display:flex;
  align-items:center;
  gap:12px;
  padding:14px;
  border-bottom:2px solid rgba(255,107,53,0.2);
}

/* BACK BUTTON */
.back-btn{
  display:flex;
  align-items:center;
  justify-content:center;
  color:#fff;
  text-decoration:none;
}

.back-btn i{
  font-size:20px;
}

/* TITLE */
.ua-header h1{
  font-size:16px;
  font-weight:600;
  margin:0;
}

/* CONTENT CARD */
.ua-card{
  margin:12px;
  padding:14px;
  background:rgba(255,255,255,.95);
  border:2px solid rgba(255,107,53,0.2);
  border-radius:14px;
  line-height:1.7;
  font-size:13px;
  color:#444;
}

/* BOTTOM SAFE SPACE */
.ua-card:last-child{
  margin-bottom:90px;
}

/* AGREE BUTTON */
.agree-btn{
  position:fixed;
  left:12px;
  right:12px;
  bottom:12px;
  height:46px;
  border:none;
  border-radius:14px;
  font-size:15px;
  font-weight:600;
  color:#fff;
  background:linear-gradient(135deg,#ff6b35,#ff8c42,#ffa552);
  box-shadow:0 6px 18px rgba(255,107,53,.45);
}

/* DISABLE HOVER EFFECTS ON MOBILE */
@media (hover:none){
  *:hover{ transform:none!important; }
}
</style>
</head>

<body>

<!-- HEADER -->
<div class="ua-header">
  <a href="index.php" class="back-btn">
    <i class="fas fa-arrow-left"></i>
  </a>
  <h1>User Agreement</h1>
</div>

<!-- CONTENT -->
<div class="ua-card">
  <p>1. To avoid disputes, users must carefully read and understand all platform rules before using this application.</p>

  <p>2. Users are fully responsible for maintaining the confidentiality of their account credentials. All actions performed using the account will be considered authorized.</p>

  <p>3. The platform shall not be responsible for losses caused by incorrect transfers, delays, unauthorized access, or user negligence.</p>

  <p>4. The platform reserves the right to modify, suspend, or terminate services or terms at any time without prior notice.</p>

  <p>5. Users must be legally eligible according to the laws of their country or region.</p>

  <p>6. All investment or earning activities involve risk. Users participate voluntarily and accept possible outcomes.</p>

  <p>7. The platform’s decision will be final in all disputes.</p>
</div>

<!-- AGREE BUTTON -->
<button class="agree-btn" onclick="agreeNow()">I Agree & Continue</button>

<script>
function agreeNow(){
  localStorage.setItem('user_agreed','1');
  window.location.href = "index.php";
}
</script>

</body>
</html>
