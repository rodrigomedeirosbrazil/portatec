<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class PlaceSelect extends Component
{
    public function __construct(
        public Collection $places,
        public string $label = 'Local',
        public bool $required = false,
        public bool $includeEmpty = false,
        public string $emptyOptionLabel = 'Todos',
        public ?string $errorName = null,
    ) {}

    public function render(): View
    {
        return view('components.place-select');
    }
}
