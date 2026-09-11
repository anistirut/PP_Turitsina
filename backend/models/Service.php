<?php

class Service
{
    public int $id;
    public string $name;
    public int $duration_minutes;
    public float $price;

    public function __construct(
        int $id,
        string $name,
        int $duration_minutes,
        float $price
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->duration_minutes = $duration_minutes;
        $this->price = $price;
    }
}
