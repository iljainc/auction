<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminRequest extends Model
{
    use HasFactory;

    // Константы для статусов
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'tid',
        'telegram_log_id',
        'status',
    ];

    // Связь с моделью User
    public function tuser()
    {
        return $this->belongsTo(TelegramUser::class, 'tid', 'tid');
    }

    // Связь с моделью TelegramLog
    public function telegramLog()
    {
        return $this->belongsTo(TelegramLog::class);
    }
}
