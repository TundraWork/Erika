<?php

namespace App\Service;

use \Nats\Connection;

class NatsService {
    protected Connection $natsInstance;

    public function __construct() {
        $this->connect();
    }

    public function connect() {
        $encoder = new \Nats\Encoders\JSONEncoder();
        $options = new \Nats\ConnectionOptions();
        $client = new \Nats\EncodedConnection($options, $encoder);
        $this->natsInstance = $client;
        try {
            $this->natsInstance->connect(1);
        } catch {
            $this->natsInstance = null;
        }
    }

    public function publishInsert($bucket, $columns, $values) {
        if ($this->natsInstance == null) {
            return null;
        }
        return $this->natsInstance->publish('erika.insert' . $bucket, [
            'columns' => $columns,
            'values' => $values
        ]);
    }
}
