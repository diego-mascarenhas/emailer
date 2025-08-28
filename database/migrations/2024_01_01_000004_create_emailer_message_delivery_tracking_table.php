<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emailer_message_delivery_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_delivery_id')->constrained('emailer_message_deliveries')->onDelete('cascade');
            $table->string('event', 50); // opened, clicked, delivered, bounced, error, etc.
            $table->timestamp('tracked_at')->nullable();
            $table->string('ip_address', 45)->nullable(); // Supports IPv6
            $table->text('user_agent')->nullable(); // Can be long
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->json('metadata')->nullable(); // Additional tracking data
            $table->timestamps();

            // Indexes for performance
            $table->index(['message_delivery_id', 'event']);
            $table->index(['event', 'tracked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emailer_message_delivery_tracking');
    }
};
