<?php

namespace App\Http\Controllers;

use App\Service\ClickHouseServiceInterface;
use Cache;
use Illuminate\Http\Request;

class DataController
{

    public function __construct(Request $request)
    {
    }

    public function single(Request $request, string $bucket_id, ClickHouseServiceInterface $ClickHouse)
    {
        if (!$request->isJson()) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: invalid body format.'])->setStatusCode(400);
        }
        $mode = isset($request->mode) ? $request->mode : false;
        switch ($mode) {
            case 'compact':
                $compact = true;
                break;
            case false:
                break;
            default:
                return response()->json(['code' => 400, 'message' => 'Bad Request: invalid mode parameter.'])->setStatusCode(400);
        }
        if (empty($data = $request->json()->all())) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: failed to parse request body json.'])->setStatusCode(400);
        }
        if (!Cache::has('bucket_' . $bucket_id)) {
            return response()->json(['code' => 404, 'message' => 'Not Found: Bucket does not exist.'])->setStatusCode(404);
        }
        $bucket_data = Cache::get('bucket_' . $bucket_id);
        if ($compact) {
            if (count($data) !== count($bucket_data['structure']['columns'])) {
                return response()->json(['code' => 400, 'message' => 'Bad Request: Data format does not match bucket structure.'])->setStatusCode(400);
            }
            if (!$ClickHouse->connect()) {
                return response()->json(['code' => 500, 'message' => 'Failed to connect to database, try again later.'])->setStatusCode(500);
            }
            $insert = $ClickHouse->insert('buffer_' . $bucket_id, $data, array_keys($bucket_data['structure']['columns']));
            if (!$insert[0]) {
                return response()->json(['code' => 400, 'message' => 'Database Error: ' . $insert[1]])->setStatusCode(400);
            }
            return response()->json(['code' => 200, 'message' => 'OK'])->setStatusCode(200);
        } else {
            if (count($data) !== count($bucket_data['structure']['columns'])) {
                return response()->json(['code' => 400, 'message' => 'Bad Request: Data format does not match bucket structure.'])->setStatusCode(400);
            }
            foreach ($bucket_data['structure']['columns'] as $column_name => $column_value) {
                if (!isset($data[$column_name])) {
                    return response()->json(['code' => 400, 'message' => 'Bad Request: Data format does not match bucket structure.'])->setStatusCode(400);
                }
                $values[] = $data[$column_name];
            }
            $columns = array_keys($data);
            if (!$ClickHouse->connect()) {
                return response()->json(['code' => 500, 'message' => 'Failed to connect to database, try again later.'])->setStatusCode(500);
            }
            $insert = $ClickHouse->insert('buffer_' . $bucket_id, $values, $columns);
            if (!$insert[0]) {
                return response()->json(['code' => 400, 'message' => 'Database Error: ' . $insert[1]])->setStatusCode(400);
            }
            return response()->json(['code' => 200, 'message' => 'OK'])->setStatusCode(200);
        }
    }

    public function batch(Request $request, string $bucket_id, ClickHouseServiceInterface $ClickHouse)
    {
        if (!$request->isJson()) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: invalid body format.'])->setStatusCode(400);
        }
        $mode = isset($request->mode) ? $request->mode : false;
        switch ($mode) {
            case 'compact':
                $compact = true;
                break;
            case false:
                break;
            default:
                return response()->json(['code' => 400, 'message' => 'Bad Request: invalid mode parameter.'])->setStatusCode(400);
        }
        if (empty($dataRaw = $request->json()->all())) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: failed to parse request body json.'])->setStatusCode(400);
        }
        if (!Cache::has('bucket_' . $bucket_id)) {
            return response()->json(['code' => 404, 'message' => 'Not Found: Bucket does not exist.'])->setStatusCode(404);
        }
        $bucket_data = Cache::get('bucket_' . $bucket_id);
        $data = [];
        foreach ($dataRaw as $dataAtom) {
            if ($compact) {
                if (count($dataAtom) !== count($bucket_data['structure']['columns'])) {
                    return response()->json(['code' => 400, 'message' => 'Bad Request: Data format does not match bucket structure.'])->setStatusCode(400);
                }
                $data[] = $dataAtom;
            } else {
                if (count($dataAtom) !== count($bucket_data['structure']['columns'])) {
                    return response()->json(['code' => 400, 'message' => 'Bad Request: Data format does not match bucket structure.'])->setStatusCode(400);
                }
                $values = [];
                foreach ($bucket_data['structure']['columns'] as $column_name => $column_value) {
                    if (!isset($dataAtom[$column_name])) {
                        return response()->json(['code' => 400, 'message' => 'Bad Request: Data format does not match bucket structure.'])->setStatusCode(400);
                    }
                    $values[] = $dataAtom[$column_name];
                }
                $data[] = $values;
            }
        }
        if (!$ClickHouse->connect()) {
            return response()->json(['code' => 500, 'message' => 'Failed to connect to database, try again later.'])->setStatusCode(500);
        }
        $insert = $ClickHouse->insertBatch('buffer_' . $bucket_id, $data, array_keys($bucket_data['structure']['columns']));
        if (!$insert[0]) {
            return response()->json(['code' => 400, 'message' => 'Database Error: ' . $insert[1]])->setStatusCode(400);
        }
        return response()->json(['code' => 200, 'message' => 'OK'])->setStatusCode(200);
    }
}
