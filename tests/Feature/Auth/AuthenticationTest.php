<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_user_can_authenticate_with_unmasked_cpf(): void
    {
        $user = User::factory()->create([
            'cpf' => '52998224725',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'cpf' => '52998224725',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_authenticate_with_masked_cpf(): void
    {
        $user = User::factory()->create([
            'cpf' => '52998224725',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'cpf' => '529.982.247-25',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_authenticate_with_wrong_password(): void
    {
        User::factory()->create([
            'cpf' => '52998224725',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'cpf' => '52998224725',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('cpf');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        User::factory()->inactive()->create([
            'cpf' => '52998224725',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'cpf' => '52998224725',
            'password' => 'password',
        ])->assertSessionHasErrors('cpf');

        $this->assertGuest();
    }

    public function test_blocked_user_cannot_authenticate(): void
    {
        User::factory()->blocked()->create([
            'cpf' => '52998224725',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'cpf' => '52998224725',
            'password' => 'password',
        ])->assertSessionHasErrors('cpf');

        $this->assertGuest();
    }

    public function test_logout_invalidates_authenticated_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_rate_limit_blocks_after_excessive_attempts(): void
    {
        User::factory()->create([
            'cpf' => '52998224725',
            'password' => 'password',
        ]);

        RateLimiter::clear(LoginRequest::throttleKeyFor('52998224725', '10.1.1.1'));

        for ($attempt = 0; $attempt < LoginRequest::MAX_ATTEMPTS; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])->post('/login', [
                'cpf' => '529.982.247-25',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('cpf');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])->post('/login', [
            'cpf' => '52998224725',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('cpf');

        $message = session('errors')->getBag('default')->first('cpf');
        $this->assertStringContainsString('Muitas tentativas de login.', $message);
    }

    public function test_last_login_at_is_updated_after_successful_login(): void
    {
        $user = User::factory()->create([
            'cpf' => '52998224725',
            'password' => 'password',
            'last_login_at' => null,
        ]);

        $this->post('/login', [
            'cpf' => '52998224725',
            'password' => 'password',
        ])->assertRedirect('/');

        $freshUser = $user->fresh();

        $this->assertNotNull($freshUser);
        $this->assertNotNull($freshUser->last_login_at);
    }
}
