<?php

class Room
{
    public int $id;
    public string $number;
    public ?string $description;

    public function __construct(
        int $id,
        string $number,
        ?string $description = null
    ) {
        $this->id = $id;
        $this->number = $number;
        $this->description = $description;
    }
}
