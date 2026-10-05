<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="life-atlas-shell min-h-screen bg-stone-50 px-6 py-8 text-slate-800">

            <div class="mx-auto max-w-6xl">

                <!-- Navigation -->
                <nav aria-label="Primary" class="mb-6 rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-3 sm:px-5">

                        <!-- Logo -->
                        <a href="{{ route('dashboard') }}"
                           class="shrink-0 rounded-md text-xl font-semibold tracking-tight text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">
                            LifeAtlas
                        </a>

                        <!-- Menu -->
                        <div class="flex w-full flex-wrap gap-1 sm:w-auto">
                            @foreach (['dashboard' => 'Dashboard', 'history' => 'History', 'stats' => 'Statistics', 'daily-practice' => "Bruce's Practice"] as $routeName => $label)
                                <a href="{{ route($routeName) }}"
                                   @if (request()->routeIs($routeName)) aria-current="page" @endif
                                   @class([
                                       'rounded-lg px-3 py-2 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2',
                                       'bg-emerald-50 text-emerald-900 hover:bg-emerald-100' => request()->routeIs($routeName),
                                       'text-slate-600 hover:bg-stone-100 hover:text-slate-900' => ! request()->routeIs($routeName),
                                   ])>
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>

                    </div>
                </nav>

                <livewire:backup-status />

                <!-- Page Heading -->
                @if (isset($header))
                    <header class="mb-6">
                        {{ $header }}
                    </header>
                @endif

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>

            </div>

        </div>
    </body>
</html>
