<?php

class Master
{
    public int $id;
    public string $last_name;
    public string $first_name;
    public string $phone;
    public string $hire_date;

    public function __construct(
        int $id,
        string $last_name,
        string $first_name,
        string $phone,
        string $hire_date
    ) {
        $this->id = $id;
        $this->last_name = $last_name;
        $this->first_name = $first_name;
        $this->phone = $phone;
        $this->hire_date = $hire_date;
    }
}
