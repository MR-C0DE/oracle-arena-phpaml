<?php

declare(strict_types=1);

namespace App\Controllers;

use AML\Data\Connection;
use PHPAML\Http\Request;
use PHPAML\Http\Response;

final class GameController
{
    private const CHOICES = ['rock', 'paper', 'scissors'];
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(Request $request): Response
    {
        $playerName = trim((string) $request->input('player_name', ''));
        if (mb_strlen($playerName) < 2 || mb_strlen($playerName) > 24
            || preg_match('/^[\p{L}\p{N} _.-]+$/u', $playerName) !== 1) {
            return Response::json(['error' => 'Le pseudonyme doit contenir entre 2 et 24 caractères.'], 422);
        }
        $publicId = bin2hex(random_bytes(16));
        $this->connection->execute(
            'INSERT INTO games (public_id, player_name, player_score, oracle_score, draws, rounds_played, status) VALUES (:public_id, :player_name, 0, 0, 0, 0, :status)',
            ['public_id' => $publicId, 'player_name' => $playerName, 'status' => 'active'],
        );

        return Response::json(['id' => $publicId, 'player_name' => $playerName, 'status' => 'active'], 201);
    }

    public function storeRound(Request $request): Response
    {
        $publicId = trim((string) $request->input('game_id', ''));
        $playerChoice = (string) $request->input('player_choice', '');
        $oracleChoice = (string) $request->input('oracle_choice', '');
        $roundNumber = filter_var($request->input('round_number'), FILTER_VALIDATE_INT);

        if ($publicId === '' || !in_array($playerChoice, self::CHOICES, true)
            || !in_array($oracleChoice, self::CHOICES, true)
            || !is_int($roundNumber) || $roundNumber < 1 || $roundNumber > 5) {
            return Response::json(['error' => 'Données de manche invalides.'], 422);
        }
        $outcome = $this->outcome($playerChoice, $oracleChoice);

        $game = $this->connection->execute(
            'SELECT id, status FROM games WHERE public_id = :public_id LIMIT 1',
            ['public_id' => $publicId],
        )->fetch();
        if (!is_array($game)) {
            return Response::json(['error' => 'Partie introuvable.'], 404);
        }
        if (($game['status'] ?? '') !== 'active') {
            return Response::json(['error' => 'Cette partie est déjà terminée.'], 409);
        }

        try {
            $result = $this->connection->transaction(function (Connection $connection) use ($game, $roundNumber, $playerChoice, $oracleChoice, $outcome): array {
                $gameId = (int) $game['id'];
                $connection->execute(
                    'INSERT INTO rounds (game_id, round_number, player_choice, oracle_choice, outcome) VALUES (:game_id, :round_number, :player_choice, :oracle_choice, :outcome)',
                    ['game_id' => $gameId, 'round_number' => $roundNumber, 'player_choice' => $playerChoice, 'oracle_choice' => $oracleChoice, 'outcome' => $outcome],
                );
                $playerDelta = $outcome === 'win' ? 1 : 0;
                $oracleDelta = $outcome === 'loss' ? 1 : 0;
                $drawDelta = $outcome === 'draw' ? 1 : 0;
                $status = $roundNumber === 5 ? 'finished' : 'active';
                $connection->execute(
                    'UPDATE games SET player_score = player_score + :player_delta, oracle_score = oracle_score + :oracle_delta, draws = draws + :draw_delta, rounds_played = :rounds_played, status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                    ['player_delta' => $playerDelta, 'oracle_delta' => $oracleDelta, 'draw_delta' => $drawDelta, 'rounds_played' => $roundNumber, 'status' => $status, 'id' => $gameId],
                );
                $updated = $connection->execute('SELECT player_score, oracle_score, draws, rounds_played, status FROM games WHERE id = :id', ['id' => $gameId])->fetch();
                return is_array($updated) ? $updated : [];
            });
        } catch (\PDOException $error) {
            if (str_contains(strtolower($error->getMessage()), 'unique')) {
                return Response::json(['error' => 'Cette manche a déjà été enregistrée.'], 409);
            }
            throw $error;
        }

        return Response::json(['game_id' => $publicId, 'game' => $result], 201);
    }

    public function leaderboard(Request $request): Response
    {
        $rows = $this->connection->execute(
            "SELECT public_id, player_name, player_score, oracle_score, draws, rounds_played, created_at FROM games WHERE status = 'finished' ORDER BY player_score DESC, oracle_score ASC, created_at ASC LIMIT 10",
        )->fetchAll();

        return Response::json(['data' => $rows]);
    }

    private function outcome(string $playerChoice, string $oracleChoice): string
    {
        if ($playerChoice === $oracleChoice) {
            return 'draw';
        }

        return ($playerChoice === 'rock' && $oracleChoice === 'scissors')
            || ($playerChoice === 'paper' && $oracleChoice === 'rock')
            || ($playerChoice === 'scissors' && $oracleChoice === 'paper')
                ? 'win'
                : 'loss';
    }
}
