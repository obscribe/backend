<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('salt')->nullable()->after('vault_nonce');
            $table->text('recovery_encrypted_vault_key')->nullable()->after('salt');
            $table->string('recovery_vault_nonce')->nullable()->after('recovery_encrypted_vault_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['salt', 'recovery_encrypted_vault_key', 'recovery_vault_nonce']);
        });
    }
};
