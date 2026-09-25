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
            // Figures arrive with the Capex lifecycle (Phase 5); tints come from the pastel theme tokens.
            'kpis' => [
                ['label' => __('Total Requests'), 'tone' => 'bg-pastel-sky text-pastel-sky-ink'],
                ['label' => __('Pending Approvals'), 'tone' => 'bg-pastel-peach text-pastel-peach-ink'],
                ['label' => __('Requests in Process'), 'tone' => 'bg-pastel-lavender text-pastel-lavender-ink'],
                ['label' => __('Completed Requests'), 'tone' => 'bg-pastel-mint text-pastel-mint-ink'],
                ['label' => __('Total Paid Amount'), 'tone' => 'bg-pastel-rose text-pastel-rose-ink'],
            ],
        ]);
    }
}
