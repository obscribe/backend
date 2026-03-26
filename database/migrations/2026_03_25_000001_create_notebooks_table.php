<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notebooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->string('type')->default('permanent'); // permanent, temporary
            $table->timestamp('destruction_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamp('trashed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'trashed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notebooks');
    }
};
