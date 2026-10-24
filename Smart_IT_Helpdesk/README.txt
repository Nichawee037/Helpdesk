SMART IT HELPDESK - PHP + MYSQL + EMAIL NOTIFICATION

ติดตั้ง:
1. แตก ZIP ไปไว้ใน C:\xampp\htdocs\Smart_IT_Helpdesk
2. Start Apache + MySQL ใน XAMPP
3. เข้า phpMyAdmin แล้ว Import database/smart_it_helpdesk.sql
4. เข้า http://localhost/Smart_IT_Helpdesk/

บัญชีทดลอง:
User: user@example.com / password
Technician: tech@example.com / password
Admin: admin@example.com / password

ระบบอีเมล:
1. Login เป็น Admin
2. เข้าเมนู "ตั้งค่าอีเมล"
3. สำหรับ Gmail ใช้ smtp.gmail.com, Port 587, TLS และใช้ Gmail App Password
4. ใส่ From Email และ Admin Email
5. กด "บันทึกการตั้งค่า"
6. กด "ส่งอีเมลทดสอบ"
7. เมื่อ User สร้าง Ticket ใหม่ ระบบจะส่งอีเมลไปยัง Admin ตามค่าที่ตั้งไว้
8. เมื่อ User ปิด Ticket ระบบสามารถแจ้ง Admin ได้ตาม checkbox
9. เมื่อตีกลับงาน ระบบแจ้ง Technician (ถ้ามีอีเมลช่าง)

หมายเหตุ:
- ระบบนี้ใช้ SMTP client แบบ PHP เพื่อให้ทดสอบบน XAMPP ได้โดยไม่ต้องติดตั้ง Composer
- Gmail ต้องเปิด 2-Step Verification และสร้าง App Password
- ไม่ควรนำระบบตัวอย่างนี้ขึ้น production โดยไม่เพิ่ม CSRF, rate limiting, secret encryption, audit/security hardening


ระบบสมัครสมาชิก:
- หน้า Login มีปุ่ม "สมัครสมาชิก"
- ผู้สมัครกรอกชื่อ, Email, รหัสผ่าน และยืนยันรหัสผ่าน
- รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร
- Email ซ้ำจะสมัครไม่ได้
- ระบบเข้ารหัสรหัสผ่านด้วย password_hash()
- บัญชีที่สมัครใหม่จะเป็น Role = User เท่านั้น เพื่อป้องกันผู้สมัครตั้งตัวเองเป็น Admin/Technician


ตั้งค่าผู้รับแจ้งเตือนอีเมลใหม่:
- เมื่อมีผู้ใช้ส่งรายการแจ้งซ่อม ระบบจะส่งแจ้งเตือนไปที่ 654230048@webmail.npru.ac.th
- ต้องตั้งค่า SMTP ในเมนู Admin > ตั้งค่าอีเมล และกดบันทึกก่อน ระบบจึงจะส่งอีเมลจริงได้
- หลังตั้งค่า SMTP แล้ว ให้กดส่งอีเมลทดสอบเพื่อตรวจสอบการเชื่อมต่อ
