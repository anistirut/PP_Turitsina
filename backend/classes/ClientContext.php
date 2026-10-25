<?php

require_once __DIR__ . '/../models/Client.php';

// Работа с таблицей clients: список с поиском, добавление, изменение, удаление.
class ClientContext
{
    // По этим столбцам можно сортировать. Имя столбца в ORDER BY нельзя передать параметром, поэтому проверяем по списку.
    public const SORTABLE_COLUMNS = ['last_name', 'first_name', 'phone', 'birth_date'];

    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    // Список клиентов. $search - часть фамилии, $limit = 0 значит «без ограничения».
    public function getAll(
        string $search = '',
        string $sortColumn = 'last_name',
        string $sortDirection = 'ASC',
        int $limit = 0,
        int $offset = 0
    ): array {
        if (!in_array($sortColumn, self::SORTABLE_COLUMNS, true)) {
            $sortColumn = 'last_name';
        }
        $sortDirection = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT id, last_name, first_name, phone, birth_date FROM clients";
        $params = [];
        $types = '';

        if ($search !== '') {
            $sql .= " WHERE last_name LIKE CONCAT('%', ?, '%')";
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

        $clients = [];
        while ($row = $result->fetch_assoc()) {
            $clients[] = $this->mapRow($row);
        }
        $stmt->close();

        return $clients;
    }

    // Сколько клиентов подходит под поиск (нужно, чтобы посчитать страницы).
    public function countAll(string $search = ''): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM clients";
        $params = [];

        if ($search !== '') {
            $sql .= " WHERE last_name LIKE CONCAT('%', ?, '%')";
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

    // Один клиент по id или null.
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

    // Добавляет клиента, возвращает его id.
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

    // Меняет данные клиента.
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

    // Удаляет клиента.
    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    // Строка из БД -> объект Client.
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
