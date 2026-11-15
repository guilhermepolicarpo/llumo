{{-- "at" followed by the given time, or by a blank to write it in by hand. --}}
{{ __('at') }}
@if ($time ?? null)
    <span class="filled">{{ $time }}</span>
@else
    <span class="blank digits"></span>:<span class="blank digits"></span>
@endif
