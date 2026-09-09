<?php

namespace App\Livewire;

use App\Models\Event;
use Livewire\Component;
use App\Models\LifeArea;
use Carbon\Carbon;

class Stats extends Component {
    public function render() {
        return view('livewire.stats', []);
    }
}
