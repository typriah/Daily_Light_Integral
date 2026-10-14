<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "dlidb";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Koneksi DB gagal: " . $conn->connect_error);