<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function user(Request $request): User
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return $user;
    }
}
