<?php

namespace App\Models;

use App\Models\Traits\MasterOrderTrait;
use App\Services\ChatGptService;
use App\Services\LangService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\MasterOrderCheck;

class Order extends Model
{
    const SELF = 'Order';

    use HasFactory;

    use MasterOrderTrait;

    const TYPE_INTERNAL = 'internal';
    const TYPE_EXTERNAL = 'external';
    
    // Order source constants
    const SOURCE_TELEGRAM = 'telegram';
    const SOURCE_API = 'api';
    const SOURCE_AI = 'ai';
    const SOURCE_WEB = 'web';

    // Auction status constants
    const AUCTION_STATUS_NEW = 'new';
    const AUCTION_STATUS_PUBLISHING = 'publishing';
    const AUCTION_STATUS_PUBLISHED = 'published';
    const AUCTION_STATUS_FAILED = 'failed';

    protected $fillable = ['uid', 'text', 'lot_name', 'text_en', 'text_admin_check', 'lang',
        'status', 'closed_at', 'order_type', 'source', 'block_timestamp', 'bid', 'locations',
        'auction_message_id', 'auction_comment_message_id', 'auction_status', 'auction_posted_at'];

    protected $casts = [
        'block_timestamp' => 'datetime',
        'auction_posted_at' => 'datetime',
    ];

    protected static function boot() {
        parent::boot();

        static::saving(function ($model) {
            if ($model->status == self::STATUS_BROKEN)                           $model->status = self::STATUS_BROKEN; // Preserve broken status
            elseif (!empty($model->closed_at))                                   $model->status = self::STATUS_CLOSED;
            elseif ($model->text_admin_check == self::TEXT_ADMIN_CHECK_BLOCKED)  $model->status = self::STATUS_REJECTED_BY_ADMIN;
            elseif ($model->text_admin_check == self::TEXT_ADMIN_CHECK_INWORK)   $model->status = self::STATUS_IN_WORK;
            else                                                                 $model->status = self::STATUS_NEW;
        });
    }

    // Отношения
    public function langs()
    {
        return $this->belongsToMany(Lang::class, 'order_lang');
    }

    public function masterOrderChecks()
    {
        return $this->hasMany(MasterOrderCheck::class);
    }

    public function masters()
    {
        return $this->belongsToMany(Master::class, 'master_order');
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'order_location');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uid', 'id');
    }

    public function telegramUser()
    {
        return $this->belongsTo(TelegramUser::class, 'uid', 'uid');
    }

    public function masterOrderCheck()
    {
        return $this->hasOne(MasterOrderCheck::class);
    }

    public function masterOrders()
    {
        return $this->hasMany(MasterOrder::class);
    }

    public function media()
    {
        return $this->hasMany(OrderMedia::class);
    }



    /**
     * Process and sync locations using AI
     */
    public function processLocations()
    {
        // Skip if model already has locations
        if ($this->locations()->count() > 0) {
            return;
        }
        
        // Find locations in text using AI
        $locationIds = app(\App\Services\LocationService::class)->findLocationsInText($this->locations);
        
        if (!empty($locationIds)) {
            // Synchronize locations - remove old, keep existing, add new
            $this->locations()->sync($locationIds);
            
            // Reload locations for display
            $this->load('locations');
        }
    }

    /**
     * Attach temporary files to this order
     */
    public function attachTemporaryFiles($uid)
    {
        $tempFiles = UserTemporaryTelegramFile::newForUser($uid)->get();
        
        foreach ($tempFiles as $tempFile) {
            // Создаем запись в order_media
            OrderMedia::create([
                'order_id' => $this->id,
                'file_path' => null,
                'original_name' => null,
                'file_type' => $tempFile->file_type,
                'file_size' => null,
                'file_hash' => null,
                'mime_type' => null,
                'telegram_file_id' => $tempFile->telegram_file_id,
                'telegram_file_unique_id' => null
            ]);
            
            // Помечаем временный файл как использованный
            $tempFile->markAsUsed();
        }
        
        return $tempFiles->count();
    }

}
