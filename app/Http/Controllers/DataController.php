<?php

namespace App\Http\Controllers;

use App\Service\ClickHouseServiceInterface;
use App\Service\NatsServiceInterface;
use Cache;
use Illuminate\Http\Request;

class DataController extends Controller
{

    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    private function isCompact(Request $request)
    {
        $mode = isset($request->mode) ? $request->mode : false;
        $compact = false;
        switch ($mode) {
            case 'compact':
                $compact = true;
                break;
            case false:
                break;
            default:
                return response()->json(['code' => 400, 'message' => 'Bad Request: invalid mode parameter.'])->setStatusCode(400);
        }
        return $compact;
    }

    private function getReservedColumns(Request $request)
    {
        $data = [];
        foreach ($this->reservedColumns as $name => $properties) {
            $data[$name] = eval($properties['expression']);
        }
        return $data;
    }

    public function do(Request $request, string $bucket_id, string $batch, ClickHouseServiceInterface $clickhouseService, NatsServiceInterface $natsService)
    {
        if (!$request->isJson()) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: invalid body format.'])->setStatusCode(400);
        }
        if ($batch == 'single') {
            $batch = false;
        } elseif ($batch == 'batch') {
            $batch = true;
        } else {
            return response()->json(['code' => 400, 'message' => 'Bad Request: invalid path parameter.'])->setStatusCode(400);
        }
        $compact = $this->isCompact($request);
        if (empty($request_data = $request->json()->all())) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: failed to parse request body json.'])->setStatusCode(400);
        }
        if (!Cache::has('bucket_' . $bucket_id)) {
            return response()->json(['code' => 404, 'message' => 'Not Found: Bucket does not exist.'])->setStatusCode(404);
        }
        $reserved_columns = $this->getReservedColumns($request);
        $bucket_data = Cache::get('bucket_' . $bucket_id);
        if ($batch) {
            [$columns, $values] = $this->batch($request_data, $bucket_data, $reserved_columns, $compact);
        } else {
            [$columns, $values] = $this->single($request_data, $bucket_data, $reserved_columns, $compact);
        }
        if (!$clickhouseService->connect('write', false)) {
            return response()->json(['code' => 500, 'message' => 'Failed to connect to database, try again later.'])->setStatusCode(500);
        }
        $insert = $this->insert($bucket_id, $columns, $values, $clickhouseService, $natsService);
        if (!$insert[0][0]) {
            return response()->json(['code' => 500, 'message' => 'MessageQueue Error: ' . $insert[0][1]])->setStatusCode(500);
        }
        if (!$insert[1][0]) {
            return response()->json(['code' => 500, 'message' => 'Database Error: ' . $insert[1][1]])->setStatusCode(500);
        }
        return response()->json(['code' => 200, 'message' => 'OK'])->setStatusCode(200);
    }

    private function insert($bucket, $columns, $values, ClickHouseServiceInterface $clickhouseService, NatsServiceInterface $natsService)
    {
        $result[0] = $natsService->publishInsert($bucket, $columns, $values);
        $result[1] = $clickhouseService->insertBatch('buffer_' . $bucket, $values, $columns);
        return $result;
    }

    private function single(array $request_data, array $bucket_data, array $reserved_columns, bool $is_compact)
    {
        $columns = array_keys($bucket_data['structure']['columns']);
        if ($is_compact) {
            $values = array_merge($request_data, array_values($reserved_columns));
        } else {
            $data = array_merge($request_data, $reserved_columns);
            $values = [];
            foreach ($bucket_data['structure']['columns'] as $column_name => $column_type) {
                if (!isset($data[$column_name])) {
                    return response()->json(['code' => 400, 'message' => 'Bad Request: Data format does not match bucket structure.'])->setStatusCode(400);
                }
                $values[] = $data[$column_name];
            }
        }
        return [$columns, [$values]];
    }

    private function batch(array $request_data, array $bucket_data, array $reserved_columns, bool $is_compact)
    {
        $columns = array_keys($bucket_data['structure']['columns']);
        $data = [];
        foreach ($request_data as $dataAtom) {
            if ($is_compact) {
                $data[] = array_merge($dataAtom, array_values($reserved_columns));
            } else {
                $dataAtom = array_merge($dataAtom, $reserved_columns);
                $values = [];
                foreach ($bucket_data['structure']['columns'] as $column_name => $column_type) {
                    if (!isset($dataAtom[$column_name])) {
                        return response()->json(['code' => 400, 'message' => 'Bad Request: Data format does not match bucket structure.'])->setStatusCode(400);
                    }
                    $values[] = $dataAtom[$column_name];
                }
                $data[] = $values;
            }
        }
        return [$columns, $data];
    }
}
