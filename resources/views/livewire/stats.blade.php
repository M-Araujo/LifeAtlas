<div>
    <header class="mb-6">
        <p class="text-sm font-medium text-emerald-700">
            Personal dashboard
        </p>

        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">
            Stats
        </h1>

        <p class="mt-1 text-sm text-slate-500">
            View your life statistics.
        </p>
    </header>

    {{-- Statistics --}}
    <section class="mt-4 grid gap-4 md:grid-cols-3">
        {{-- Check-in days --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">
                Check-in days
            </p>

            <p class="mt-2 text-3xl font-semibold text-slate-900">
                {{ $stats['check_in_days'] }}
            </p>

            <p class="mt-1 text-xs text-slate-400">
                This month
            </p>
        </div>

        {{-- Total entries --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">
                Total entries
            </p>

            <p class="mt-2 text-3xl font-semibold text-slate-900">
                {{ $stats['total'] }}
            </p>

            <p class="mt-1 text-xs text-slate-400">
                All time
            </p>
        </div>

        {{-- Life areas --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">
                Life areas
            </p>

            <p class="mt-2 text-3xl font-semibold text-slate-900">
                {{ $stats['life_areas'] }}
            </p>

            <p class="mt-1 text-xs text-slate-400">
                Currently tracked
            </p>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Positive</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-700">
                    {{ $stats['positive'] }}
                </p>
                <p class="mt-1 text-xs text-slate-400">All time</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Neutral</p>
                <p class="mt-2 text-3xl font-semibold text-slate-700">
                    {{ $stats['neutral'] }}
                </p>
                <p class="mt-1 text-xs text-slate-400">All time</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Negative</p>
                <p class="mt-2 text-3xl font-semibold text-red-700">
                    {{ $stats['negative'] }}
                </p>
                <p class="mt-1 text-xs text-slate-400">All time</p>
            </div>
        </div>
    </section>

    {{-- Analysis --}}
    <section class="mt-4 grid gap-4 lg:grid-cols-2">
        {{-- Life Balance --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-900">
                Life balance
            </h2>

            <p class="text-sm text-slate-500">
                Activity across your life areas.
            </p>

            {{-- Temporary chart --}}
            <div class="mt-6 flex h-72 items-center justify-center">
                <div class="relative h-52 w-52">
                    {{-- Radar grid --}}
                    <div class="absolute inset-0 rotate-45 border border-slate-200"></div>
                    <div class="absolute inset-8 rotate-45 border border-slate-200"></div>
                    <div class="absolute inset-16 rotate-45 border border-slate-200"></div>

                    {{-- Dummy data shape --}}
                    <div
                        class="absolute inset-[25%] rotate-45 border-2 border-emerald-500 bg-emerald-100/40"
                    ></div>

                    {{-- Labels --}}
                    <span class="absolute left-1/2 -top-5 -translate-x-1/2 text-xs text-slate-400">
                        Health
                    </span>

                    <span class="absolute -right-16 top-1/4 text-xs text-slate-400">
                        Career
                    </span>

                    <span class="absolute -right-20 bottom-1/4 text-xs text-slate-400">
                        Relationships
                    </span>

                    <span class="absolute bottom-[-20px] left-1/2 -translate-x-1/2 text-xs text-slate-400">
                        Finance
                    </span>

                    <span class="absolute -left-14 bottom-1/4 text-xs text-slate-400">
                        Growth
                    </span>

                    <span class="absolute -left-16 top-1/4 text-xs text-slate-400">
                        Recreation
                    </span>
                </div>
            </div>
        </div>

        {{-- Category Analysis --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-900">
                Category analysis
            </h2>

            <p class="text-sm text-slate-500">
                Activity by life area.
            </p>

            <div class="mt-4 space-y-2">
                @foreach ($lifeAreas as $lifeArea)
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
                        <span class="text-sm">
                            {{ $lifeArea->name }}
                        </span>

                        <span class="text-xs text-slate-500">
                            {{ $lifeArea->events->count() }} entries
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

</div>
