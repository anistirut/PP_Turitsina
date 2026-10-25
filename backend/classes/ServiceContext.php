<?php

require_once __DIR__ . '/../models/Service.php';

// Работа с таблицей services: список с поиском, добавление, изменение, удаление.
class ServiceContext
{
    // По этим столбцам можно сортировать. Имя столбца в ORDER BY нельзя передать параметром, поэтому проверяем по списку.
    public const SORTABLE_COLUMNS = ['name', 'duration_minutes', 'price'];

    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    // Список услуг. $search - часть названия, $limit = 0 значит «без ограничения».
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

    // Сколько услуг подходит под поиск (нужно, чтобы посчитать страницы).
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

    // Одна услуга по id или null.
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

    // Добавляет услугу, возвращает её id.
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

    // Меняет данные услуги.
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

    // Удаляет услугу.
    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM services WHERE id = ?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    // Строка из БД -> объект Service.
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
