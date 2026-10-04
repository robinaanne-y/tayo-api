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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_at')->nullable();
            $table->foreignId('created_by_member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('assigned_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('recurring_rule_id')->nullable()->constrained('recurring_rules')->nullOnDelete();
            $table->timestamps();

            // Idempotency safety net for the recurring-occurrence generation
            // job -- a given rule can never have two occurrences on the same
            // due date. A null recurring_rule_id (one-off tasks) doesn't
            // collide with itself, since every tested DB driver here
            // (sqlite/pgsql) treats NULLs as distinct in a unique index.
            $table->unique(['recurring_rule_id', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
