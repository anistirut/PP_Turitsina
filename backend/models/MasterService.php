<?php

class MasterService
{
    public int $master_id;
    public int $service_id;

    public function __construct(
        int $master_id,
        int $service_id
    ) {
        $this->master_id = $master_id;
        $this->service_id = $service_id;
    }
}
