<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\PaymentAttempt;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminEventControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_open_course_editor(): void
    {
        $this->get(route('admin.events.create'))->assertRedirect(route('admin.login'));
    }

    public function test_guest_cannot_delete_course(): void
    {
        $event = Event::factory()->create();

        $this->delete(route('admin.events.destroy', $event))->assertRedirect(route('admin.login'));

        $this->assertModelExists($event);
    }

    public function test_admin_creates_course_with_exact_price_and_public_details(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.events.store'), [
            'title' => 'Oficina de Bordado',
            'description' => 'Aprenda pontos básicos.',
            'place' => 'Rua das Flores, 10',
            'materials' => 'Agulha e linha',
            'price_reais' => '85,90',
            'capacity' => '12',
            'cash_payment_deadline' => '2026-10-10T18:30',
            'status' => 'published',
        ])->assertRedirect(route('admin.events.index'));

        $event = Event::firstOrFail();
        $this->assertSame(8590, $event->price_cents);
        $this->assertSame(12, $event->capacity);
        $this->assertSame('10/10/2026 18:30', $event->cash_payment_deadline->format('d/m/Y H:i'));
        $this->get(route('admin.events.index'))->assertSee('Suas oficinas');
        $this->get(route('admin.events.edit', $event))->assertSee('Editar oficina');
        $this->get(route('admin.events.edit', $event))->assertSee('Prazo para pagamento em dinheiro');
        $this->get(route('events.show', $event->id))
            ->assertSee('Rua das Flores, 10')
            ->assertSee('Agulha e linha')
            ->assertSee('R$ 85,90');
    }

    public function test_admin_cannot_publish_course_with_zero_price(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.events.store'), [
            'title' => 'Oficina de Bordado',
            'description' => 'Aprenda pontos básicos.',
            'price_reais' => '0,00',
            'status' => 'published',
        ])->assertSessionHasErrors('price_reais');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_admin_cannot_set_a_capacity_lower_than_one(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.events.store'), [
            'title' => 'Oficina de Bordado',
            'description' => 'Aprenda pontos básicos.',
            'price_reais' => '85,00',
            'capacity' => '0',
            'status' => 'published',
        ])->assertSessionHasErrors('capacity');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_admin_uploads_replaces_and_removes_course_banner(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.events.store'), [
            'title' => 'Oficina de Bordado',
            'description' => 'Aprenda pontos básicos.',
            'price_reais' => '85,00',
            'status' => 'published',
            'banner_image' => UploadedFile::fake()->image('bordado.jpg', 1200, 675),
            'banner_alt' => 'Pessoas bordando em uma mesa',
        ])->assertRedirect(route('admin.events.index'));

        $event = Event::firstOrFail();
        $firstBanner = $event->banner_path;
        Storage::disk('public')->assertExists($firstBanner);
        $this->get(route('events.index'))->assertSee($event->banner_url, false);
        $this->get(route('events.show', $event->id))
            ->assertSee($event->banner_url, false)
            ->assertSee('Pessoas bordando em uma mesa');

        $this->post(route('admin.events.update', $event), [
            '_method' => 'PUT',
            'title' => $event->title,
            'description' => $event->description,
            'price_reais' => '85,00',
            'status' => 'published',
            'banner_image' => UploadedFile::fake()->image('novo-banner.png', 1200, 675),
            'banner_alt' => 'Novos materiais de bordado',
        ])->assertRedirect(route('admin.events.index'));

        $event->refresh();
        Storage::disk('public')->assertMissing($firstBanner);
        Storage::disk('public')->assertExists($event->banner_path);
        $this->get(route('admin.events.edit', $event))->assertSee('Imagem atual');

        $secondBanner = $event->banner_path;
        $this->post(route('admin.events.update', $event), [
            '_method' => 'PUT',
            'title' => $event->title,
            'description' => $event->description,
            'price_reais' => '85,00',
            'status' => 'published',
            'remove_banner' => '1',
        ])->assertRedirect(route('admin.events.index'));

        $event->refresh();
        $this->assertNull($event->banner_path);
        Storage::disk('public')->assertMissing($secondBanner);
    }

    public function test_admin_cannot_upload_a_file_that_is_not_an_image(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.events.store'), [
            'title' => 'Oficina de Bordado',
            'description' => 'Aprenda pontos básicos.',
            'price_reais' => '85,00',
            'status' => 'published',
            'banner_image' => UploadedFile::fake()->create('arquivo.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('banner_image');

        $this->post(route('admin.events.store'), [
            'title' => 'Oficina de Bordado',
            'description' => 'Aprenda pontos básicos.',
            'price_reais' => '85,00',
            'status' => 'published',
            'banner_image' => UploadedFile::fake()->image('grande.jpg', 1200, 675)->size(3000),
        ])->assertSessionHasErrors('banner_image');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_existing_image_link_is_kept_when_admin_edits_without_new_upload(): void
    {
        $organizer = User::factory()->create();
        $this->actingAs($organizer);
        $event = Event::factory()->for($organizer)->create([
            'banner_path' => 'https://example.com/oficina.jpg',
        ]);

        $this->post(route('admin.events.update', $event), [
            '_method' => 'PUT',
            'title' => $event->title,
            'description' => $event->description,
            'price_reais' => '85,00',
            'status' => 'published',
        ])->assertRedirect(route('admin.events.index'));

        $this->assertSame('https://example.com/oficina.jpg', $event->refresh()->banner_url);
        $this->get(route('events.show', $event->id))->assertSee('https://example.com/oficina.jpg', false);
    }

    public function test_admin_permanently_deletes_course_related_registrations_payments_and_banner(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('events/banner.jpg', 'image contents');
        $event = Event::factory()->create(['banner_path' => 'events/banner.jpg']);
        $registration = Registration::factory()->create(['event_id' => $event->id]);
        $paymentAttempt = PaymentAttempt::create([
            'registration_id' => $registration->id,
            'idempotency_key' => 'delete-course-payment',
            'environment' => 'testing',
            'amount_cents' => 8500,
            'currency' => 'BRL',
            'local_status' => 'pending',
        ]);
        $this->actingAs($event->user);

        $this->get(route('admin.events.index'))
            ->assertSee('Excluir curso')
            ->assertSee('Esta ação não pode ser desfeita.');
        $this->delete(route('admin.events.destroy', $event))
            ->assertRedirect(route('admin.events.index'))
            ->assertSessionHas('success', 'Curso removido permanentemente.');

        $this->assertModelMissing($paymentAttempt);
        $this->assertModelMissing($registration);
        $this->assertModelMissing($event);
        Storage::disk('public')->assertMissing('events/banner.jpg');
    }
}
