<?php

class ReportContext
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Отчёт «Список мастеров с оказываемыми услугами» — мастер и список его услуг.
     */
    public function mastersWithServices(): array
    {
        $sql = "SELECT m.id, m.last_name, m.first_name, s.name AS service_name
                FROM masters m
                LEFT JOIN master_service ms ON ms.master_id = m.id
                LEFT JOIN services s ON s.id = ms.service_id
                ORDER BY m.last_name, m.first_name, s.name";
        $result = $this->mysqli->query($sql);

        $masters = [];
        while ($row = $result->fetch_assoc()) {
            $masterId = (int)$row['id'];
            if (!isset($masters[$masterId])) {
                $masters[$masterId] = [
                    'name'     => $row['last_name'] . ' ' . $row['first_name'],
                    'services' => [],
                ];
            }
            if ($row['service_name'] !== null) {
                $masters[$masterId]['services'][] = $row['service_name'];
            }
        }

        return array_values($masters);
    }

    /**
     * Отчёт «Расписание мастера» — все записи выбранного мастера.
     */
    public function masterSchedule(int $masterId): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT a.appointment_date, c.last_name AS client_last_name, c.first_name AS client_first_name,
                    s.name AS service_name, r.number AS room_number, a.status
             FROM appointments a
             JOIN clients c ON c.id = a.client_id
             JOIN services s ON s.id = a.service_id
             JOIN rooms r ON r.id = a.room_id
             WHERE a.master_id = ?
             ORDER BY a.appointment_date"
        );
        $stmt->bind_param('i', $masterId);
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
     * Отчёт «История посещений клиента».
     */
    public function clientHistory(int $clientId): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT a.appointment_date, m.last_name AS master_last_name, m.first_name AS master_first_name,
                    s.name AS service_name, r.number AS room_number, a.status
             FROM appointments a
             JOIN masters m ON m.id = a.master_id
             JOIN services s ON s.id = a.service_id
             JOIN rooms r ON r.id = a.room_id
             WHERE a.client_id = ?
             ORDER BY a.appointment_date DESC"
        );
        $stmt->bind_param('i', $clientId);
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
     * Отчёт «Количество записей каждого мастера».
     */
    public function appointmentsCountByMaster(): array
    {
        $sql = "SELECT m.last_name, m.first_name, COUNT(a.id) AS appointments_count
                FROM masters m
                LEFT JOIN appointments a ON a.master_id = m.id
                GROUP BY m.id
                ORDER BY appointments_count DESC, m.last_name";
        $result = $this->mysqli->query($sql);

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Отчёт «Популярность услуг» за период (обе даты необязательны).
     */
    public function servicePopularity(?string $dateFrom, ?string $dateTo): array
    {
        $sql = "SELECT s.name, COUNT(a.id) AS appointments_count
                FROM services s
                LEFT JOIN appointments a ON a.service_id = s.id";

        $conditions = [];
        $params = [];
        $types = '';

        if ($dateFrom !== null && $dateFrom !== '') {
            $conditions[] = 'a.appointment_date >= ?';
            $params[] = $dateFrom . ' 00:00:00';
            $types .= 's';
        }
        if ($dateTo !== null && $dateTo !== '') {
            $conditions[] = 'a.appointment_date <= ?';
            $params[] = $dateTo . ' 23:59:59';
            $types .= 's';
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' GROUP BY s.id ORDER BY appointments_count DESC, s.name';

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
}
