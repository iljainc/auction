<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Facades\Log;

class LocationService
{
    /**
     * Найти или создать локацию
     * 
     * @param string|null $city
     * @param string|null $region
     * @param string $country
     * @return Location
     */
    public function findOrCreateLocation(?string $city, ?string $region, string $country): Location
    {
        $query = Location::query();

        // Normalize region - treat "0", "null", empty string as NULL
        if ($region === '0' || $region === 'null' || $region === '' || empty($region)) {
            $region = null;
        }

        if (!empty($city))  $query->where('city', $city);
        else                $query->whereNull('city');

        if (!empty($region))    $query->where('region', $region);

        if (!empty($country)) { // Указана страна
            $query->where('country', $country);
        };

        // Попытка найти существующую локацию
        $location = $query->first();

        if (empty($location)) {
            // Создаем новую запись, если локация не найдена
            $location = Location::create([
                'city' => $city ?? null,
                'region' => $region ?? null,
                'country' => $country
            ]);
        }

        return $location;
    }

    /**
     * Найти локации в тексте через ИИ
     * 
     * @param string $text Текст для анализа
     * @return array Массив ID найденных локаций
     */
    public function findLocationsInText(string $text): array
    {
        if (empty($text)) {
            return [];
        }

        try {
            // Используем новый ИИ с шаблоном "Find location"
            $service = new \Idpromogroup\LaravelOpenaiResponses\Services\OpenAIService('location_search', $text);
            $result = $service
                ->useTemplate('Find locations')  // Используем шаблон по названию
                ->execute();
                
            if (!$result->success) {
                Log::error("Location AI processing failed: " . $result->error);
                return [];
            }
            
            $response = $result->getAssistantMessage();
            
            // Декодируем JSON ответ
            $decoded = json_decode($response, true);
            $locations = is_array($decoded['locations'] ?? null) ? $decoded['locations'] : [];

            $locationIds = [];
            foreach ($locations as $loc) {
                $country = $loc['country'] ?? null;
                $city    = $loc['city'] ?? null;
                $region  = $loc['region'] ?? null;

                if ($country === 'WW') {
                    $location = $this->findOrCreateLocation(null, null, 'WW');
                    $locationIds[] = $location->id;
                } else if (!empty($country)) {
                    $location = $this->findOrCreateLocation($city ?: null, $region ?: null, strtoupper($country));
                    $locationIds[] = $location->id;
                }
            }

            return array_values(array_unique(array_filter($locationIds)));
            
        } catch (\Exception $e) {
            Log::error("Location processing failed: " . $e->getMessage());
            return [];
        }
    }

}
