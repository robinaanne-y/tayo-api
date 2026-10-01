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
        Schema::create('meal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_member_id')->constrained('members')->cascadeOnDelete();
            $table->date('requested_date');
            $table->string('requested_slot');
            $table->string('title');
            $table->string('status')->default('pending');
            $table->foreignId('responded_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->dateTime('responded_at')->nullable();
            $table->text('response_note')->nullable();
            $table->dateTime('requester_acknowledged_at')->nullable();
            $table->foreignId('meal_plan_item_id')->nullable()->constrained('meal_plan_items')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_requests');
    }
};
