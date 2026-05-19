<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_callback_creates_user_and_redirects_with_token(): void
    {
        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'google-123',
            'name' => 'Google User',
            'email' => 'google-user@example.com',
            'avatar' => 'https://example.com/avatar.jpg',
        ]));

        $response = $this->get('/auth/google/callback?code=fake-code');

        $response->assertRedirect();
        $this->assertStringContainsString('api_token=', $response->headers->get('Location') ?? '');

        $this->assertDatabaseHas('users', [
            'email' => 'google-user@example.com',
            'name' => 'Google User',
        ]);

        $user = User::query()->where('email', 'google-user@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->tokens);
    }

    public function test_google_redirect_returns_oauth_redirect(): void
    {
        Socialite::fake('google');

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect('https://socialite.fake/google/authorize');
    }

    public function test_google_callback_stores_long_avatar_url(): void
    {
        $longAvatarUrl = 'https://lh3.googleusercontent.com/a-/'.str_repeat('x', 500).'=s96-c';

        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'google-long-avatar',
            'name' => 'Long Avatar User',
            'email' => 'long-avatar@example.com',
            'avatar' => $longAvatarUrl,
        ]));

        $response = $this->get('/auth/google/callback?code=fake-code');

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'long-avatar@example.com',
            'avatar' => $longAvatarUrl,
        ]);
    }
}
