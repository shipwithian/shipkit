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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_protected')->default(false);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_protected')->default(false);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->boolean('is_protected')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_protected');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_protected');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('is_protected');
        });
    }
};
