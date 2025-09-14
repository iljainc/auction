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
        Schema::table('orders', function (Blueprint $table) {
            $table->bigInteger('auction_message_id')->nullable()->after('locations');
            $table->bigInteger('auction_comment_message_id')->nullable()->after('auction_message_id');
            $table->string('auction_status')->nullable()->after('auction_comment_message_id');
            $table->timestamp('auction_posted_at')->nullable()->after('auction_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['auction_message_id', 'auction_comment_message_id', 'auction_status', 'auction_posted_at']);
        });
    }
};
