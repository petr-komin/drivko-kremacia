<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Číslo případu v systému kremace – klíč pro budoucí propojení s jejich IS
            $table->string('case_number', 64)->nullable()->index();
            $table->string('pet_name');
            $table->foreignId('wood_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('urn_size_id')->constrained()->restrictOnDelete();
            $table->text('note')->nullable();
            $table->string('status', 16)->default('new')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // Kdy zakázka prošla jednotlivými stavy – evidence a měsíční přehled
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Poslední zpráva, fotka nebo změna stavu – řazení přehledu
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('order_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // null u systémových záznamů (změna stavu)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('order_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // paw = podklad otisku od kremace, finished = hotový kus, message = příloha zprávy
            $table->string('kind', 16)->index();
            // Cesta na privátním disku – fotky se nikdy neservírují přímo
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 64);
            $table->unsignedBigInteger('size');
            $table->boolean('has_preview')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_photos');
        Schema::dropIfExists('order_messages');
        Schema::dropIfExists('orders');
    }
};
