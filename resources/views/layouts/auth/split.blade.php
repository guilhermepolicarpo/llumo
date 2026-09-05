<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="flex min-h-screen">
            <div class="flex flex-1 items-center justify-center p-6 lg:p-8">
                <div class="w-80 max-w-80 space-y-6">
                    <a href="{{ route('home') }}" class="flex items-center justify-center gap-3" wire:navigate>
                        <span class="bg-accent-content text-accent-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                            <x-app-logo-icon class="size-5 fill-none stroke-current text-white dark:text-black" />
                        </span>
                        <span class="text-xl font-semibold">{{ config('app.name', 'Laravel') }}</span>
                    </a>

                    {{ $slot }}
                </div>
            </div>

            <div class="flex flex-1 p-4 max-lg:hidden">
                @php
                    [$message, $author] = str(Illuminate\Foundation\Inspiring::quotes()->random())->explode('-');
                @endphp

                <div
                    class="relative flex h-full w-full flex-col justify-end overflow-hidden rounded-lg bg-zinc-900 bg-cover bg-center p-16 text-white"
                    style="background-image: url('{{ \Illuminate\Support\Facades\Vite::asset('resources/images/auth_aurora_2x.png') }}')"
                >
                    <blockquote class="relative z-10">
                        <p class="mb-6 text-3xl italic xl:text-4xl">&ldquo;{{ trim($message) }}&rdquo;</p>
                        <footer class="font-medium">{{ trim($author) }}</footer>
                    </blockquote>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
