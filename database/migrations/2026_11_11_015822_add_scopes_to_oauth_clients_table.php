<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scopes a client may request. Null means every scope; a self-registered AI client is limited
 * to `mcp:use`, so connecting it never grants the REST API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->text('scopes')->nullable()->after('grant_types');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn('scopes');
        });
    }
};
