<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    /**
     * Установите атрибут 'country' в верхний регистр перед сохранением.
     *
     * @param  string  $value
     * @return void
     */
    public function setCountryAttribute($value)
    {
        $this->attributes['country'] = strtoupper($value);
    }

    public $timestamps = false; // Отключаем автоматическое управление временными метками

    protected $fillable = ['city', 'district', 'region', 'country'];



}
