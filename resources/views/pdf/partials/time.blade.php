{{-- "at" followed by the given time, or by a blank to write it in by hand. --}}
{{ __('at') }}
@if ($time ?? null)
    <span class="filled">{{ $time }}</span>
@else
    <span class="blank" style="width: 5mm;"></span>:<span class="blank" style="width: 5mm;"></span>
@endif
