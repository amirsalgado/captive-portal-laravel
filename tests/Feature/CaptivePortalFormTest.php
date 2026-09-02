<?php

namespace Tests\Feature;

use App\Livewire\CaptivePortalForm;
use App\Models\Client;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CaptivePortalFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_submission_creates_a_client_and_records_a_visit(): void
    {
        Livewire::test(CaptivePortalForm::class)
            ->set('full_name', 'Juan Perez')
            ->set('phone_number', '3001234567')
            ->set('birth_date', '1990-05-15')
            ->call('submit');

        $client = Client::where('phone_number', '3001234567')->first();

        $this->assertNotNull($client);
        $this->assertSame('Juan Perez', $client->full_name);
        $this->assertSame(1, Visit::where('client_id', $client->id)->count());
    }

    public function test_resubmitting_an_existing_phone_number_updates_the_client_and_adds_a_new_visit(): void
    {
        $client = Client::factory()->create(['phone_number' => '3001234567']);

        Livewire::test(CaptivePortalForm::class)
            ->set('full_name', 'Nombre Actualizado')
            ->set('phone_number', '3001234567')
            ->set('birth_date', '1990-05-15')
            ->call('submit');

        $this->assertSame(1, Client::where('phone_number', '3001234567')->count());
        $this->assertSame('Nombre Actualizado', $client->fresh()->full_name);
        $this->assertSame(1, Visit::where('client_id', $client->id)->count());
    }

    public function test_form_resets_after_a_successful_submission(): void
    {
        Livewire::test(CaptivePortalForm::class)
            ->set('full_name', 'Juan Perez')
            ->set('phone_number', '3001234567')
            ->set('birth_date', '1990-05-15')
            ->call('submit')
            ->assertSet('full_name', '')
            ->assertSet('phone_number', '')
            ->assertSet('birth_date', '');
    }

    public function test_full_name_is_required(): void
    {
        Livewire::test(CaptivePortalForm::class)
            ->set('full_name', '')
            ->set('phone_number', '3001234567')
            ->set('birth_date', '1990-05-15')
            ->call('submit')
            ->assertHasErrors(['full_name' => 'required']);

        $this->assertSame(0, Client::count());
    }

    public function test_phone_number_must_be_numeric(): void
    {
        Livewire::test(CaptivePortalForm::class)
            ->set('full_name', 'Juan Perez')
            ->set('phone_number', 'abc123')
            ->set('birth_date', '1990-05-15')
            ->call('submit')
            ->assertHasErrors(['phone_number' => 'numeric']);
    }

    public function test_phone_number_must_have_between_10_and_15_digits(): void
    {
        Livewire::test(CaptivePortalForm::class)
            ->set('full_name', 'Juan Perez')
            ->set('phone_number', '12345')
            ->set('birth_date', '1990-05-15')
            ->call('submit')
            ->assertHasErrors(['phone_number' => 'digits_between']);
    }

    public function test_birth_date_must_be_before_today(): void
    {
        Livewire::test(CaptivePortalForm::class)
            ->set('full_name', 'Juan Perez')
            ->set('phone_number', '3001234567')
            ->set('birth_date', now()->format('Y-m-d'))
            ->call('submit')
            ->assertHasErrors(['birth_date' => 'before']);
    }
}
