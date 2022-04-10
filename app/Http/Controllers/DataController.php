<?php

namespace App\Http\Controllers;

use App\Service\ClickHouseServiceInterface;
use Cache;
use Illuminate\Http\Request;

class DataController extends Controller
{

    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    private function isBatch(Request $request)
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

    public function single(Request $request, string $bucket_id, ClickHouseServiceInterface $ClickHouse)
    {
        if (!$request->isJson()) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: invalid body format.'])->setStatusCode(400);
        }
        $compact = $this->isBatch($request);
        if (empty($data = $request->json()->all())) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: failed to parse request body json.'])->setStatusCode(400);
        }
        if (!Cache::has('bucket_' . $bucket_id)) {
            return response()->json(['code' => 404, 'message' => 'Not Found: Bucket does not exist.'])->setStatusCode(404);
        }
        $reserved_columns = $this->getReservedColumns($request);
        $bucket_data = Cache::get('bucket_' . $bucket_id);
        $values = [];
        $columns = array_keys($bucket_data['structure']['columns']);
        if ($compact) {
            $values = array_merge($data, array_values($reserved_columns));
        } else {
            $data = array_merge($data, $reserved_columns);
            $values = [];
            foreach ($bucket_data['structure']['columns'] as $column_name => $column_type) {
                if (!isset($data[$column_name])) {
                    return response()->json(['code' => 400, 'message' => 'Bad Request: Data format does not match bucket structure.'])->setStatusCode(400);
                }
                $values[] = $data[$column_name];
            }
        }
        if (!$ClickHouse->connect('write', false)) {
            return response()->json(['code' => 500, 'message' => 'Failed to connect to database, try again later.'])->setStatusCode(500);
        }
        $insert = $this->insert($bucket_id, $columns, $values, $ClickHouse);
        if (!$insert[0]) {
            return response()->json(['code' => 500, 'message' => 'Database Error: ' . $insert[1]])->setStatusCode(500);
        }
        return response()->json(['code' => 200, 'message' => 'OK'])->setStatusCode(200);
    }

    private function insert($bucket, $columns, $values, ClickHouseServiceInterface $ClickHouse) {
        $insert = $ClickHouse->insert('buffer_' . $bucket, $values, $columns);
        return $insert;
    }

    public function batch(Request $request, string $bucket_id, ClickHouseServiceInterface $ClickHouse)
    {
        if (!$request->isJson()) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: invalid body format.'])->setStatusCode(400);
        }
        $compact = $this->isBatch($request);
        if (empty($dataRaw = $request->json()->all())) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: failed to parse request body json.'])->setStatusCode(400);
        }
        if (!Cache::has('bucket_' . $bucket_id)) {
            return response()->json(['code' => 404, 'message' => 'Not Found: Bucket does not exist.'])->setStatusCode(404);
        }
        $reserved_columns = $this->getReservedColumns($request);
        $bucket_data = Cache::get('bucket_' . $bucket_id);
        $data = [];
        foreach ($dataRaw as $dataAtom) {
            if ($compact) {
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
        if (!$ClickHouse->connect('write', false)) {
            return response()->json(['code' => 500, 'message' => 'Failed to connect to database, try again later.'])->setStatusCode(500);
        }
        $insert = $ClickHouse->insertBatch('buffer_' . $bucket_id, $data, array_keys($bucket_data['structure']['columns']));
        if (!$insert[0]) {
            return response()->json(['code' => 500, 'message' => 'Database Error: ' . $insert[1]])->setStatusCode(500);
        }
        return response()->json(['code' => 200, 'message' => 'OK'])->setStatusCode(200);
    }
}
