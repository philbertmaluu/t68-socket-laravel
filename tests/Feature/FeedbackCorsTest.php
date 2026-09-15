<?php

namespace Tests\Feature;

use Tests\TestCase;

class FeedbackCorsTest extends TestCase
{
    public function test_portal_origin_can_preflight_feedback_context(): void
    {
        $this->withHeaders([
            'Origin' => 'https://portal.nssf.go.tz',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/qms/feedback/context')
            ->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', 'https://portal.nssf.go.tz');
    }
}
