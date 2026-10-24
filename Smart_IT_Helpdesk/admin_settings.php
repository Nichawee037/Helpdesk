<?php
session_start();
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/lib/mailer.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    header('Location: index.php'); exit;
}

function h($x){ return htmlspecialchars($x ?? '', ENT_QUOTES, 'UTF-8'); }
$message=''; $error='';

$settings=$pdo->query("SELECT * FROM email_settings WHERE id=1")->fetch();
if ($settings && empty($settings['admin_email'])) { $settings['admin_email']='654230048@webmail.npru.ac.th'; }
if (!$settings) {
    $pdo->exec("INSERT INTO email_settings (id) VALUES (1)");
    $settings=$pdo->query("SELECT * FROM email_settings WHERE id=1")->fetch();
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['test_email'])) {
    $to=trim($_POST['test_recipient'] ?? '');
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)) $error='กรุณาระบุอีเมลผู้รับสำหรับทดสอบให้ถูกต้อง';
    else { [$ok,$msg]=smtp_send($pdo,$to,'ทดสอบอีเมล Smart IT Helpdesk','นี่คืออีเมลทดสอบจากระบบ Smart IT Helpdesk หากได้รับข้อความนี้ แสดงว่าการตั้งค่า SMTP ใช้งานได้แล้ว'); if($ok)$message=$msg;else$error=$msg; }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_email'])) {
    $host=trim($_POST['smtp_host'] ?? '');
    $port=(int)($_POST['smtp_port'] ?? 587);
    $secure=$_POST['smtp_secure'] ?? 'tls';
    $username=trim($_POST['smtp_username'] ?? '');
    $password=$_POST['smtp_password'] ?? '';
    $from=trim($_POST['from_email'] ?? '');
    $fromName=trim($_POST['from_name'] ?? 'Smart IT Helpdesk');
    $adminEmail=trim($_POST['admin_email'] ?? '');
    $newTicket=isset($_POST['notify_new_ticket'])?1:0;
    $rejected=isset($_POST['notify_rejected'])?1:0;
    $closed=isset($_POST['notify_closed'])?1:0;

    if ($port<1 || $port>65535) $error='SMTP Port ต้องอยู่ระหว่าง 1-65535';
    elseif (!in_array($secure,['tls','ssl','none'],true)) $error='รูปแบบความปลอดภัยไม่ถูกต้อง';
    elseif ($adminEmail!=='' && !filter_var($adminEmail,FILTER_VALIDATE_EMAIL)) $error='อีเมล Admin ไม่ถูกต้อง';
    elseif ($from!=='' && !filter_var($from,FILTER_VALIDATE_EMAIL)) $error='อีเมลผู้ส่งไม่ถูกต้อง';
    else {
        if ($password==='') $password=$settings['smtp_password'];
        $st=$pdo->prepare("UPDATE email_settings SET smtp_host=?,smtp_port=?,smtp_secure=?,smtp_username=?,smtp_password=?,from_email=?,from_name=?,admin_email=?,notify_new_ticket=?,notify_rejected=?,notify_closed=? WHERE id=1");
        $st->execute([$host,$port,$secure,$username,$password,$from,$fromName,$adminEmail,$newTicket,$rejected,$closed]);
        $settings=$pdo->query("SELECT * FROM email_settings WHERE id=1")->fetch();
        $message='บันทึกการตั้งค่าอีเมลเรียบร้อยแล้ว';
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตั้งค่าอีเมล - Smart IT Helpdesk</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:#f5f7fb;color:#182235}.side{position:fixed;width:230px;height:100%;background:#13233f;color:white;padding:22px}.side a{display:block;color:#dce6f8;text-decoration:none;padding:12px;border-radius:8px;margin:5px 0}.side a:hover,.side a.active{background:#244a86}.main{margin-left:230px;padding:28px;max-width:1000px}.top{display:flex;justify-content:space-between;align-items:center}.card{background:#fff;padding:22px;border:1px solid #e3e8f0;border-radius:14px;margin:15px 0;box-shadow:0 2px 8px #0000000a}.two{display:grid;grid-template-columns:1fr 1fr;gap:16px}label{font-weight:600;font-size:14px}input,select{width:100%;padding:11px;border:1px solid #ccd6e4;border-radius:8px;margin:7px 0 15px;background:#fff}.btn{display:inline-block;padding:10px 16px;background:#2563eb;color:#fff;border:0;border-radius:8px;font-weight:bold;text-decoration:none}.gray{background:#e8edf4;color:#27364d}.success{background:#dcfce7;color:#166534;padding:11px;border-radius:8px;margin-bottom:15px}.error{background:#fee2e2;color:#991b1b;padding:11px;border-radius:8px;margin-bottom:15px}.notice{background:#eff6ff;color:#1e4f9a;padding:13px;border-radius:8px;font-size:13px;line-height:1.6}.check{display:flex;gap:10px;align-items:center;margin:12px 0}.check input{width:auto;margin:0}@media(max-width:700px){.side{position:static;width:100%;height:auto}.main{margin:0;padding:16px}.two{grid-template-columns:1fr}}
</style>
</head>
<body>
<aside class="side"><h2>Smart IT</h2><small>IT Helpdesk</small><a href="index.php">Dashboard</a><a href="index.php?create=1">＋ แจ้งซ่อมใหม่</a><a href="index.php?tickets=1">🎫 รายการแจ้งซ่อม</a><a class="active" href="admin_settings.php">⚙ ตั้งค่าอีเมล</a><a href="index.php?logout=1">ออกจากระบบ</a></aside>
<main class="main">
<div class="top"><div><h1>ตั้งค่าอีเมล</h1><p>กำหนด SMTP สำหรับส่งการแจ้งเตือนจากระบบไปยัง Admin</p></div></div>
<?php if($message): ?><div class="success">✓ <?=h($message)?></div><?php endif; ?>
<?php if($error): ?><div class="error">✕ <?=h($error)?></div><?php endif; ?>
<div class="card"><div class="notice"><b>ตัวอย่าง Gmail:</b> SMTP Host = smtp.gmail.com, Port = 587, Security = TLS<br>สำหรับ Gmail ควรใช้ <b>App Password</b> แทนรหัสผ่าน Gmail ปกติเมื่อเปิดใช้งาน 2-Step Verification</div></div>
<form method="post"><div class="card"><h2>ทดสอบการส่งอีเมล</h2><p style="font-size:13px;color:#64748b">กดส่งหลังจากบันทึก SMTP แล้ว ระบบจะลองเชื่อมต่อ SMTP และส่งอีเมลจริง</p><label>อีเมลที่จะรับข้อความทดสอบ</label><input name="test_recipient" type="email" required placeholder="อีเมลที่จะรับข้อความทดสอบ" value="<?=h($settings['admin_email'] ?: '654230048@webmail.npru.ac.th')?>"><button class="btn" type="submit" name="test_email" value="1">ส่งอีเมลทดสอบ</button></div></form>
<form method="post">
<div class="card"><h2>SMTP Server</h2>
<div class="two"><div><label>SMTP Host</label><input name="smtp_host" value="<?=h($settings['smtp_host'])?>" placeholder="smtp.gmail.com"></div><div><label>SMTP Port</label><input name="smtp_port" type="number" value="<?=h($settings['smtp_port'])?>" min="1" max="65535"></div></div>
<div class="two"><div><label>Security</label><select name="smtp_secure"><option value="tls" <?=$settings['smtp_secure']==='tls'?'selected':''?>>TLS / STARTTLS</option><option value="ssl" <?=$settings['smtp_secure']==='ssl'?'selected':''?>>SSL</option><option value="none" <?=$settings['smtp_secure']==='none'?'selected':''?>>None</option></select></div><div><label>SMTP Username</label><input name="smtp_username" value="<?=h($settings['smtp_username'])?>" placeholder="your-email@gmail.com"></div></div>
<label>SMTP Password / App Password</label><input name="smtp_password" type="password" placeholder="เว้นว่างเพื่อใช้รหัสเดิม">
</div>
<div class="card"><h2>ข้อมูลผู้ส่ง</h2><div class="two"><div><label>From Email</label><input name="from_email" type="email" value="<?=h($settings['from_email'])?>" placeholder="your-email@gmail.com"></div><div><label>From Name</label><input name="from_name" value="<?=h($settings['from_name'])?>"></div></div><label>อีเมล Admin สำหรับรับแจ้งเตือน</label><input name="admin_email" type="email" value="<?=h($settings['admin_email'] ?: '654230048@webmail.npru.ac.th')?>" placeholder="654230048@webmail.npru.ac.th"></div>
<div class="card"><h2>รายการแจ้งเตือน</h2><label class="check"><input type="checkbox" name="notify_new_ticket" <?=$settings['notify_new_ticket']?'checked':''?>> แจ้งเตือนเมื่อมี Ticket ใหม่</label><label class="check"><input type="checkbox" name="notify_rejected" <?=$settings['notify_rejected']?'checked':''?>> แจ้งเตือนเมื่อผู้แจ้งตีกลับงาน</label><label class="check"><input type="checkbox" name="notify_closed" <?=$settings['notify_closed']?'checked':''?>> แจ้งเตือนเมื่อ Ticket ถูกปิดงาน</label></div>
<div class="card"><button class="btn" name="save_email">บันทึกการตั้งค่า</button> <a class="btn gray" href="index.php">ยกเลิก</a></div>
</form>
</main></body></html>
