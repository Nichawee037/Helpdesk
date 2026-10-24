<?php
session_start();
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/lib/mailer.php';

if(isset($_GET['logout'])){session_destroy();header('Location:index.php');exit;}
if(!isset($_SESSION['user'])){
 $error=''; $registerError=''; $registerSuccess='';
 if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['login'])){
   $st=$pdo->prepare("SELECT * FROM users WHERE email=?");
   $st->execute([trim($_POST['email'])]); $u=$st->fetch();
   if($u && password_verify($_POST['password'],$u['password_hash'])){
      $_SESSION['user']=$u; header('Location:index.php'); exit;
   }
   $error='อีเมลหรือรหัสผ่านไม่ถูกต้อง';
 }
 if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['register'])){
   $name=trim($_POST['name']??'');
   $email=trim($_POST['email']??'');
   $password=$_POST['password']??'';
   $confirm=$_POST['confirm_password']??'';
   if($name==='' || $email==='' || $password==='' || $confirm===''){
      $registerError='กรุณากรอกข้อมูลให้ครบทุกช่อง';
   } elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){
      $registerError='รูปแบบอีเมลไม่ถูกต้อง';
   } elseif(strlen($password)<8){
      $registerError='รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
   } elseif($password!==$confirm){
      $registerError='รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
   } else {
      $chk=$pdo->prepare("SELECT id FROM users WHERE email=?");
      $chk->execute([$email]);
      if($chk->fetch()){
         $registerError='อีเมลนี้มีผู้ใช้งานแล้ว';
      } else {
         $hash=password_hash($password,PASSWORD_DEFAULT);
         $ins=$pdo->prepare("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'User')");
         $ins->execute([$name,$email,$hash]);
         $registerSuccess='สมัครสมาชิกเรียบร้อยแล้ว สามารถเข้าสู่ระบบได้';
      }
   }
 }
 $page=$_GET['page']??'login';
 ?>
 <!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Smart IT Helpdesk</title><style>
 body{font-family:Arial;background:#eef3f9;display:grid;place-items:center;min-height:100vh;margin:0}.box{background:#fff;padding:32px;border-radius:16px;width:380px;max-width:92%;box-shadow:0 10px 35px #0001}h2{margin-top:0}input{width:100%;padding:11px;margin:7px 0 14px;box-sizing:border-box;border:1px solid #ccd5e2;border-radius:8px}button{width:100%;padding:11px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:bold;cursor:pointer}.err{color:#b42318}.ok{color:#167345}.switch{text-align:center;margin-top:18px}.switch a{color:#2563eb;text-decoration:none;font-weight:bold}.hint{color:#68758c;font-size:13px;line-height:1.5}
 </style></head><body><div class="box">
 <?php if($page==='register'): ?>
   <h2>สมัครสมาชิก</h2><p class="hint">สมัครใช้งาน Smart IT Helpdesk โดยบัญชีใหม่จะได้รับสิทธิ์เป็น <b>User</b></p>
   <?php if($registerError):?><p class="err"><?=htmlspecialchars($registerError,ENT_QUOTES,'UTF-8')?></p><?php endif;?>
   <?php if($registerSuccess):?><p class="ok"><?=htmlspecialchars($registerSuccess,ENT_QUOTES,'UTF-8')?></p><?php endif;?>
   <form method="post">
    <label>ชื่อ-นามสกุล</label><input name="name" required value="<?=htmlspecialchars($_POST['name']??'',ENT_QUOTES,'UTF-8')?>">
    <label>Email</label><input name="email" type="email" required value="<?=htmlspecialchars($_POST['email']??'',ENT_QUOTES,'UTF-8')?>">
    <label>รหัสผ่าน</label><input name="password" type="password" minlength="8" required>
    <label>ยืนยันรหัสผ่าน</label><input name="confirm_password" type="password" minlength="8" required>
    <button name="register">สมัครสมาชิก</button>
   </form>
   <div class="switch">มีบัญชีแล้ว? <a href="index.php">เข้าสู่ระบบ</a></div>
 <?php else: ?>
   <h2>Smart IT Helpdesk</h2><p>เข้าสู่ระบบ</p>
   <?php if($error):?><p class="err"><?=h($error)?></p><?php endif;?>
   <form method="post"><label>Email</label><input name="email" type="email" required><label>Password</label><input name="password" type="password" required><button name="login">เข้าสู่ระบบ</button></form>
   <div class="switch">ยังไม่มีบัญชี? <a href="index.php?page=register">สมัครสมาชิก</a></div>
 <?php endif; ?>
 </div></body></html><?php exit;
}
$me=$_SESSION['user'];
function h($x){return htmlspecialchars($x??'',ENT_QUOTES,'UTF-8');}
if(isset($_POST['create'])){
 $st=$pdo->prepare("INSERT INTO tickets(user_id,category_id,title,description,priority) VALUES(?,?,?,?,?)");
 $st->execute([$me['id'],$_POST['category'],$_POST['title'],$_POST['description'],$_POST['priority']]);
 $id=$pdo->lastInsertId();$pdo->prepare("INSERT INTO ticket_logs(ticket_id,user_id,new_status,note) VALUES(?,?,?,?)")->execute([$id,$me['id'],'Open','สร้างรายการแจ้งซ่อม']);
 notify_admin_new_ticket($pdo,(int)$id);
 header('Location:index.php?view='.$id);exit;
}
if(isset($_POST['comment'])){
 $pdo->prepare("INSERT INTO ticket_comments(ticket_id,user_id,comment) VALUES(?,?,?)")->execute([$_POST['ticket_id'],$me['id'],$_POST['comment']]);header('Location:index.php?view='.$_POST['ticket_id']);exit;
}
if(isset($_POST['assign']) && $me['role']==='Admin'){
 $pdo->prepare("UPDATE tickets SET technician_id=?,status='Assigned' WHERE id=?")->execute([$_POST['tech'],$_POST['ticket_id']]);
 $pdo->prepare("INSERT INTO ticket_logs(ticket_id,user_id,old_status,new_status,note) VALUES(?,?,?,?,?)")->execute([$_POST['ticket_id'],$me['id'],'Open','Assigned','Admin มอบหมายช่าง']);
 header('Location:index.php?view='.$_POST['ticket_id']);exit;
}
if(isset($_POST['transition']) && $me['role']==='Technician'){
 $ticketId=(int)($_POST['ticket_id']??0);
 $q=$pdo->prepare("SELECT t.status,t.user_id,t.technician_id,u.email AS user_email FROM tickets t JOIN users u ON u.id=t.user_id WHERE t.id=?");
 $q->execute([$ticketId]);$ticket=$q->fetch();
 if($ticket && (int)$ticket['technician_id']===(int)$me['id']){
  $old=$ticket['status'];
  $new=$_POST['new_status']??$old;
  // อนุญาตเฉพาะการเปลี่ยนสถานะที่หน้าระบบกำหนดไว้
  if(($old==='Assigned' && $new==='InProgress') || ($old==='InProgress' && $new==='Resolved')){
   $pdo->prepare("UPDATE tickets SET status=? WHERE id=?")->execute([$new,$ticketId]);
   $note=trim($_POST['note']??'');
   $pdo->prepare("INSERT INTO ticket_logs(ticket_id,user_id,old_status,new_status,note) VALUES(?,?,?,?,?)")->execute([$ticketId,$me['id'],$old,$new,$note]);
   // เมื่อช่างซ่อมเสร็จ ให้ส่งอีเมลแจ้งผู้ที่สร้างรายการแจ้งซ่อม
   if($new==='Resolved' && !empty($ticket['user_email'])){
    notify_ticket($pdo,$ticketId,'ช่างดำเนินการซ่อมเสร็จแล้ว',$ticket['user_email'],'แจ้งซ่อมเสร็จแล้ว - Ticket #'.$ticketId,'ช่างผู้รับผิดชอบ: '.$me['name'].'รายละเอียด: '.$note.'กรุณาเข้าสู่ระบบเพื่อตรวจสอบผลการซ่อมและยืนยันการปิดงาน');
   }
  }
 }
 header('Location:index.php?view='.$ticketId);exit;
}
if(isset($_POST['reject_ticket']) && $me['role']==='User'){
 $q=$pdo->prepare("SELECT status,technician_id FROM tickets WHERE id=? AND user_id=?");$q->execute([$_POST['ticket_id'],$me['id']]);$x=$q->fetch();
 if($x && $x['status']==='Resolved'){
  $note=trim($_POST['reject_reason']??'ยังไม่เรียบร้อย');
  $pdo->prepare("UPDATE tickets SET status='InProgress' WHERE id=?")->execute([$_POST['ticket_id']]);
  $pdo->prepare("INSERT INTO ticket_logs(ticket_id,user_id,old_status,new_status,note) VALUES(?,?,?,?,?)")->execute([$_POST['ticket_id'],$me['id'],'Resolved','InProgress','ผู้แจ้งตีกลับ: '.$note]);
  if($x['technician_id']){ $q2=$pdo->prepare("SELECT email FROM users WHERE id=?");$q2->execute([$x['technician_id']]);$techEmail=$q2->fetchColumn(); notify_ticket($pdo,(int)$_POST['ticket_id'],'ผู้แจ้งตีกลับงาน',$techEmail,'มีการตีกลับ Ticket #'.$_POST['ticket_id'],$note); }
 }
 header('Location:index.php?view='.$_POST['ticket_id']);exit;
}
if(isset($_POST['close_ticket']) && $me['role']==='User'){
 $closeStmt=$pdo->prepare("UPDATE tickets SET status='Closed' WHERE id=? AND user_id=? AND status='Resolved'");
 $closeStmt->execute([$_POST['ticket_id'],$me['id']]);
 if($closeStmt->rowCount()){$pdo->prepare("INSERT INTO ratings(ticket_id,user_id,score,feedback) VALUES(?,?,?,?)")->execute([$_POST['ticket_id'],$me['id'],$_POST['score'],$_POST['feedback']]);$pdo->prepare("INSERT INTO ticket_logs(ticket_id,user_id,old_status,new_status,note) VALUES(?,?,?,?,?)")->execute([$_POST['ticket_id'],$me['id'],'Resolved','Closed','ผู้แจ้งยืนยันผลการซ่อม']);$s=email_settings($pdo);if($s && $s['notify_closed'] && $s['admin_email']) notify_ticket($pdo,(int)$_POST['ticket_id'],'ผู้แจ้งยืนยันผลการซ่อมและปิด Ticket',$s['admin_email'],'Ticket #'.$_POST['ticket_id'].' ถูกปิดงาน','คะแนน: '.$_POST['score'].'/5
ข้อเสนอแนะ: '.($_POST['feedback']??'-'));}
 header('Location:index.php?view='.$_POST['ticket_id']);exit;
}
function tickets($pdo,$me){
 if($me['role']==='User'){$s=$pdo->prepare("SELECT t.*,c.name category,u.name username,te.name tech FROM tickets t LEFT JOIN categories c ON c.id=t.category_id JOIN users u ON u.id=t.user_id LEFT JOIN users te ON te.id=t.technician_id WHERE t.user_id=? ORDER BY t.id DESC");$s->execute([$me['id']]);}
 elseif($me['role']==='Technician'){$s=$pdo->prepare("SELECT t.*,c.name category,u.name username,te.name tech FROM tickets t LEFT JOIN categories c ON c.id=t.category_id JOIN users u ON u.id=t.user_id LEFT JOIN users te ON te.id=t.technician_id WHERE t.technician_id=? OR t.status='Open' ORDER BY t.id DESC");$s->execute([$me['id']]);}
 else{$s=$pdo->query("SELECT t.*,c.name category,u.name username,te.name tech FROM tickets t LEFT JOIN categories c ON c.id=t.category_id JOIN users u ON u.id=t.user_id LEFT JOIN users te ON te.id=t.technician_id ORDER BY t.id DESC");}
 return $s->fetchAll();
}
$rows=tickets($pdo,$me);
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Smart IT Helpdesk</title><style>
*{box-sizing:border-box}body{margin:0;font-family:Segoe UI,Arial;background:#f5f7fb;color:#182235}.side{position:fixed;width:230px;height:100%;background:#13233f;color:white;padding:22px}.side a{display:block;color:#dce6f8;text-decoration:none;padding:12px;border-radius:8px;margin:5px 0}.side a:hover{background:#244a86}.main{margin-left:230px;padding:28px;max-width:1250px}.top{display:flex;justify-content:space-between}.card{background:#fff;padding:20px;border:1px solid #e3e8f0;border-radius:14px;margin:15px 0}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.stat{font-size:28px;font-weight:bold}table{width:100%;border-collapse:collapse}th,td{padding:12px;border-bottom:1px solid #edf0f5;text-align:left}.btn{display:inline-block;padding:9px 13px;background:#2563eb;color:#fff;border:0;border-radius:8px;text-decoration:none}.gray{background:#e8edf4;color:#27364d}.green{background:#dcfce7;color:#166534}.orange{background:#fff3d4;color:#8a5b00}.red{background:#fee2e2;color:#a11}.badge{padding:5px 9px;border-radius:20px;font-size:12px}.open{background:#e8f1ff;color:#245aa8}.assigned{background:#fff3d4;color:#8a5b00}.progress{background:#eee9ff;color:#6545a5}.resolved{background:#e3f8eb;color:#197344}.closed{background:#e9edf2;color:#526070}input,textarea,select{width:100%;padding:10px;border:1px solid #ccd6e4;border-radius:8px;margin:6px 0 14px}textarea{min-height:110px}.two{display:grid;grid-template-columns:1fr 1fr;gap:14px}.log{border-left:3px solid #2563eb;padding:8px 15px;margin:12px}.comment{background:#f7f9fc;padding:12px;border-radius:8px;margin:8px 0}@media(max-width:700px){.side{position:static;width:100%;height:auto}.main{margin:0;padding:16px}.grid,.two{grid-template-columns:1fr}}
</style></head><body>
<aside class="side"><h2>Smart IT</h2><small>IT Helpdesk</small><a href="index.php">Dashboard</a><a href="index.php?create=1">＋ แจ้งซ่อมใหม่</a><a href="index.php?tickets=1">🎫 รายการแจ้งซ่อม</a><?php if($me['role']==='Admin'): ?><a href="admin_settings.php">⚙ ตั้งค่าอีเมล</a><?php endif; ?><a href="index.php?logout=1">ออกจากระบบ</a></aside>
<main class="main"><div class="top"><div><h1>Smart IT Helpdesk</h1><p>สวัสดี <?=h($me['name'])?> · <?=h($me['role'])?></p></div></div>
<?php if(isset($_GET['create'])):?>
<div class="card"><h2>แจ้งซ่อมใหม่</h2><form method="post"><label>หัวข้อ</label><input name="title" required><label>หมวดหมู่</label><select name="category"><?php foreach($pdo->query("SELECT * FROM categories") as $c):?><option value="<?=$c['id']?>"><?=h($c['name'])?></option><?php endforeach;?></select><div class="two"><div><label>ความสำคัญ</label><select name="priority"><option>Low</option><option selected>Medium</option><option>High</option></select></div></div><label>รายละเอียด</label><textarea name="description" required></textarea><button class="btn" name="create">ส่งรายการแจ้งซ่อม</button></form></div>
<?php elseif(isset($_GET['view'])):
 $s=$pdo->prepare("SELECT t.*,c.name category,u.name username,u.email,te.name tech FROM tickets t LEFT JOIN categories c ON c.id=t.category_id JOIN users u ON u.id=t.user_id LEFT JOIN users te ON te.id=t.technician_id WHERE t.id=?");$s->execute([$_GET['view']]);$t=$s->fetch();
 if(!$t || ($me['role']==='User' && $t['user_id']!=$me['id'])) die('<div class="card">ไม่พบรายการหรือไม่มีสิทธิ์เข้าถึง</div>');
?>
<div class="card"><a class="btn gray" href="index.php">← กลับ</a><h2>#<?=$t['id']?> <?=h($t['title'])?></h2><p>ผู้แจ้ง: <?=h($t['username'])?> · หมวด: <?=h($t['category'])?></p><p><?=h($t['description'])?></p><p>ความสำคัญ: <b><?=h($t['priority'])?></b> · สถานะ: <span class="badge <?=strtolower($t['status'])?>"><?=h($t['status'])?></span></p><p>ช่าง: <?=h($t['tech']?:'ยังไม่มอบหมาย')?></p>
<?php if($me['role']==='Admin' && $t['status']==='Open'):?><form method="post"><select name="tech"><?php $ts=$pdo->query("SELECT id,name FROM users WHERE role='Technician'");foreach($ts as $x):?><option value="<?=$x['id']?>"><?=h($x['name'])?></option><?php endforeach;?></select><input type="hidden" name="ticket_id" value="<?=$t['id']?>"><button class="btn" name="assign">มอบหมายช่าง</button></form><?php endif;?>
<?php if($me['role']==='Technician' && $t['status']==='Assigned'):?><form method="post"><input type="hidden" name="ticket_id" value="<?=$t['id']?>"><input type="hidden" name="new_status" value="InProgress"><input type="hidden" name="note" value="ช่างรับงานและเริ่มตรวจสอบ"><button class="btn" name="transition">รับงาน / เริ่มซ่อม</button></form><?php endif;?>
<?php if($me['role']==='Technician' && $t['status']==='InProgress'):?><form method="post"><input type="hidden" name="ticket_id" value="<?=$t['id']?>"><input type="hidden" name="new_status" value="Resolved"><input type="hidden" name="note" value="ซ่อมเสร็จ ส่งให้ผู้แจ้งตรวจสอบ"><button class="btn green" name="transition">แก้ไขแล้ว</button></form><?php endif;?>
<?php if($me['role']==='User' && $t['status']==='Resolved'):?><form method="post"><input type="hidden" name="ticket_id" value="<?=$t['id']?>"><label>คะแนน 1-5</label><select name="score"><option>5</option><option>4</option><option>3</option><option>2</option><option>1</option></select><label>ข้อเสนอแนะ</label><textarea name="feedback"></textarea><button class="btn green" name="close_ticket">ยืนยันผลการซ่อมและปิดงาน</button></form><?php endif;?>
</div>
<div class="card"><h3>ประวัติสถานะ</h3><?php $q=$pdo->prepare("SELECT l.*,u.name FROM ticket_logs l JOIN users u ON u.id=l.user_id WHERE ticket_id=? ORDER BY l.id");$q->execute([$t['id']]);foreach($q as $l):?><div class="log"><b><?=h($l['new_status'])?></b> — <?=h($l['note'])?><br><small><?=h($l['name'])?> · <?=$l['created_at']?></small></div><?php endforeach;?></div>
<div class="card"><h3>ความคิดเห็น</h3><?php $q=$pdo->prepare("SELECT c.*,u.name FROM ticket_comments c JOIN users u ON u.id=c.user_id WHERE ticket_id=? ORDER BY c.id");$q->execute([$t['id']]);foreach($q as $c):?><div class="comment"><b><?=h($c['name'])?></b>: <?=h($c['comment'])?><br><small><?=$c['created_at']?></small></div><?php endforeach;?><form method="post"><input type="hidden" name="ticket_id" value="<?=$t['id']?>"><input name="comment" placeholder="พิมพ์ความคิดเห็น..." required><button class="btn" name="comment">เพิ่มความคิดเห็น</button></form></div>
<?php else:
$count=count($rows);$open=count(array_filter($rows,fn($x)=>$x['status']==='Open'));$working=count(array_filter($rows,fn($x)=>in_array($x['status'],['Assigned','InProgress'])));$closed=count(array_filter($rows,fn($x)=>$x['status']==='Closed'));
?>
<div class="grid"><div class="card"><div>รายการทั้งหมด</div><div class="stat"><?=$count?></div></div><div class="card"><div>รอรับเรื่อง</div><div class="stat"><?=$open?></div></div><div class="card"><div>กำลังดำเนินการ</div><div class="stat"><?=$working?></div></div><div class="card"><div>ปิดงานแล้ว</div><div class="stat"><?=$closed?></div></div></div>
<div class="card"><h2>รายการแจ้งซ่อม</h2><table><tr><th>Ticket</th><th>หัวข้อ</th><th>ผู้แจ้ง</th><th>สถานะ</th><th></th></tr><?php foreach($rows as $r):?><tr><td>#<?=$r['id']?></td><td><?=h($r['title'])?></td><td><?=h($r['username'])?></td><td><span class="badge <?=strtolower($r['status'])?>"><?=h($r['status'])?></span></td><td><a class="btn gray" href="index.php?view=<?=$r['id']?>">ดู</a></td></tr><?php endforeach;?></table></div>
<?php endif;?></main></body></html>