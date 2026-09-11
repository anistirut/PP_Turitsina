<?php

require_once __DIR__ . '/../models/Client.php';

class ClientContext
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function getAll(string $search = ''): array
    {
        if ($search !== '') {
            $stmt = $this->mysqli->prepare(
                "SELECT id, last_name, first_name, phone, birth_date
                 FROM clients
                 WHERE last_name LIKE CONCAT('%', ?, '%')
                 ORDER BY last_name, first_name"
            );
            $stmt->bind_param('s', $search);
        } else {
            $stmt = $this->mysqli->prepare(
                "SELECT id, last_name, first_name, phone, birth_date
                 FROM clients
                 ORDER BY last_name, first_name"
            );
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $clients = [];
        while ($row = $result->fetch_assoc()) {
            $clients[] = $this->mapRow($row);
        }
        $stmt->close();

        return $clients;
    }

    public function getById(int $id): ?Client
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, last_name, first_name, phone, birth_date FROM clients WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? $this->mapRow($row) : null;
    }

    public function create(string $lastName, string $firstName, string $phone, ?string $birthDate): int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO clients (last_name, first_name, phone, birth_date) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('ssss', $lastName, $firstName, $phone, $birthDate);
        $stmt->execute();
        $id = (int)$this->mysqli->insert_id;
        $stmt->close();

        return $id;
    }

    public function update(int $id, string $lastName, string $firstName, string $phone, ?string $birthDate): bool
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE clients SET last_name = ?, first_name = ?, phone = ?, birth_date = ? WHERE id = ?"
        );
        $stmt->bind_param('ssssi', $lastName, $firstName, $phone, $birthDate, $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    private function mapRow(array $row): Client
    {
        return new Client(
            (int)$row['id'],
            $row['last_name'],
            $row['first_name'],
            $row['phone'],
            $row['birth_date']
        );
    }
}
