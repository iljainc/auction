<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_temporary_telegram_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uid');
            $table->string('file_type'); // photo, video, document, audio
            $table->string('telegram_file_id');
            $table->enum('status', ['new', 'used', 'expired'])->default('new');
            $table->timestamps();
            
            $table->index(['uid', 'status']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_temporary_telegram_files');
    }
};
