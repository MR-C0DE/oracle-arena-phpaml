<?php

declare(strict_types=1);

use AML\Data\Connection;
use AML\Data\Migrations\Migration;
use AML\Data\Schema\{AlterTable, Schema};

return new class extends Migration {
    public function up(Connection $connection): void
    {
        (new Schema($connection))->table('games', function (AlterTable $table): void {
            $table->string('player_name', 24)->default('Anonyme');
        });
    }

    public function down(Connection $connection): void
    {
        (new Schema($connection))->table('games', function (AlterTable $table): void {
            $table->dropColumn('player_name');
        });
    }
};
