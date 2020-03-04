<?php

namespace App\Http\Middleware;

use Cache;
use Closure;
use Illuminate\Http\Request;

class Authenticate
{
    /**
     * Initialize all needed data.
     */
    public function __construct()
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->hasHeader('X-Erika-User-Id') && $request->hasHeader('X-Erika-User-Token')) {
            $user_id = $request->header('X-Erika-User-Id');
            $user_token = $request->header('X-Erika-User-Token');
        } else {
            return response()->json(['code' => 400, 'message' => 'Bad Request: missing header parameter.'])->setStatusCode(400);
        }
        if ($user_id === config('user.admin_id')) {
            if ($user_token === config('user.admin_token')) {
                $request->attributes->add(['user.id' => $user_id, 'user.admin' => true]);
            } else {
                return response()->json(['code' => 403, 'message' => 'Unauthorized: invalid client token.'])->setStatusCode(403);
            }
        } else {
            if (!Cache::has('user_' . $user_id)) {
                return response()->json(['code' => 403, 'message' => 'Unauthorized: invalid client credential.'])->setStatusCode(403);
            } else {
                $user_data = Cache::get('user_' . $user_id);
                if ($user_token === $user_data['token']) {
                    $request->attributes->add(['user.id' => $user_id, 'user.admin' => false, 'user.buckets' => $user_data['buckets']]);
                } else {
                    return response()->json(['code' => 403, 'message' => 'Unauthorized: invalid client credential.'])->setStatusCode(403);
                }
            }
        }
        return $next($request);
    }
}
