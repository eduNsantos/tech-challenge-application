<?php

namespace Tests\Feature;

use Tests\TestCase;

class RequestCorrelationTest extends TestCase
{
    public function test_request_id_is_returned_in_response_headers(): void
    {
        $response = $this->withHeaders([
            'X-Request-Id' => 'req-123',
        ])->get('/up');

        $response->assertOk();
        $this->assertSame('req-123', $response->headers->get('X-Request-Id'));
    }
}
