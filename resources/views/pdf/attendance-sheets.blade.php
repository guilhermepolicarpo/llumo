@use('App\Enums\AppointmentMode')

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ trans_choice('Attendance sheet|Attendance sheets', $appointments->count()) }}</title>
    <style>
        @page { margin: 10mm 11mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; line-height: 1.3; color: #141414; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 0; vertical-align: bottom; }
        .page { position: relative; height: 188mm; }
        .page-break { page-break-before: always; }
        .shrink { white-space: nowrap; width: 1%; }

        .header td { vertical-align: middle; }
        .header .logo { width: 18mm; }
        .header .logo img { width: 15.5mm; height: 15.5mm; }
        .team-name { font-size: 10pt; font-weight: bold; text-transform: uppercase; }
        .team-detail { font-size: 7.5pt; color: #333; margin-top: 0.7mm; }
        .header .badge-cell { width: 26mm; text-align: center; }
        .badge { border: 1.1pt solid #141414; border-radius: 0.7mm; padding: 1.3mm 1.4mm; font-size: 7.5pt; font-weight: bold; line-height: 1.2; letter-spacing: 0.4pt; text-transform: uppercase; }
        .badge-date { font-size: 7.5pt; color: #333; margin-top: 1mm; }
        .rule { border: 0; border-top: 1.6pt solid #141414; margin: 3.5mm 0 4.2mm; }

        .label { font-size: 7.5pt; font-weight: bold; letter-spacing: 0.3pt; text-transform: uppercase; color: #333; }
        .identification { margin-bottom: 2.1mm; }
        .identification td { border-bottom: 0.8pt solid #141414; padding-bottom: 0.7mm; }
        .identification td.label { padding-right: 1.4mm; }
        .identification td.gap { border-bottom: 0; width: 4.2mm; }
        .identification .value { font-size: 9.5pt; font-weight: bold; }

        .section-label { margin: 5mm 0 1.8mm; }
        .item { margin-bottom: 2mm; }
        .box { display: inline-block; width: 3mm; height: 3mm; border: 0.8pt solid #141414; border-radius: 0.4mm; vertical-align: -0.4mm; margin-right: 2.1mm; }
        .radio { display: inline-block; width: 3mm; height: 3mm; border: 0.8pt solid #141414; border-radius: 1.5mm; }

        .passes { margin-top: 5mm; border-bottom: 0.8pt solid #141414; }
        .passes th { padding: 0 0 1.4mm; border-bottom: 0.8pt solid #141414; text-align: center; font-size: 7.5pt; font-weight: bold; color: #333; }
        .passes th.label { text-align: left; }
        .passes td { height: 8.8mm; vertical-align: middle; text-align: center; border-top: 0.5pt solid #bdbdbd; }
        .passes tr:first-child td { border-top: 0; }
        .passes td.name { text-align: left; }
        .passes .quantity { display: inline-block; width: 8.5mm; height: 3.5mm; border-bottom: 0.8pt solid #141414; }

        .write-in { margin-top: 5mm; }
        .write-in table { margin-bottom: 3.2mm; }
        .write-in td { padding-right: 1.8mm; }
        .line-fill { border-bottom: 0.8pt solid #141414; }
        .blank { display: inline-block; height: 3.5mm; border-bottom: 0.8pt solid #141414; vertical-align: -0.7mm; }
        .notes-line { border-bottom: 0.5pt dotted #9a9a9a; height: 6.4mm; }

        .closing { position: absolute; left: 0; right: 0; bottom: 0; }
        .closing td { font-size: 8.5pt; }
        .closing .signature-line { width: 56mm; height: 9mm; border-bottom: 0.8pt solid #141414; }
        .closing .caption { padding-top: 1mm; vertical-align: top; color: #333; }

        .back-title { font-size: 12pt; font-weight: bold; letter-spacing: 0.6pt; text-transform: uppercase; }
        .back-detail { font-size: 8.5pt; color: #333; margin-top: 0.7mm; }
        .back-rule { border: 0; border-top: 1.6pt solid #141414; margin: 3.2mm 0 0; }
        .back-line { border-bottom: 0.5pt solid #bdbdbd; height: 8mm; }
        .back-footer { position: absolute; left: 0; bottom: 0; font-size: 7.5pt; color: #555; }
    </style>
</head>
<body>
    @php
        // The closing is pinned to the page bottom, so each guidance (≈7mm) or pass (≈9mm) beyond the
        // three of each the sheet was laid out for takes the room of the 6.4mm dotted notes lines.
        $extraCatalogHeight = max(0, $guidances->count() - 3) * 7 + max(0, $passTypes->count() - 3) * 9;
        $notesLineCount = max(0, 4 - (int) ceil(max(0, $extraCatalogHeight - 12) / 6.4));
    @endphp

    @foreach ($appointments as $appointment)
        @php
            $assistedPerson = $appointment->assistedPerson;
            $sheetDay = $appointment->received_at ?? today();
            $sheetDate = $sheetDay->translatedFormat('d \d\e F \d\e Y');
        @endphp

        {{-- Front: filled in at the reception, then marked by the medium during the attendance. --}}
        <div @class(['page', 'page-break' => ! $loop->first])>
            <table class="header">
                <tr>
                    @if ($logo)
                        <td class="logo"><img src="{{ $logo }}" alt=""></td>
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

            @if ($guidances->isNotEmpty())
                <div class="label section-label">{{ __('Guidances') }}</div>
                @foreach ($guidances as $guidance)
                    <div class="item"><span class="box"></span>{{ $guidance }}</div>
                @endforeach
            @endif

            @if ($passTypes->isNotEmpty())
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
                        @foreach ($passTypes as $passType)
                            <tr>
                                <td class="name">{{ $passType }}</td>
                                <td><span class="quantity"></span></td>
                                @foreach (AppointmentMode::cases() as $mode)
                                    <td><span class="radio"></span></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="write-in">
                <table>
                    <tr>
                        <td class="shrink"><span class="box"></span>{{ __('Infiltration — site') }}</td>
                        <td class="line-fill"></td>
                        <td class="shrink" style="padding-left: 1.8mm;">{{ __('Removal') }}</td>
                        <td class="shrink" style="padding-right: 0;">
                            <span class="blank" style="width: 5mm;"></span>/<span class="blank" style="width: 5mm;"></span>/<span class="blank" style="width: 8mm;"></span>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td class="shrink"><span class="box"></span>{{ __('Return on') }}</td>
                        <td>
                            <span class="blank" style="width: 5mm;"></span>/<span class="blank" style="width: 5mm;"></span>/<span class="blank" style="width: 8mm;"></span>
                        </td>
                    </tr>
                </table>

                <div>{{ __('Observations') }}:</div>
                @for ($line = 0; $line < $notesLineCount; $line++)
                    <div class="notes-line"></div>
                @endfor
            </div>

            <div class="closing">
                <table>
                    <tr>
                        <td style="padding-bottom: 0.9mm;">{{ $sheetDate }}</td>
                        <td class="signature-line"></td>
                    </tr>
                    <tr>
                        <td class="caption">{{ $team->address_city_line }}</td>
                        <td class="caption" style="text-align: center;">{{ __('Medium signature') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- Back: lined for the fluidic remedies, written by hand during the attendance. --}}
        <div class="page page-break">
            <div class="back-title">{{ __('Fluidic remedies') }}</div>
            <div class="back-detail">{{ $assistedPerson->name }} · {{ $sheetDate }}</div>
            <hr class="back-rule">
            @foreach (range(1, 20) as $line)
                <div class="back-line"></div>
            @endforeach
            <div class="back-footer">{{ $team->display_legal_name }}</div>
        </div>
    @endforeach
</body>
</html>
