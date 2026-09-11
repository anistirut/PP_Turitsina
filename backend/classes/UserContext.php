<?php

require_once __DIR__ . '/../models/User.php';

class UserContext
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

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

    public function create(string $login, string $passwordHash): int
    {
        $stmt = $this->mysqli->prepare("INSERT INTO users (login, password) VALUES (?, ?)");
        $stmt->bind_param('ss', $login, $passwordHash);
        $stmt->execute();
        $id = (int)$this->mysqli->insert_id;
        $stmt->close();

        return $id;
    }
}
