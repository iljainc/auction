<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class Publisher extends Command
{
    protected $signature = 'publish {--order= : Test specific order by ID}';
    protected $description = 'Publish approved orders to auction channel';

    public function handle()
    {
        // Check if test mode for specific order
        $testOrderId = $this->option('order');
        
        if ($testOrderId) {
            $order = Order::find($testOrderId);
            if (!$order) {
                $this->error("Order #{$testOrderId} not found");
                return;
            }
            $this->publishToAuction($order);
            return true;
        }
        
        // Normal mode
        $this->line('Starting auction publishing...');
        
        $startTime = time();
        $maxExecutionTime = 55;
        
        while (true) {
            if (time() - $startTime >= $maxExecutionTime) {
                $this->line('Execution time limit reached');
                break;
            }
            
            $processed = $this->processNextOrder();
            
            if (!$processed) {
                $this->line('No orders to publish, sleeping 1 second...');
                sleep(1);
            }
        }
        
        $this->line('Publishing completed');
    }

    private function processNextOrder(): bool
    {
        $order = null;
        
        DB::transaction(function () use (&$order) {
            $order = Order::where('text_admin_check', Order::TEXT_ADMIN_CHECK_INWORK)
                ->whereNull('auction_status')
                ->with(['telegramUser', 'media'])
                ->lockForUpdate()
                ->first();
                
            if ($order) {
                $order->update(['auction_status' => Order::AUCTION_STATUS_PUBLISHING]);
            }
        });
        
        if (!$order) {
            return false;
        }
        
        try {
            $this->line("Starting to publish order #{$order->id} (lot: {$order->lot_name})");
            $this->publishToAuction($order);
            
            $this->line("✅ Successfully published order #{$order->id}");
            return true;

        } catch (\Exception $e) {
            $errorMsg = "❌ Failed to publish order #{$order->id}: " . $e->getMessage();
            Log::error($errorMsg, [
                'order_id' => $order->id,
                'lot_name' => $order->lot_name,
                'exception' => $e->getTraceAsString()
            ]);
            $this->error($errorMsg);
            
            $order->update(['auction_status' => Order::AUCTION_STATUS_FAILED]);
            return true;
        }
    }


    /**
     * Публикует заказ в канал аукциона
     * 
     * Процесс:
     * 1. Получает настройки каналов из конфига
     * 2. Форматирует сообщение с информацией о лоте  
     * 3. Отправляет основное сообщение в канал аукциона
     * 4. Создает комментарий "Делайте ваши ставки" к сообщению
     * 5. Форвардит сообщение в группу (если настроена)
     * 6. Обновляет статус заказа в БД
     */
    private function publishToAuction(Order $order): void
    {
        // Получаем ID каналов из конфигурации
        $channelId = config('services.auction.channel_id');        // Основной канал аукциона (@auctiong1)
        $repostGroupId = config('services.auction.repost_group_id'); // Группа для репоста (@Haifa_myLife)
        $repostTopicId = config('services.auction.repost_topic_id'); // Топик в группе

        $this->line("📋 Channel ID: {$channelId}");
        $this->line("📋 Repost Group: {$repostGroupId}");

        // Проверяем что канал настроен
        if (!$channelId) {
            throw new \Exception('AUCTION_CHANNEL_ID not configured in services.auction.channel_id');
        }

        // Форматируем текст сообщения (лот, цена, локация и т.д.)
        $text = $this->formatOrder($order);
        
        // ШАГ 1: Отправляем основное сообщение в канал аукциона (с повторами при 502/503 ошибках)
        $this->line("📤 Sending message to channel {$channelId}...");
        $result = $this->sendWithRetry(function() use ($channelId, $text, $order) {
            return TelegramService::sendMessage($channelId, $text, '', 'auction_'.$order->id, $order->media);
        });
        
        // Извлекаем ID отправленного сообщения из результата
        $messageId = $this->extractMessageId($result);
        if (!$messageId) {
            $this->error("❌ Failed to extract message ID from result");
            if (is_object($result) && isset($result->error)) {
                throw new \Exception("Failed to post to channel: " . $result->error);
            }
            throw new \Exception('Failed to post to channel - no message ID returned');
        }

        $this->line("✅ Posted to channel, message ID: {$messageId}");

        // ШАГ 2: Создаем комментарий к основному сообщению
        // Ищем авто-форвард в discussion group и получаем правильный thread_id
        $reply_to_message_id = $this->findAutoForwardThreadId($messageId);
        
        if ($reply_to_message_id) {
            $this->line("✅ Found auto-forward thread ID: {$reply_to_message_id}");
            $discussionGroupId = config('services.auction.discussion_group_id');
            
            $this->line("💬 Sending comment to thread {$reply_to_message_id}...");
            $commentResult = TelegramService::sendMessage($discussionGroupId, "Делайте ваши ставки", '', 
                'auction_comm_'.$order->id, [], null, null, $reply_to_message_id);
                        
            $commentMessageId = $this->extractMessageId($commentResult);
            
            if ($commentMessageId) {
                $this->line("✅ Comment sent successfully, message ID: {$commentMessageId}");
            } else {
                $this->error("❌ Failed to send comment to thread");
            }
        } else {
            $this->error("❌ Could not find auto-forward in discussion group");
            $commentMessageId = null;
        }

        // ШАГ 3: Форвардим сообщение в группу (если настроена)
        // Это нужно для дублирования лотов в другие каналы/группы
        if ($repostGroupId) {
            $this->line("🔄 Forwarding message {$messageId} to repost group {$repostGroupId}...");
            $forwardResult = TelegramService::forwardMessage($channelId, $repostGroupId, $messageId, $repostTopicId);
  
            if ($forwardResult && $forwardResult->response) {
                $response = json_decode($forwardResult->response, true);
                if (isset($response['ok']) && $response['ok']) {
                    $this->line("✅ Message forwarded successfully to repost group (ID: {$response['result']['message_id']})");
                } else {
                    $this->error("❌ Failed to forward message to repost group");
                }
            } else {
                $this->error("❌ No response from forward request");
            }
        } else {
            $this->line("ℹ️ No repost group configured, skipping forward");
        }

        // ШАГ 4: Обновляем заказ в БД - сохраняем ID сообщений и меняем статус на "опубликовано"
        $this->line("💾 Updating order #{$order->id} in database...");
        $order->update([
            'auction_message_id' => $messageId,           // ID основного сообщения в канале
            'auction_comment_message_id' => $commentMessageId, // ID комментария (может быть null)
            'auction_status' => Order::AUCTION_STATUS_PUBLISHED, // Статус "опубликовано"
            'auction_posted_at' => now(),                 // Время публикации
        ]);
        
        $this->line("🎉 Order #{$order->id} published successfully!");
        $this->line("   📝 Channel message ID: {$messageId}");
        $this->line("   💬 Comment message ID: " . ($commentMessageId ?: 'none'));
        $this->line("   📊 Status: " . Order::AUCTION_STATUS_PUBLISHED);
    }

    private function formatOrder(Order $order): string
    {
        $text = "";
        
        // Лот: название
        if ($order->lot_name) {
            $text .= "<b>Лот:</b> {$order->lot_name}\n";
        }
        
        // Информация: описание
        if ($order->text) {
            $text .= "<b>Информация:</b> {$order->text}\n";
        }
        
        // Начальная ставка
        if ($order->bid) {
            $text .= "<b>Начальная ставка:</b> {$order->bid}₪\n";
        }
        
        // Следующий шаг (пока захардкодим)
        $text .= "<b>Следующий шаг:</b> 10₪\n";
        
        // Забирать: локации
        if ($order->locations) {
            $text .= "<b>Забирать:</b> {$order->locations}\n";
        }
        
        $text .= "👉<b>Для участия в лоте переходи по ссылке:</b> ";
        $text .= " 👈\n";
        $text .= "<b>Желаешь стать продавцом пиши лс:</b> @AuctionsIsrBot\n";
        $text .= "<b>Канал аукциона:</b> https://t.me/Auction_Israel\n";
        $text .= "<b>Основная группа:</b> https://t.me/+55aFKrgFFBUxN2Zi";
        
        return $text;
    }

    private function extractMessageId($result): ?int
    {
        if (!$result || !isset($result->message_id)) {
            return null;
        }
        
        $messageIds = $result->message_id;
        
        if (!is_array($messageIds) || empty($messageIds)) {
            return null;
        }
        
        return $messageIds[array_key_last($messageIds)];
    }

    /**
     * Ищет авто-форвард в discussion group через request_logs и возвращает thread_id
     */
    private function findAutoForwardThreadId(int $originalMessageId): ?int 
    {
        $discussionGroupId = config('services.auction.discussion_group_id');
        
        if (!$discussionGroupId) {
            $this->error("No discussionGroupId found");
            return null;
        }
        
        $this->line("🔍 Looking for auto-forward of message {$originalMessageId} in request logs...");
        
        // Пробуем найти авто-форвард несколько раз
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->line("🔄 Attempt {$attempt}/10...");
            
            // Получаем ID канала-источника из конфига
            $channelId = config('services.auction.channel_id');
            
            // Ищем в request_logs webhook запросы с автоматическими форвардами
            $requestLogs = \App\Models\RequestLog::where('url', 'LIKE', '%/api/telegram/assistant_webhook%')
                ->where('created_at', '>=', now()->subMinutes(5)) // последние 5 минут
                ->where('request_data', 'LIKE', '%"is_automatic_forward":true%') // только автоматические форварды
                ->where('request_data', 'LIKE', '%"forward_from_message_id":' . $originalMessageId . '%') // конкретное сообщение
                ->where('request_data', 'LIKE', '%"chat":{"id":' . $discussionGroupId . '%') // в нужную группу
                ->orderBy('created_at', 'desc')
                ->limit(1) // должна быть максимум 1 запись
                ->get();
                
            $this->line("📋 Checking {$requestLogs->count()} webhook requests...");
                
            foreach ($requestLogs as $log) {
                $requestData = json_decode($log->request_data, true);
                
                if (!$requestData || !isset($requestData['message'])) {
                    continue;
                }
                
                $message = $requestData['message'];
                
                // Детальная проверка автоматического форварда
                if (isset($message['is_automatic_forward']) && 
                    $message['is_automatic_forward'] === true &&
                    isset($message['forward_from_message_id']) &&
                    $message['forward_from_message_id'] == $originalMessageId) {
                    
                    // Проверяем что форвард идет из нужного канала в нужную группу
                    $fromChannelId = $message['forward_from_chat']['id'] ?? null;
                    $toGroupId = $message['chat']['id'] ?? null;
                    
                    // Точная проверка: из нашего канала в нашу discussion группу
                    if ($fromChannelId == $channelId && $toGroupId == $discussionGroupId) {
                        $threadId = $message['message_id'];
                        $this->line("✅ Perfect match! Thread ID: {$threadId}");
                        return $threadId;
                    } else {
                        $this->line("❌ Channel/Group mismatch, continuing search...");
                    }
                }
            }
            
            $this->line("⏳ Auto-forward not found yet, waiting 2 seconds...");
            sleep(2);
        }
        
        $this->error("❌ Auto-forward not found after 10 attempts");
        return null;
    }



    private function getCommentLink(string $channelId, int $messageId): string
    {
        $cleanId = ltrim($channelId, '@-');
        return "https://t.me/{$cleanId}/{$messageId}";
    }

    private function addLinkToText(string $text, string $link): string
    {
        return str_replace(
            '👉<b>Для участия в лоте переходи по ссылке:</b>  👈',
            "👉<b>Для участия в лоте переходи по ссылке:</b> {$link} 👈",
            $text
        );
    }

    private function sendWithRetry(callable $callback, int $maxRetries = 3)
    {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $result = $callback();
            
            // Check if result contains 502/503 error
            if (is_object($result) && isset($result->error)) {
                $error = $result->error;
                if (str_contains($error, '"error_code":502') || str_contains($error, '"error_code":503')) {
                    $this->line("⚠️  Attempt {$attempt}: Telegram server error (502/503), retrying in 2 seconds...");
                    if ($attempt < $maxRetries) {
                        sleep(2);
                        continue;
                    }
                }
            }
            
            return $result;
        }
        
        return $result;
    }
}
