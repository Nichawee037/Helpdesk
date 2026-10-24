<?php
function email_settings(PDO $pdo){
    $s=$pdo->query("SELECT * FROM email_settings WHERE id=1")->fetch();
    return $s ?: null;
}
function smtp_send(PDO $pdo,string $to,string $subject,string $body): array {
    $s=email_settings($pdo);
    if(!$s || !$s['smtp_host'] || !$s['smtp_username'] || !$s['smtp_password'] || !$s['from_email']) return [false,'ยังไม่ได้ตั้งค่า SMTP ให้ครบ'];
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)) return [false,'อีเมลผู้รับไม่ถูกต้อง'];
    $host=$s['smtp_host']; $port=(int)$s['smtp_port']; $secure=$s['smtp_secure'];
    $transport=$secure==='ssl'?'ssl://'.$host:$host;
    $errno=0;$errstr='';
    $fp=@stream_socket_client($transport.':'.$port,$errno,$errstr,15,STREAM_CLIENT_CONNECT);
    if(!$fp) return [false,"เชื่อมต่อ SMTP ไม่ได้: $errstr ($errno)"];
    stream_set_timeout($fp,15);
    $read=function()use($fp){$data='';while(($line=fgets($fp,515))!==false){$data.=$line;if(strlen($line)<4||$line[3]===' ')break;}return $data;};
    $cmd=function($c,$codes)use($fp,$read){fwrite($fp,$c."\r\n");$r=$read();$code=(int)substr($r,0,3);return in_array($code,(array)$codes,true)?$r:false;};
    $greeting=$read(); if((int)substr($greeting,0,3)!==220){fclose($fp);return [false,'SMTP ไม่ตอบรับ'];}
    $hello=$cmd('EHLO localhost',250); if($hello===false){fclose($fp);return [false,'EHLO ไม่สำเร็จ'];}
    if($secure==='tls'){
        $r=$cmd('STARTTLS',220); if($r===false){fclose($fp);return [false,'SMTP ไม่รองรับ STARTTLS'];}
        if(!@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){fclose($fp);return [false,'เปิด TLS ไม่สำเร็จ'];}
        if($cmd('EHLO localhost',250)===false){fclose($fp);return [false,'EHLO หลัง TLS ไม่สำเร็จ'];}
    }
    if($cmd('AUTH LOGIN',334)===false || $cmd(base64_encode($s['smtp_username']),334)===false || $cmd(base64_encode($s['smtp_password']),235)===false){fclose($fp);return [false,'SMTP Authentication ไม่สำเร็จ'];}
    if($cmd('MAIL FROM:<'.$s['from_email'].'>',250)===false || $cmd('RCPT TO:<'.$to.'>',[250,251])===false || $cmd('DATA',354)===false){fclose($fp);return [false,'SMTP รับข้อมูลผู้รับไม่ได้'];}
    $fromName=mb_encode_mimeheader($s['from_name'],'UTF-8','B');
    $sub=mb_encode_mimeheader($subject,'UTF-8','B');
    $headers="From: {$fromName} <{$s['from_email']}>\r\nTo: {$to}\r\nSubject: {$sub}\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    $payload=$headers."\r\n".$body;
    $payload=preg_replace('/\r?\n\./',"\r\n..",$payload)."\r\n.";
    fwrite($fp,$payload."\r\n");$r=$read();$ok=(int)substr($r,0,3)===250;
    $cmd('QUIT',221);fclose($fp);
    return $ok?[true,'ส่งอีเมลสำเร็จ']:[false,'SMTP ส่งอีเมลไม่สำเร็จ: '.trim($r)];
}
function notify_admin_new_ticket(PDO $pdo,int $ticketId): void {
    $s=email_settings($pdo); if(!$s || !$s['notify_new_ticket']) return;
    $q=$pdo->prepare("SELECT t.*,u.name username,u.email,c.name category FROM tickets t JOIN users u ON u.id=t.user_id LEFT JOIN categories c ON c.id=t.category_id WHERE t.id=?");$q->execute([$ticketId]);$t=$q->fetch();if(!$t)return;
    $body="มีรายการแจ้งซ่อมใหม่\n\nTicket #{$t['id']}\nผู้แจ้ง: {$t['username']}\nอีเมล: {$t['email']}\nหัวข้อ: {$t['title']}\nหมวดหมู่: {$t['category']}\nความสำคัญ: {$t['priority']}\nรายละเอียด: {$t['description']}\nวันที่แจ้ง: {$t['created_at']}\n\nกรุณาเข้าสู่ระบบ Smart IT Helpdesk เพื่อดูรายละเอียดและมอบหมายช่าง";
    smtp_send($pdo,$s['admin_email'],"มีรายการแจ้งซ่อมใหม่ #{$t['id']}",$body);
}
function notify_ticket(PDO $pdo,int $ticketId,string $event,string $recipient,string $subject,string $extra=''): void {
    if(!$recipient)return;
    $q=$pdo->prepare("SELECT t.*,u.name username,c.name category,te.name tech FROM tickets t JOIN users u ON u.id=t.user_id LEFT JOIN categories c ON c.id=t.category_id LEFT JOIN users te ON te.id=t.technician_id WHERE t.id=?");$q->execute([$ticketId]);$t=$q->fetch();if(!$t)return;
    $body="Smart IT Helpdesk\n\n{$event}\nTicket #{$t['id']}\nหัวข้อ: {$t['title']}\nผู้แจ้ง: {$t['username']}\nสถานะ: {$t['status']}\nช่าง: ".($t['tech']?:'ยังไม่มอบหมาย')."\n{$extra}\n\nกรุณาเข้าสู่ระบบเพื่อตรวจสอบรายละเอียด";
    smtp_send($pdo,$recipient,$subject,$body);
}
