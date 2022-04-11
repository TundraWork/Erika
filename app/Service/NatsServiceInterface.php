<?php

namespace App\Service;

interface NatsServiceInterface
{
    public function __construct();

    public function connect(): array;

    public function publishInsert($bucket, $columns, $values): array;
}
