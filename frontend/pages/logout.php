<?php

session_start();

require_once __DIR__ . '/../../backend/functions/session.php';

logoutUser();
header('Location: login.php');
exit;
