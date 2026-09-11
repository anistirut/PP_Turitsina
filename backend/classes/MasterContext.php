<?php

require_once __DIR__ . '/../models/Master.php';

class MasterContext
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function getAll(): array
    {
        $result = $this->mysqli->query(
            "SELECT id, last_name, first_name, phone, hire_date FROM masters ORDER BY last_name, first_name"
        );

        $masters = [];
        while ($row = $result->fetch_assoc()) {
            $masters[] = $this->mapRow($row);
        }

        return $masters;
    }

    /**
     * Список мастеров вместе со строкой оказываемых ими услуг (через JOIN и GROUP_CONCAT).
     * Возвращает массив вида ['master' => Master, 'services' => string].
     */
    public function getAllWithServices(): array
    {
        $sql = "SELECT m.id, m.last_name, m.first_name, m.phone, m.hire_date,
                       GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') AS services
                FROM masters m
                LEFT JOIN master_service ms ON ms.master_id = m.id
                LEFT JOIN services s ON s.id = ms.service_id
                GROUP BY m.id
                ORDER BY m.last_name, m.first_name";
        $result = $this->mysqli->query($sql);

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'master'   => $this->mapRow($row),
                'services' => $row['services'] ?? '',
            ];
        }

        return $rows;
    }

    public function getById(int $id): ?Master
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, last_name, first_name, phone, hire_date FROM masters WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? $this->mapRow($row) : null;
    }

    public function create(string $lastName, string $firstName, string $phone, string $hireDate): int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO masters (last_name, first_name, phone, hire_date) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('ssss', $lastName, $firstName, $phone, $hireDate);
        $stmt->execute();
        $id = (int)$this->mysqli->insert_id;
        $stmt->close();

        return $id;
    }

    public function update(int $id, string $lastName, string $firstName, string $phone, string $hireDate): bool
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE masters SET last_name = ?, first_name = ?, phone = ?, hire_date = ? WHERE id = ?"
        );
        $stmt->bind_param('ssssi', $lastName, $firstName, $phone, $hireDate, $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM masters WHERE id = ?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Идентификаторы услуг, которые оказывает мастер (таблица master_service).
     */
    public function getServiceIds(int $masterId): array
    {
        $stmt = $this->mysqli->prepare("SELECT service_id FROM master_service WHERE master_id = ?");
        $stmt->bind_param('i', $masterId);
        $stmt->execute();
        $result = $stmt->get_result();

        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['service_id'];
        }
        $stmt->close();

        return $ids;
    }

    /**
     * Карта «мастер → список id его услуг» для всех мастеров сразу
     * (используется, чтобы на форме записи показывать только услуги выбранного мастера).
     */
    public function getAllServiceIds(): array
    {
        $result = $this->mysqli->query("SELECT master_id, service_id FROM master_service");

        $map = [];
        while ($row = $result->fetch_assoc()) {
            $map[(int)$row['master_id']][] = (int)$row['service_id'];
        }

        return $map;
    }

    /**
     * Полностью заменяет список услуг мастера на переданный (используется формой назначения услуг).
     */
    public function setServices(int $masterId, array $serviceIds): void
    {
        $this->mysqli->begin_transaction();

        $delete = $this->mysqli->prepare("DELETE FROM master_service WHERE master_id = ?");
        $delete->bind_param('i', $masterId);
        $delete->execute();
        $delete->close();

        if (!empty($serviceIds)) {
            $insert = $this->mysqli->prepare(
                "INSERT INTO master_service (master_id, service_id) VALUES (?, ?)"
            );
            foreach ($serviceIds as $serviceId) {
                $serviceId = (int)$serviceId;
                $insert->bind_param('ii', $masterId, $serviceId);
                $insert->execute();
            }
            $insert->close();
        }

        $this->mysqli->commit();
    }

    private function mapRow(array $row): Master
    {
        return new Master(
            (int)$row['id'],
            $row['last_name'],
            $row['first_name'],
            $row['phone'],
            $row['hire_date']
        );
    }
}
