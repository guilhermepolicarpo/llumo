@use('App\Enums\AppointmentMode')
@use('App\Enums\AttendanceSheetSection')
@use('App\Models\AppointmentRecord')

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ trans_choice('Attendance sheet|Attendance sheets', $appointments->count()) }}</title>
    <style>
        @page { margin: 10mm 11mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; line-height: 1.2; color: #141414; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 0; vertical-align: bottom; }
        .page { position: relative; height: 188mm; }
        .page-break { page-break-before: always; }
        .shrink { white-space: nowrap; width: 1%; }

        .header td { vertical-align: middle; }
        .header .logo { padding-right: 2.5mm; }
        .header .logo img { max-width: 32mm; max-height: 15.5mm; }
        .team-name { font-size: 10pt; font-weight: bold; text-transform: uppercase; }
        .team-detail { font-size: 7.5pt; color: #333; margin-top: 0.7mm; }
        .header .badge-cell { width: 26mm; text-align: center; }
        .badge { border: 1.1pt solid #141414; border-radius: 0.7mm; padding: 1.3mm 1.4mm; font-size: 7.5pt; font-weight: bold; line-height: 1.2; letter-spacing: 0.4pt; text-transform: uppercase; }
        .badge-date { font-size: 7.5pt; color: #333; margin-top: 1mm; }
        .rule { border: 0; border-top: 1.6pt solid #141414; margin: 3mm 0 3.5mm; }

        .label { font-size: 7.5pt; font-weight: bold; letter-spacing: 0.3pt; text-transform: uppercase; color: #333; }
        .identification { margin-bottom: 2.1mm; }
        .identification td { border-bottom: 0.8pt solid #141414; padding-bottom: 0.7mm; }
        .identification td.label { padding-right: 1.4mm; }
        .identification td.gap { border-bottom: 0; width: 4.2mm; }
        .identification .value { font-size: 9.5pt; }

        .section-label { margin: 4mm 0 1.5mm; }
        .guidance-columns td { vertical-align: top; }
        .guidance-columns.two-columns td { width: 50%; }
        .guidance-columns.two-columns td + td { padding-left: 3mm; }
        .item { margin-bottom: 2mm; padding-left: 5.7mm; text-indent: -5.7mm; }
        .box { display: inline-block; text-indent: 0; width: 3mm; height: 3mm; border: 0.8pt solid #141414; border-radius: 0.4mm; vertical-align: -1.1mm; margin-right: 2.1mm; position: relative; }
        .radio { display: inline-block; width: 3mm; height: 3mm; border: 0.8pt solid #141414; border-radius: 1.5mm; }
        .tick { position: absolute; left: 0.3mm; top: -0.9mm; font-size: 10pt; font-weight: bold; line-height: 1; }
        .radio.checked { background: #141414; }
        .detail { color: #333; }
        .written-notes { margin-top: 1mm; white-space: pre-line; }

        .passes { margin-top: 2mm; border-bottom: 0.8pt solid #141414; }
        .passes th { padding: 0 0 1.4mm; border-bottom: 0.8pt solid #141414; text-align: center; font-size: 7.5pt; font-weight: bold; color: #333; }
        .passes th.label { text-align: left; }
        .passes td { height: 8.8mm; vertical-align: middle; text-align: center; border-top: 0.5pt solid #bdbdbd; }
        .passes tr:first-child td { border-top: 0; }
        .passes td.name { text-align: left; }
        .passes .quantity { display: inline-block; width: 8.5mm; height: 3.5mm; border-bottom: 0.8pt solid #141414; }

        .write-in table { margin-bottom: 2.4mm; }
        .write-in td { padding-right: 1.8mm; }
        .write-in td.return-option { padding-right: 5mm; }
        .line-fill, .blank, .notes-line, .back-line { border-bottom: 0.5pt solid #bdbdbd; }
        .blank { display: inline-block; height: 3.5mm; vertical-align: -0.7mm; }
        .notes-line { height: 6mm; }
        .section-label + .notes-line { height: 4.1mm; }

        .closing { position: absolute; left: 0; right: 0; bottom: 0; }
        .closing .signature-line { width: 56mm; height: 9mm; border-bottom: 0.8pt solid #141414; }
        .closing .caption { padding-top: 1mm; vertical-align: top; color: #333; }

        .back-title { font-size: 12pt; font-weight: bold; letter-spacing: 0.6pt; text-transform: uppercase; }
        .back-detail { color: #333; margin-top: 0.7mm; }
        .back-rule { border: 0; border-top: 1.6pt solid #141414; margin: 3.2mm 0 0; }
        .back-line { height: 8mm; }
        .back-line.filled { height: auto; padding-top: 3.6mm; line-height: 3.4mm; white-space: nowrap; }
        .back-columns td { width: 50%; border-bottom: 0.5pt solid #bdbdbd; }
        .back-columns .back-line { border-bottom: 0; }
        .back-footer { position: absolute; left: 0; bottom: 0; font-size: 7.5pt; color: #555; }
    </style>
</head>
<body>
    @foreach ($appointments as $appointment)
        @php
            $assistedPerson = $appointment->assistedPerson;
            // A completed appointment's sheet is printed filled in with its record, listing only what was given; a blank sheet
            // lists the catalogs to be marked by hand, keeping only the sections, guidances and passes the team chose to print.
            $record = $appointment->filledRecord();
            $sheetHiddenSections = $record ? collect() : $hiddenSections;
            $givenGuidances = $record?->guidances->keyBy('id') ?? collect();
            $prescriptionsByPass = $record?->passPrescriptions->groupBy('pass_type_id') ?? collect();
            $sheetGuidances = $record ? $givenGuidances->pluck('name', 'id') : $guidances;
            $sheetPassTypes = $record ? $prescriptionsByPass->map(fn ($prescriptions) => $prescriptions->first()->passType->name) : $passTypes;

            // With more than two guidances they are split in two columns, filled top to bottom. A column fits about
            // 36 characters per line, so each guidance is estimated at its wrapped lines (≈6.3mm the first, ≈4.4mm each more).
            $guidanceRowHeight = 6.3;
            $guidanceColumns = $sheetGuidances->count() > 2 ? $sheetGuidances->splitIn(2) : collect([$sheetGuidances]);
            $guidanceLines = fn (string $name, int $id) => max(1, (int) ceil(mb_strlen(trim($name.' '.$givenGuidances->get($id)?->pivot->detail)) / 36));
            $guidanceColumnLines = $guidanceColumns->map(fn ($column) => $column->map($guidanceLines)->values());
            $guidancesHeight = $guidanceColumnLines->first()->keys()
                ->sum(fn (int $row) => $guidanceRowHeight + 4.4 * ($guidanceColumnLines->map(fn ($lines) => $lines->get($row, 1))->max() - 1));

            // The closing is pinned to the page bottom, leaving ≈23mm below the notes title for its lines (≈6.2mm each, border
            // included) on a sheet laid out for three rows of guidances and three passes. Each guidance row or pass (≈9mm)
            // short of the three gives its room to the notes, and each one beyond them takes from it. A section left out, or
            // a catalog with nothing to list, also gives the height of its title and lines.
            $extraCatalogHeight = ($guidancesHeight - 3 * $guidanceRowHeight) + ($sheetPassTypes->count() - 3) * 9;
            $leftOutSectionHeights = [
                AttendanceSheetSection::Guidances->value => $sheetGuidances->isEmpty() ? 8 : 0,
                AttendanceSheetSection::Passes->value => $sheetPassTypes->isEmpty() ? 6 : 0,
                AttendanceSheetSection::Infiltration->value => $sheetHiddenSections->contains(AttendanceSheetSection::Infiltration) ? 20 : 0,
                AttendanceSheetSection::Return->value => $sheetHiddenSections->contains(AttendanceSheetSection::Return) ? 20 : 0,
            ];
            $leftOutHeight = array_sum($leftOutSectionHeights);
            $notesLineCount = max(0, (int) floor((23 + $leftOutHeight - $extraCatalogHeight) / 6.2));
            $sheetDay = $appointment->received_at ?? today();
            $sheetDate = $sheetDay->translatedFormat('d \d\e F \d\e Y');
        @endphp

        {{-- Front: filled in at the reception, then marked by the medium during the attendance. --}}
        <div @class(['page', 'page-break' => ! $loop->first])>
            <table class="header">
                <tr>
                    @if ($logo)
                        <td class="logo shrink"><img src="{{ $logo }}" alt=""></td>
                    @endif
                    <td>
                        <div class="team-name">{{ $team->display_legal_name }}</div>
                        @if ($team->address_summary)
                            <div class="team-detail">{{ $team->address_summary }}</div>
                        @endif
                        @if ($team->formatted_phone)
                            <div class="team-detail">{{ $team->formatted_phone }}</div>
                        @endif
                    </td>
                    <td class="badge-cell">
                        <div class="badge">{{ trans_choice('Attendance sheet|Attendance sheets', 1) }}</div>
                        <div class="badge-date">{{ $sheetDay->format('d/m/Y') }}</div>
                    </td>
                </tr>
            </table>

            <hr class="rule">

            <table class="identification">
                <tr>
                    <td class="label shrink">{{ __('Name') }}:</td>
                    <td class="value">{{ $assistedPerson->name }}</td>
                    <td class="gap"></td>
                    <td class="label shrink">{{ __('Age') }}:</td>
                    <td class="value" style="width: 19mm;">{{ $assistedPerson->formatted_age }}</td>
                </tr>
            </table>
            <table class="identification">
                <tr>
                    <td class="label shrink">{{ __('Address') }}:</td>
                    <td>{{ $assistedPerson->address_summary }}</td>
                </tr>
            </table>

            @if ($sheetGuidances->isNotEmpty())
                <div class="label section-label">{{ __('Guidances') }}</div>
                <table @class(['guidance-columns', 'two-columns' => $guidanceColumns->count() > 1])>
                    <tr>
                        @foreach ($guidanceColumns as $column)
                            <td class="guidance-column">
                                @foreach ($column as $guidanceId => $guidance)
                                    @php
                                        $given = $givenGuidances->get($guidanceId);
                                    @endphp
                                    <div class="item">
                                        <span class="box">@if ($given)<span class="tick">✓</span>@endif</span>{{ $guidance }}
                                        @if ($given?->pivot->detail)
                                            <span class="detail">· {{ $given->pivot->detail }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                </table>
            @endif

            @if ($sheetPassTypes->isNotEmpty())
                <table class="passes">
                    <thead>
                        <tr>
                            <th class="label">{{ __('Passes') }}</th>
                            <th style="width: 17mm;">{{ __('Qty.') }}</th>
                            @foreach (AppointmentMode::cases() as $mode)
                                <th style="width: 20mm;">{{ $mode->label() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sheetPassTypes as $passTypeId => $passType)
                            @php
                                $prescriptions = $prescriptionsByPass->get($passTypeId, collect());
                            @endphp
                            <tr>
                                <td class="name">{{ $passType }}</td>
                                <td>
                                    @if ($prescriptions->isNotEmpty())
                                        <span class="filled">{{ $prescriptions->pluck('quantity')->implode(' + ') }}</span>
                                    @else
                                        <span class="quantity"></span>
                                    @endif
                                </td>
                                @foreach (AppointmentMode::cases() as $mode)
                                    <td><span @class(['radio', 'checked' => $prescriptions->contains('mode', $mode)])></span></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @unless ($sheetHiddenSections->contains(AttendanceSheetSection::Infiltration))
                <div class="label section-label">{{ __('Infiltration') }}</div>
                <div class="write-in">
                    @if ($record?->infiltration_site)
                        <table>
                            <tr>
                                <td>{{ __('Site or reason') }}: <span class="filled">{{ $record->infiltration_site }}</span></td>
                            </tr>
                        </table>
                    @else
                        <table>
                            <tr>
                                <td class="shrink">{{ __('Site or reason') }}:</td>
                                <td class="line-fill"></td>
                            </tr>
                        </table>
                    @endif
                    <table>
                        <tr>
                            <td>
                                {{ __('Remove on day') }}:
                                @if ($record?->infiltration_remove_at)
                                    <span class="filled">{{ $record->infiltration_remove_at->translatedFormat('d/m/Y (l)') }}</span>
                                @else
                                    <span class="blank" style="width: 5mm;"></span>/<span class="blank" style="width: 5mm;"></span>/<span class="blank" style="width: 8mm;"></span>
                                @endif
                                @include('pdf.partials.time', ['time' => AppointmentRecord::timeOf($record?->infiltration_remove_at)])
                            </td>
                        </tr>
                    </table>
                </div>
            @endunless

            @unless ($sheetHiddenSections->contains(AttendanceSheetSection::Return))
                <div class="label section-label">{{ __('Return') }}</div>
                <div class="write-in">
                    @if ($record?->return_at)
                        <table>
                            <tr>
                                <td>
                                    {{ __('Return on day') }}: <span class="filled">{{ $record->return_at->translatedFormat('d/m/Y (l)') }}</span>
                                    @include('pdf.partials.time', ['time' => AppointmentRecord::timeOf($record->return_at)])
                                </td>
                            </tr>
                        </table>
                    @else
                        {{-- The same intervals the record form offers as shortcuts, plus a line below for any other return. --}}
                        <table>
                            <tr>
                                @foreach (AppointmentRecord::RETURN_INTERVALS_DAYS as $days)
                                    <td class="shrink return-option"><span class="box"></span>{{ __('+:days days', ['days' => $days]) }}</td>
                                @endforeach
                                <td></td>
                            </tr>
                        </table>
                        <table>
                            <tr>
                                <td class="shrink"><span class="box"></span>{{ __('Other date') }}:</td>
                                <td class="line-fill"></td>
                                <td class="shrink">@include('pdf.partials.time', ['time' => null])</td>
                            </tr>
                        </table>
                    @endif
                </div>
            @endunless

            @unless ($sheetHiddenSections->contains(AttendanceSheetSection::Observations))
                <div class="label section-label">{{ __('Observations') }}</div>
                @if ($record?->observations)
                    <div class="written-notes filled">{{ $record->observations }}</div>
                @else
                    @for ($line = 0; $line < $notesLineCount; $line++)
                        <div class="notes-line"></div>
                    @endfor
                @endif
            @endunless

            <div class="closing">
                <table>
                    <tr>
                        {{-- Spans both rows, so the city lines up with the signature caption and the date sits right above it. --}}
                        <td rowspan="2">
                            <div>{{ $sheetDate }}</div>
                            @if ($team->address_city_line)
                                <div class="detail">{{ $team->address_city_line }}</div>
                            @endif
                        </td>
                        <td class="signature-line" style="text-align: center; vertical-align: bottom;">{{ $record?->mentor?->name }}</td>
                    </tr>
                    <tr>
                        <td class="caption" style="text-align: center;">{{ __('Mentor signature') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- Back: lined for the fluidic remedies, written by hand during the attendance. --}}
        @unless ($sheetHiddenSections->contains(AttendanceSheetSection::FluidicRemedies))
            <div class="page page-break">
                <div class="back-title">{{ __('Fluidic remedies') }}</div>
                <div class="back-detail">{{ $assistedPerson->name }} · {{ $sheetDate }}</div>
                <hr class="back-rule">
                @php
                    // Each written line takes one ruled line, so the instructions are wrapped to the width of the page; when the
                    // remedies would not fit the ruled lines in one column, they are split into two columns, filled top to bottom.
                    $backLineCount = 20;
                    $remedies = $record?->fluidicRemedies->pluck('name') ?? collect();
                    $instructionsLabel = __('How to take').':';
                    $instructionLines = $record?->fluid_instructions
                        ? collect(explode("\n", wordwrap($instructionsLabel.' '.$record->fluid_instructions, 75)))
                        : collect();
                    $remedyColumns = $remedies->count() + $instructionLines->count() > $backLineCount ? $remedies->split(2) : collect([$remedies]);
                    $writtenLineCount = $remedyColumns->first()->count() + $instructionLines->count();
                @endphp
                @if ($remedyColumns->count() > 1)
                    <table class="back-columns">
                        @foreach ($remedyColumns->first()->zip($remedyColumns->last()) as [$leftRemedy, $rightRemedy])
                            <tr>
                                <td><div class="back-line filled">{{ $leftRemedy }}</div></td>
                                <td><div class="back-line filled">{{ $rightRemedy }}</div></td>
                            </tr>
                        @endforeach
                    </table>
                @else
                    @foreach ($remedies as $remedy)
                        <div class="back-line filled">{{ $remedy }}</div>
                    @endforeach
                @endif
                @foreach ($instructionLines as $line)
                    <div class="back-line filled">
                        @if ($loop->first)
                            <span class="label">{{ $instructionsLabel }}</span>{{ mb_substr($line, mb_strlen($instructionsLabel)) }}
                        @else
                            {{ $line }}
                        @endif
                    </div>
                @endforeach
                @for ($line = $writtenLineCount; $line < $backLineCount; $line++)
                    <div class="back-line"></div>
                @endfor
                <div class="back-footer">{{ $team->display_legal_name }}</div>
            </div>
        @endunless
    @endforeach
</body>
</html>
