<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sfmanager_servers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->unique();
            $table->text('token')->nullable();           // encrypted Satisfactory API token
            $table->string('privilege', 32)->nullable(); // Administrator, InitialAdmin, Client ...
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sfmanager_servers');
    }
};
