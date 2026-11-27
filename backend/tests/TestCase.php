<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Testing\LoggedExceptionCollection;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\CustomTestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create the test response instance from given base response.
     *
     * @param  Response  $response
     * @param  Request  $request
     * @return CustomTestResponse
     */
    protected function createTestResponse($response, $request)
    {
        return tap(CustomTestResponse::fromBaseResponse($response, $request), function ($response) {
            $response->withExceptions(
                $this->app->bound(LoggedExceptionCollection::class)
                    ? $this->app->make(LoggedExceptionCollection::class)
                    : new LoggedExceptionCollection
            );
        });
    }
}
