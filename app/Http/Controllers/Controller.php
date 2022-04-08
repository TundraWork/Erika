<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    protected array $client = [];
    protected array $user = [];
    protected string $token = '';
    protected array $reservedColumns = [];

    public function __construct(Request $request)
    {
        $this->client = [
            'id' => $request->attributes->get('client.id'),
            'admin' => $request->attributes->get('client.admin'),
            'buckets' => $request->attributes->get('client.buckets'),
        ];
        $this->user = [
            'id' => $request->attributes->get('user.id'),
            'admin' => $request->attributes->get('user.admin'),
            'buckets' => $request->attributes->get('user.buckets'),
        ];
        $this->token = $request->attributes->get('token', '');
        $this->reservedColumns = [
            '_timestamp' => ['type' => 'UInt64', 'expression' => 'return (int)(microtime(true)*1000)'],
            '_request_ip' => ['type' => 'IPv4', 'expression' => 'return $request->getClientIp();'],
        ];
    }
}
