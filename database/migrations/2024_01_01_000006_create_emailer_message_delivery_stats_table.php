<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('emailer_message_delivery_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id')->unique();
            $table->unsignedInteger('total_contacts')->default(0);
            $table->unsignedInteger('subscribers')->nullable(); // Legacy field
            $table->unsignedInteger('remaining')->nullable();
            $table->unsignedInteger('pending_deliveries')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('opened')->default(0);
            $table->unsignedInteger('unsubscribed')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('unique_opens')->default(0);
            $table->decimal('ratio', 5, 2)->nullable(); // Legacy field for open rate
            $table->decimal('success_rate', 5, 2)->default(0);
            $table->decimal('open_rate', 5, 2)->default(0);
            $table->decimal('click_rate', 5, 2)->default(0);
            $table->decimal('bounce_rate', 5, 2)->default(0);
            $table->timestamps();

            $table->foreign('message_id')
                ->references('id')->on('emailer_messages')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // Indexes for performance
            $table->index(['success_rate']);
            $table->index(['open_rate']);
            $table->index(['click_rate']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('emailer_message_delivery_stats');
    }
};
