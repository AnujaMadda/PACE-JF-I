<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

/**
 * Stand-in page for sidebar sections that later phases deliver.
 */
class Placeholder extends Component
{
    public string $section = '';

    /** @var array<string, array{0: string, 1: string}> */
    private const SECTIONS = [
        'capex' => ['Capex Requests', 'Phase 5'],
        'payments' => ['Payments Tracker', 'Phase 5'],
        'reports' => ['Reports & Analytics', 'Phase 6'],
    ];

    public function mount(string $section): void
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);

        $this->section = $section;
    }

    public function render(): View
    {
        [$title, $phase] = self::SECTIONS[$this->section];

        return view('livewire.placeholder', ['title' => __($title), 'phase' => $phase])
            ->title(__($title));
    }
}
