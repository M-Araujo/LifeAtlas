<?php

namespace App\Livewire;

use App\Models\Backup;
use Livewire\Component;

class BackupStatus extends Component
{
    public function render()
    {
        $backup = Backup::latest('backup_date')
            ->latest('updated_at')
            ->first();

        return view('livewire.backup-status', [
            'backup' => $backup,
        ]);
    }
}
