<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('emailer_message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('recipient_email')->nullable(); // Fallback for when contact is deleted
            $table->string('recipient_name')->nullable(); // Fallback for when contact is deleted
            $table->unsignedSmallInteger('smtp_id')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('removed_at')->nullable();
            $table->tinyInteger('status_id')->default(0); // 0=pending, 1=sent, 2=delivered, 3=opened, 4=clicked, 5=error

            // Email provider tracking
            $table->string('email_provider', 50)->nullable()->index(); // mailbaby, sendgrid, mailgun, smtp, etc.
            $table->string('provider_message_id')->nullable()->index(); // Provider-specific message ID
            $table->string('delivery_status', 50)->nullable(); // delivered, bounced, failed, etc.
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->json('provider_data')->nullable(); // Provider-specific webhook data

            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('emailer_messages')->onDelete('cascade');

            // Note: Foreign keys for team_id and contact_id should be added
            // by the consuming application as they depend on the host application's models

            // Indexes for performance
            $table->index(['team_id', 'message_id']);
            $table->index(['status_id', 'sent_at']);
            $table->index(['opened_at']);
            $table->index(['clicked_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('emailer_message_deliveries');
    }
};
