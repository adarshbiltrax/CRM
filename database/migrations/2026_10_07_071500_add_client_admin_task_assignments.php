<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('orgnization_id')
                ->nullable()
                ->after('id')
                ->constrained('orgnizations')
                ->restrictOnDelete();
            $table->foreignId('client_admin_id')
                ->nullable()
                ->after('orgnization_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->unsignedBigInteger('template_id')->nullable()->after('client_admin_id');
            $table->foreignId('executive_id')->nullable()->change();
            $table->unique(['orgnization_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropUnique(['orgnization_id', 'template_id']);
            $table->dropConstrainedForeignId('client_admin_id');
            $table->dropConstrainedForeignId('orgnization_id');
            $table->dropColumn('template_id');
            $table->foreignId('executive_id')->nullable(false)->change();
        });
    }
};
