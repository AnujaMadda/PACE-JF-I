<?php

namespace App\Livewire;

use App\Domain\Core\Support\CurrentEntity;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Role-aware dashboard. Phase 1 shows the layout and KPI placeholders; the
 * figures and widgets arrive with the Capex lifecycle (Phase 5) and
 * Visibility (Phase 6).
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(CurrentEntity $currentEntity): View
    {
        return view('livewire.dashboard', [
            'entity' => $currentEntity->require(),
            'kpis' => [
                __('Total Requests'),
                __('Pending Approvals'),
                __('Requests in Process'),
                __('Completed Requests'),
                __('Total Paid Amount'),
            ],
        ]);
    }
}
