<?php

namespace Tests\Feature;

use App\Mail\OrderUpdateMail;
use App\Models\Order;
use App\Models\User;
use App\Models\WoodType;
use App\Support\Notifier;
use App\Support\Photos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $client;
    private WoodType $wood;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Photos::DISK);
        Mail::fake();

        $this->admin  = User::factory()->admin()->create();
        $this->client = User::factory()->create();
        $this->wood   = WoodType::create(['name' => 'Olše', 'name_sk' => 'Jelša']);
    }

    public function test_user_switches_language_and_interface_and_wood_names_are_translated(): void
    {
        $this->actingAs($this->client)->get(route('orders.create'))
            ->assertSee('Nová zakázka')->assertSee('Olše')->assertDontSee('Jelša');

        $this->post(route('profile.locale'), ['locale' => 'sk'])->assertRedirect();
        $this->assertSame('sk', $this->client->fresh()->locale);

        $this->get(route('orders.create'))
            ->assertSee('Nová zákazka')->assertSee('Odtlačok labky')->assertSee('Jelša')->assertSee('lang="sk"', false);

        $this->post(route('profile.locale'), ['locale' => 'de'])->assertSessionHasErrors('locale');
    }

    public function test_emails_and_system_messages_follow_reader_language(): void
    {
        $this->client->update(['locale' => 'sk']);
        $order = $this->makeOrder();

        $this->actingAs($this->admin)->post(route('orders.status', $order), ['status' => Order::STATUS_IN_PRODUCTION]);

        if (Notifier::CLIENT_EMAILS) {
            Mail::assertSent(OrderUpdateMail::class, function (OrderUpdateMail $mail) {
                $mail->assertHasSubject('U-0001 Rex: stav: Vo výrobe');
                $mail->assertSeeInHtml('Jelša');

                return $mail->hasTo($this->client->email);
            });
        }

        // Systémová zpráva ve vlákně: dílna česky, kremace slovensky
        $this->get(route('orders.show', $order))->assertSee('Stav změněn na „Ve výrobě“.', false);
        $this->actingAs($this->client)->get(route('orders.show', $order))->assertSee('Stav zmenený na „Vo výrobe“.', false);
    }

    private function makeOrder(): Order
    {
        return Order::create([
            'pet_name' => 'Rex', 'wood_type_id' => $this->wood->id, 'volume_ml' => 800,
            'with_paw' => false, 'status' => Order::STATUS_NEW,
            'created_by' => $this->client->id, 'last_activity_at' => now(),
        ]);
    }
}
