<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranslationCache extends Model
{
    protected $table = 'translation_cache';
    
    protected $fillable = [
        'source_text',
        'translated_text',
        'source_language',
        'target_language',
        'requests_count'
    ];

    protected $casts = [
        'requests_count' => 'integer',
    ];

    /**
     * Increment requests count
     */
    public function incrementRequestsCount()
    {
        $this->increment('requests_count');
    }
}
