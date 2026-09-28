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
        Schema::create('permission_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_member_id')->constrained('members')->cascadeOnDelete();
            $table->string('type')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('requested_start_at')->nullable();
            $table->dateTime('requested_end_at')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('responded_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->dateTime('responded_at')->nullable();
            $table->text('response_note')->nullable();
            $table->foreignId('promoted_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_requests');
    }
};
