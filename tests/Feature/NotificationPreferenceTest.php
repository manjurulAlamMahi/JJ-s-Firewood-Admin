<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\NotificationPreference;
use Laravel\Sanctum\Sanctum;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_view_notification_preferences(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/notification-preferences');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'transactional' => ['email', 'push'],
                'helpful' => ['email', 'push'],
                'updates' => ['email', 'push'],
            ]);
    }

    public function test_user_can_update_transactional_preference(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/notification-preferences/transactional', [
            'email' => false,
            'push' => true,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->user->id,
            'category' => 'transactional',
            'email' => false,
            'push' => true,
        ]);
    }

    public function test_user_can_update_helpful_preference(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/notification-preferences/helpful', [
            'email' => true,
            'push' => false,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->user->id,
            'category' => 'helpful',
            'email' => true,
            'push' => false,
        ]);
    }

    public function test_user_can_update_updates_preference(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/notification-preferences/updates', [
            'email' => false,
            'push' => false,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->user->id,
            'category' => 'updates',
            'email' => false,
            'push' => false,
        ]);
    }

    public function test_user_can_view_specific_preference(): void
    {
        Sanctum::actingAs($this->user);

        NotificationPreference::create([
            'user_id' => $this->user->id,
            'category' => 'transactional',
            'email' => false,
            'push' => true,
        ]);

        $response = $this->getJson('/api/notification-preferences/transactional');

        $response->assertStatus(200)
            ->assertJson([
                'category' => 'transactional',
                'email' => false,
                'push' => true,
            ]);
    }

    public function test_unauthenticated_user_cannot_access_preferences(): void
    {
        $response = $this->getJson('/api/notification-preferences');

        $response->assertStatus(401);
    }

    public function test_category_notification_respects_preferences(): void
    {
        // Disable email for transactional
        NotificationPreference::create([
            'user_id' => $this->user->id,
            'category' => 'transactional',
            'email' => false,
            'push' => true,
        ]);

        $notification = new \App\Notifications\CategoryNotification('transactional', [
            'subject' => 'Test',
            'body' => 'Test message',
        ]);

        $channels = $notification->via($this->user);

        $this->assertNotContains('mail', $channels);
        $this->assertContains(\App\Notifications\Channels\FcmChannel::class, $channels);
    }
}
