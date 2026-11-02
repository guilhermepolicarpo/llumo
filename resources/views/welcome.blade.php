@use('App\Enums\AppointmentStatus')
@use('App\Enums\BookLoanStatus')
@use('Illuminate\Support\Facades\Vite')

{{-- This page is cached by the CDN: it can't depend on the session or the database, only on how the installation is configured. --}}
@php
    $pageTitle = __('Management for Spiritist centers').' - '.config('app.name', 'Laravel');
    $repositoryUrl = 'https://github.com/guilhermepolicarpo/llumo';
    $cloudUrl = 'https://llumo.app.br';
    $container = 'mx-auto w-full max-w-370 px-5 sm:px-8 lg:px-12 xl:px-15';
    $mutedOnNight = 'text-white/70';
    $railDotMotion = '[&>span:nth-child(2)]:motion-safe:animate-dot-light [&>span:nth-child(2)]:motion-safe:[animation-range:cover_47%_cover_50%] [&>span:nth-child(2)]:motion-safe:[animation-timeline:view()]';
    $skyItemMotion = 'lg:motion-safe:animate-star-appear lg:motion-safe:[animation-range:cover_calc(15%+var(--x)*.3)_cover_calc(22%+var(--x)*.3)] lg:motion-safe:[animation-timeline:--sky]';
    $hasOpenRegistration = (bool) config('llumo.open_registration');

    $primaryAction = $hasOpenRegistration
        ? ['label' => __('Use llumo cloud'), 'href' => route('register'), 'icon' => null]
        : ['label' => __('Use llumo cloud'), 'href' => $cloudUrl, 'icon' => 'arrow-up-right'];

    $steps = [
        [
            'hour' => '18:30',
            'status' => AppointmentStatus::Scheduled,
            'badge' => true,
            'title' => __('The gates open'),
            'description' => __("The night's schedule is ready, with in-person and remote sessions and filters by status, type and attendant. The records come out as a PDF, ready for the session room."),
            'image' => 'agendamentos',
            'alt' => __("llumo's scheduling screen: the day's schedule with search, filters, a notice about sessions awaiting a record, and the list of assisted persons with the Check in and Attend actions"),
        ],
        [
            'hour' => '18:42',
            'status' => AppointmentStatus::Waiting,
            'badge' => true,
            'title' => __('Someone arrives at the front desk'),
            'description' => __('One click on Check in and the arrival is recorded, in order. The front desk sees who is in the house and for how long, and nobody needs to ask who is next.'),
            'image' => 'inicio',
            'alt' => __("llumo's home screen: progress of the day's sessions, assisted persons at the front desk in order of arrival, pending items and overdue loans"),
        ],
        [
            'hour' => '18:50',
            'status' => BookLoanStatus::Open,
            'badge' => true,
            'title' => __('A book leaves the library'),
            'description' => __('Search by ISBN in the public catalogs and the book comes in with its cover. Copies with call numbers, loans, renewals, returns and overdue control, under the rules your house defines.'),
            'image' => 'biblioteca',
            'alt' => __("llumo's library collection: books with authors, categories and available copies"),
            'aspect' => 'aspect-1625/860',
            'crop' => 'absolute top-0 left-[-23.1%] h-auto w-[123.1%] max-w-none',
        ],
        [
            'hour' => '19:00',
            'status' => AppointmentStatus::Scheduled,
            'badge' => false,
            'title' => __('The talk begins'),
            'description' => __('The house listens. At the front desk, whoever arrives later keeps joining the queue, without interrupting anyone.'),
            'image' => null,
            'alt' => null,
        ],
        [
            'hour' => '20:00',
            'status' => AppointmentStatus::InProgress,
            'badge' => true,
            'title' => __('Passes and treatments begin'),
            'description' => __('In the room, everything goes on as always. Then the record leaves the paper: the front desk enters it in llumo, with mentor, fluidic remedies and dosage, passes, guidance, infiltration removal, return and notes, and a draft saved along the way.'),
            'image' => 'atendimento',
            'alt' => __("llumo's session record with the mentor, fluidic remedies and dosage filled in, next to the assisted person's details"),
        ],
        [
            'hour' => '21:30',
            'status' => AppointmentStatus::Completed,
            'badge' => true,
            'title' => __("The assisted person's story goes on"),
            'description' => __('Next time, the house finds everything done in each session, along with the library loans. The house does not lose its own memory.'),
            'image' => 'assistido',
            'alt' => __("An assisted person's history in llumo: a timeline of past sessions with fluidic remedies, guidance, passes and notes"),
        ],
    ];

    $foundations = [
        ['icon' => 'home-modern', 'title' => __('Many houses, isolated data'), 'description' => __('One installation can host several houses. Each one sees only its own data.')],
        ['icon' => 'users', 'title' => __('Members and roles'), 'description' => __('Owner, administrator and member, with email invitations that expire.')],
        ['icon' => 'finger-print', 'title' => __('Protected access'), 'description' => __('Email verification, 2FA with recovery codes and passkeys.')],
        ['icon' => 'clipboard-document-list', 'title' => __("The house's registers"), 'description' => __('Session types, mentors, fluidic remedies, guidance and types of pass.')],
        ['icon' => 'map-pin', 'title' => __('Address from the postal code'), 'description' => __('Type the CEP and the address fills in, for assisted persons and for the house.')],
        ['icon' => 'calendar-days', 'title' => __('Limits for each session type'), 'description' => __('Each session type has its weekdays and its daily limit.')],
    ];

    $ways = [
        [
            'icon' => 'cloud',
            'title' => __('llumo cloud'),
            'description' => __('Create your account, register the house and start using it, with nothing to install. It is hosted by the project maintainer and always runs the latest version of the code.'),
            'fit' => __('Ideal for houses without someone to look after a server.'),
        ],
        [
            'icon' => 'server-stack',
            'title' => __('On your server'),
            'description' => __('Install from one of the published versions. The data stays entirely on your infrastructure, with SQLite by default, or with MySQL and PostgreSQL.'),
            'fit' => __('Ideal for those who prefer to look after their own installation.'),
        ],
    ];

    $promises = [
        ['title' => __('It never suggests spiritual treatment.'), 'description' => __('Fluidic remedies, passes and guidance belong to the mentor and the mediums. llumo records and shows. The artificial intelligence features on the roadmap will support the routine, without prescribing.')],
        ['title' => __("It never mixes one house's data with another's."), 'description' => __('Health and belief are sensitive data under article 11 of the LGPD. Each house sees only its own data and, if it prefers, keeps everything on its own server.')],
        ['title' => __('It never splits features by plan.'), 'description' => __('There is no paid edition and no exclusive features. In the cloud or on your server, llumo is the same.')],
    ];

    $contributions = [
        ['key' => 'first-issue', 'label' => __('Find a first issue'), 'href' => $repositoryUrl.'/issues?q=is%3Aissue%20is%3Aopen%20label%3A%22good%20first%20issue%22'],
        ['key' => 'contributing', 'label' => __('Read how to contribute'), 'href' => $repositoryUrl.'/blob/main/CONTRIBUTING.md'],
        ['key' => 'discussions', 'label' => __('Talk in the Discussions'), 'href' => $repositoryUrl.'/discussions'],
        ['key' => 'issues', 'label' => __('Report a problem or suggest an idea'), 'href' => $repositoryUrl.'/issues'],
    ];

    $nextModules = [
        'finances' => ['title' => __('Finances'), 'description' => __("Control of the house's income and expenses"), 'x' => 34, 'y' => 16],
        'social-assistance' => ['title' => __('Social assistance'), 'description' => __('Donations and assistance actions for assisted persons'), 'x' => 52, 'y' => 44],
    ];

    $radar = [
        'docker' => ['label' => __('Docker image and deploy guide'), 'x' => 4, 'y' => 8, 'dot' => 'size-1.5'],
        'reminders' => ['label' => __('Reminders'), 'x' => 16, 'y' => 32, 'dot' => 'size-2'],
        'self-scheduling' => ['label' => __('Self-scheduling'), 'x' => 5, 'y' => 54, 'dot' => 'size-1.5'],
        'digital-record' => ['label' => __("Assisted person's digital record"), 'x' => 24, 'y' => 62, 'dot' => 'size-1.5'],
        'check-in' => ['label' => __('QR code check-in'), 'x' => 38, 'y' => 78, 'dot' => 'size-1.5'],
        'offline' => ['label' => __('PWA and offline mode'), 'x' => 8, 'y' => 84, 'dot' => 'size-[5px]'],
        'ai' => ['label' => __('Artificial intelligence'), 'x' => 64, 'y' => 27, 'dot' => 'size-2'],
        'indicators' => ['label' => __('Indicators'), 'x' => 70, 'y' => 10, 'dot' => 'size-1.5'],
        'volunteers' => ['label' => __('Volunteer rota'), 'x' => 82, 'y' => 30, 'dot' => 'size-1.5'],
        'courses' => ['label' => __('Courses and study groups'), 'x' => 74, 'y' => 56, 'dot' => 'size-2'],
        'lgpd' => ['label' => __('LGPD compliance'), 'x' => 60, 'y' => 84, 'dot' => 'size-1.5'],
        'network' => ['label' => __('Network of houses'), 'x' => 84, 'y' => 78, 'dot' => 'size-[5px]'],
    ];

    $sky = $nextModules + $radar;
    $constellations = [
        ['docker', 'reminders'],
        ['reminders', 'self-scheduling'],
        ['self-scheduling', 'digital-record'],
        ['reminders', 'digital-record'],
        ['digital-record', 'check-in'],
        ['self-scheduling', 'offline'],
        ['reminders', 'finances'],
        ['finances', 'social-assistance'],
        ['social-assistance', 'ai'],
        ['ai', 'indicators'],
        ['indicators', 'volunteers'],
        ['volunteers', 'courses'],
        ['social-assistance', 'courses'],
        ['courses', 'lgpd'],
        ['courses', 'network'],
        ['check-in', 'social-assistance'],
    ];

    $footerColumns = [
        [
            'title' => __('Product'),
            'links' => [
                ['key' => 'night', 'label' => __('A night at the house'), 'href' => '#noite'],
                ['key' => 'how-to-use', 'label' => __('How to use'), 'href' => '#como-usar'],
                ['key' => 'roadmap', 'label' => __('Roadmap'), 'href' => '#roadmap'],
                ['key' => 'cloud', 'label' => __('llumo cloud'), 'href' => $cloudUrl],
            ],
        ],
        [
            'title' => __('Project'),
            'links' => [
                ['key' => 'repository', 'label' => __('Repository'), 'href' => $repositoryUrl],
                ['key' => 'releases', 'label' => __('Versions'), 'href' => $repositoryUrl.'/releases'],
                ['key' => 'installation', 'label' => __('Installation'), 'href' => $repositoryUrl.'#instalação'],
                ['key' => 'license', 'label' => __('MIT license'), 'href' => $repositoryUrl.'/blob/main/LICENSE'],
            ],
        ],
        [
            'title' => __('Community'),
            'links' => [
                ['key' => 'discussions', 'label' => __('Discussions'), 'href' => $repositoryUrl.'/discussions'],
                ['key' => 'issues', 'label' => __('Issues'), 'href' => $repositoryUrl.'/issues'],
                ['key' => 'contributing', 'label' => __('How to contribute'), 'href' => $repositoryUrl.'/blob/main/CONTRIBUTING.md'],
                ['key' => 'conduct', 'label' => __('Code of conduct'), 'href' => $repositoryUrl.'/blob/main/CODE_OF_CONDUCT.md'],
                ['key' => 'security', 'label' => __('Report a vulnerability'), 'href' => $repositoryUrl.'/security/advisories/new'],
            ],
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="motion-safe:scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $pageTitle }}</title>

        @include('partials.link-preview', ['title' => $pageTitle])

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        {{-- The styles travel inside the page: a copy cached before a deploy keeps working after the hashed CSS file is gone. --}}
        @if (Vite::isRunningHot())
            @vite('resources/css/app.css')
        @else
            <style>{!! Vite::content('resources/css/app.css') !!}</style>
        @endif

        @fluxAppearance
    </head>
    {{-- The body shares the sky's blue, so overscrolling past either end of the page stays in the same color. --}}
    <body class="min-h-screen bg-blue-700 antialiased">
        {{-- The sky, the night and the footer are always dark: only the lit house follows the appearance setting. --}}
        <div class="overflow-x-clip bg-white text-zinc-800 [--site-surface:var(--color-white)] dark:bg-zinc-800 dark:text-white dark:[--site-surface:var(--color-zinc-800)]">
            <section class="dark relative overflow-hidden bg-blue-700 text-white">
                <x-app-logo-icon aria-hidden="true" class="absolute top-117.5 left-1/2 size-[clamp(62.5rem,120vw,112.5rem)] -translate-x-1/2 -translate-y-1/2 fill-none stroke-current text-blue-600 motion-safe:supports-[animation-timeline:scroll()]:animate-sun-set motion-safe:supports-[animation-timeline:scroll()]:[animation-range:0_100vh] motion-safe:supports-[animation-timeline:scroll()]:[animation-timeline:scroll(root)]" />

                <header class="{{ $container }} relative flex flex-wrap items-center justify-between gap-x-8 gap-y-4 py-5 lg:grid lg:grid-cols-[1fr_auto_1fr]">
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5 justify-self-start" data-test="site-logo">
                        <span class="flex size-8 items-center justify-center rounded-md bg-white text-blue-700">
                            <x-app-logo-icon aria-hidden="true" class="size-5 fill-none stroke-current" />
                        </span>
                        <span class="text-lg font-bold">{{ config('app.name', 'Laravel') }}</span>
                    </a>

                    <flux:navbar :aria-label="__('Site sections')" class="hidden py-0! lg:flex">
                        <flux:navbar.item href="#noite" :accent="false" class="dark:text-white!" data-test="nav-night">{{ __('A night at the house') }}</flux:navbar.item>
                        <flux:navbar.item href="#como-usar" :accent="false" class="dark:text-white!" data-test="nav-how-to-use">{{ __('How to use') }}</flux:navbar.item>
                        <flux:navbar.item href="#software-livre" :accent="false" class="dark:text-white!" data-test="nav-free-software">{{ __('Free software') }}</flux:navbar.item>
                        <flux:navbar.item href="#roadmap" :accent="false" class="dark:text-white!" data-test="nav-roadmap">{{ __('Roadmap') }}</flux:navbar.item>
                    </flux:navbar>

                    <div class="flex flex-wrap items-center gap-2 justify-self-end lg:flex-nowrap">
                        <flux:button variant="ghost" square :href="$repositoryUrl" :aria-label="__('Repository')" class="max-sm:hidden" data-test="nav-repository">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="size-5"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                        </flux:button>

                        <flux:button variant="ghost" :href="route('login')" class="border border-white/40" data-test="nav-login">{{ __('Log in') }}</flux:button>

                        @if ($hasOpenRegistration)
                            <flux:button variant="ghost" :href="route('register')" class="border border-white/40" data-test="nav-register">{{ __('Create your account') }}</flux:button>
                        @endif
                    </div>
                </header>

                <div class="relative mx-auto flex max-w-295 flex-col items-center px-5 pt-[clamp(3.5rem,7vw,6.5rem)] text-center sm:px-8">
                    <h1 class="motion-safe:animate-hero-enter text-[clamp(2.75rem,6.5vw,6rem)] leading-[1.02] font-medium tracking-[-0.035em] text-balance">
                        {{ __('Take care of whoever arrives.') }}
                        <span class="block text-blue-200">{{ __('llumo takes care of the rest.') }}</span>
                    </h1>

                    <p class="motion-safe:animate-hero-enter motion-safe:[animation-delay:120ms] mt-7 max-w-165 text-[clamp(1.0625rem,1.5vw,1.25rem)] leading-normal text-pretty">
                        {{ __('Assisted persons, appointments, sessions and library of a Spiritist center in one place. Free software, in the cloud or on your own server.') }}
                    </p>

                    <div class="mt-9 flex flex-wrap justify-center gap-3 motion-safe:animate-hero-enter motion-safe:[animation-delay:240ms]">
                        <flux:button variant="primary" :href="$primaryAction['href']" :icon:trailing="$primaryAction['icon']" icon:variant="outline" class="h-11! px-5! [--color-accent-foreground:var(--color-blue-700)] [--color-accent:var(--color-white)]" data-test="hero-primary-action">{{ $primaryAction['label'] }}</flux:button>
                        <flux:button variant="ghost" href="#instalar" class="h-11! border border-white/40 px-5!" data-test="hero-install">{{ __('Install on your server') }}</flux:button>
                    </div>

                    <p class="mt-5 text-sm text-blue-50 motion-safe:animate-hero-enter motion-safe:[animation-delay:360ms]">{{ __('MIT license · Same features both ways · 0.x versions, in active development') }}</p>
                </div>

                <div class="relative mt-[clamp(3rem,5vw,4.5rem)] pb-16 bg-[linear-gradient(to_bottom,transparent_62%,var(--site-surface)_62%)]">
                    <figure class="motion-safe:animate-hero-enter motion-safe:[animation-delay:480ms] mx-auto max-w-330 px-5 sm:px-8 lg:px-15">
                        <div class="overflow-hidden rounded-xl border border-white/25 bg-white shadow-2xl">
                            <img
                                src="{{ Vite::asset('resources/images/screenshots/inicio.webp') }}"
                                width="2732"
                                height="1536"
                                fetchpriority="high"
                                alt="{{ __("llumo's home screen: progress of the day's sessions, assisted persons at the front desk in order of arrival, pending items and overdue loans") }}"
                                class="block h-auto w-full"
                            >
                        </div>
                    </figure>
                </div>
            </section>

            <section class="mx-auto max-w-330 px-5 pt-[clamp(1.5rem,5vw,4.5rem)] pb-[clamp(4.5rem,9vw,8.5rem)] sm:px-8 lg:px-15">
                <p class="max-w-265 text-[clamp(1.625rem,3.2vw,2.75rem)] leading-[1.22] font-medium tracking-[-0.02em] text-pretty">
                    <span class="text-zinc-500 dark:text-white/70">{{ __('Lumo means light in Esperanto, a language tied to the history of the Spiritist movement.') }}</span>
                    {{ __('That is what llumo brings to the everyday life of the house: clarity about who arrived, who is waiting, who has been attended and what still needs attention.') }}
                </p>
            </section>

            <section id="noite" class="{{ $container }}">
                <div class="flex flex-wrap items-end justify-between gap-x-12 gap-y-3 pb-12 lg:pb-18">
                    <h2 class="text-[clamp(2.125rem,4.6vw,4rem)] leading-[1.05] font-medium tracking-[-0.03em]">{{ __('A night at the house') }}</h2>
                    <p class="max-w-120 text-zinc-500 text-pretty dark:text-white/70">{{ __("Follow an appointment from the schedule to the assisted person's story. The screens come from llumo itself, with demo data, and the times are examples: each house has its own.") }}</p>
                </div>

                <div class="relative">
                    {{-- The light walks down the rail as the page scrolls, past the end of this block, and stops on the 00:05 dot. --}}
                    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -bottom-[calc(7.5rem+47px)] z-10 hidden grid-cols-[minmax(0,4fr)_6rem_minmax(0,8fr)] gap-x-6 lg:grid">
                        <div class="relative col-start-2 flex justify-center">
                            {{-- The rail lights up behind the marker, starting from the first dot. --}}
                            <div class="absolute inset-x-0 top-[1.8125rem] bottom-0 flex justify-center overflow-y-clip">
                                <div class="sticky top-[-50vh] -mt-[100vh] h-screen w-px shrink-0 self-start bg-blue-500"></div>
                            </div>

                            <div class="sticky top-[calc(50vh-1.125rem)] mt-2.75 flex h-9 items-center gap-1.5 self-start rounded-full border border-zinc-200 bg-white ps-2 pe-3 shadow-xs dark:border-zinc-600 dark:bg-zinc-700">
                                <x-app-logo-icon class="size-5 fill-none stroke-current text-accent-content motion-safe:animate-sun-turn motion-safe:[animation-timeline:scroll(root)]" />
                                <span class="text-xs font-semibold tabular-nums" data-site-clock>{{ $steps[0]['hour'] }}</span>
                            </div>
                        </div>
                    </div>

                    <ol>
                        @foreach ($steps as $step)
                            <li class="grid grid-cols-[0.75rem_minmax(0,1fr)] gap-x-4 gap-y-6 lg:grid-cols-[minmax(0,4fr)_6rem_minmax(0,8fr)] lg:gap-x-6 lg:gap-y-0" data-site-hour="{{ $step['hour'] }}">
                                <div @class(['col-start-2 row-start-1 flex flex-col items-start gap-3 pt-1.25 lg:col-start-1', 'lg:pb-24' => $step['image']])>
                                    <p class="text-[2.75rem] leading-12 font-medium tracking-[-0.02em] tabular-nums">{{ $step['hour'] }}</p>

                                    @if ($step['badge'])
                                        <flux:badge size="sm" :color="$step['status']->color()">{{ $step['status']->label() }}</flux:badge>
                                    @endif

                                    @if ($step['image'])
                                        <h3 class="mt-3 text-2xl font-medium tracking-[-0.01em]">{{ $step['title'] }}</h3>
                                        <p class="leading-6.5 text-zinc-500 text-pretty dark:text-white/70">{{ $step['description'] }}</p>
                                    @endif
                                </div>

                                <x-assisted-people.timeline-rail :status="$step['status']" :first="$loop->first" class="col-start-1 row-span-2 row-start-1 lg:col-start-2 lg:row-span-1 lg:justify-self-center {{ $railDotMotion }}" />

                                @if ($step['image'])
                                    <figure class="{{ $step['aspect'] ?? 'aspect-1625/1124' }} motion-safe:animate-screen-rise motion-safe:[animation-range:entry_0%_entry_40%] motion-safe:[animation-timeline:view()] relative col-start-2 row-start-2 mb-16 self-start overflow-hidden rounded-xl border border-zinc-800/10 bg-white shadow-lg lg:col-start-3 lg:row-start-1 lg:mb-24 dark:border-white/10">
                                        <img
                                            src="{{ Vite::asset('resources/images/screenshots/'.$step['image'].'.webp') }}"
                                            width="2732"
                                            height="1536"
                                            loading="lazy"
                                            decoding="async"
                                            alt="{{ $step['alt'] }}"
                                            class="{{ $step['crop'] ?? 'block size-full object-cover object-[100%_0]' }}"
                                        >
                                    </figure>
                                @else
                                    <div class="col-start-2 row-start-2 mb-16 lg:col-start-3 lg:row-start-1 lg:mb-24 lg:pt-1.25">
                                        <h3 class="text-[clamp(1.75rem,2.6vw,2.25rem)] leading-[1.15] font-medium tracking-[-0.02em]">{{ $step['title'] }}</h3>
                                        <p class="mt-3 max-w-160 text-lg/7 text-zinc-500 text-pretty dark:text-white/70">{{ $step['description'] }}</p>
                                    </div>
                                @endif
                            </li>
                        @endforeach

                        <li class="grid grid-cols-[0.75rem_minmax(0,1fr)] gap-x-4 gap-y-6 bg-zinc-50 shadow-[0_0_0_100vmax_var(--color-zinc-50)] [clip-path:inset(0_-100vmax)] lg:grid-cols-[minmax(0,4fr)_6rem_minmax(0,8fr)] lg:gap-x-6 lg:gap-y-0 dark:bg-zinc-900 dark:shadow-[0_0_0_100vmax_var(--color-zinc-900)]" data-site-hour="22:00">
                            <div class="col-start-2 row-start-1 flex flex-col items-start gap-3 pt-17.25 lg:col-start-1 lg:pt-27.25 lg:pb-26">
                                <p class="text-[2.75rem] leading-12 font-medium tracking-[-0.02em] tabular-nums">22:00</p>
                                <h3 class="mt-3 text-2xl font-medium tracking-[-0.01em]">{{ __('Before turning off the light') }}</h3>
                                <p class="leading-6.5 text-zinc-500 text-pretty dark:text-white/70">{{ __('Behind all of this, llumo keeps the house organized and the data protected.') }}</p>
                            </div>

                            <div aria-hidden="true" class="col-start-1 row-span-2 row-start-1 flex flex-col items-center lg:col-start-2 lg:row-span-1 lg:justify-self-center">
                                <span class="h-16 w-px shrink-0 bg-zinc-200 lg:h-26 dark:bg-white/10"></span>
                                <x-assisted-people.timeline-rail :status="AppointmentStatus::Scheduled" class="grow {{ $railDotMotion }}" />
                            </div>

                            <ul class="col-start-2 row-start-2 grid grid-cols-[repeat(auto-fit,minmax(min(17.5rem,100%),1fr))] content-start gap-x-10 gap-y-9 pb-16 lg:col-start-3 lg:row-start-1 lg:pt-26 lg:pb-26">
                                @foreach ($foundations as $foundation)
                                    <li class="flex gap-3.5">
                                        <flux:icon :name="$foundation['icon']" class="mt-0.5 size-5 shrink-0 text-zinc-400 dark:text-zinc-500" />
                                        <div>
                                            <h4 class="font-medium">{{ $foundation['title'] }}</h4>
                                            <p class="mt-0.5 text-sm leading-5.5 text-zinc-500 dark:text-white/70">{{ $foundation['description'] }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    </ol>
                </div>
            </section>

            <div class="dark bg-zinc-950 text-white">
                <div class="{{ $container }}">
                    <div class="grid grid-cols-[0.75rem_minmax(0,1fr)] gap-x-4 gap-y-6 lg:grid-cols-[minmax(0,4fr)_6rem_minmax(0,8fr)] lg:gap-x-6 lg:gap-y-0" data-site-hour="00:05">
                        <div class="col-start-2 row-start-1 flex flex-col items-start gap-3 pt-21.25 lg:col-start-1 lg:pt-31.25">
                            <p class="text-[2.75rem] leading-12 font-medium tracking-[-0.02em] tabular-nums">00:05</p>
                            <flux:badge size="sm" :color="AppointmentStatus::NoShow->color()">{{ AppointmentStatus::NoShow->label() }}</flux:badge>
                        </div>

                        <div aria-hidden="true" class="col-start-1 row-span-2 row-start-1 flex flex-col items-center lg:col-start-2 lg:row-span-1 lg:justify-self-center">
                            <span class="h-20 w-px shrink-0 bg-white/10 lg:h-30"></span>
                            <x-assisted-people.timeline-rail :status="AppointmentStatus::NoShow" :last="true" class="grow {{ $railDotMotion }}" />
                        </div>

                        <div class="col-start-2 row-start-2 pb-16 lg:col-start-3 lg:row-start-1 lg:pt-30 lg:pb-26">
                            <h2 class="text-[clamp(2.125rem,4.6vw,4rem)] leading-[1.05] font-medium tracking-[-0.03em]">{{ __('While the house sleeps') }}</h2>
                            <p class="mt-5 max-w-160 text-lg/7 text-pretty {{ $mutedOnNight }}">{{ __('Right after midnight, llumo marks the appointments left without a session as no-shows and deletes expired invitations. When the house opens again, the schedule is up to date.') }}</p>
                        </div>
                    </div>
                </div>

                <section id="como-usar" class="{{ $container }} pt-12 pb-24 lg:pt-18 lg:pb-30">
                    <div class="flex flex-wrap items-end justify-between gap-x-12 gap-y-3 pb-12">
                        <h2 class="text-[clamp(1.875rem,3.4vw,3rem)] leading-[1.1] font-medium tracking-tight text-balance">{{ __('The data stays where you choose') }}</h2>
                        <p class="{{ $mutedOnNight }}">{{ __('Two ways to use llumo, with the same features.') }}</p>
                    </div>

                    <div class="grid grid-cols-[repeat(auto-fit,minmax(min(26.25rem,100%),1fr))] gap-4">
                        @foreach ($ways as $way)
                            <flux:card class="flex flex-col items-start p-6! sm:p-10!">
                                <flux:icon :name="$way['icon']" class="size-7 text-blue-400" />
                                <h3 class="mt-5 text-2xl font-medium tracking-[-0.01em]">{{ $way['title'] }}</h3>
                                <p class="mt-2 leading-6.5 text-pretty {{ $mutedOnNight }}">{{ $way['description'] }}</p>
                                <p class="mt-3 mb-7 text-sm leading-5.5 text-white/60">{{ $way['fit'] }}</p>

                                @if ($loop->first)
                                    <flux:button variant="primary" :href="$cloudUrl.'/register'" icon:trailing="arrow-up-right" icon:variant="outline" class="mt-auto h-11! px-5!" data-test="how-to-use-cloud">{{ __('Use llumo cloud') }}</flux:button>
                                @else
                                    <flux:button href="#instalar" class="mt-auto h-11! px-5!" data-test="how-to-use-install">{{ __('Install on your server') }}</flux:button>
                                @endif
                            </flux:card>
                        @endforeach
                    </div>
                </section>

                <section class="{{ $container }} pb-24 lg:pb-34">
                    <div class="grid gap-x-30 gap-y-10 border-t border-white/10 pt-12 lg:grid-cols-[minmax(0,4fr)_minmax(0,8fr)] lg:pt-18">
                        <h2 class="text-[clamp(1.875rem,3.4vw,3rem)] leading-[1.1] font-medium tracking-tight text-balance">{{ __('What llumo never does') }}</h2>

                        <ul class="flex flex-col gap-12">
                            @foreach ($promises as $promise)
                                <li>
                                    <h3 class="text-[clamp(1.375rem,2.2vw,1.875rem)] leading-tight font-medium tracking-[-0.015em]">{{ $promise['title'] }}</h3>
                                    <p class="mt-2.5 max-w-170 leading-6.5 text-pretty {{ $mutedOnNight }}">{{ $promise['description'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>

                <section id="software-livre" class="{{ $container }} pb-24 lg:pb-34">
                    <h2 class="max-w-275 text-[clamp(2.125rem,5.4vw,4.75rem)] leading-[1.03] font-medium tracking-[-0.03em] text-balance">
                        {{ __('The data belongs to the house.') }}
                        <span class="text-blue-300">{{ __('The code belongs to everyone.') }}</span>
                    </h2>
                    <p class="mt-6 max-w-155 text-lg/7 text-pretty {{ $mutedOnNight }}">{{ __('Open source under the MIT license, with no license cost and no dependence on a vendor. Read, install, adapt and contribute.') }}</p>

                    <div class="mt-16 grid grid-cols-[minmax(0,1fr)] gap-x-12 gap-y-10 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
                        <div id="instalar" class="scroll-mt-8">
                            <flux:card class="overflow-hidden p-0!">
                                <div class="flex items-center justify-between gap-4 border-b border-white/10 py-2.5 ps-5 pe-3">
                                    <h3 class="text-sm font-medium">{{ __('Install on your server') }}</h3>
                                    <flux:button variant="filled" size="sm" icon="clipboard" icon:variant="outline" data-site-copy data-site-copied-label="{{ __('Copied') }}" data-test="install-copy"><span data-site-copy-label>{{ __('Copy commands') }}</span></flux:button>
                                </div>

                                <div class="overflow-x-auto p-5 font-mono text-sm leading-6.5 text-zinc-200" data-site-commands>
                                    <code class="block whitespace-pre">git clone {{ $repositoryUrl }}.git</code>
                                    <code class="block whitespace-pre">cd llumo</code>
                                    <code class="block whitespace-pre">composer setup</code>
                                    <code class="block whitespace-pre">composer dev</code>
                                </div>
                            </flux:card>

                            <p class="mt-4 text-sm leading-5.5 text-pretty {{ $mutedOnNight }}">{{ __('composer setup takes care of everything: dependencies, .env, application key, migrations and assets. You need PHP 8.3 or higher, Composer and Node.js 22 or higher.') }}</p>

                            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium text-blue-300">
                                <a href="{{ $repositoryUrl }}#instalação" class="underline-offset-4 hover:underline" data-test="install-guide">{{ __('Installation guide') }}</a>
                                <a href="{{ $repositoryUrl }}/releases" class="underline-offset-4 hover:underline" data-test="install-releases">{{ __('Published versions') }}</a>
                            </div>
                        </div>

                        <div>
                            <h3 class="pb-2 font-medium">{{ __('Be part of it') }}</h3>

                            <ul class="border-t border-white/10">
                                @foreach ($contributions as $contribution)
                                    <li class="border-b border-white/10">
                                        <a href="{{ $contribution['href'] }}" class="flex min-h-13 items-center justify-between gap-4 px-1 py-3 hover:bg-white/5" data-test="contribute-{{ $contribution['key'] }}">
                                            {{ $contribution['label'] }}
                                            <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-400" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>

                            <p class="mt-6 text-sm leading-5.5 text-pretty {{ $mutedOnNight }}">{{ __('Built with PHP 8.3+, Laravel 13, Livewire 4, Flux UI 2 and Tailwind CSS 4. On every push and pull request, CI runs Pint, PHPStan at level 7 and Pest.') }}</p>
                            <p class="mt-3 text-sm leading-5.5 text-pretty {{ $mutedOnNight }}">{{ __('llumo is in active development. The everyday modules already work and are covered by tests. Versions are still 0.x and may bring bigger changes from one to the next.') }}</p>
                        </div>
                    </div>
                </section>

                <section id="roadmap" class="{{ $container }} pb-16 lg:pb-30">
                    <div class="flex flex-wrap items-end justify-between gap-x-12 gap-y-3 border-t border-white/10 pt-12 pb-8 lg:pt-18">
                        <h2 class="text-[clamp(1.875rem,3.4vw,3rem)] leading-[1.1] font-medium tracking-tight">{{ __('What is still to come') }}</h2>
                        <p class="max-w-120 text-pretty {{ $mutedOnNight }}">
                            {{ __('The suns are the next modules. The stars are on the radar, with no promised dates.') }}
                            <a href="{{ $repositoryUrl }}/discussions" class="font-medium text-blue-300 underline-offset-4 hover:underline" data-test="roadmap-discussions">{{ __('Tell us what your house needs.') }}</a>
                        </p>
                    </div>

                    <div class="relative [view-timeline-name:--sky] lg:h-150">
                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" class="absolute inset-0 hidden size-full text-white/15 lg:block lg:motion-safe:animate-sky-reveal lg:motion-safe:[animation-range:cover_15%_cover_50%] lg:motion-safe:[animation-timeline:--sky]">
                            @foreach ($constellations as [$from, $to])
                                <line x1="{{ $sky[$from]['x'] }}" y1="{{ $sky[$from]['y'] }}" x2="{{ $sky[$to]['x'] }}" y2="{{ $sky[$to]['y'] }}" stroke="currentColor" stroke-width="1" vector-effect="non-scaling-stroke" />
                            @endforeach
                        </svg>

                        <ul class="flex flex-col gap-3.5 py-2 lg:absolute lg:inset-0 lg:block lg:py-0">
                            @foreach ($nextModules as $module)
                                <li style="--x: {{ $module['x'] }}%; --y: {{ $module['y'] }}%" class="flex gap-3 lg:absolute lg:top-(--y) lg:left-(--x) lg:w-80 lg:-translate-x-3.5 lg:-translate-y-3.5 {{ $skyItemMotion }}">
                                    <x-app-logo-icon aria-hidden="true" class="size-7 shrink-0 fill-none stroke-current text-blue-300 motion-safe:animate-sun-turn motion-safe:[animation-timeline:scroll(root)]" />
                                    <div>
                                        <p class="text-xl font-medium">{{ $module['title'] }}</p>
                                        <p class="text-sm {{ $mutedOnNight }}">{{ $module['description'] }}</p>
                                    </div>
                                </li>
                            @endforeach

                            @foreach ($radar as $star)
                                <li style="--x: {{ $star['x'] }}%; --y: {{ $star['y'] }}%" class="flex items-center gap-2.5 text-sm text-zinc-200 lg:absolute lg:top-(--y) lg:left-(--x) lg:-translate-x-0.75 lg:-translate-y-2.5 {{ $skyItemMotion }}">
                                    <span aria-hidden="true" class="{{ $star['dot'] }} shrink-0 rounded-full bg-white"></span>
                                    {{ $star['label'] }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>

                <section class="flex flex-col items-center overflow-x-clip px-5 pt-16 text-center sm:px-8 lg:pt-24">
                    <h2 class="text-[clamp(2.375rem,6vw,5.25rem)] leading-[1.03] font-medium tracking-[-0.03em] text-balance">{{ __('Soon the gates open again.') }}</h2>
                    <p class="mt-5 text-lg/7 {{ $mutedOnNight }}">{{ __('Choose how to start. llumo is the same both ways.') }}</p>

                    <div class="mt-8 flex flex-wrap justify-center gap-3">
                        <flux:button variant="primary" :href="$primaryAction['href']" :icon:trailing="$primaryAction['icon']" icon:variant="outline" class="h-11! px-5!" data-test="closing-primary-action">{{ $primaryAction['label'] }}</flux:button>
                        <flux:button href="#instalar" class="h-11! px-5!" data-test="closing-install">{{ __('Install on your server') }}</flux:button>
                    </div>

                    <div aria-hidden="true" class="mt-16 h-[clamp(16.25rem,31vw,28.125rem)] w-[clamp(32.5rem,62vw,56.25rem)] shrink-0 overflow-hidden [view-timeline-name:--closing-sun]">
                        <x-app-logo-icon class="h-[200%] w-full fill-none stroke-current text-blue-700 motion-safe:animate-sun-rise motion-safe:[animation-range:entry_0%_entry_100%] motion-safe:[animation-timeline:--closing-sun]" />
                    </div>
                </section>
            </div>

            <footer class="dark bg-blue-700 text-white">
                <div class="{{ $container }} pt-16 pb-8">
                    <div class="grid grid-cols-[repeat(auto-fit,minmax(min(12.5rem,100%),1fr))] gap-x-12 gap-y-10">
                        <div class="max-w-105">
                            <div class="flex items-center gap-2.5">
                                <span class="flex size-8 items-center justify-center rounded-md bg-white text-blue-700">
                                    <x-app-logo-icon aria-hidden="true" class="size-5 fill-none stroke-current" />
                                </span>
                                <span class="text-lg font-bold">{{ config('app.name', 'Laravel') }}</span>
                            </div>
                            <p class="mt-4 text-pretty">{{ __('Management for Spiritist centers: free, in the cloud or on your server, and made with care.') }}</p>
                        </div>

                        @foreach ($footerColumns as $column)
                            <nav aria-label="{{ $column['title'] }}">
                                <h3 class="text-sm font-semibold">{{ $column['title'] }}</h3>
                                <ul class="mt-3 flex flex-col gap-2 text-sm text-blue-50">
                                    @foreach ($column['links'] as $link)
                                        <li><a href="{{ $link['href'] }}" class="underline-offset-4 hover:underline" data-test="footer-link-{{ $link['key'] }}">{{ $link['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            </nav>
                        @endforeach
                    </div>

                    <div class="mt-12 flex flex-wrap justify-between gap-x-8 gap-y-2 border-t border-white/20 pt-5 text-sm text-blue-50">
                        <p>{{ __('lumo: light, in Esperanto.') }}</p>
                        <a href="{{ $repositoryUrl }}/blob/main/README.en.md" lang="en" class="underline-offset-4 hover:underline" data-test="footer-link-english">Read about llumo in English</a>
                    </div>
                </div>
            </footer>
        </div>

        <script>
            (() => {
                const clock = document.querySelector('[data-site-clock]');
                const clockObserver = new IntersectionObserver((entries) => {
                    entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
                        clock.textContent = entry.target.dataset.siteHour;
                    });
                }, { rootMargin: '-50% 0px -49% 0px' });

                document.querySelectorAll('[data-site-hour]').forEach((step) => clockObserver.observe(step));

                const copyButton = document.querySelector('[data-site-copy]');

                if (! navigator.clipboard) {
                    copyButton.remove();

                    return;
                }

                const copyLabel = copyButton.querySelector('[data-site-copy-label]');
                const initialLabel = copyLabel.textContent;

                copyButton.addEventListener('click', async () => {
                    const commands = [...document.querySelectorAll('[data-site-commands] code')].map((line) => line.textContent);

                    await navigator.clipboard.writeText(commands.join('\n'));

                    copyLabel.textContent = copyButton.dataset.siteCopiedLabel;
                    setTimeout(() => { copyLabel.textContent = initialLabel; }, 2000);
                });
            })();
        </script>
    </body>
</html>
