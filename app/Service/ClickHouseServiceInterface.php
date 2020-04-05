<?php

namespace App\Service;

interface ClickHouseServiceInterface
{
    public function connect(string $user, bool $readonly): bool;

    public function createBuffer(string $name, string $source_table, int $min_time, int $max_time, int $min_rows, int $max_rows, int $min_bytes, int $max_bytes): array;

    public function createTable(string $name, array $columns, string $date_column, array $primary_keys): array;

    public function descTable(string $name): array;

    public function dropTable(string $name): array;

    public function truncateTable(string $name): array;

    public function tableSize(string $name): array;

    public function insert(string $table, array $data, array $columns): array;

    public function insertBatch(string $table, array $data, array $columns): array;

    public function query(string $query): array;
}
