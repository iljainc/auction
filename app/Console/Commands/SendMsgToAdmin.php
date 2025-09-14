<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\TelegramService;
use Illuminate\Console\Command;

use App\Models\Location;
use App\Models\Lang;

use App\Services\LangService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


class SendMsgToAdmin extends Command
{
    protected $signature = 'sendMsgToAdmin 
                            {--order= : Test specific order by ID}';
    protected $description = 'Send messages to admin for approval';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if test mode for specific order
        $testOrderId = $this->option('order');
        
        if ($testOrderId) {
            $this->info("Test mode: Processing order ID: {$testOrderId}");
            $this->haveNewOrder($testOrderId);
            return true;
        }
        
        // Normal mode
        $this->info('Init SendMsgToAdmin - continuous mode');
        
        // Сбрасываем блокировки старше 5 минут один раз в начале
        $this->resetOldBlocks();
        
        $startTime = time();
        $maxExecutionTime = 55; // 1 minute
        
        while (true) {
            // Check if we've been running for more than 1 minute
            if (time() - $startTime >= $maxExecutionTime) {
                $this->info('Execution time limit reached, terminating');
                break;
            }
            
            // Обрабатываем заказы
            $orderProcessed = $this->haveNewOrder();
            
            // Если нет заданий - спим
            if (!$orderProcessed) {
                $this->info('No tasks to process, sleep 1');
                sleep(1);
            }
        }
    }

    private function haveNewOrder($specificId = null){
        // Получаем один заказ для обработки
        $order = null;
        
        if ($specificId) {
            // Test mode - just get specific order by ID, no transaction
            $order = Order::where('id', $specificId)->with(['telegramUser'])->first();
        } else {
            // Normal mode - with transaction and status update
            DB::transaction(function () use (&$order) {
                $order = Order::where('text_admin_check', Order::TEXT_ADMIN_CHECK_NEW)
                             ->whereNull('closed_at') // Еще не закрыт
                             ->with(['telegramUser'])
                             ->lockForUpdate()
                             ->first();
                                 
                    if ($order) {
                        $order->update([
                            'text_admin_check' => Order::TEXT_ADMIN_CHECK_SENDING,
                            'block_timestamp' => now()
                        ]);
                    }
                });
            
        }

        if (!$order) {
            return false;
        }

        $this->info('Processing new order #'.$order->id);

        // Process locations if not already processed
        $order->processLocations();

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => __("✅ Accept"), 'callback_data' => 'admin_acceptOrder_'.$order->id],
                    ['text' => __("❌ Reject"), 'callback_data' => 'admin_rejectOrder_'.$order->id]
                ]
            ]
        ];

        // Формируем сообщение с локациями и ценой
        $locationText = '';
        $orderLocations = $order->locations()->get(); // Получаем коллекцию локаций
        if ($orderLocations->count() > 0) {
            $locationNames = $orderLocations->map(function($location) {
                $parts = array_filter([$location->city, $location->region, $location->country]);
                return implode(', ', $parts);
            })->implode('; ');
            $locationText = "📍 - {$order->locations} ({$locationNames})\n";
        }
        
        $bidText = $order->bid ? "💰 - {$order->bid}₪\n" : '';
        
        // Название лота
        $lotNameText = $order->lot_name ? "🏷️ - {$order->lot_name}\n" : '';
        
        // Информация о пользователе телеграм
        $userText = '';
        if ($order->telegramUser) {
            if ($order->telegramUser->username) {
                $userText .= "👤 - @{$order->telegramUser->username}\n";
            }
        }
        
        $text = "<b>For admin::</b>\n📋 Новый лот <b>#$order->id</b>\n\n{$lotNameText}📝 - {$order->text}\n{$locationText}{$bidText}{$userText}🆔 - {$order->id}\n";

        TelegramService::sendMessageToAdmin($text, $keyboard, "admin_order_{$order->id}");

        $this->info('Send msg for order #'.$order->id);

        $order->update([ 
            'text_admin_check' => Order::TEXT_ADMIN_CHECK_SENDED,
            'block_timestamp' => null
        ]);
        
        return true;
    }

    
    
    /**
     * Сбрасывает блокировки старше 5 минут для мастеров и заказов
     */
    private function resetOldBlocks()
    {
        $this->info('Resetting old blocks...');
        
        // Сбрасываем блокировки заказов старше 5 минут
        $ordersReset = Order::where('text_admin_check', Order::TEXT_ADMIN_CHECK_SENDING)
            ->where('block_timestamp', '<', now()->subMinutes(5))
            ->update([
                'text_admin_check' => Order::TEXT_ADMIN_CHECK_NEW,
                'block_timestamp' => null
            ]);
            
        if ($ordersReset > 0) {
            $this->info("Reset blocks: {$ordersReset} orders");
        }
    }

}
