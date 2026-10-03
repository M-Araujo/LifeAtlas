<div>
    <header class="mb-6">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Bruce's Practice</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $practice->practice_date->format('j M Y') }} · Europe/Lisbon</p>
        <p class="mt-5 rounded-xl bg-emerald-50 p-5 text-2xl font-semibold text-emerald-900">How am I going to express myself honestly today?</p>
    </header>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
        <div class="space-y-4">
            <section aria-labelledby="water-heading" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 id="water-heading" class="font-semibold text-slate-900">Water and adaptability</h2>
                <p class="mt-2 text-sm text-slate-600">Pause to breathe and refocus. Picture water adapting to its surroundings, and consider where you could respond with more flexibility today.</p>
            </section>

            <form wire:submit="addImprovement" wire:key="add-improvement" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <label for="new-issue" class="block font-semibold text-slate-900">What do I need to improve?</label>
                <p id="add-guidance" class="mt-2 text-sm text-slate-500">Identify 3 things you would like to improve today. Add as many as you need.</p>
                <textarea id="new-issue" wire:model="newIssue" aria-describedby="add-guidance" rows="3" class="mt-3 w-full rounded-lg border-slate-300 text-sm"></textarea>
                @error('newIssue') <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                <button type="submit" wire:loading.attr="disabled" wire:target="addImprovement" class="mt-3 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">Add</button>
            </form>
        </div>

        <section aria-labelledby="active-heading" class="min-w-0">
            <h2 id="active-heading" class="text-xl font-semibold text-slate-900">What I’m working on</h2>
            <p class="mt-1 text-sm text-slate-500">Active improvements from today and previous days, newest first.</p>
            <div class="mt-4 space-y-4">
                @forelse ($items as $item)
                    <article wire:key="improvement-{{ $item->id }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-500">Identified {{ $item->practice->practice_date->format('j M Y') }} · {{ $statuses[$item->status] }}</p>
                        <h3 class="mt-2 whitespace-pre-wrap break-words font-medium text-slate-900">{{ $item->issue }}</h3>
                        <p class="mt-3 whitespace-pre-wrap break-words text-sm text-slate-600">{{ $item->solution ?? 'No solution yet' }}</p>
                        @if (in_array($item->id, $editingIds, true))
                            <form wire:submit="saveImprovement({{ $item->id }})" class="mt-4">
                                <label for="issue-{{ $item->id }}" class="block text-sm font-medium text-slate-700">Thing to improve</label>
                                <textarea id="issue-{{ $item->id }}" wire:model="drafts.{{ $item->id }}.issue" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea>
                                @error("drafts.$item->id.issue") <p role="alert" class="text-sm text-red-700">{{ $message }}</p> @enderror
                                <label for="solution-{{ $item->id }}" class="mt-3 block text-sm font-medium text-slate-700">Solution (optional)</label>
                                <textarea id="solution-{{ $item->id }}" wire:model="drafts.{{ $item->id }}.solution" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea>
                                @error("drafts.$item->id.solution") <p role="alert" class="text-sm text-red-700">{{ $message }}</p> @enderror
                                <label for="status-{{ $item->id }}" class="mt-3 block text-sm font-medium text-slate-700">Status</label>
                                <select id="status-{{ $item->id }}" wire:model="drafts.{{ $item->id }}.status" class="mt-1 rounded-lg border-slate-300 text-sm">
                                    @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                                </select>
                                @error("drafts.$item->id.status") <p role="alert" class="text-sm text-red-700">{{ $message }}</p> @enderror
                                <div class="mt-3 flex gap-4">
                                    <button type="submit" wire:loading.attr="disabled" wire:target="saveImprovement({{ $item->id }})" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">Save</button>
                                    <button type="button" wire:click="cancelImprovementEdit({{ $item->id }})" class="text-sm text-slate-700 underline">Cancel</button>
                                </div>
                            </form>
                        @else
                            <button type="button" wire:click="editImprovement({{ $item->id }})" class="mt-4 text-sm font-medium text-emerald-700 underline">Edit</button>
                        @endif
                        <button type="button" wire:click="deleteImprovement({{ $item->id }})" wire:confirm="Delete this improvement? This cannot be undone." class="mt-4 text-sm font-medium text-red-700 underline">Delete</button>
                    </article>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-600">No active improvements. Add something you would like to work on.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
