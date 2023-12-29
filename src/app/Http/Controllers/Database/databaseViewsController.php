<?php

namespace App\Http\Controllers\database;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class databaseViewsController extends Controller
{
    public function view_hosts()
    {

        return view('app_admin.database.list_hosts');
    }
}
