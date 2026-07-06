<?php

$host = "sql311.infinityfree.com";
$user = "if0_42265566";
$pass = "HxjbTLXWz0cGc";
$dbname = "if0_42265566_phuphadb";

$conn = new mysqli($host, $user, $pass, $dbname);
// ตรวจสอบสถานะการเชื่อมต่อ
if ($conn->connect_error) {
    die("ขออภัย ระบบเชื่อมต่อฐานข้อมูลขัดข้อง");
}

$conn->set_charset("utf8mb4");

$conn->query("UPDATE activities SET type = 'past' WHERE act_date < CURDATE() AND type = 'new'");
