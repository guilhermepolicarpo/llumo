{{-- The team's letterhead at the top of a printed page, with a badge naming the page and its date. --}}
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
            <div class="badge">{{ $badge }}</div>
            <div class="badge-date">{{ $date->format('d/m/Y') }}</div>
        </td>
    </tr>
</table>

<hr class="rule">
