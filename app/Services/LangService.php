<?php
namespace App\Services;

use App\Models\TranslationCache;
use Idpromogroup\LaravelOpenAIAssistants\Facades\OpenAIAssistants;

/*
 * VER 03.11.2024
 *
 */

class LangService
{
    // Маппинг устаревших кодов языков на актуальные
    protected static $languageMap = [
        'iw' => 'he', // иврит
        'in' => 'id', // индонезийский
        'ji' => 'yi', // идиш
    ];

    public static function translate($text, $toLang) {
        // Проверяем кэш переводов
        $cachedTranslation = TranslationCache::where('source_text', $text)
            ->where('target_language', $toLang)
            ->first();

        if ($cachedTranslation) {
            $cachedTranslation->incrementRequestsCount();
            return $cachedTranslation->translated_text;
        }
        
        $data = '{"text":"'.$text.'","lang":"'.$toLang.'"}';

        $newText = OpenAIAssistants::assistantNoThread(config('app.openai_translate'), $data, 'LangService:t', 0, 0);

        // Иногда сбоит
        if (!empty($text) && empty($newText)) {
            $newText = OpenAIAssistants::assistantNoThread(config('app.openai_translate'), $data, 'LangService:t', 0, 0);
        };

        if (!empty($text) && !empty($newText)) {
            // Сохраняем новый перевод в базу данных
            TranslationCache::create([
                'source_text' => $text,
                'translated_text' => $newText,
                'source_language' => 'xx', // если исходный язык неизвестен
                'target_language' => $toLang
            ]);
        };

        return $newText;
    }

    public static function findLanguageCode($text, $userId = 0) {
        // Подготавливаем контекст для ассистента
        $context = mb_substr($text, 0, 500);
                
        // Передаем ID ассистента из конфига
        $code = OpenAIAssistants::assistantNoThread(config('app.openai_get_lang_code'), 
            $context, 'LangService:f', $userId, 0);

        $codeLower = mb_strtolower($code);

        return $codeLower;
    }


}
