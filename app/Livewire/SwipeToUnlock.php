<?php

namespace App\Livewire;

use Livewire\Component;

class SwipeToUnlock extends Component
{
    public $unlocked = false;

    public function unlock()
    {
        $this->unlocked = true;
    }

    public function render()
    {
        return view('livewire.swipe-to-unlock');
    }
}
