<?php

require_once __DIR__ . '/../models/User.php';

// Экранирует текст перед выводом в HTML.
function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// После входа в сессии лежит $_SESSION['user'], по нему и узнаём, что пользователь вошёл.
function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

// Закрывает страницу от гостей: отправляет на форму входа.
function requireAuth(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Запоминает пользователя в сессии. Пароль не сохраняем.
function loginUser(User $user): void
{
    $_SESSION['user'] = [
        'id'    => $user->id,
        'login' => $user->login,
    ];
}

// Выход: очищаем и уничтожаем сессию.
function logoutUser(): void
{
    $_SESSION = [];
    session_destroy();
}

/**
 * Текущая строка запроса с применёнными изменениями — используется для ссылок
 * сортировки/пагинации, чтобы не терять активные фильтры и поиск.
 */
function queryStringWith(array $overrides): string
{
    $params = array_merge($_GET, $overrides);

    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }

    return '?' . http_build_query($params);
}

/**
 * Направление сортировки, на которое переключит клик по данному столбцу.
 */
function nextSortDirection(string $column, string $currentSort, string $currentDir): string
{
    return ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
}

/**
 * Стрелка рядом с заголовком столбца, по которому сейчас идёт сортировка.
 */
function sortIndicator(string $column, string $currentSort, string $currentDir): string
{
    if ($currentSort !== $column) {
        return '';
    }

    return $currentDir === 'asc' ? ' ▲' : ' ▼';
}
