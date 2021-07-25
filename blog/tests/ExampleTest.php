<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

/**
 * @covers App\Http\Controllers\HomeController
 */
class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     * @covers App\Http\Controllers\HomeController::home
     */
    public function testExample()
    {
        $this->get('/');

        $this->assertEquals(
            $this->app->version(), $this->response->getContent()
        );
    }
}
