<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\User;
use App\Models\WoodType;
use App\Mail\OrderUpdateMail;
use App\Support\Photos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderFlowTest extends TestCase
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
        $this->wood   = WoodType::create(['name' => 'Dub']);
    }

    private function createOrder(): Order
    {
        $this->actingAs($this->client)->post(route('orders.store'), [
            'pet_name'     => 'Rex',
            'case_number'  => 'K-2026-15',
            'wood_type_id' => $this->wood->id,
            'volume_ml'    => 1500,
            'note'         => 'Levá přední',
            'photos'       => [UploadedFile::fake()->image('tlapka.jpg', 2400, 1800)],
        ])->assertRedirect();

        return Order::firstOrFail();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $this->client->update(['active' => false]);

        $this->post(route('login.post'), ['email' => $this->client->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_client_creates_order_with_paw_photo_and_admin_is_notified(): void
    {
        $order = $this->createOrder();

        $this->assertSame(Order::STATUS_NEW, $order->status);
        $this->assertSame('U-0001', $order->number);

        $photo = $order->pawPhotos()->firstOrFail();
        $this->assertTrue($photo->has_preview);
        Storage::disk(Photos::DISK)->assertExists([$photo->path, Photos::variantPath($photo->path, 'thumb')]);

        Mail::assertSent(OrderUpdateMail::class, fn ($m) => $m->hasTo($this->admin->email));
        Mail::assertNotSent(OrderUpdateMail::class, fn ($m) => $m->hasTo($this->client->email));

        $this->get(route('orders.show', $order))->assertOk()->assertSee('Rex')->assertSee('K-2026-15');
        $this->get(route('orders.index'))->assertOk()->assertSee('Rex');
    }

    public function test_order_requires_photo(): void
    {
        $this->actingAs($this->client)->post(route('orders.store'), [
            'pet_name'     => 'Rex',
            'wood_type_id' => $this->wood->id,
            'volume_ml'    => 1500,
        ])->assertSessionHasErrors('photos');

        $this->assertSame(0, Order::count());
    }

    public function test_inactive_wood_cannot_be_chosen(): void
    {
        $this->wood->update(['active' => false]);

        $this->actingAs($this->client)->post(route('orders.store'), [
            'pet_name'     => 'Rex',
            'wood_type_id' => $this->wood->id,
            'volume_ml'    => 1500,
            'photos'       => [UploadedFile::fake()->image('a.jpg')],
        ])->assertSessionHasErrors('wood_type_id');
    }

    public function test_client_message_photo_counts_as_paw_photo_and_notifies_admin(): void
    {
        $order = $this->createOrder();
        Mail::fake();

        $this->post(route('messages.store', $order), [
            'body'   => 'Posílám ostřejší fotku',
            'photos' => [UploadedFile::fake()->image('b.png')],
        ])->assertRedirect();

        $this->assertSame(2, $order->pawPhotos()->count());
        Mail::assertSent(OrderUpdateMail::class, fn ($m) => $m->hasTo($this->admin->email));
    }

    public function test_empty_message_is_rejected(): void
    {
        $order = $this->createOrder();

        $this->post(route('messages.store', $order), ['body' => '  '])->assertSessionHasErrors('body');
    }

    public function test_client_cannot_change_status(): void
    {
        $order = $this->createOrder();

        $this->post(route('orders.status', $order), ['status' => Order::STATUS_FINISHED])->assertForbidden();
        $this->get(route('report.index'))->assertForbidden();
    }

    public function test_finished_requires_photo_of_finished_piece(): void
    {
        $order = $this->createOrder();

        $this->actingAs($this->admin)
            ->post(route('orders.status', $order), ['status' => Order::STATUS_FINISHED])
            ->assertSessionHasErrors('photos');

        $this->assertSame(Order::STATUS_NEW, $order->fresh()->status);
    }

    public function test_status_flow_records_timestamps_and_report(): void
    {
        $order = $this->createOrder();
        Mail::fake();

        $this->actingAs($this->admin);
        $this->post(route('orders.status', $order), ['status' => Order::STATUS_IN_PRODUCTION]);
        $this->post(route('orders.status', $order), [
            'status' => Order::STATUS_FINISHED,
            'body'   => 'Hotovo, můžete si vyzvednout.',
            'photos' => [UploadedFile::fake()->image('urna.jpg')],
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(Order::STATUS_FINISHED, $order->status);
        $this->assertNotNull($order->started_at);
        $this->assertNotNull($order->finished_at);
        $this->assertSame(1, $order->finishedPhotos()->count());

        Mail::assertSent(OrderUpdateMail::class, fn ($m) => $m->hasTo($this->client->email) && $m->text === 'Hotovo, můžete si vyzvednout.');

        $this->get(route('report.index'))->assertOk()->assertSee('Rex');
        $csv = $this->get(route('report.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('K-2026-15', $csv);

        // Návrat o krok zpět smaže čas dokončení – kus se nesmí počítat jako hotový
        $this->post(route('orders.status', $order), ['status' => Order::STATUS_IN_PRODUCTION]);
        $this->assertNull($order->fresh()->finished_at);
        $this->get(route('report.index'))->assertDontSee('Rex');
    }

    public function test_client_can_edit_only_new_order(): void
    {
        $order = $this->createOrder();

        $this->put(route('orders.update', $order), [
            'pet_name'     => 'Rexík',
            'wood_type_id' => $this->wood->id,
            'volume_ml'    => 1500,
        ])->assertRedirect();
        $this->assertSame('Rexík', $order->fresh()->pet_name);

        $order->moveTo(Order::STATUS_IN_PRODUCTION);
        $this->get(route('orders.edit', $order))->assertForbidden();
    }

    public function test_photos_are_served_only_to_logged_in_users(): void
    {
        $order = $this->createOrder();
        $photo = $order->pawPhotos()->firstOrFail();

        $this->get($photo->url('thumb'))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get($photo->downloadUrl())->assertOk()->assertDownload("U-0001-rex-tlapka-{$photo->id}.jpg");

        auth()->logout();
        $this->get($photo->url('thumb'))->assertRedirect(route('login'));
    }

    public function test_only_author_or_admin_can_delete_photo(): void
    {
        $order = $this->createOrder();
        $photo = $order->pawPhotos()->firstOrFail();
        $other = User::factory()->create();

        $this->actingAs($other)->delete(route('photos.destroy', $photo))->assertForbidden();

        $this->actingAs($this->admin)->delete(route('photos.destroy', $photo))->assertRedirect();
        $this->assertModelMissing($photo);
        Storage::disk(Photos::DISK)->assertMissing($photo->path);
    }

    public function test_admin_downloads_zip(): void
    {
        $order = $this->createOrder();

        $this->actingAs($this->admin)->get(route('orders.zip', $order))
            ->assertOk()->assertDownload('U-0001-rex.zip');
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $this->actingAs($this->admin)->put(route('users.update', $this->admin), [
            'name' => $this->admin->name, 'email' => $this->admin->email,
            'role' => User::ROLE_ADMIN, 'active' => '0',
        ])->assertSessionHasErrors('active');

        $this->assertTrue($this->admin->fresh()->active);
    }

    public function test_wood_photo_and_default_are_shown_on_new_order(): void
    {
        $alder = WoodType::create(['name' => 'Olše']);

        $this->actingAs($this->admin)->put(route('catalog.wood.update', $alder), [
            'name'       => 'Olše',
            'active'     => 1,
            'is_default' => 1,
            'photo'      => UploadedFile::fake()->image('olse.jpg', 1200, 900),
        ])->assertSessionHasNoErrors();

        $alder->refresh();
        $this->assertTrue($alder->is_default);
        Storage::disk(Photos::DISK)->assertExists(Photos::variantPath($alder->photo_path, 'thumb'));

        // Výchozí je jen jedna
        $this->put(route('catalog.wood.update', $this->wood), ['name' => 'Dub', 'active' => 1, 'is_default' => 1]);
        $this->assertFalse($alder->fresh()->is_default);
        $alder->makeDefault();

        $html = $this->actingAs($this->client)->get(route('orders.create'))
            ->assertSee($alder->photoUrl(), false)->getContent();
        $this->assertMatchesRegularExpression('/id="wood' . $alder->id . '"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="wood' . $this->wood->id . '"[^>]*checked/', $html);

        $this->get($alder->photoUrl())->assertOk()->assertHeader('Content-Type', 'image/webp');

        $old = $alder->photo_path;
        $this->actingAs($this->admin)->put(route('catalog.wood.update', $alder), ['name' => 'Olše', 'active' => 1, 'remove_photo' => 1]);
        $this->assertNull($alder->fresh()->photo_path);
        Storage::disk(Photos::DISK)->assertMissing($old);
    }

    public function test_inner_dimensions_are_optional_pair_and_shown_with_outer_size(): void
    {
        $base = [
            'pet_name' => 'Micka', 'wood_type_id' => $this->wood->id, 'volume_ml' => 1500,
            'photos'   => [UploadedFile::fake()->image('a.jpg')],
        ];

        $this->actingAs($this->client)->post(route('orders.store'), $base + ['inner_width_cm' => 10])
            ->assertSessionHasErrors('inner_height_cm');

        $this->post(route('orders.store'), $base + ['inner_width_cm' => 10, 'inner_height_cm' => 15])
            ->assertSessionHasNoErrors();

        $order = Order::latest('id')->first();
        $this->assertSame('10 × 10 × 15 cm', $order->dimensionsLabel());
        // stěny 2 × 8 mm, výška + 8 mm dno + 15 mm víko + 8 mm horní stěna
        $this->assertSame('11,6 × 11,6 × 18,1 cm', $order->dimensionsLabel(outer: true));

        $this->get(route('orders.show', $order))->assertSee('11,6 × 11,6 × 18,1 cm');
    }

    public function test_urn_without_paw_needs_no_photo_and_layout_only_with_paw_and_text(): void
    {
        $base = ['pet_name' => 'Bobík', 'wood_type_id' => $this->wood->id, 'volume_ml' => 800];

        // S tlapkou bez fotky neprojde
        $this->actingAs($this->client)->post(route('orders.store'), $base)->assertSessionHasErrors('photos');

        // Bez tlapky, jen text – fotka ani rozložení nejsou potřeba
        $this->post(route('orders.store'), $base + ['with_paw' => 0, 'layout' => 'paw_left', 'urn_lines' => [
            ['text' => ' Bobík ', 'size' => 'L'], ['text' => '', 'size' => 'M'], ['text' => '2012–2026', 'size' => 'S'],
        ]])
            ->assertSessionHasNoErrors();
        $order = Order::latest('id')->first();
        $this->assertFalse($order->with_paw);
        // Prázdný řádek vypadne, text se ořízne, velikosti zůstanou
        $this->assertSame([['text' => 'Bobík', 'size' => 'L'], ['text' => '2012–2026', 'size' => 'S']], $order->urn_lines);
        $this->assertSame("Bobík\n2012–2026", $order->urn_text);
        $this->assertSame('Bobík (velké) / 2012–2026 (malé)', $order->urnTextWithSizes());
        $this->assertNull($order->layout);

        // Tlapka + text → rozložení se uloží (bez volby výchozí)
        $this->post(route('orders.store'), $base + [
            'with_paw' => 1, 'urn_lines' => [['text' => 'Bobík', 'size' => 'L']], 'layout' => 'text_left',
            'photos'   => [UploadedFile::fake()->image('a.jpg')],
        ])->assertSessionHasNoErrors();
        $order = Order::latest('id')->first();
        $this->assertSame('text_left', $order->layout);

        $this->get(route('orders.show', $order))->assertSee('Text vlevo, tlapka vpravo');

        $this->post(route('orders.store'), $base + ['with_paw' => 0, 'layout' => 'nesmysl'])->assertSessionHasErrors('layout');
    }

    public function test_admin_can_record_order_without_emails(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), [
            'pet_name' => 'Stará zakázka', 'wood_type_id' => $this->wood->id, 'volume_ml' => 800,
            'with_paw' => 0, 'notify' => 0,
        ])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertFalse($order->notify);

        $this->post(route('orders.status', $order), ['status' => Order::STATUS_IN_PRODUCTION, 'body' => 'Zpětně']);
        $this->post(route('messages.store', $order), ['body' => 'Poznámka']);
        $this->get(route('orders.show', $order))->assertSee('bez e-mailů');

        Mail::assertNothingSent();
    }

    public function test_client_cannot_turn_off_emails(): void
    {
        $this->actingAs($this->client)->post(route('orders.store'), [
            'pet_name' => 'Rex', 'wood_type_id' => $this->wood->id, 'volume_ml' => 800,
            'with_paw' => 0, 'notify' => 0,
        ])->assertRedirect();

        $this->assertTrue(Order::firstOrFail()->notify);
        Mail::assertSent(OrderUpdateMail::class);
        $this->get(route('orders.create'))->assertDontSee('Posílat e-maily k této zakázce');
    }

    public function test_admin_toggles_emails_on_order_detail(): void
    {
        $order = $this->createOrder();

        $this->post(route('orders.notify', $order), ['notify' => 0])->assertForbidden();

        $this->actingAs($this->admin)->get(route('orders.show', $order))->assertSee('Posílat e-maily k této zakázce');
        $this->post(route('orders.notify', $order), ['notify' => 0])->assertRedirect();
        $this->assertFalse($order->fresh()->notify);
    }

    public function test_default_tab_is_all_for_client_and_open_for_admin(): void
    {
        $order = $this->createOrder();
        $order->moveTo(Order::STATUS_HANDED_OVER);

        $this->get(route('orders.index'))->assertSee('Rex');
        $this->actingAs($this->admin)->get(route('orders.index'))->assertDontSee('Rex');
    }
}
