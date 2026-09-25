<?php

namespace App\Domain\MasterData\Import;

enum ImportStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('Queued'),
            self::Running => __('Running'),
            self::Completed => __('Completed'),
            self::Failed => __('Failed — nothing imported'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued, self::Running => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
