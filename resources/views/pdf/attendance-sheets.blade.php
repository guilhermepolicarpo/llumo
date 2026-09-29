@use('App\Enums\AppointmentMode')

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ trans_choice('Attendance sheet|Attendance sheets', $appointments->count()) }}</title>
    <style>
        @page { margin: 10mm 11mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; line-height: 1.35; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 0; }
        .page-break { page-break-before: always; }
        .header td { vertical-align: middle; }
        .header .logo { width: 20mm; padding-right: 3mm; }
        .header .logo img { width: 18mm; height: auto; }
        .header .center { text-align: center; }
        .team-name { font-size: 10.5pt; font-weight: bold; text-transform: uppercase; }
        .team-detail { font-size: 8.5pt; }
        .rule { border: 0; border-top: 0.6pt solid #111; margin: 3mm 0 4mm; }
        .shrink { white-space: nowrap; width: 1%; padding-right: 2mm; }
        .label { font-weight: bold; text-transform: uppercase; }
        .field { border-bottom: 0.6pt solid #111; padding: 0 1mm 0.5mm; }
        .identification td { padding-bottom: 2.5mm; }
        .section-title { font-weight: bold; text-transform: uppercase; margin: 2mm 0 2mm; }
        .item { margin-bottom: 2.5mm; text-transform: uppercase; }
        .sub-line { margin: 1mm 0 0 4.5mm; }
        .box { display: inline-block; width: 3mm; height: 3mm; border: 0.6pt solid #111; margin-right: 1.5mm; vertical-align: -0.4mm; }
        .line-row td { vertical-align: bottom; }
        .line-fill { border-bottom: 0.6pt solid #111; }
        .blank { display: inline-block; border-bottom: 0.6pt solid #111; height: 3mm; vertical-align: -0.5mm; }
        .option { white-space: nowrap; margin-right: 3mm; }
        .closing { margin-top: 8mm; }
        .closing td { padding-bottom: 7mm; }
        .back-title { font-size: 11pt; font-weight: bold; text-transform: uppercase; }
        .back-detail { font-size: 8pt; color: #555; }
    </style>
</head>
<body>
    @foreach ($appointments as $appointment)
        @php
            $assistedPerson = $appointment->assistedPerson;
            $sheetDate = ($appointment->received_at ?? today())->translatedFormat('d \d\e F \d\e Y');
        @endphp

        {{-- Front: filled in at the reception, then marked by the mentor during the attendance. --}}
        <div @class(['page-break' => ! $loop->first])>
            <table class="header">
                <tr>
                    @if ($logo)
                        <td class="logo"><img src="{{ $logo }}" alt=""></td>
                    @endif
                    <td class="center">
                        <div class="team-name">{{ $team->display_legal_name }}</div>
                        @if ($team->address_summary)
                            <div class="team-detail">{{ $team->address_summary }}</div>
                        @endif
                        @if ($team->formatted_phone)
                            <div class="team-detail">{{ __('Phone') }}: {{ $team->formatted_phone }}</div>
                        @endif
                    </td>
                </tr>
            </table>

            <hr class="rule">

            <table class="identification">
                <tr>
                    <td class="label shrink">{{ __('Name') }}:</td>
                    <td class="field">{{ $assistedPerson->name }}</td>
                    <td class="label shrink" style="padding-left: 3mm;">{{ __('Age') }}:</td>
                    <td class="field" style="width: 20mm;">{{ $assistedPerson->formatted_age }}</td>
                </tr>
            </table>
            <table class="identification">
                <tr>
                    <td class="label shrink">{{ __('Address') }}:</td>
                    <td class="field">{{ $assistedPerson->address_summary }}</td>
                </tr>
            </table>

            <div class="section-title">{{ __('Recommendations') }}:</div>

            @foreach ($guidances as $guidance)
                <div class="item"><span class="box"></span>{{ $guidance }}</div>
            @endforeach

            @foreach ($passTypes as $passType)
                <div class="item">
                    <span class="box"></span>{{ $passType }}
                    <span class="blank" style="width: 8mm;"></span> {{ __('Quantity') }}
                    <div class="sub-line">
                        @foreach (AppointmentMode::cases() as $mode)
                            <span class="option"><span class="box"></span>{{ $mode->label() }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="item">
                <table class="line-row">
                    <tr>
                        <td class="shrink"><span class="box"></span>{{ __('Infiltration') }} / {{ __('Site') }}</td>
                        <td class="line-fill"></td>
                    </tr>
                </table>
                <div class="sub-line">
                    {{ __('Removal') }} <span class="blank" style="width: 6mm;"></span>/<span class="blank" style="width: 6mm;"></span>/<span class="blank" style="width: 10mm;"></span>
                </div>
            </div>

            <div class="item">
                <span class="box"></span>{{ __('Return') }}
                <span class="blank" style="width: 60mm;"></span>
            </div>

            <table class="closing">
                <tr>
                    <td>{{ collect([$team->address_city_line, $sheetDate])->filter()->implode(', ') }}</td>
                </tr>
                <tr>
                    <td>{{ __('Signature') }}: <span class="blank" style="width: 80mm;"></span></td>
                </tr>
            </table>
        </div>

        {{-- Back: left blank for the fluidic remedies, written by hand during the attendance. --}}
        <div class="page-break">
            <div class="back-title">{{ __('Fluidic remedies') }}</div>
            <div class="back-detail">{{ $assistedPerson->name }} · {{ $sheetDate }}</div>
            <hr class="rule">
        </div>
    @endforeach
</body>
</html>
