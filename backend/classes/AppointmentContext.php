<?php

require_once __DIR__ . '/../models/Appointment.php';

class AppointmentContext
{
    /** Ключ фильтра/сортировки на странице => реальное выражение колонки в SQL. */
    public const SORTABLE_COLUMNS = [
        'date'    => 'a.appointment_datetime',
        'client'  => 'c.last_name',
        'master'  => 'm.last_name',
        'service' => 's.name',
        'room'    => 'r.number',
        'status'  => 'a.status',
    ];

    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Журнал записей с возможностью фильтрации по клиенту, мастеру и/или услуге
     * (0 означает «без фильтра по этому полю»), сортировкой и постраничным выводом.
     */
    public function getAllWithDetails(
        int $clientId = 0,
        int $masterId = 0,
        int $serviceId = 0,
        string $sortColumn = 'date',
        string $sortDirection = 'DESC',
        int $limit = 0,
        int $offset = 0
    ): array {
        $sql = "SELECT a.id, a.appointment_datetime, a.status, a.comment,
                       c.id AS client_id, c.last_name AS client_last_name, c.first_name AS client_first_name,
                       m.id AS master_id, m.last_name AS master_last_name, m.first_name AS master_first_name,
                       s.id AS service_id, s.name AS service_name,
                       r.id AS room_id, r.number AS room_number
                FROM appointments a
                JOIN clients c ON c.id = a.client_id
                JOIN masters m ON m.id = a.master_id
                JOIN services s ON s.id = a.service_id
                JOIN rooms r ON r.id = a.room_id";

        [$where, $params, $types] = $this->buildFilter($clientId, $masterId, $serviceId);
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        $orderColumn = self::SORTABLE_COLUMNS[$sortColumn] ?? self::SORTABLE_COLUMNS['date'];
        $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderColumn} {$sortDirection}";

        if ($limit > 0) {
            $sql .= ' LIMIT ? OFFSET ?';
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

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }

    public function countWithDetails(int $clientId = 0, int $masterId = 0, int $serviceId = 0): int
    {
        [$where, $params, $types] = $this->buildFilter($clientId, $masterId, $serviceId);

        $sql = 'SELECT COUNT(*) AS cnt FROM appointments a';
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        $stmt = $this->mysqli->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)$row['cnt'];
    }

    private function buildFilter(int $clientId, int $masterId, int $serviceId): array
    {
        $conditions = [];
        $params = [];
        $types = '';

        if ($clientId > 0) {
            $conditions[] = 'a.client_id = ?';
            $params[] = $clientId;
            $types .= 'i';
        }
        if ($masterId > 0) {
            $conditions[] = 'a.master_id = ?';
            $params[] = $masterId;
            $types .= 'i';
        }
        if ($serviceId > 0) {
            $conditions[] = 'a.service_id = ?';
            $params[] = $serviceId;
            $types .= 'i';
        }

        return [implode(' AND ', $conditions), $params, $types];
    }

    /**
     * Записи за указанный месяц (для календаря), с именами клиента/мастера/услуги.
     */
    public function getForMonth(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $end = date('Y-m-d 00:00:00', strtotime($start . ' +1 month'));

        $stmt = $this->mysqli->prepare(
            "SELECT a.id, a.appointment_datetime, a.status,
                    c.last_name AS client_last_name, c.first_name AS client_first_name,
                    m.last_name AS master_last_name, m.first_name AS master_first_name,
                    s.name AS service_name
             FROM appointments a
             JOIN clients c ON c.id = a.client_id
             JOIN masters m ON m.id = a.master_id
             JOIN services s ON s.id = a.service_id
             WHERE a.appointment_datetime >= ? AND a.appointment_datetime < ?
             ORDER BY a.appointment_datetime"
        );
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }

    /**
     * Ищет запись этого же мастера, чей интервал [начало; начало + длительность услуги)
     * пересекается с проверяемым интервалом. Отменённые записи и запись с id = $excludeId
     * (текущая редактируемая) в проверке не участвуют. Возвращает данные пересекающейся
     * записи или null, если мастер свободен.
     */
    public function findConflict(int $masterId, string $startDateTime, int $durationMinutes, int $excludeId = 0): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT a.appointment_datetime, s.duration_minutes
             FROM appointments a
             JOIN services s ON s.id = a.service_id
             WHERE a.master_id = ?
               AND a.status <> 'Отменено'
               AND a.id <> ?
               AND a.appointment_datetime < DATE_ADD(?, INTERVAL ? MINUTE)
               AND DATE_ADD(a.appointment_datetime, INTERVAL s.duration_minutes MINUTE) > ?
             LIMIT 1"
        );
        $stmt->bind_param('iisis', $masterId, $excludeId, $startDateTime, $durationMinutes, $startDateTime);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * То же самое, но проверяет занятость кабинета, а не мастера.
     */
    public function findRoomConflict(int $roomId, string $startDateTime, int $durationMinutes, int $excludeId = 0): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT a.appointment_datetime, s.duration_minutes
             FROM appointments a
             JOIN services s ON s.id = a.service_id
             WHERE a.room_id = ?
               AND a.status <> 'Отменено'
               AND a.id <> ?
               AND a.appointment_datetime < DATE_ADD(?, INTERVAL ? MINUTE)
               AND DATE_ADD(a.appointment_datetime, INTERVAL s.duration_minutes MINUTE) > ?
             LIMIT 1"
        );
        $stmt->bind_param('iisis', $roomId, $excludeId, $startDateTime, $durationMinutes, $startDateTime);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    public function getById(int $id): ?Appointment
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, client_id, master_id, service_id, room_id, appointment_datetime, status, comment
             FROM appointments WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? $this->mapRow($row) : null;
    }

    public function create(
        int $clientId,
        int $masterId,
        int $serviceId,
        int $roomId,
        string $appointmentDate,
        string $status,
        ?string $comment
    ): int {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO appointments (client_id, master_id, service_id, room_id, appointment_datetime, status, comment)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'iiiisss',
            $clientId,
            $masterId,
            $serviceId,
            $roomId,
            $appointmentDate,
            $status,
            $comment
        );
        $stmt->execute();
        $id = (int)$this->mysqli->insert_id;
        $stmt->close();

        return $id;
    }

    public function update(
        int $id,
        int $clientId,
        int $masterId,
        int $serviceId,
        int $roomId,
        string $appointmentDate,
        string $status,
        ?string $comment
    ): bool {
        $stmt = $this->mysqli->prepare(
            "UPDATE appointments
             SET client_id = ?, master_id = ?, service_id = ?, room_id = ?, appointment_datetime = ?, status = ?, comment = ?
             WHERE id = ?"
        );
        $stmt->bind_param(
            'iiiisssi',
            $clientId,
            $masterId,
            $serviceId,
            $roomId,
            $appointmentDate,
            $status,
            $comment,
            $id
        );
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM appointments WHERE id = ?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    private function mapRow(array $row): Appointment
    {
        return new Appointment(
            (int)$row['id'],
            (int)$row['client_id'],
            (int)$row['master_id'],
            (int)$row['service_id'],
            (int)$row['room_id'],
            $row['appointment_datetime'],
            $row['status'],
            $row['comment']
        );
    }
}
