<?php

namespace App\Helpers;

use App\Models\Order;
use App\Models\ExportProject;

class OrderFormatterHelper
{
    /**
     * Format additional info text for order (contact details, locations etc.)
     *
     * @param Order $order
     * @return string
     */
    public static function formatAdditionalInfo(Order $order): string
    {
        $additionalInfoText = "";
        
        if ($order->user) {
            if ($order->user->name) {
                $additionalInfoText .= "👤: {$order->user->name}\n";
            }
            if ($order->user->phone) {
                $additionalInfoText .= "📞: {$order->user->phone}\n";
            }
            if ($order->user->email) {
                $additionalInfoText .= "📧: {$order->user->email}\n";
            }
        }
        
        if ($order->telegramUser) {
            if ($order->telegramUser->username) {
                $additionalInfoText .= "💬: @{$order->telegramUser->username}\n";
            }
            $additionalInfoText .= "🆔: {$order->telegramUser->tid}\n";
        }
        
        // Add location comments if available
        if (!empty($order->location_comm)) {
            $additionalInfoText .= "\n💭 <b>Location comment:</b>\n{$order->location_comm}\n";
        }
        
        // Add locations
        if ($order->locations && $order->locations->count() > 0) {
            $additionalInfoText .= "\n🌍 <b>Locations:</b>\n";
            foreach ($order->locations as $location) {
                $locationParts = [];
                if ($location->city) $locationParts[] = $location->city;
                if ($location->region) $locationParts[] = $location->region;
                if ($location->country) $locationParts[] = $location->country;
                
                $locationText = implode(', ', $locationParts);
                $additionalInfoText .= "📍 {$locationText}\n";
            }
        }
        
        return trim($additionalInfoText);
    }

    /**
     * Format order data for Telegram sending - returns announcement text, media files and entities.
     *
     * @param Order $order
     * @param string|null $template Custom template or null for default
     * @param object|null $command Command instance for logging
     * @return array
     */
    public static function formatForTelegram(Order $order, ?string $template = null, $command = null): array
    {        
        // Ensure all media files are uploaded to Telegram
        $order->ensureTelegramFilesUploaded();
        
        // Second message: announcement content (text + media)
        if ($template) {
            $announcementText = self::processTemplate($template, $order, $command);
        } else {
            $announcementText = $order->text_en ?: $order->text;
            if ($command && method_exists($command, 'line')) {
                $message = "No template - using " . ($order->text_en ? 'text_en' : 'text') . " for order " . $order->id;
                $command->line($message);
            }
        }
        
        // Get media files for this order
        $mediaFiles = $order->media()->get();
        
        // Filter files that have telegram_file_id
        $validMediaFiles = $mediaFiles->filter(function($file) {
            return !empty($file->telegram_file_id);
        });
        
        // Get entities from order if available
        $entities = $order->telegram_entities;
        
        return [
            'announcementText' => $announcementText,
            'mediaFiles' => $validMediaFiles,
            'entities' => $entities
        ];
    }

    /**
     * Process template with order data using Blade.
     *
     * @param string $template
     * @param Order $order
     * @param object|null $command Command instance for logging
     * @return string
     */
    private static function processTemplate(string $template, Order $order, $command = null): string
    {
        try {
            // Prepare template variables
            $username = $order->telegramUser && $order->telegramUser->username 
                ? '@' . $order->telegramUser->username 
                : '';
            
            // Choose text for template: text_en if exists, otherwise original text
            $textForTemplate = $order->text_en ?: $order->text;
            
            
            // Use Blade to render template with order data
            $rendered = \Illuminate\Support\Facades\Blade::render($template, [
                'order' => $order,
                'username' => $username,
                'text' => $textForTemplate
            ], deleteCachedView: true);
            
            return trim($rendered);
        } catch (\Exception $e) {
            // Fallback to simple replacement if Blade fails
            \Illuminate\Support\Facades\Log::error('Blade template processing failed: ' . $e->getMessage());
            
            $text = $template;
            
            // Prepare username for fallback
            $username = $order->telegramUser && $order->telegramUser->username 
                ? '@' . $order->telegramUser->username 
                : '';
            
            // Simple variable replacement as fallback
            $text = str_replace('{{ $username }}', $username, $text);
            $text = str_replace('{{$username}}', $username, $text);
            
            foreach ($order->getAttributes() as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $text = str_replace("{{ \$order->{$key} }}", $value, $text);
                    $text = str_replace("{{\$order->{$key}}}", $value, $text);
                }
            }
            
            return trim($text);
        }
    }
}
