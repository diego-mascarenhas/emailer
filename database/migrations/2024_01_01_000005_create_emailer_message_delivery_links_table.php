<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('emailer_message_delivery_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_delivery_id');
            $table->text('link'); // Tracked/shortened link
            $table->text('original_url')->nullable(); // Original destination URL
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();

            $table->foreign('message_delivery_id')
                ->references('id')
                ->on('emailer_message_deliveries')
                ->onDelete('cascade');

            // Indexes for performance
            $table->index(['message_delivery_id']);
            $table->index(['click_count']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('emailer_message_delivery_links');
    }
};
