<?php

namespace App\Http\Controllers;

use Cache;
use Exception;
use Illuminate\Http\Request;
use Webpatser\Uuid\Uuid;

class UserController extends Controller
{

    public function create(Request $request)
    {
        if (!$this->user['admin']) {
            return response()->json(['code' => 403, 'message' => 'Forbidden: You have no access to this interface.'])->setStatusCode(403);
        }
        if (!$request->has('user_id')) {
            return response()->json(['code' => 400, 'message' => 'Bad Request: missing parameters.'])->setStatusCode(400);
        }
        $user_id = $request->input('user_id');
        if (Cache::has('user_' . $user_id)) {
            return response()->json(['code' => 409, 'message' => 'Conflict: User already exists.'])->setStatusCode(409);
        }
        try {
            $user_token = (string)Uuid::generate();
        } catch (Exception $e) {
            return response()->json(['code' => 500, 'message' => 'Server Error: Failed to generate UUID, try again later.'])->setStatusCode(500);
        }
        if (Cache::forever('user_' . $user_id, ['token' => $user_token, 'buckets' => [], 'clients' => []])) {
            return response()->json(['code' => 200, 'data' => ['user' => ['id' => $user_id, 'token' => $user_token, 'buckets' => []]], 'message' => 'OK'])->setStatusCode(200);
        } else {
            return response()->json(['code' => 500, 'message' => 'Server Error: Failed to set user data, try again later.'])->setStatusCode(500);
        }
    }

    public function info(string $user_id)
    {
        if ($user_id !== $this->user['id']) {
            if (!$this->user['admin']) {
                return response()->json(['code' => 403, 'message' => 'Forbidden: You have no access to this user.'])->setStatusCode(403);
            }
        }
        if (!Cache::has('user_' . $user_id)) {
            return response()->json(['code' => 404, 'message' => 'Not Found: User does not exist.'])->setStatusCode(404);
        }
        if ($user_data = Cache::get('user_' . $user_id)) {
            return response()->json(['code' => 200, 'data' => ['user' => ['id' => $user_id, 'token' => $user_data['token'], 'buckets' => $user_data['buckets']]], 'message' => 'OK'])->setStatusCode(200);
        } else {
            return response()->json(['code' => 500, 'message' => 'Server Error: Failed to get user data, try again later.'])->setStatusCode(500);
        }
    }

    public function destroy(string $user_id)
    {
        if (!$this->user['admin']) {
            return response()->json(['code' => 403, 'message' => 'Forbidden: You have no access to this interface.'])->setStatusCode(403);
        }
        if (!Cache::has('user_' . $user_id)) {
            return response()->json(['code' => 404, 'message' => 'Not Found: User does not exist.'])->setStatusCode(404);
        }
        if (Cache::forget('user_' . $user_id)) {
            return response()->json(['code' => 200, 'message' => 'OK'])->setStatusCode(200);
        } else {
            return response()->json(['code' => 500, 'message' => 'Server Error: Failed to delete user data, try again later.'])->setStatusCode(500);
        }
    }
}
