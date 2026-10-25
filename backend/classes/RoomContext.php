<?php

require_once __DIR__ . '/../models/Room.php';

// Работа с таблицей rooms: список с поиском, добавление, изменение, удаление.
class RoomContext
{
    // По этим столбцам можно сортировать. Имя столбца в ORDER BY нельзя передать параметром, поэтому проверяем по списку.
    public const SORTABLE_COLUMNS = ['number', 'description'];

    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    // Список кабинетов. $search ищет по номеру и описанию, $limit = 0 значит «без ограничения».
    public function getAll(
        string $search = '',
        string $sortColumn = 'number',
        string $sortDirection = 'ASC',
        int $limit = 0,
        int $offset = 0
    ): array {
        if (!in_array($sortColumn, self::SORTABLE_COLUMNS, true)) {
            $sortColumn = 'number';
        }
        $sortDirection = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT id, number, description FROM rooms";
        $params = [];
        $types = '';

        if ($search !== '') {
            $sql .= " WHERE number LIKE CONCAT('%', ?, '%') OR description LIKE CONCAT('%', ?, '%')";
            $params[] = $search;
            $params[] = $search;
            $types .= 'ss';
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

        $rooms = [];
        while ($row = $result->fetch_assoc()) {
            $rooms[] = $this->mapRow($row);
        }
        $stmt->close();

        return $rooms;
    }

    // Сколько кабинетов подходит под поиск (нужно, чтобы посчитать страницы).
    public function countAll(string $search = ''): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM rooms";
        $params = [];

        if ($search !== '') {
            $sql .= " WHERE number LIKE CONCAT('%', ?, '%') OR description LIKE CONCAT('%', ?, '%')";
            $params[] = $search;
            $params[] = $search;
        }

        $stmt = $this->mysqli->prepare($sql);
        if ($params) {
            $stmt->bind_param('ss', ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)$row['cnt'];
    }

    // Один кабинет по id или null.
    public function getById(int $id): ?Room
    {
        $stmt = $this->mysqli->prepare("SELECT id, number, description FROM rooms WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? $this->mapRow($row) : null;
    }

    // Добавляет кабинет, возвращает его id.
    public function create(string $number, ?string $description): int
    {
        $stmt = $this->mysqli->prepare("INSERT INTO rooms (number, description) VALUES (?, ?)");
        $stmt->bind_param('ss', $number, $description);
        $stmt->execute();
        $id = (int)$this->mysqli->insert_id;
        $stmt->close();

        return $id;
    }

    // Меняет данные кабинета.
    public function update(int $id, string $number, ?string $description): bool
    {
        $stmt = $this->mysqli->prepare("UPDATE rooms SET number = ?, description = ? WHERE id = ?");
        $stmt->bind_param('ssi', $number, $description, $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    // Удаляет кабинет.
    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    // Строка из БД -> объект Room.
    private function mapRow(array $row): Room
    {
        return new Room(
            (int)$row['id'],
            $row['number'],
            $row['description']
        );
    }
}
