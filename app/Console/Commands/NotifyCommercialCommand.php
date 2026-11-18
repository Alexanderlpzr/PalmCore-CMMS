<?php

namespace App\Console\Commands;

use App\Domain\Commercial\Services\CommercialNotices;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('commercial:notify')]
#[Description('Avisa por la campana la cartera vencida, los cargues sin facturar, las ofertas por cumplirse y los tanques sin medir o con acidez alta')]
class NotifyCommercialCommand extends Command
{
    public function handle(CommercialNotices $notices): int
    {
        $sent = $notices->send();

        $this->info("Facturas vencidas: {$sent['overdue']}. Cargues sin facturar: {$sent['uninvoiced']}. Ofertas por cumplirse: {$sent['offers']}. Avisos de tanques: {$sent['tanks']}.");

        return Command::SUCCESS;
    }
}
