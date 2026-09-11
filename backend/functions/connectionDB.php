<?php

try {
    $mysqli = new mysqli('localhost', 'root', '', 'beauty_salon');
} catch (mysqli_sql_exception $e) {
    die('Не удалось подключиться к базе данных. Попробуйте позже.');
}
