<?php

class Appointment
{
    public int $id;
    public int $client_id;
    public int $master_id;
    public int $service_id;
    public int $room_id;
    public string $appointment_date;
    public string $status;
    public ?string $comment;

    public function __construct(
        int $id,
        int $client_id,
        int $master_id,
        int $service_id,
        int $room_id,
        string $appointment_date,
        string $status,
        ?string $comment = null
    ) {
        $this->id = $id;
        $this->client_id = $client_id;
        $this->master_id = $master_id;
        $this->service_id = $service_id;
        $this->room_id = $room_id;
        $this->appointment_date = $appointment_date;
        $this->status = $status;
        $this->comment = $comment;
    }
}
