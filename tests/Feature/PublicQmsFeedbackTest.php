<?php

namespace Tests\Feature;

use App\Domains\Feedback\Models\Feedback;
use App\Domains\Feedback\Services\FeedbackTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicQmsFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        if (!DB::table('tenants')->where('id', 1)->exists()) {
            DB::table('tenants')->insert([
                'id' => 1,
                'name' => 'Tenant A',
                'domain' => 'tenant-a.local',
                'database' => 'tenant_a',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_portal_path_alias_returns_feedback_context(): void
    {
        $token = (new FeedbackTokenService())->createGeneralToken('1', '42');

        $this->getJson('/api/qms/public/qms/feedback/'.rawurlencode($token))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.feedback_type', 'general')
            ->assertJsonPath('data.office_id', '42');
    }

    public function test_context_query_returns_feedback_context(): void
    {
        $token = (new FeedbackTokenService())->createGeneralToken('1', '42');

        $this->getJson('/api/qms/feedback/context?token='.urlencode($token))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.feedback_type', 'general');
    }

    public function test_portal_payload_can_submit_feedback(): void
    {
        $token = (new FeedbackTokenService())->createGeneralToken('1', '42');

        $this->postJson('/api/qms/public/qms/feedback', [
            'token' => $token,
            'rating' => 5,
            'category_key' => 'excellent',
            'category_label' => 'Excellent',
            'comments' => 'Great service',
            'source' => 'portal-qms-feedback',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('feedbacks', [
            'tenant_id' => 1,
            'feedback_type' => Feedback::TYPE_GENERAL,
            'office_id' => '42',
            'comment_key' => 'excellent',
            'comment_text' => 'Great service',
            'source' => 'portal-qms-feedback',
        ]);
    }
}
