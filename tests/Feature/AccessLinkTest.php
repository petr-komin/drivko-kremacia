<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_logs_in_and_redirects_to_clean_url(): void
    {
        $user  = User::factory()->create(['password' => null]);
        $token = $user->issueAccessToken();

        $this->get(route('access', $token))
            ->assertRedirect(route('orders.index'))
            ->assertCookie(auth()->guard()->getRecallerName());

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($token, $user->fresh()->access_token_hash);
    }

    public function test_reissued_link_invalidates_old_one(): void
    {
        $user = User::factory()->create();
        $old  = $user->issueAccessToken();
        $user->issueAccessToken();

        $this->get(route('access', $old))->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_link_of_inactive_user_does_not_work(): void
    {
        $user  = User::factory()->create(['active' => false]);
        $token = $user->issueAccessToken();

        $this->get(route('access', $token))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_without_password_cannot_log_in_with_empty_password(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'x'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_creates_account_without_password_and_gets_link(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Kremace', 'email' => 'kremace@example.com', 'role' => User::ROLE_CLIENT,
        ])->assertSessionHas('access_link');

        $client = User::where('email', 'kremace@example.com')->firstOrFail();
        $this->assertNull($client->password);
        $this->assertTrue($client->hasAccessLink());

        $url = session('access_link')['url'];
        $this->get(route('users.index'))->assertSee($url);
        $this->get(route('users.index'))->assertSee($url);
    }

    public function test_revoke_logs_out_remembered_devices(): void
    {
        $admin  = User::factory()->admin()->create();
        $client = User::factory()->create();
        $client->issueAccessToken();
        $remember = $client->fresh()->getRememberToken();

        $this->actingAs($admin)->delete(route('users.link.revoke', $client))->assertRedirect();

        $client->refresh();
        $this->assertFalse($client->hasAccessLink());
        $this->assertNotSame($remember, $client->getRememberToken());
    }

    public function test_admin_without_password_cannot_revoke_own_link(): void
    {
        $admin = User::factory()->admin()->create(['password' => null]);
        $admin->issueAccessToken();

        $this->actingAs($admin)->delete(route('users.link.revoke', $admin))->assertSessionHasErrors('active');
        $this->assertTrue($admin->fresh()->hasAccessLink());
    }

    public function test_link_keeps_working_until_revoked(): void
    {
        $user  = User::factory()->create(['password' => null]);
        $token = $user->issueAccessToken();

        // Opakované otevření z chatu, i po odhlášení
        foreach (range(1, 3) as $_) {
            $this->get(route('access', $token))->assertRedirect(route('orders.index'));
            $this->assertAuthenticatedAs($user);
            auth()->logout();
        }
    }

    public function test_remember_cookie_works_for_account_without_password(): void
    {
        $user  = User::factory()->create(['password' => null]);
        $token = $user->issueAccessToken();

        $cookie = $this->get(route('access', $token))->getCookie(auth()->guard()->getRecallerName());
        auth()->forgetGuards();
        $this->flushSession();

        $this->withCookie($cookie->getName(), $cookie->getValue())
            ->get(route('orders.index'))->assertOk();
    }

    public function test_link_preview_bot_is_not_logged_in(): void
    {
        $user  = User::factory()->create();
        $token = $user->issueAccessToken();

        $this->withHeader('User-Agent', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)')
            ->get(route('access', $token))->assertOk()->assertDontSee('Zakázky');
        $this->assertGuest();

        $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0')->get(route('access', $token))->assertOk();
        $this->assertGuest();
    }

    public function test_admin_can_show_current_link_again(): void
    {
        $admin  = User::factory()->admin()->create();
        $client = User::factory()->create(['password' => null]);
        $token  = $client->issueAccessToken();

        $this->assertNotSame($token, $client->fresh()->getRawOriginal('access_token_encrypted'));

        $this->actingAs($admin)->get(route('users.index'))->assertSee(route('access', $token));

        $client->revokeAccessToken();
        $this->assertNull($client->fresh()->accessLink());
    }
}
