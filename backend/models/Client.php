<?php

class Client
{
    public int $id;
    public string $last_name;
    public string $first_name;
    public string $phone;
    public ?string $birth_date;

    public function __construct(
        int $id,
        string $last_name,
        string $first_name,
        string $phone,
        ?string $birth_date = null
    ) {
        $this->id = $id;
        $this->last_name = $last_name;
        $this->first_name = $first_name;
        $this->phone = $phone;
        $this->birth_date = $birth_date;
    }
}
