<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderMedia extends Model
{
    use HasFactory;

    protected $table = 'order_media';

    protected $fillable = [
        'order_id',
        'file_path',
        'original_name',
        'file_type',
        'file_size',
        'file_hash',
        'mime_type',
        'telegram_file_id',
        'telegram_file_unique_id'
    ];

    protected $casts = [
        'file_size' => 'integer'
    ];

    // File types
    const TYPE_PHOTO = 'photo';
    const TYPE_VIDEO = 'video';
    const TYPE_DOCUMENT = 'document';
    const TYPE_AUDIO = 'audio';

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getFileUrlAttribute()
    {
        if ($this->file_path) {
            return asset('storage/' . $this->file_path);
        }
        return null;
    }

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
}
