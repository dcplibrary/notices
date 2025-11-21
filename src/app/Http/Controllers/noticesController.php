<?php

namespace Dcplibrary\notices\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class noticesController extends Controller
{
    /**
     * Display the notices index page.
     */
    public function index()
    {
        return view('notices::index');
    }
}
