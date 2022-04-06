<?php

namespace App\Http\Controllers;

use App\Service\ClickHouseServiceInterface;
use Cache;
use Exception;
use Illuminate\Http\Request;
use Webpatser\Uuid\Uuid;

class BucketController extends Controller
{

    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function create(Request $request, ClickHouseServiceInterface $ClickHouse)
    {
        if (!$request->isJson()) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: invalid body format.'])->setStatusCode(400);
        }
        if (empty($data = $request->json()->all())) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: failed to parse request body json.'])->setStatusCode(400);
        }
        if (!isset($data['name']) || !isset($data['structure'])) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: missing parameters.'])->setStatusCode(400);
        }
        if ($this->user['admin']) {
            return response()->json(['code' => 403, 'message' => 'Forbidden: please access this interface as a normal user, not admin.'])->setStatusCode(403);
        }
        if (!isset($data['structure']['columns']) || !isset($data['structure']['date_column']) || !isset($data['structure']['primary_keys'])) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: missing object in structure.'])->setStatusCode(400);
        }
        if (!$ClickHouse->connect('write', false)) {
            return response()->json(['code' => 500, 'message' => 'Server Error: Failed to connect to database, try again later.'])->setStatusCode(500);
        }
        try {
            if (isset($data['id'])) {
                $bucket_id = $data['id'];
                unset($data['id']);
            } else {
                $bucket_id = (string)Uuid::generate();
            }
            $guest_id = (string)Uuid::generate();
        } catch (Exception $e) {
            return response()->json(['code' => 500, 'message' => 'Server Error: Failed to generate UUID, try again later.'])->setStatusCode(500);
        }
        $createTable = $ClickHouse->createTable('data_' . $bucket_id, $data['structure']['columns'], $data['structure']['date_column'], $data['structure']['primary_keys']);
        if (!$createTable[0]) {
            return response()->json(['code' => 500, 'message' => 'Database Error: ' . $createTable[1]])->setStatusCode(500);
        }
        $createBuffer = $ClickHouse->createBuffer('buffer_' . $bucket_id, 'data_' . $bucket_id, 2, 30, 2, 10, 1, 10000);
        if (!$createBuffer[0]) {
            $dropTable = $ClickHouse->dropTable('data_' . $bucket_id);
            if (!$dropTable[0]) {
                return response()->json(['code' => 500, 'message' => 'Database Error: ' . $dropTable[1]])->setStatusCode(500);
            }
            return response()->json(['code' => 500, 'message' => 'Database Error: ' . $createBuffer[1]])->setStatusCode(500);
        }
        $tableSize = $ClickHouse->tableSize('data_' . $bucket_id);
        if (!$tableSize[0]) {
            return response()->json(['code' => 500, 'message' => 'Database Error: ' . $tableSize[1]])->setStatusCode(500);
        }
        $bucket_stats = [
            'sizebytes' => $tableSize[1]['sizebytes'],
            'size' => $tableSize[1]['size'],
            'min_date' => $tableSize[1]['min_date'],
            'max_date' => $tableSize[1]['max_date'],
        ];
        Cache::forever('bucket_' . $bucket_id, ['name' => $data['name'], 'structure' => $data['structure'], 'owner_id' => $this->user['id'], 'guest_id' => $guest_id]);
        $user_data = Cache::get('user_' . $this->user['id']);
        $user_data['buckets'][] = $bucket_id;
        Cache::forever('user_'. $this->user['id'], $user_data);
        return response()->json(['code' => 200, 'data' => ['bucket' => ['id' => $bucket_id, 'name' => $data['name'], 'structure' => $data['structure'], 'stats' => $bucket_stats], 'query_token' => $guest_id], 'message' => 'OK'])->setStatusCode(200);
    }

    public function info(string $bucket_id, ClickHouseServiceInterface $ClickHouse)
    {
        if (!Cache::has('bucket_' . $bucket_id)) {
            return response()->json(['code' => 404, 'message' => 'Not Found: Bucket does not exist.'])->setStatusCode(404);
        }
        $bucket_data = Cache::get('bucket_' . $bucket_id);
        if (!$this->user['admin']) {
            if ($bucket_data['user_id'] !== $this->user['id']) {
                return response()->json(['code' => 403, 'message' => 'Forbidden: You have no access to this bucket.'])->setStatusCode(403);
            }
        }
        if (!$ClickHouse->connect('read', true)) {
            return response()->json(['code' => 500, 'message' => 'Server Error: Failed to connect to database, try again later.'])->setStatusCode(500);
        }
        $tableSize = $ClickHouse->tableSize('data_' . $bucket_id);
        if (!$tableSize[0]) {
            return response()->json(['code' => 500, 'message' => 'Database Error: ' . $tableSize[1]])->setStatusCode(500);
        }
        $bucket_stats = [
            'sizebytes' => $tableSize[1]['sizebytes'],
            'size' => $tableSize[1]['size'],
            'min_date' => $tableSize[1]['min_date'],
            'max_date' => $tableSize[1]['max_date'],
        ];
        unset($bucket_stats['table']);
        unset($bucket_stats['database']);
        return response()->json(['code' => 200, 'data' => ['bucket' => ['id' => $bucket_id, 'name' => $bucket_data['name'], 'structure' => $bucket_data['structure'], 'stats' => $bucket_stats], 'query_token' => $bucket_data['guest_id']], 'message' => 'OK'])->setStatusCode(200);
    }

    public function empty(string $bucket_id, ClickHouseServiceInterface $ClickHouse)
    {
        if (!Cache::has('bucket_' . $bucket_id)) {
            return response()->json(['code' => 404, 'message' => 'Not Found: Bucket does not exist.'])->setStatusCode(404);
        }
        $bucket_data = Cache::get('bucket_' . $bucket_id);
        if (!$this->user['admin']) {
            if ($bucket_data['user_id'] !== $this->user['id']) {
                return response()->json(['code' => 403, 'message' => 'Forbidden: You have no access to this bucket.'])->setStatusCode(403);
            }
        }
        if (!$ClickHouse->connect('write', false)) {
            return response()->json(['code' => 500, 'message' => 'Server Error: Failed to connect to database, try again later.'])->setStatusCode(500);
        }
        $truncateTable = $ClickHouse->truncateTable('data_' . $bucket_id);
        if (!$truncateTable[0]) {
            return response()->json(['code' => 500, 'message' => 'Database Error: ' . $truncateTable[1]])->setStatusCode(400);
        }
        return response()->json(['code' => 200, 'message' => 'OK'])->setStatusCode(200);
    }

    public function destroy(string $bucket_id, ClickHouseServiceInterface $ClickHouse)
    {
        if (!Cache::has('bucket_' . $bucket_id)) {
            return response()->json(['code' => 404, 'message' => 'Not Found: Bucket does not exist.'])->setStatusCode(404);
        }
        $bucket_data = Cache::get('bucket_' . $bucket_id);
        if (!$this->user['admin']) {
            if ($bucket_data['user_id'] !== $this->user['id']) {
                return response()->json(['code' => 403, 'message' => 'Forbidden: You have no access to this bucket.'])->setStatusCode(403);
            }
        }
        if (!$ClickHouse->connect('write', false)) {
            return response()->json(['code' => 500, 'message' => 'Server Error: Failed to connect to database, try again later.'])->setStatusCode(500);
        }
        $dropTable = $ClickHouse->dropTable('buffer_' . $bucket_id);
        if (!$dropTable[0]) {
            return response()->json(['code' => 500, 'message' => 'Database Error: ' . $dropTable[1]])->setStatusCode(500);
        }
        $dropTable = $ClickHouse->dropTable('data_' . $bucket_id);
        if (!$dropTable[0]) {
            return response()->json(['code' => 500, 'message' => 'Database Error: ' . $dropTable[1]])->setStatusCode(500);
        }
        if ($this->user['admin']) {
            $user_data = Cache::get('user_' . $bucket_data['user_id']);
            unset($user_data['buckets'][array_search($bucket_id, $user_data['buckets'])]);
            Cache::forever('user_' . $bucket_data['user_id'], $user_data);
        } else {
            $user_data = Cache::get('user_' . $this->user['id']);
            unset($user_data['buckets'][array_search($bucket_id, $user_data['buckets'])]);
            Cache::forever('user_' . $this->user['id'], $user_data);
        }
        Cache::forget('bucket_' . $bucket_id);
        return response()->json(['code' => 200, 'message' => 'OK'])->setStatusCode(200);
    }
}
