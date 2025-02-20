<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;

class SwipeToUnlock extends Component
{
    public $unlocked = false;
    public $device_id;

    public function mount($device_id)
    {
        $this->device_id = $device_id;
    }

    #[On('unlock')]
    public function unlock()
    {
        $this->unlocked = true;
        $this->dispatch('pushButton', deviceId: $this->device_id);
    }

    public function render()
    {
        return view('livewire.swipe-to-unlock');
    }
}
