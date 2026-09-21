<?php

use App\Enums\Catalog;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Team;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Open the record screen of an in-progress appointment for a new member of a new team.
 *
 * @return array{0: Testable, 1: Team}
 */
function attendWithCatalogs(): array
{
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);

    test()->actingAs($user);
    $user->switchTeam($team);

    return [Livewire::test('pages::appointments.attend', ['appointment' => $appointment]), $team];
}

test('catalog entries created inline are trimmed and selected', function (Catalog $catalog, Closure $selection) {
    [$component, $team] = attendWithCatalogs();

    if ($catalog === Catalog::PassType) {
        $component->call('addPassPrescription');
    }

    $component
        ->call('createCatalogEntry', $catalog->value, '  Nova entrada  ', 0)
        ->assertHasNoErrors();

    $entry = $team->{$catalog->relationName()}()->sole();

    $component->assertReturned(['id' => (string) $entry->id, 'name' => 'Nova entrada']);

    expect($entry->name)->toBe('Nova entrada')
        ->and($selection($component))->toBe((string) $entry->id);
})->with([
    'mentor' => [Catalog::Mentor, fn ($component) => $component->get('mentorId')],
    'fluidic remedy' => [Catalog::FluidicRemedy, fn ($component) => $component->get('fluidicRemedyIds')[0]],
    'guidance' => [Catalog::Guidance, fn ($component) => $component->get('guidanceIds')[0]],
    'pass type' => [Catalog::PassType, fn ($component) => $component->get('passPrescriptions')[0]['pass_type_id']],
]);

test('catalog entry names are unique per team', function (Catalog $catalog) {
    [$component, $team] = attendWithCatalogs();
    $catalog->modelClass()::factory()->for($team)->create(['name' => 'Repetida']);
    $catalog->modelClass()::factory()->for(teamOwnedBy(User::factory()->create()))->create(['name' => 'De outra casa']);

    $component
        ->call('createCatalogEntry', $catalog->value, ' Repetida ')
        ->assertHasErrors(['new_'.$catalog->value => 'unique'])
        ->call('createCatalogEntry', $catalog->value, 'De outra casa')
        ->assertHasNoErrors(['new_'.$catalog->value]);

    expect($team->{$catalog->relationName()}()->pluck('name')->sort()->values()->all())->toBe(['De outra casa', 'Repetida']);
})->with(Catalog::cases());

test('catalog entries of another team cannot be selected', function () {
    [$component] = attendWithCatalogs();
    $otherTeam = teamOwnedBy(User::factory()->create());

    $component
        ->set('guidanceIds', [(string) Catalog::Guidance->modelClass()::factory()->for($otherTeam)->create()->id])
        ->call('addPassPrescription')
        ->set('passPrescriptions.0.pass_type_id', (string) Catalog::PassType->modelClass()::factory()->for($otherTeam)->create()->id)
        ->call('save')
        ->assertHasErrors(['guidanceIds.0' => 'exists', 'passPrescriptions.0.pass_type_id' => 'exists']);
});

test('follow-up appointment types can be created inline and are selected', function (string $target) {
    [$component, $team] = attendWithCatalogs();
    AppointmentType::factory()->for($team)->create(['name' => 'Retirada']);

    $component
        ->call('createAppointmentType', ' Retirada ', $target)
        ->assertHasErrors(['new_'.$target => 'unique'])
        ->call('createAppointmentType', ' Retirada de infiltração ', $target)
        ->assertHasNoErrors();

    $appointmentType = $team->appointmentTypes()->firstWhere('name', 'Retirada de infiltração');

    $component->assertReturned(['id' => (string) $appointmentType->id, 'name' => 'Retirada de infiltração']);

    expect($appointmentType->requires_record)->toBeFalse()
        ->and($component->get($target))->toBe((string) $appointmentType->id);
})->with(['removalAppointmentTypeId', 'returnAppointmentTypeId']);

test('appointment types cannot be created inline into other form fields', function () {
    [$component, $team] = attendWithCatalogs();
    $typeCount = $team->appointmentTypes()->count();

    $component
        ->call('createAppointmentType', 'Novo tipo', 'mentorId')
        ->assertNotFound();

    expect($team->appointmentTypes()->count())->toBe($typeCount);
});

test('pickers load the team catalog entries in the background instead of rendering them', function (Catalog $catalog) {
    [$component, $team] = attendWithCatalogs();
    $factory = $catalog->modelClass()::factory();
    $zebra = $factory->for($team)->create(['name' => 'Zebra']);
    $abacate = $factory->for($team)->create(['name' => 'Abacate']);
    $factory->for($team)->trashed()->create(['name' => 'Excluída']);
    $factory->for(teamOwnedBy(User::factory()->create()))->create(['name' => 'De outra casa']);

    $component
        ->call('pickerOptions')
        ->assertReturned(fn (array $options) => $options[$catalog->value] === [
            ['id' => (string) $abacate->id, 'name' => 'Abacate'],
            ['id' => (string) $zebra->id, 'name' => 'Zebra'],
        ]);

    Livewire::test('pages::appointments.attend', ['appointment' => $component->get('appointment')])
        ->assertDontSee('Abacate')
        ->assertDontSee('Zebra');
})->with(Catalog::cases());

test('one background call brings every picker list, including the follow-up appointment types', function () {
    [$component, $team] = attendWithCatalogs();
    AppointmentType::factory()->for($team)->trashed()->create(['name' => 'Tipo excluído']);
    AppointmentType::factory()->for(teamOwnedBy(User::factory()->create()))->create();

    $component
        ->call('pickerOptions')
        ->assertReturned(fn (array $options) => array_keys($options) === [...array_column(Catalog::cases(), 'value'), 'appointment_types']
            && $options['appointment_types'] === $team->appointmentTypes()->orderBy('name')->get()->map(fn (AppointmentType $appointmentType) => [
                'id' => (string) $appointmentType->id,
                'name' => $appointmentType->name,
            ])->all());
});

test('selected catalog entries are named on the page', function () {
    [$component, $team] = attendWithCatalogs();
    $remedies = Catalog::FluidicRemedy->modelClass()::factory()->for($team)->count(2)->sequence(['name' => 'Calmante'], ['name' => 'Vitamina D'])->create();

    $component
        ->set('fluidicRemedyIds', [(string) $remedies[1]->id])
        ->assertSee('Vitamina D')
        ->assertDontSee('Calmante');
});

test('the picker x-data does not change with the selection, so saving does not re-create it', function () {
    [$component, $team] = attendWithCatalogs();
    $remedy = Catalog::FluidicRemedy->modelClass()::factory()->for($team)->create(['name' => 'Calmante']);
    $pickerData = fn (string $html): string => str($html)->match('/x-data="(catalogPicker\(\{\s*model: \'fluidicRemedyIds\'.*?\}\))"/s')->toString();

    $before = $pickerData($component->html());
    $after = $pickerData($component->set('fluidicRemedyIds', [(string) $remedy->id])->html());

    expect($before)->not->toBe('')
        ->and($after)->toBe($before)
        ->and($component->html())->toContain('data-selected-options="{&quot;'.$remedy->id.'&quot;:&quot;Calmante&quot;}"');
});
