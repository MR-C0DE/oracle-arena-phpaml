<?php

declare(strict_types=1);

use AML\Data\Connection;
use AML\Data\Migrations\Migration;
use AML\Data\Schema\{Schema, Table};

return new class extends Migration {
    public function up(Connection $connection): void
    {
        $schema = new Schema($connection);
        $schema->create('games', function (Table $table): void {
            $table->id();
            $table->string('public_id', 64)->unique();
            $table->integer('player_score')->default(0);
            $table->integer('oracle_score')->default(0);
            $table->integer('draws')->default(0);
            $table->integer('rounds_played')->default(0);
            $table->string('status', 24)->default('active');
            $table->timestamps();
        });
        $schema->create('rounds', function (Table $table): void {
            $table->id();
            $table->foreignId('game_id')->references('id', 'games', 'CASCADE');
            $table->integer('round_number');
            $table->string('player_choice', 16);
            $table->string('oracle_choice', 16);
            $table->string('outcome', 16);
            $table->timestamps();
            $table->uniqueIndex(['game_id', 'round_number']);
        });
    }

    public function down(Connection $connection): void
    {
        $schema = new Schema($connection);
        $schema->dropIfExists('rounds');
        $schema->dropIfExists('games');
    }
};
