<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Language priorities table
        Schema::create('language_priorities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uid');
            $table->char('lang', 2);
            $table->timestamp('created_at')->useCurrent();
            $table->tinyInteger('priority');
            
            $table->foreign('uid')->references('id')->on('users')->onDelete('cascade');
        });

        // Locations table
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->char('country', 2)->nullable();
        });

        // Log entries table
        Schema::create('log_entries', function (Blueprint $table) {
            $table->id();
            $table->string('level');
            $table->text('message');
            $table->text('context')->nullable();
            $table->timestamps();
        });

        // Orders table
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->text('text');
            $table->text('text_en')->nullable();
            $table->boolean('text_admin_check')->default(false);
            $table->timestamp('block_timestamp')->nullable();
            $table->char('lang', 2)->nullable();
            $table->unsignedBigInteger('uid');
            $table->smallInteger('status')->default(0);
            $table->enum('order_type', ['internal', 'external'])->default('internal');
            $table->enum('source', ['telegram', 'api', 'web'])->default('telegram');
            $table->timestamps();
            $table->timestamp('closed_at')->nullable();
        });

        // Order location pivot table
        Schema::create('order_location', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('location_id');
            
            $table->unique(['order_id', 'location_id']);
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
        });

        // Request logs table
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method');
            $table->text('url');
            $table->string('ip_address')->nullable();
            $table->string('real_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('status_code')->nullable();
            $table->double('execution_time')->nullable();
            $table->integer('request_size')->nullable();
            $table->integer('response_size')->nullable();
            $table->longText('request_data')->nullable();
            $table->longText('response_data')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        // Telegram logs table
        Schema::create('telegram_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tid')->nullable();
            $table->json('message_id')->nullable();
            $table->string('type')->nullable();
            $table->text('text');
            $table->text('error')->nullable();
            $table->text('response')->nullable();
            $table->json('comm')->nullable();
            $table->timestamps();
            $table->enum('direction', ['sent', 'received'])->default('received');
        });

        // Telegram message queue table
        Schema::create('telegram_message_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tid');
            $table->unsignedBigInteger('obj_id')->nullable();
            $table->string('type');
            $table->text('json')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->dateTime('sent_at')->nullable();
            
            $table->unique(['tid', 'type', 'obj_id'], 'unique_notification');
        });

        // Telegram users table
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uid')->nullable()->unique();
            $table->unsignedBigInteger('tid')->nullable()->unique();
            $table->string('state', 20)->nullable();
            $table->string('name');
            $table->string('username')->nullable();
            $table->timestamps();
            $table->text('json')->nullable();
            $table->enum('activity_status', ['active', 'blocked'])->default('active');
        });

        // Order media table
        Schema::create('order_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->enum('file_type', ['photo', 'video', 'document', 'audio']);
            $table->bigInteger('file_size')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->string('mime_type')->nullable();
            $table->string('telegram_file_id')->nullable();
            $table->string('telegram_file_unique_id')->nullable();
            $table->timestamps();
            
            $table->index(['order_id', 'file_type']);
            $table->index('telegram_file_id');
            $table->index('file_hash');
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_media');
        Schema::dropIfExists('telegram_users');
        Schema::dropIfExists('telegram_message_queue');
        Schema::dropIfExists('telegram_logs');
        Schema::dropIfExists('request_logs');
        Schema::dropIfExists('order_location');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('log_entries');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('language_priorities');
    }
};
