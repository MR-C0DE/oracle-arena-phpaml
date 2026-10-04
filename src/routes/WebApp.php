<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\HomeController;
use App\Controllers\GameController;
use PHPAML\Routing\Route;

final class WebApp extends Route
{
    protected function routes(): void
    {
        $this->get('/api/health', [HomeController::class, 'index']);
        $this->post('/api/games', [GameController::class, 'create']);
        $this->post('/api/rounds', [GameController::class, 'storeRound']);
        $this->get('/api/leaderboard', [GameController::class, 'leaderboard']);
    }
}
