<div class="min-h-screen bg-stone-50 px-6 py-8 text-slate-800">

    <div class="mx-auto max-w-6xl">

        {{-- Header --}}
        <header class="mb-6">
            <p class="text-sm font-medium text-emerald-700">
                Personal dashboard
            </p>

            <h1 class="text-3xl font-semibold tracking-tight text-slate-900">
                Life check-ins
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Track an area, search your history, and see the balance.
            </p>
        </header>


        {{-- Add Entry --}}
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            <div class="mb-5">
                <h2 class="font-semibold text-slate-900">
                    Add entry
                </h2>

                <p class="text-sm text-slate-500">
                    Pick a category and jot a quick note.
                </p>
            </div>


            <form wire:submit="saveRecord">

                <div class="grid gap-4 md:grid-cols-[200px_1fr_auto]">

                    {{-- Category --}}
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">
                            Category
                        </label>

                        <select
                            wire:model="life_area_id"
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
                        >
                            <option value="">Select a category</option>
                            <option value="1">Health</option>
                            <option value="2">Career</option>
                            <option value="3">Relationships</option>
                            <option value="4">Finance</option>
                            <option value="5">Growth</option>
                            <option value="6">Recreation</option>
                        </select>
                    </div>

                    {{-- Note --}}
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">
                            Note
                        </label>

                        <input
                            type="text"
                            wire:model="description"
                            placeholder="What shaped this moment?"
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
                        >
                    </div>

                    {{-- Type --}}
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">
                            Type
                        </label>

                        <select
                            wire:model="type"
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
                        >
                            <option value="neutral">Neutral</option>
                            <option value="positive">Positive</option>
                            <option value="negative">Negative</option>
                        </select>
                    </div>

                    {{-- Save --}}
                    <div class="flex items-end">
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-emerald-800 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-900 md:w-auto"
                        >
                            + Save
                        </button>
                    </div>

                </div>

            </form>

        </section>


        {{-- Statistics --}}
        <section class="mt-4 grid gap-4 md:grid-cols-3">

            {{-- Check-in days --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

                <p class="text-sm text-slate-500">
                    Check-in days
                </p>

                <p class="mt-2 text-3xl font-semibold text-slate-900">
                    12
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
                    27
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
                    6
                </p>

                <p class="mt-1 text-xs text-slate-400">
                    Currently tracked
                </p>

            </div>

        </section>


        {{-- Results --}}
        <section class="mt-4 rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 p-5">

                <div class="flex items-center justify-between">

                    <div>
                        <h2 class="font-semibold text-slate-900">
                            Results
                        </h2>

                        <p class="text-sm text-slate-500">
                            Recent life check-ins
                        </p>
                    </div>

                    <span class="text-xs text-slate-400">
                        27 entries
                    </span>

                </div>


                {{-- Search --}}
                <div class="mt-4">

                    <input
                        type="search"
                        placeholder="Search notes or category..."
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm"
                    >

                </div>

            </div>


            {{-- Dummy results --}}
            <div class="divide-y divide-slate-100">

                @foreach ($events as $event)
                    <div class="grid gap-2 px-5 py-4 md:grid-cols-[120px_160px_1fr]">

                        <span class="text-sm text-slate-400">
                            {{ $event->created_at->format('d M Y') }}
                        </span>

                        <span class="text-sm font-medium text-emerald-800">
                            {{ $event->lifeArea->name }}
                        </span>

                        <span class="text-sm text-slate-600">
                            {{ $event->description }}
                        </span>

                    </div>
                @endforeach
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


                {{-- Search --}}
                <div class="mt-4">

                    <input
                        type="search"
                        placeholder="Search a category..."
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm"
                    >

                </div>


                {{-- Dummy categories --}}
                <div class="mt-4 space-y-2">

                    @foreach($lifeAreas as $lifeArea)
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">

                            <span class="text-sm">
                                {{$lifeArea->name}}
                            </span>

                            <span class="text-xs text-slate-500">
                                {{$lifeArea->events->count()}} entries
                            </span>

                        </div>
                    @endforeach

                </div>

            </div>

        </section>

    </div>

</div>
