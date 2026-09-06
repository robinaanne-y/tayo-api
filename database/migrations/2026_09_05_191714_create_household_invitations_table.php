<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')
                ->constrained('households')
                ->cascadeOnDelete();
            $table->foreignId('created_by_member_id')
                ->constrained('members')
                ->cascadeOnDelete();
            $table->enum('role', ['owner', 'adult', 'minor', 'child']);
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by_member_id')
                ->nullable()
                ->constrained('members')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('household_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_invitations');
    }
};
