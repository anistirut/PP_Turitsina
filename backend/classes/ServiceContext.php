<?php

require_once __DIR__ . '/../models/Service.php';

class ServiceContext
{
    public const SORTABLE_COLUMNS = ['name', 'duration_minutes', 'price'];

    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function getAll(
        string $search = '',
        string $sortColumn = 'name',
        string $sortDirection = 'ASC',
        int $limit = 0,
        int $offset = 0
    ): array {
        if (!in_array($sortColumn, self::SORTABLE_COLUMNS, true)) {
            $sortColumn = 'name';
        }
        $sortDirection = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT id, name, duration_minutes, price FROM services";
        $params = [];
        $types = '';

        if ($search !== '') {
            $sql .= " WHERE name LIKE CONCAT('%', ?, '%')";
            $params[] = $search;
            $types .= 's';
        }

        $sql .= " ORDER BY {$sortColumn} {$sortDirection}";

        if ($limit > 0) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
        }

        $stmt = $this->mysqli->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $services = [];
        while ($row = $result->fetch_assoc()) {
            $services[] = $this->mapRow($row);
        }
        $stmt->close();

        return $services;
    }

    public function countAll(string $search = ''): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM services";
        $params = [];

        if ($search !== '') {
            $sql .= " WHERE name LIKE CONCAT('%', ?, '%')";
            $params[] = $search;
        }

        $stmt = $this->mysqli->prepare($sql);
        if ($params) {
            $stmt->bind_param('s', ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)$row['cnt'];
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
