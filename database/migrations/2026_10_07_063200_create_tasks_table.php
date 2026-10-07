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
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('executive_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
