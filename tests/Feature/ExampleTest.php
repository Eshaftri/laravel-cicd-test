<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/books');

        $response->assertStatus(200);
    }

    /**
     * Test root redirects to books.
     */
    public function test_root_redirects_to_books(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/books');
    }
}
