<?php

require_once __DIR__ . '/../models/Room.php';

class RoomContext
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function getAll(): array
    {
        $result = $this->mysqli->query(
            "SELECT id, number, description FROM rooms ORDER BY number"
        );

        $rooms = [];
        while ($row = $result->fetch_assoc()) {
            $rooms[] = $this->mapRow($row);
        }

        return $rooms;
    }

    public function getById(int $id): ?Room
    {
        $stmt = $this->mysqli->prepare("SELECT id, number, description FROM rooms WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? $this->mapRow($row) : null;
    }

    public function create(string $number, ?string $description): int
    {
        $stmt = $this->mysqli->prepare("INSERT INTO rooms (number, description) VALUES (?, ?)");
        $stmt->bind_param('ss', $number, $description);
        $stmt->execute();
        $id = (int)$this->mysqli->insert_id;
        $stmt->close();

        return $id;
    }

    public function update(int $id, string $number, ?string $description): bool
    {
        $stmt = $this->mysqli->prepare("UPDATE rooms SET number = ?, description = ? WHERE id = ?");
        $stmt->bind_param('ssi', $number, $description, $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    private function mapRow(array $row): Room
    {
        return new Room(
            (int)$row['id'],
            $row['number'],
            $row['description']
        );
    }
}
