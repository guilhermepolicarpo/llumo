@php
    $appName = config('app.name', 'Laravel');
    $pageTitle = filled($title ?? null) ? $title.' - '.$appName : $appName;
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ $pageTitle }}
</title>

@include('partials.link-preview', ['title' => $pageTitle])

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
