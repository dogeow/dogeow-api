<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class GithubControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_returns_github_oauth_url(): void
    {
        // Use partial mock to add methods to driver
        $driver = Mockery::mock(\Laravel\Socialite\Two\GithubProvider::class)->makePartial();
        $redirect = Mockery::mock();
        $redirect->shouldReceive('getTargetUrl')
            ->andReturn('https://github.com/login/oauth/authorize?client_id=test');

        $driver->shouldReceive('scopes')->andReturnSelf();
        $driver->shouldReceive('redirect')->andReturn($redirect);

        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) use ($driver) {
            $mock->shouldReceive('driver')->with('github')->andReturn($driver);
        });

        $response = $this->getJson('/api/auth/github');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'url',
        ]);
    }

    public function test_callback_creates_new_user(): void
    {
        $driver = Mockery::mock(\Laravel\Socialite\Two\GithubProvider::class)->makePartial();

        $githubUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $githubUser->id = 'github123';
        $githubUser->name = 'Test User';
        $githubUser->email = 'test@example.com';
        $githubUser->avatar = 'https://avatars.githubusercontent.com/u/123';

        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($githubUser);

        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) use ($driver) {
            $mock->shouldReceive('driver')->with('github')->andReturn($driver);
        });

        Config::set('services.github.redirect', 'http://localhost:3000/auth/github/callback');

        $response = $this->get('/api/auth/github/callback');

        $response->assertRedirect();

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'github_id' => 'github123',
            'email' => 'test@example.com',
        ]);
    }

    public function test_callback_existing_user_returns_token(): void
    {
        $user = User::factory()->create([
            'github_id' => 'github123',
        ]);

        $driver = Mockery::mock(\Laravel\Socialite\Two\GithubProvider::class)->makePartial();

        $githubUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $githubUser->id = 'github123';
        $githubUser->name = 'Test User';
        $githubUser->email = $user->email;
        $githubUser->avatar = 'https://avatars.githubusercontent.com/u/123';

        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($githubUser);

        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) use ($driver) {
            $mock->shouldReceive('driver')->with('github')->andReturn($driver);
        });

        Config::set('services.github.redirect', 'http://localhost:3000/auth/github/callback');

        $response = $this->get('/api/auth/github/callback');

        $response->assertRedirect();
        $response->assertRedirectContains('token=');
    }
}
