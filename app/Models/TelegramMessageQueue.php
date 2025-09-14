<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramMessageQueue extends Model
{
    const STATUS_QUEUED = 'queued';
    const STATUS_PROCESSING = 'processing'; // Статус для сообщений в обработке
    const STATUS_SENT = 'sent';
    const STATUS_SENT_ERRORS = 'sent_error'; // Новый статус для сообщений, отправленных с ошибками

    const TYPE_NEW_ORDER_TO_MASTER = 'new_order_to_master';

    const TYPE_NEW_MASTER_TO_CLIENT = 'new_master_to_client';

    const TYPE_MASTERS_FOUND_FOR_ORDER = 'masters_found_for_order';

    const TYPE_NO_MASTERS_FOUND_FOR_ORDER = 'no_masters_found_for_order';

    protected $casts = [
        'json' => 'array',  // Указывает, что 'json' должен быть автоматически преобразован в JSON и обратно
    ];

    protected $table = 'telegram_message_queue';

    use HasFactory;

    protected $fillable = [
        'type',               // Тип сообщения
        'tid',                // Ссылка на пользователя Telegram
        'obj_id',             // ID объекта (order_id, master_id и т.д.)
        'status',             // Статус сообщения
        'json',               // Дополнительные данные
    ];

    // Связь с моделью TelegramUser
    public function tuser()
    {
        return $this->belongsTo(TelegramUser::class, 'tid', 'tid');
    }
}
