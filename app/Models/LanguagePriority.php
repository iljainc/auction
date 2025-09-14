<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LanguagePriority extends Model
{
    // Убедитесь, что указано правильное имя таблицы
    protected $table = 'language_priorities';

    // Укажите, какие поля могут быть заполнены
    protected $fillable = ['uid', 'lang', 'priority'];

    // Только created_at, без updated_at
    const UPDATED_AT = null;

    // Связь с моделью User
    public function user()
    {
        return $this->belongsTo(User::class, 'uid');
    }
}
