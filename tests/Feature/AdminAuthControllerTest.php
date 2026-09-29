<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminAuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_form_asks_for_username_instead_of_email(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertDontSee('name="email"', false);
    }

    public function test_admin_can_log_in_with_valid_username_and_password(): void
    {
        $admin = User::factory()->create(['username' => 'Victor']);

        $this->post(route('admin.login.submit'), [
            'username' => 'Victor',
            'password' => 'password',
        ])->assertRedirect(route('admin.events.index'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_cannot_log_in_with_wrong_password(): void
    {
        User::factory()->create(['username' => 'Victor']);

        $this->post(route('admin.login.submit'), [
            'username' => 'Victor',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_admin_login_is_limited_by_username_and_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('admin.login.submit'), [
                'username' => 'Victor',
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors('username');
        }

        $this->post(route('admin.login.submit'), [
            'username' => 'Victor',
            'password' => 'incorrect-password',
        ])->assertStatus(429);
    }

    public function test_admin_creation_stores_the_username(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Nome do administrador', 'Val')
            ->expectsQuestion('Usuário de acesso', 'Val')
            ->expectsQuestion('Senha', 'Teste12#a')
            ->expectsQuestion('Confirme a senha', 'Teste12#a')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['username' => 'Val']);
    }

    public function test_admin_creation_rejects_a_password_shorter_than_eight_characters(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Nome do administrador', 'Administrador')
            ->expectsQuestion('Usuário de acesso', 'admin')
            ->expectsQuestion('Senha', 'Te12#ab')
            ->expectsQuestion('Confirme a senha', 'Te12#ab')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_creation_rejects_a_duplicate_username(): void
    {
        User::factory()->create(['username' => 'Val']);

        $this->artisan('admin:create')
            ->expectsQuestion('Nome do administrador', 'Outra pessoa')
            ->expectsQuestion('Usuário de acesso', 'Val')
            ->expectsQuestion('Senha', 'Senha-Forte-2026')
            ->expectsQuestion('Confirme a senha', 'Senha-Forte-2026')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_creation_rejects_a_weak_password(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Nome do administrador', 'Administrador')
            ->expectsQuestion('Usuário de acesso', 'admin')
            ->expectsQuestion('Senha', 'weakpass')
            ->expectsQuestion('Confirme a senha', 'weakpass')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
