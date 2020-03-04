<?php

namespace App\Service;

use ClickHouseDB\Client;
use Throwable;

class ClickHouseService implements ClickHouseServiceInterface
{
    protected Client $clickHouseInstance;

    public function connect(): bool
    {
        try {
            $clickHouse = new Client([
                'host' => config('clickhouse.host'),
                'port' => config('clickhouse.port'),
                'username' => config('clickhouse.username'),
                'password' => config('clickhouse.password')
            ]);
            $clickHouse->database(config('clickhouse.database'));
            $clickHouse->setTimeout(1.5);
            $clickHouse->setTimeout(10);
            $clickHouse->setConnectTimeOut(5);
        } catch (Throwable $clickHouseException) {
            return false;
        }
        $this->clickHouseInstance = $clickHouse;
        return true;
    }

    public function createBuffer(string $name, string $source_table, int $min_time, int $max_time, int $min_rows, int $max_rows, int $min_bytes, int $max_bytes): array
    {
        if (empty($name) || empty($source_table) || empty($min_time) || empty($max_time) || empty($min_rows) || empty($max_rows) || empty($min_bytes) || empty($max_bytes)) {
            return [false, 'Empty table parameters'];
        }
        try {
            $this->clickHouseInstance->write('DESC TABLE "' . $source_table . '"');
        } catch (Throwable $clickHouseException) {
            return [false, strtok($clickHouseException->getMessage(), chr(10))];
        }
        $query_string = 'CREATE TABLE IF NOT EXISTS "' . $name . '" as "' . $source_table . '" ENGINE=Buffer("' .
            config('clickhouse.database') . '", "' . $source_table . '", 16, ' .
            $min_time . ', ' . $max_time . ', ' . $min_rows . ', ' . $max_rows . ', ' . $min_bytes . ', ' . $max_bytes . ')';
        try {
            $this->clickHouseInstance->write($query_string);
        } catch (Throwable $clickHouseException) {
            return [false, strtok($clickHouseException->getMessage(), chr(10))];
        }
        return [true, 'OK'];
    }

    public function createTable(string $name, array $columns, string $date_column, array $primary_keys): array
    {
        if (empty($name) || empty($columns) || empty($date_column) || empty($primary_keys)) {
            return [false, 'Empty structure parameters'];
        }
        if (!isset($columns[$date_column]) || $columns[$date_column] !== 'Date') {
            return [false, 'You must specify a column with Date type in table structure'];
        }
        $query_string = 'CREATE TABLE IF NOT EXISTS "' . $name . '" (';
        foreach ($columns as $column_name => $column_type) {
            $query_string_temp1[] = $column_name . ' ' . $column_type;
        }
        $query_string .= implode(', ', $query_string_temp1);
        $query_string .= ') ENGINE=MergeTree(';
        $query_string .= $date_column .', (';
        foreach ($primary_keys as $primary_key) {
            if (!isset($columns[$primary_key])) {
                return [false, 'You must specify primary keys in columns defined in table structure'];
            }
            $query_string_temp2[] = $primary_key;
        }
        $query_string .= implode(', ', $query_string_temp2);
        $query_string .= '), 8192)';
        try {
            $this->clickHouseInstance->write($query_string);
        } catch (Throwable $clickHouseException) {
            return [false, strtok($clickHouseException->getMessage(), chr(10))];
        }
        return [true, 'OK'];
    }

    public function descTable(string $name): array
    {
        if (empty($name)) {
            return [false, 'Empty table name'];
        }
        try {
            $query_data = json_decode($this->clickHouseInstance->write('DESC TABLE "' . $name . '" FORMAT JSON')->rawData(), true);
        } catch (Throwable $clickHouseException) {
            return [false, strtok($clickHouseException->getMessage(), chr(10))];
        }
        return [true, $query_data];
    }

    public function dropTable(string $name): array
    {
        if (empty($name)) {
            return [false, 'Empty table name'];
        }
        try {
            $this->clickHouseInstance->write('DROP TABLE IF EXISTS "' . $name . '"');
        } catch (Throwable $clickHouseException) {
            return [false, strtok($clickHouseException->getMessage(), chr(10))];
        }
        return [true, 'OK'];
    }

    public function tableSize(string $name): array
    {
        if (empty($name)) {
            return [false, 'Empty table name'];
        }
        try {
            $query_data = $this->clickHouseInstance->tableSize($name);
        } catch (Throwable $clickHouseException) {
            return [false, strtok($clickHouseException->getMessage(), chr(10))];
        }
        return [true, $query_data];
    }

    public function insert(string $table, array $data, array $columns): array
    {
        if (empty($table) || empty($data) || empty($columns)) {
            return [false, 'Empty required parameters'];
        }
        if (count($data) !== count($columns)) {
            return [false, 'Data rows count do not match columns count'];
        }
        try {
            $this->clickHouseInstance->insert($table, [$data], $columns);
        } catch (Throwable $clickHouseException) {
            return [false, strtok($clickHouseException->getMessage(), chr(10))];
        }
        return [true, 'OK'];
    }

    public function insertBatch(string $table, array $data, array $columns): array
    {
        if (empty($table) || empty($data) || empty($columns)) {
            return [false, 'Empty required parameters'];
        }
        foreach ($data as $row) {
            if (count($row) !== count($columns)) {
                return [false, 'Data rows count do not match columns count'];
            }
        }
        try {
            $this->clickHouseInstance->insert($table, $data, $columns);
        } catch (Throwable $clickHouseException) {
            return [false, strtok($clickHouseException->getMessage(), chr(10))];
        }
        return [true, 'OK'];
    }
}
