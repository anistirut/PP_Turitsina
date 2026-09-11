<?php

class User
{
    public int $id;
    public string $login;
    public string $password;

    public function __construct(
        int $id,
        string $login,
        string $password
    ) {
        $this->id = $id;
        $this->login = $login;
        $this->password = $password;
    }
}
