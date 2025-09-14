<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Location;
use App\Services\LocationService;

class TestOpenAITemplate extends Command
{
    protected $signature = 'test {text?}';
    protected $description = 'Test location finding functionality';

    public function handle()
    {
        $text = $this->argument('text') ?: 'Адар Хайфа';
        
        $this->line("Testing LocationService::findLocationsInText() with text: '{$text}'");
        
        try {
            // Test the location finding method
            $locationService = app(LocationService::class);
            $locationIds = $locationService->findLocationsInText($text);
            
            $this->line("=== RESULT ===");
            $this->line("Found location IDs: " . json_encode($locationIds));
            
            if (!empty($locationIds)) {
                $locations = Location::whereIn('id', $locationIds)->get();
                $this->line("\n=== FOUND LOCATIONS ===");
                foreach ($locations as $location) {
                    $this->line("ID: {$location->id}");
                    $this->line("City: " . ($location->city ?: 'null'));
                    $this->line("Region: " . ($location->region ?: 'null'));
                    $this->line("Country: " . ($location->country ?: 'null'));
                    $this->line("---");
                }
            } else {
                $this->line("No locations found");
            }
            
        } catch (\Exception $e) {
            $this->line("Exception: " . $e->getMessage());
            $this->line("Trace: " . $e->getTraceAsString());
        }
    }
}
