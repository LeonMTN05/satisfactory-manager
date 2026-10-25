<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sfmanager_servers', function (Blueprint $table) {
            $table->json('settings')->nullable();                  // automation settings
            $table->timestamp('last_backup_at')->nullable();
            $table->unsignedSmallInteger('watchdog_failures')->default(0);
            $table->timestamp('watchdog_action_at')->nullable();
            $table->string('last_restart_slot', 32)->nullable();  // "2026-10-25 04:00", prevents double restarts
        });

        Schema::create('sfmanager_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->boolean('online');
            $table->unsignedSmallInteger('players')->nullable();
            $table->unsignedSmallInteger('player_limit')->nullable();
            $table->float('tick_rate')->nullable();
            $table->unsignedTinyInteger('tech_tier')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['server_id', 'created_at']);
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });

        Schema::create('sfmanager_backups', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->string('file');                  // path inside the server, e.g. .sfmanager-backups/x.tar.gz
            $table->unsignedBigInteger('size')->default(0);
            $table->string('reason', 32);            // manual, scheduled, before-restart, before-update, before-load
            $table->string('session')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['server_id', 'created_at']);
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sfmanager_backups');
        Schema::dropIfExists('sfmanager_metrics');
        Schema::table('sfmanager_servers', function (Blueprint $table) {
            $table->dropColumn(['settings', 'last_backup_at', 'watchdog_failures', 'watchdog_action_at', 'last_restart_slot']);
        });
    }
};
