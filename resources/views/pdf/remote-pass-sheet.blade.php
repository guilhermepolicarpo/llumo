<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Remote passes') }}</title>
    <style>
        @page { margin: 8mm 10.5mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 10.5pt; line-height: 1.2; color: #141414; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 0; vertical-align: top; }
        .rule { border: 0; border-top: 1.5pt solid #141414; margin: 3.2mm 0; }
        .label { font-size: 8.25pt; font-weight: bold; letter-spacing: 0.3pt; text-transform: uppercase; color: #333; }
        .box { display: inline-block; width: 3.2mm; height: 3.2mm; border: 0.8pt solid #141414; border-radius: 0.3mm; vertical-align: middle; }
        .blank { display: inline-block; height: 3.5mm; border-bottom: 0.5pt solid #141414; vertical-align: -0.7mm; }
        .blank.day { width: 5mm; }
        .blank.year { width: 8mm; }
        .schedule .blank.day { width: 6mm; }
        .schedule .blank.year { width: 10mm; }

        .header td { vertical-align: middle; }
        .header .logo { width: 1%; padding-right: 3.2mm; }
        .header .logo img { max-width: 32mm; max-height: 14mm; }
        .header.compact .logo img { max-width: 24mm; max-height: 10.5mm; }
        .team-name { font-size: 12pt; font-weight: bold; text-transform: uppercase; }
        .header.compact .team-name { font-size: 10.5pt; }
        .team-detail { font-size: 9.75pt; color: #333; margin-top: 0.8mm; }
        .header.compact .team-detail { font-size: 9pt; margin-top: 0.5mm; }
        .header .badge-cell { width: 48mm; text-align: right; }
        .badge { display: inline-block; border: 1.1pt solid #141414; border-radius: 0.8mm; padding: 1.3mm 2.6mm; font-size: 9pt; font-weight: bold; letter-spacing: 0.4pt; text-transform: uppercase; white-space: nowrap; }
        .badge-date { font-size: 8.25pt; color: #333; margin-top: 1.3mm; }

        .section-label { margin-bottom: 1.6mm; }

        .cards { border-collapse: separate; border-spacing: 0; table-layout: fixed; }
        .cards td.card { width: 48%; border: 0.8pt solid #141414; padding: 2.6mm 3.2mm 3.2mm; }
        .cards td.gap { width: 4%; }
        .cards tr.spacer td { height: 4.5mm; }
        .card-name { font-size: 12pt; font-weight: bold; }
        .card-schedule { font-size: 9pt; color: #333; margin-top: 0.5mm; }
        .card-person { margin-top: 1.6mm; padding: 1.3mm 0 1.6mm; border-top: 0.5pt solid #bdbdbd; border-bottom: 0.5pt solid #bdbdbd; }
        .card-address { margin-top: 0.5mm; font-size: 9.75pt; color: #333; }
        .dates { margin-top: 2.1mm; }
        .dates th { padding: 0 0 1mm; border-bottom: 0.8pt solid #141414; text-align: left; font-size: 8.25pt; font-weight: bold; text-transform: uppercase; color: #333; }
        .dates td { padding: 0.5mm 0; border-bottom: 0.5pt solid #bdbdbd; vertical-align: middle; }
        .dates .done { width: 15mm; text-align: center; }

        .cut { margin: 3.7mm 0 3.2mm; color: #555; }
        .cut td { vertical-align: middle; }
        .cut .scissors { width: 1%; padding-right: 1.6mm; font-size: 11pt; line-height: 1; }
        .cut .line .dash { border-top: 0.8pt dashed #555; }
        .cut .caption { width: 1%; padding: 0 2.6mm; white-space: nowrap; font-size: 7.5pt; letter-spacing: 0.3pt; text-transform: uppercase; }

        .guide { page-break-inside: avoid; }
        .guide .rule { margin: 2.1mm 0 2.6mm; }
        .guide-title { font-size: 14.5pt; font-weight: bold; }
        .guide-name { margin-top: 1.1mm; font-size: 11pt; }
        .guide-name .label { font-size: 9pt; }
        .guide-text { margin: 1.6mm 0 1.3mm; font-size: 11pt; line-height: 1.25; }
        .schedule { font-size: 11pt; line-height: 1.2; }
        .schedule th { padding: 0 0 1.3mm; border-bottom: 0.8pt solid #141414; text-align: left; font-size: 9pt; font-weight: bold; text-transform: uppercase; color: #333; }
        .schedule td { padding: 0.7mm 0; border-bottom: 0.5pt solid #bdbdbd; vertical-align: middle; }
        .schedule .rest { width: 53mm; }
        .schedule .done { width: 16mm; text-align: center; }
        .schedule .box { width: 4mm; height: 4mm; border-width: 1.1pt; }
        .preparation { margin-top: 2.1mm; font-size: 11pt; line-height: 1.25; }
        .preparation ul { margin: 0.5mm 0 0; padding-left: 5.8mm; }
        .contact { margin-top: 2.6mm; padding-top: 1.6mm; border-top: 0.5pt solid #bdbdbd; color: #333; }
        .contact strong { color: #141414; }
    </style>
</head>
<body>
    {{-- Top: one card per pass type, kept in the pass room, where each pass is ticked on its day. --}}
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
                <div class="badge">{{ __('Remote passes') }}</div>
                <div class="badge-date">{{ __('Record of :date', ['date' => $appointment->scheduled_on->format('d/m/Y')]) }}</div>
            </td>
        </tr>
    </table>

    <hr class="rule">

    <div class="label section-label">{{ __('For the pass room') }}</div>

    {{-- The cards of a row are cells of one table row, so they always share its height. --}}
    <table class="cards">
        @foreach ($passes->chunk(2) as $pair)
            @unless ($loop->first)
                <tr class="spacer"><td colspan="3"></td></tr>
            @endunless
            <tr>
                @foreach ($pair->values() as $position => $pass)
                    @if ($position > 0)
                        <td class="gap"></td>
                    @endif
                    <td class="card" data-test="remote-pass-card">
                        <div class="card-name">{{ $pass['passType']->name }}</div>
                        @if ($cardSchedule = $pass['passType']->remoteCardScheduleLabel())
                            <div class="card-schedule">{{ $cardSchedule }}</div>
                        @endif

                        <div class="card-person">
                            <div>
                                <strong>{{ $assistedPerson->name }}</strong>{{ $assistedPerson->formatted_age ? ', '.$assistedPerson->formatted_age : '' }}
                            </div>
                            @if ($assistedPerson->address_summary)
                                <div class="card-address">{{ $assistedPerson->address_summary }}</div>
                            @endif
                        </div>

                        <table class="dates">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th class="done">{{ __('Done') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pass['dates'] as $day)
                                    <tr>
                                        <td>{{ $day->format('d/m/Y') }}</td>
                                        <td class="done"><span class="box"></span></td>
                                    </tr>
                                @endforeach
                                @for ($blank = count($pass['dates']); $blank < $pass['quantity']; $blank++)
                                    <tr>
                                        <td><span class="blank day"></span>/<span class="blank day"></span>/<span class="blank year"></span></td>
                                        <td class="done"><span class="box"></span></td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </td>
                @endforeach
                @if ($pair->count() === 1)
                    <td class="gap"></td>
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>

    <table class="cut">
        <tr>
            <td class="scissors">✂</td>
            <td class="line"><div class="dash"></div></td>
            <td class="caption">{{ __('Cut here · the part below goes to the assisted person') }}</td>
            <td class="line"><div class="dash"></div></td>
        </tr>
    </table>

    {{-- Bottom: handed to the assisted person, with every day they rest to receive a pass and how to prepare. --}}
    <div class="guide">
        <table class="header compact">
            <tr>
                @if ($logo)
                    <td class="logo"><img src="{{ $logo }}" alt=""></td>
                @endif
                <td>
                    <div class="team-name">{{ $team->display_legal_name }}</div>
                    @if ($guideDetail = collect([$team->address_summary, $team->formatted_phone])->filter()->implode(' · '))
                        <div class="team-detail">{{ $guideDetail }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <hr class="rule">

        <div class="guide-title">{{ __('Your remote pass') }}</div>
        <div class="guide-name"><span class="label">{{ __('Name') }}:</span> {{ $assistedPerson->name }}</div>

        <p class="guide-text">{{ __('On the dates below, rest at the time shown, somewhere quiet, to receive the pass.') }}</p>

        <table class="schedule">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th class="rest">{{ __('Rest') }}</th>
                    <th class="done">{{ __('Done') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($schedule as $pass)
                    <tr data-test="remote-pass-schedule-row">
                        <td>
                            @if ($pass['date'])
                                <strong>{{ $pass['weekday'] }}</strong>, {{ $pass['date'] }}
                            @else
                                <span class="blank day"></span>/<span class="blank day"></span>/<span class="blank year"></span>
                            @endif
                        </td>
                        <td class="rest">
                            @if ($pass['time'])
                                {{ $pass['time'] }}
                            @else
                                <span class="blank" style="width: 40mm;"></span>
                            @endif
                        </td>
                        <td class="done"><span class="box"></span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="preparation">
            <strong>{{ __('Before resting, if possible, prepare:') }}</strong>
            <ul>
                <li>{{ __('a glass of filtered water;') }}</li>
                <li>{{ __('soft instrumental music;') }}</li>
                <li>{{ __('the reading of a short passage of “The Gospel According to Spiritism”, by Allan Kardec.') }}</li>
            </ul>
        </div>

        @if ($team->formatted_phone)
            <div class="contact">{{ __('Questions or a problem with any date? Call') }} <strong>{{ $team->formatted_phone }}</strong>.</div>
        @endif
    </div>
</body>
</html>
