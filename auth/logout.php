<?php
session_start();
require_once __DIR__ . '/../config/db.php';

session_destroy();
header('Location: ../auth/login.php');
exit;
