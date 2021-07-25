<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Application;

class HomeController extends Controller
{
    protected Application $app;

    public function __construct(Application $app) {
        $this->app = $app;
    }

    public function home() {
        return $this->app->version();
    }
}
