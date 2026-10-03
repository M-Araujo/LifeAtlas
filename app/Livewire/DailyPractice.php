<?php

namespace App\Livewire;

use App\Models\DailyImprovement;
use App\Models\DailyPractice as Practice;
use App\Models\Principle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DailyPractice extends Component
{
    public string $newIssue = '';

    public array $drafts = [];

    #[Locked]
    public array $editingIds = [];

    private function today(): string
    {
        return CarbonImmutable::today('Europe/Lisbon')->toDateString();
    }

    private function createPractice(): Practice
    {
        $principle = Principle::where('active', true)->whereNotNull('verified_at')
            ->where('verified_at', '<=', now())
            ->whereRaw("TRIM(text) <> ''")->whereRaw("TRIM(source_reference) <> ''")
            ->inRandomOrder()->first();

        return Practice::create([
            'practice_date' => $this->today(),
            'principle_id' => $principle?->id,
        ]);
    }

    public function addImprovement(): void
    {
        $this->newIssue = trim($this->newIssue);
        $this->validate(['newIssue' => ['required', 'string', 'max:10000']]);
        DB::transaction(function () {
            $this->createPractice()->improvements()->create(['issue' => $this->newIssue]);
        });
        $this->reset('newIssue');
    }

    private function activeItems(): Builder
    {
        return DailyImprovement::where('status', '!=', 'resolved')
            ->whereHas('practice', fn ($query) => $query->where('practice_date', '<=', $this->today()));
    }

    public function editImprovement(int $id): void
    {
        $item = $this->activeItems()->find($id);
        abort_unless($item, 404);
        if (in_array($id, $this->editingIds, true)) {
            return;
        }
        $this->editingIds[] = $id;
        $this->drafts[$id] = ['issue' => $item->issue, 'solution' => $item->solution ?? '', 'status' => $item->status];
    }

    public function saveImprovement(int $id): void
    {
        abort_unless(in_array($id, $this->editingIds, true), 404);
        if (is_string($this->drafts[$id]['issue'] ?? null)) {
            $this->drafts[$id]['issue'] = trim($this->drafts[$id]['issue']);
        }
        $validated = $this->validate([
            "drafts.$id.issue" => ['required', 'string', 'max:10000'],
            "drafts.$id.solution" => ['nullable', 'string', 'max:10000'],
            "drafts.$id.status" => ['required', Rule::in(array_keys(DailyImprovement::STATUSES))],
        ]);
        DB::transaction(function () use ($id, $validated) {
            $item = $this->activeItems()->lockForUpdate()->find($id);
            abort_unless($item, 404);
            $draft = $validated['drafts'][$id];
            $item->issue = $draft['issue'];
            $solution = trim($draft['solution'] ?? '');
            $item->solution = $solution === '' ? null : $solution;
            $item->applyStatus($draft['status']);
            $item->save();
        });
        $this->cancelImprovementEdit($id);
    }

    public function cancelImprovementEdit(int $id): void
    {
        unset($this->drafts[$id]);
        $this->editingIds = array_values(array_diff($this->editingIds, [$id]));
        $this->resetValidation(["drafts.$id.issue", "drafts.$id.solution", "drafts.$id.status"]);
    }

    public function deleteImprovement(int $id): void
    {
        $item = $this->activeItems()->find($id);
        abort_unless($item, 404);
        $item->delete();
        $this->cancelImprovementEdit($id);
    }

    public function render()
    {
        return view('livewire.daily-practice', [
            'practice' => new Practice(['practice_date' => $this->today()]),
            'items' => $this->activeItems()->with('practice')->orderByDesc('created_at')->orderByDesc('id')->get(),
            'statuses' => DailyImprovement::STATUSES,
        ])->layout('components.layouts.app');
    }
}
