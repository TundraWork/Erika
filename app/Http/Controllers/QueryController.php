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
            if ($bucket_data['query_token'] !== $this->token) {
                return response()->json(['code' => 403, 'message' => 'Forbidden: You have no access to this bucket.'])->setStatusCode(403);
            }
        }
        if (!str_starts_with(strtoupper($data['query']), 'SELECT ')) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: query string should start with "SELECT" clause.'])->setStatusCode(400);
        }
        if (substr_count($data['query'], 'FROM BUCKET') === 1) {
            $query_string = str_replace('FROM BUCKET', 'FROM `buffer_' . $bucket_id . '`', $data['query']);
        } elseif (substr_count($data['query'], 'from bucket') === 1) {
            $query_string = str_replace('from bucket', 'from `buffer_' . $bucket_id . '`', $data['query']);
        } else {
            return response()->json(['code' => 400, 'message' => 'Bad Request: query string should contain one "FROM BUCKET" clause.'])->setStatusCode(400);
        }
        if (!$ClickHouse->connect('read', true)) {
            return response()->json(['code' => 500, 'message' => 'Failed to connect to database, try again later.'])->setStatusCode(500);
        }
        $query = $ClickHouse->query($query_string);
        if (!$query[0]) {
            return response()->json(['code' => 500, 'message' => 'Database Error: ' . $query[1]])->setStatusCode(500);
        }
        return response()->json(['code' => 200, 'message' => 'OK', 'data' => $query[1]])->setStatusCode(200);
    }
}
