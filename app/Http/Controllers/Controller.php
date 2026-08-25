<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    // Every screen in this system is gated by role (§9), so authorisation is
    // available on the base controller rather than trait-by-trait.
    use AuthorizesRequests, ValidatesRequests;
}
