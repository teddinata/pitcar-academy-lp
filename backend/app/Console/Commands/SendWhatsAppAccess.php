<?php

namespace App\Console\Commands;

use App\Services\Lms\AccessWhatsApp;
use Illuminate\Console\Command;

class SendWhatsAppAccess extends Command
{
    protected $signature = 'lms:send-whatsapp-access';

    protected $description = 'Retry class-access WhatsApp messages that kirimdev could not take on the first try';

    public function handle(AccessWhatsApp $whatsapp): int
    {
        $tried = $whatsapp->retryPending();

        if ($tried > 0) {
            $this->info("Dicoba ulang: {$tried} pesan.");
        }

        return self::SUCCESS;
    }
}
