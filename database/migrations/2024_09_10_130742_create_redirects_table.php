<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from');
            $table->string('to')->nullable();
            $table->string('type');
            $table->integer('priority')->nullable();
            $table->integer('status_code')->default(301);
            $table->boolean('include_headers')->default(true);
            $table->boolean('include_query')->default(true);
            $table->integer('hits')->default(0);
            $table->timestamp('last_hit')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
