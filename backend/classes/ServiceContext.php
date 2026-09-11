<?php

require_once __DIR__ . '/../models/Service.php';

class ServiceContext
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function getAll(): array
    {
        $result = $this->mysqli->query(
            "SELECT id, name, duration_minutes, price FROM services ORDER BY name"
        );

        $services = [];
        while ($row = $result->fetch_assoc()) {
            $services[] = $this->mapRow($row);
        }

        return $services;
    }

    public function getById(int $id): ?Service
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, name, duration_minutes, price FROM services WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? $this->mapRow($row) : null;
    }

    public function create(string $name, int $durationMinutes, float $price): int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO services (name, duration_minutes, price) VALUES (?, ?, ?)"
        );
        $stmt->bind_param('sid', $name, $durationMinutes, $price);
        $stmt->execute();
        $id = (int)$this->mysqli->insert_id;
        $stmt->close();

        return $id;
    }

    public function update(int $id, string $name, int $durationMinutes, float $price): bool
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE services SET name = ?, duration_minutes = ?, price = ? WHERE id = ?"
        );
        $stmt->bind_param('sidi', $name, $durationMinutes, $price, $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM services WHERE id = ?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    private function mapRow(array $row): Service
    {
        return new Service(
            (int)$row['id'],
            $row['name'],
            (int)$row['duration_minutes'],
            (float)$row['price']
        );
    }
}
