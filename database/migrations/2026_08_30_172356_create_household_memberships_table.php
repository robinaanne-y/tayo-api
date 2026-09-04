<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')
                ->constrained('households')
                ->cascadeOnDelete();
            $table->foreignId('member_id')
                ->constrained('members')
                ->cascadeOnDelete();
            $table->enum('role', ['owner', 'adult', 'minor', 'child']);
            $table->string('status')->default('active');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['household_id', 'member_id']);
            $table->index('household_id');
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_memberships');
    }
};
