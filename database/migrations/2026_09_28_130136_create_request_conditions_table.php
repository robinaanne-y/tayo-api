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
        Schema::create('request_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_member_id')->constrained('members')->cascadeOnDelete();
            $table->text('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_conditions');
    }
};
