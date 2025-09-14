<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserTemporaryTelegramFile extends Model
{
    use HasFactory;

    protected $table = 'user_temporary_telegram_files';

    protected $fillable = [
        'uid',
        'file_type',
        'telegram_file_id',
        'status'
    ];

    // File types
    const TYPE_PHOTO = 'photo';
    const TYPE_VIDEO = 'video';
    const TYPE_DOCUMENT = 'document';
    const TYPE_AUDIO = 'audio';

    // Statuses
    const STATUS_NEW = 'new';
    const STATUS_USED = 'used';
    const STATUS_EXPIRED = 'expired';

    public function isImage()
    {
        return $this->file_type === self::TYPE_PHOTO;
    }

    public function isVideo()
    {
        return $this->file_type === self::TYPE_VIDEO;
    }

    public function isDocument()
    {
        return $this->file_type === self::TYPE_DOCUMENT;
    }

    public function isAudio()
    {
        return $this->file_type === self::TYPE_AUDIO;
    }

    public function isExpired()
    {
        return $this->created_at->addHour()->isPast();
    }

    public function markAsUsed()
    {
        $this->update(['status' => self::STATUS_USED]);
    }

    public function markAsExpired()
    {
        $this->update(['status' => self::STATUS_EXPIRED]);
    }

    // Scope для получения новых файлов пользователя
    public function scopeNewForUser($query, $uid)
    {
        return $query->where('uid', $uid)
                    ->where('status', self::STATUS_NEW)
                    ->where('created_at', '>', Carbon::now()->subHour());
    }

    // Scope для получения просроченных файлов
    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_NEW)
                    ->where('created_at', '<=', Carbon::now()->subHour());
    }
}
