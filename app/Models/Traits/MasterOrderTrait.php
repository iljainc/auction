<?php

namespace App\Models\Traits;

use App\Models\Lang;
use App\Models\Location;
use App\Services\ChatGptService;
use App\Services\LangService;
use App\Services\TelegramService;

trait MasterOrderTrait
{
    const STATUS_NEW = 0; // Waiting for admin check ***
    const STATUS_REJECTED_BY_ADMIN = 2; // Waiting for ChatGPT load
    const STATUS_IN_WORK = 3;
    const STATUS_BROKEN = 4; // Broken for FindMastersForOrders
    const STATUS_CLOSED = 8;

    public static $statuses = [
        self::STATUS_NEW => 'New',
        self::STATUS_REJECTED_BY_ADMIN => 'Rejected by adm',
        self::STATUS_IN_WORK => 'In work',
        self::STATUS_BROKEN => 'Broken',
        self::STATUS_CLOSED => 'Closed',
    ];

    const TEXT_ADMIN_CHECK_NEW = 0;
    const TEXT_ADMIN_CHECK_SENDED = 1;
    const TEXT_ADMIN_CHECK_BLOCKED = 2;
    const TEXT_ADMIN_CHECK_INWORK = 3;
    const TEXT_ADMIN_CHECK_BROKEN = 4; // Broken for FindMastersForOrders
    const TEXT_ADMIN_CHECK_SENDING = 5; // Message is being sent to admin

    public function setTextEn() {
        $this->text_en = LangService::translate($this->text, 'en');
        $this->save();
    }
}
