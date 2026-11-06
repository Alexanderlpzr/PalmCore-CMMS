<?php

namespace App\Console\Commands;

use App\Domain\Safety\Services\SafetyFindingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sst:notify-overdue')]
#[Description('Avisa por la campana los hallazgos de SST que se vencieron sin corregirse')]
class NotifyOverdueSstFindingsCommand extends Command
{
    public function handle(SafetyFindingService $findings): int
    {
        $count = $findings->notifyOverdue();

        $this->info("Hallazgos vencidos avisados: {$count}.");

        return Command::SUCCESS;
    }
}
