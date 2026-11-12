<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_la_entrada_lleva_al_inicio(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');
    }
}
