<?php

require_once __DIR__ . '/../models/User.php';

// Работа с таблицей users.
class UserContext
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    // Ищет пользователя по логину (нужно при входе и регистрации). Нет такого - null.
    public function findByLogin(string $login): ?User
    {
        $stmt = $this->mysqli->prepare("SELECT id, login, password FROM users WHERE login = ?");
        $stmt->bind_param('s', $login);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        return new User((int)$row['id'], $row['login'], $row['password']);
    }

    // Добавляет пользователя, пароль приходит уже в виде хеша.
    public function create(string $login, string $passwordHash): int
    {
        $stmt = $this->mysqli->prepare("INSERT INTO users (login, password) VALUES (?, ?)");
        $stmt->bind_param('ss', $login, $passwordHash);
        $stmt->execute();
        $id = (int)$this->mysqli->insert_id;
        $stmt->close();

        return $id;
    }

    // Пользователь по id или null.
    public function getById(int $id): ?User
    {
        $stmt = $this->mysqli->prepare("SELECT id, login, password FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        return new User((int)$row['id'], $row['login'], $row['password']);
    }

    // Записывает новый хеш пароля.
    public function updatePassword(int $id, string $passwordHash): bool
    {
        $stmt = $this->mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $passwordHash, $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}
