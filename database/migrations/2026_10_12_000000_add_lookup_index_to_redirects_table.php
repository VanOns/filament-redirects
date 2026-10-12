<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Every request looks up a static redirect by its path. A project may have added this index itself.
        if (Schema::hasIndex('redirects', ['type', 'from'])) {
            return;
        }

        Schema::table('redirects', function (Blueprint $table) {
            $table->index(['type', 'from']);
        });
    }

    public function down(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->dropIndex(['type', 'from']);
        });
    }
};
