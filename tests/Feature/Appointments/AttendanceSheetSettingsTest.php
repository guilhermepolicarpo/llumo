<?php

use App\Enums\AttendanceSheetSection;
use App\Enums\TeamRole;
use App\Models\Guidance;
use App\Models\PassType;
use App\Models\User;
use Livewire\Livewire;

test('a team that never chose prints every section, guidance and pass', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);
    $guidance = Guidance::factory()->for($team)->create();
    $passType = PassType::factory()->for($team)->create();

    Livewire::test('pages::team-settings.attendance-sheet')
        ->assertSet('sections', [
            'guidances' => true,
            'passes' => true,
            'infiltration' => true,
            'return' => true,
            'observations' => true,
            'fluidic_remedies' => true,
        ])
        ->assertSet('printedEntryIds', ['guidances' => [(string) $guidance->id], 'passes' => [(string) $passType->id]]);
});

test('admins choose the sections, guidances and passes the blank sheet prints', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);
    $printedGuidance = Guidance::factory()->for($team)->create();
    $leftOutGuidance = Guidance::factory()->for($team)->create();
    $printedPassType = PassType::factory()->for($team)->leftOffAttendanceSheet()->create();
    $leftOutPassType = PassType::factory()->for($team)->create();

    Livewire::test('pages::team-settings.attendance-sheet')
        ->set('sections.infiltration', false)
        ->set('sections.fluidic_remedies', false)
        ->set('printedEntryIds.guidances', [(string) $printedGuidance->id])
        ->set('printedEntryIds.passes', [(string) $printedPassType->id])
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($team->fresh()->attendance_sheet_hidden_sections->all())->toBe([AttendanceSheetSection::Infiltration, AttendanceSheetSection::FluidicRemedies])
        ->and($printedGuidance->fresh()->printed_on_attendance_sheet)->toBeTrue()
        ->and($leftOutGuidance->fresh()->printed_on_attendance_sheet)->toBeFalse()
        ->and($printedPassType->fresh()->printed_on_attendance_sheet)->toBeTrue()
        ->and($leftOutPassType->fresh()->printed_on_attendance_sheet)->toBeFalse();
});

test('the guidances of another team cannot be chosen for the sheet', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);
    $otherTeamGuidance = Guidance::factory()->for(teamOwnedBy(User::factory()->create()))->create();

    Livewire::test('pages::team-settings.attendance-sheet')
        ->set('printedEntryIds.guidances', [(string) $otherTeamGuidance->id])
        ->call('saveSettings')
        ->assertHasErrors('printedEntryIds.guidances.0');

    expect($otherTeamGuidance->fresh()->printed_on_attendance_sheet)->toBeTrue();
});

test('members cannot open the attendance sheet settings', function () {
    actingAsTeamMember(TeamRole::Member);

    Livewire::test('pages::team-settings.attendance-sheet')->assertForbidden();
});

test('the settings navigation links to the attendance sheet settings', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $this->get(route('team-settings.attendance-sheet', ['current_team' => $team]))
        ->assertOk()
        ->assertSeeHtml('data-test="team-settings-nav-attendance-sheet"');
});
