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
        <div class="min-h-screen bg-stone-50 px-6 py-8 text-slate-800">

            <div class="mx-auto max-w-6xl">

                <!-- Navigation -->
                <nav class="mb-6 rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex h-16 items-center justify-between px-6">

                        <!-- Logo -->
                        <a href="{{ route('dashboard') }}"
                           class="text-xl font-semibold text-slate-900">
                            Life Atlas
                        </a>

                        <!-- Menu -->
                        <div class="flex gap-6">
                            <a href="{{ route('dashboard') }}">
                                Dashboard
                            </a>

                            <a href="{{ route('history') }}">
                                History
                            </a>

                            <a href="{{ route('stats') }}">
                                Statistics
                            </a>
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
