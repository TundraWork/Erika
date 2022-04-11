<?php

namespace App\Service;

use Nats\Connection;
use Nats\ConnectionOptions;
use Nats\EncodedConnection;
use Nats\Encoders\JSONEncoder;

class NatsService implements NatsServiceInterface
{
    protected ?Connection $natsInstance;

    public function __construct()
    {
        $this->natsInstance = null;
        $this->connect();
    }

    public function connect(): array
    {
        $host = config('nats.host');
        if (empty($host)) {
            return [false, 'NATS service endpoint not configured'];
        }

        $encoder = new JSONEncoder();
        $options = new ConnectionOptions();
        $options
            ->setHost($host)
            ->setPort(config('nats.port'))
            ->setUser(config('nats.user'))
            ->setPass(config('nats.pass'))
            ;
        $client = new EncodedConnection($options, $encoder);
        $this->natsInstance = $client;
        try {
            $this->natsInstance->connect(1);
        } catch (\Exception $e) {
            return [false, "[{$e->getCode()}] {$e->getMessage()}"];
        }
        return [true, 'OK'];
    }

    public function publishInsert($bucket, $columns, $values): array
    {
        if ($this->natsInstance == null) {
            return [false, 'NATS instance not initialized'];
        }
        try {
            $this->natsInstance->publish('erika.insert.' . $bucket, [
                'columns' => $columns,
                'values' => $values
            ]);
        } catch (\Exception $e) {
            return [false, "[{$e->getCode()}] {$e->getMessage()}"];
        }
        return [true, 'OK'];
    }
}
