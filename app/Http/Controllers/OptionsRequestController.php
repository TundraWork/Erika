<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class OptionsRequestController extends BaseController
{
    protected array $client = [];
    protected array $user = [];

    public function response(Request $request)
    {
        // No need to add headers here since CORS middleware will do this for us.
        return response('', 200);
    }
}
