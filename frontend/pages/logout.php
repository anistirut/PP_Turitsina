<?php

// Выход: очищаем сессию и возвращаем на страницу входа.
session_start();

require_once __DIR__ . '/../../backend/functions/session.php';

logoutUser();
header('Location: login.php');
exit;
