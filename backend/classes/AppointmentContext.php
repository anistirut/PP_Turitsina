<?php

require_once __DIR__ . '/../models/Appointment.php';

class AppointmentContext
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function getAllWithDetails(): array
    {
        $sql = "SELECT a.id, a.appointment_date, a.status, a.comment,
                       c.id AS client_id, c.last_name AS client_last_name, c.first_name AS client_first_name,
                       m.id AS master_id, m.last_name AS master_last_name, m.first_name AS master_first_name,
                       s.id AS service_id, s.name AS service_name,
                       r.id AS room_id, r.number AS room_number
                FROM appointments a
                JOIN clients c ON c.id = a.client_id
                JOIN masters m ON m.id = a.master_id
                JOIN services s ON s.id = a.service_id
                JOIN rooms r ON r.id = a.room_id
                ORDER BY a.appointment_date DESC";

        $result = $this->mysqli->query($sql);

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function getById(int $id): ?Appointment
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, client_id, master_id, service_id, room_id, appointment_date, status, comment
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
            "INSERT INTO appointments (client_id, master_id, service_id, room_id, appointment_date, status, comment)
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
             SET client_id = ?, master_id = ?, service_id = ?, room_id = ?, appointment_date = ?, status = ?, comment = ?
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
            $row['appointment_date'],
            $row['status'],
            $row['comment']
        );
    }
}
