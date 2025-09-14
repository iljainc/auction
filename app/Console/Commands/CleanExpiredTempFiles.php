<?php

namespace App\Console\Commands;

use App\Models\UserTemporaryTelegramFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanExpiredTempFiles extends Command
{
    protected $signature = 'temp-files:clean';
    protected $description = 'Clean expired temporary files';

    public function handle()
    {
        $expiredFiles = UserTemporaryTelegramFile::expired()->get();
        $count = 0;
        
        foreach ($expiredFiles as $file) {
            // Помечаем как просроченный (физических файлов нет)
            $file->markAsExpired();
            $count++;
        }
        
        $this->line("Cleaned {$count} expired temporary telegram files");
        
        return 0;
    }
}
