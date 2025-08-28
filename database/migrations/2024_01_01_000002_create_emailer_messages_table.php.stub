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
        Schema::create('emailer_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->string('name');
            $table->string('subject')->nullable();
            $table->longText('content')->nullable();
            $table->unsignedInteger('type_id');
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->unsignedBigInteger('template_id')->nullable()->index();
            $table->text('text')->nullable();
            $table->tinyInteger('status_id')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('type_id')->references('id')->on('emailer_message_types')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // Note: Foreign keys for team_id, category_id, template_id should be added
            // by the consuming application as they depend on the host application's models
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emailer_messages');
    }
};
