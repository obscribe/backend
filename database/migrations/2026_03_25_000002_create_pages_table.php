<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notebook_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->longText('encrypted_content')->nullable();
            $table->string('content_nonce')->nullable();
            $table->string('date_mode')->default('undated'); // dated, undated
            $table->date('page_date')->nullable();
            $table->string('template_type')->default('blank');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_favorited')->default(false);
            $table->unsignedInteger('word_count')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamp('trashed_at')->nullable();
            $table->timestamps();

            $table->index(['notebook_id', 'trashed_at']);
            $table->index('is_pinned');
            $table->index('is_favorited');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
