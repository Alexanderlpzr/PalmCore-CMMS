<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

#[Signature('webpush:vapid')]
#[Description('Generate VAPID key pair for Web Push notifications')]
class WebPushGenerateVapidKeysCommand extends Command
{
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info('VAPID keys generated. Add these to your .env file:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->comment('Keep VAPID_PRIVATE_KEY secret. VAPID_PUBLIC_KEY is used in the browser.');

        return Command::SUCCESS;
    }
}
