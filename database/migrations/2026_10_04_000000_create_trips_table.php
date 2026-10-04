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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('destination')->nullable();
            $table->date('start_at');
            $table->date('end_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('planning');
            $table->string('thumbnail_path')->nullable();
            $table->foreignId('created_by_member_id')->constrained('members')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
