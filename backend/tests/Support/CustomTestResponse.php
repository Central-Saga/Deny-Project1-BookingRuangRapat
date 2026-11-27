<?php

namespace Tests\Support;

use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Illuminate\Testing\TestResponseAssert as PHPUnit;

class CustomTestResponse extends TestResponse
{
    /**
     * Assert that the response has the given JSON validation errors.
     * Mendukung format dictionary standar Laravel { field: [messages] }
     * dan format uniform error response schema [ { field: string, message: string } ].
     *
     * @param  string|array  $errors
     * @param  string  $responseKey
     * @return $this
     */
    public function assertJsonValidationErrors($errors = null, $responseKey = 'errors')
    {
        $errors = Arr::wrap($errors);

        PHPUnit::withResponse($this)->assertNotEmpty($errors, 'No validation errors were provided.');

        $jsonErrors = Arr::get($this->json(), $responseKey) ?? [];

        // Jika jsonErrors berbentuk daftar objek [ ['field' => ..., 'message' => ...] ]
        if (is_array($jsonErrors) && isset($jsonErrors[0]) && is_array($jsonErrors[0]) && array_key_exists('field', $jsonErrors[0])) {
            $fields = array_column($jsonErrors, 'field');

            foreach ($errors as $key => $value) {
                $expectedField = is_int($key) ? $value : $key;

                PHPUnit::withResponse($this)->assertContains(
                    $expectedField,
                    $fields,
                    "Failed to find validation error for field [{$expectedField}] in errors: ".json_encode($jsonErrors)
                );
            }

            return $this;
        }

        return parent::assertJsonValidationErrors($errors, $responseKey);
    }
}
