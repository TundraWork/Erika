<?php

namespace App\Http\Controllers;

use App\Service\ClickHouseServiceInterface;
use Cache;
use Illuminate\Http\Request;

class QueryController extends Controller
{

    public function do(Request $request, string $bucket_id, ClickHouseServiceInterface $ClickHouse)
    {
        if (!$request->isJson()) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: invalid body format.'])->setStatusCode(400);
        }
        if (empty($data = $request->json()->all())) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: failed to parse request body json.'])->setStatusCode(400);
        }
        if (!Cache::has('bucket_' . $bucket_id)) {
            return response()->json(['code' => 404, 'message' => 'Not Found: Bucket does not exist.'])->setStatusCode(404);
        }
        $bucket_data = Cache::get('bucket_' . $bucket_id);
        if (!$this->user['admin']) {
            if ($bucket_data['user_id'] !== $this->user['id']) {
                return response()->json(['code' => 403, 'message' => 'Forbidden: You have no access to this bucket.'])->setStatusCode(403);
            }
        }
        if (substr_count($data['query'], 'FROM BUCKET') !== 1) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: query string should contain one "FROM BUCKET" clause.'])->setStatusCode(400);
        }
        $replaced = str_replace('FROM BUCKET', 'FROM `data_' . $bucket_id . '`', $data['query']);
        if (!$ClickHouse->connect('read', true)) {
            return response()->json(['code' => 500, 'message' => 'Failed to connect to database, try again later.'])->setStatusCode(500);
        }
        $query = $ClickHouse->query($replaced);
        if (!$query[0]) {
            return response()->json(['code' => 400, 'message' => 'Database Error: ' . $query[1]])->setStatusCode(400);
        }
        return response()->json(['code' => 200, 'message' => 'OK', 'data' => $query[1]])->setStatusCode(200);
    }
}
