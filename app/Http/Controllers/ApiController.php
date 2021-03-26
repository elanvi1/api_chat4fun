<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponser;
use Illuminate\Http\Request;

// Controller created because of the use of the ApiResponser trait, it is extended by every controller.
class ApiController extends Controller
{
    use ApiResponser;

    public function __construct()
    {
        
    }
}
