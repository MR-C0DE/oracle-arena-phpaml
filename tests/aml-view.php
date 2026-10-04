<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/runtime/autoload.php';

$application = new \AML\View\FileApplication($root . '/src/views');
$landing = $application->mount('/');
if (!$landing instanceof \AML\View\PageResult) {
    throw new RuntimeException('Oracle Arena landing page did not return a page result.');
}

$landingHtml = $landing->rootHtml();
foreach (['ORACLE ARENA', 'Battez l’Oracle', 'JOUER MAINTENANT', '/game'] as $expected) {
    if (!str_contains($landingHtml, $expected)) {
        throw new RuntimeException("Oracle Arena landing page is missing: {$expected}");
    }
}

$game = $application->mount('/game');
if (!$game instanceof \AML\View\PageResult) {
    throw new RuntimeException('Oracle Arena game did not return a page result.');
}

$gameHtml = $game->rootHtml();
foreach (['PIERRE', 'PAPIER', 'CISEAUX', 'Conçu avec PHPAML View'] as $expected) {
    if (!str_contains($gameHtml, $expected)) {
        throw new RuntimeException("Oracle Arena game is missing: {$expected}");
    }
}
foreach (['playerScore', 'oracleScore', 'round', 'finished'] as $state) {
    if (!str_contains($gameHtml, $state)) {
        throw new RuntimeException("Oracle Arena reactive state is missing: {$state}");
    }
}

$rankingPage = $application->mount('/leaderboard');
if (!$rankingPage instanceof \AML\View\PageResult) {
    throw new RuntimeException('Oracle Arena leaderboard did not return a page result.');
}
$rankingHtml = $rankingPage->rootHtml();
foreach (['Tableau des duels', 'JOUEUR', 'player_name', 'api\\/leaderboard', 'rankings', '/game'] as $expected) {
    if (!str_contains($rankingHtml, $expected)) {
        throw new RuntimeException("Oracle Arena leaderboard is missing: {$expected}");
    }
}

$connection = \AML\Data\Connection::sqlite();
$connection->execute('CREATE TABLE games (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) NOT NULL UNIQUE, player_name VARCHAR(24) NOT NULL, player_score INTEGER NOT NULL DEFAULT 0, oracle_score INTEGER NOT NULL DEFAULT 0, draws INTEGER NOT NULL DEFAULT 0, rounds_played INTEGER NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT \'active\', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
$connection->execute('CREATE TABLE rounds (id INTEGER PRIMARY KEY AUTOINCREMENT, game_id INTEGER NOT NULL, round_number INTEGER NOT NULL, player_choice VARCHAR(20) NOT NULL, oracle_choice VARCHAR(20) NOT NULL, outcome VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE, UNIQUE(game_id, round_number))');
$controller = new \App\Controllers\GameController($connection);
$invalidName = $controller->create(new \PHPAML\Http\Request('POST', '/api/games', body: ['player_name' => '<script>']));
if ($invalidName->status() !== 422) {
    throw new RuntimeException('Oracle Arena API accepted an unsafe player name.');
}
$created = $controller->create(new \PHPAML\Http\Request('POST', '/api/games', body: ['player_name' => 'André']));
if ($created->status() !== 201) {
    throw new RuntimeException('Oracle Arena API did not create a game.');
}
$createdBody = json_decode($created->content(), true, 512, JSON_THROW_ON_ERROR);
$gameId = (string) ($createdBody['id'] ?? '');
if ($gameId === '') {
    throw new RuntimeException('Oracle Arena API did not return a public game identifier.');
}

$missingGame = $controller->storeRound(new \PHPAML\Http\Request('POST', '/api/rounds', body: [
    'game_id' => str_repeat('0', 32),
    'round_number' => 1,
    'player_choice' => 'rock',
    'oracle_choice' => 'paper',
]));
if ($missingGame->status() !== 404) {
    throw new RuntimeException('Oracle Arena API did not reject an unknown game.');
}
$invalidRound = $controller->storeRound(new \PHPAML\Http\Request('POST', '/api/rounds', body: [
    'game_id' => $gameId,
    'round_number' => 6,
    'player_choice' => 'rock',
    'oracle_choice' => 'paper',
]));
if ($invalidRound->status() !== 422) {
    throw new RuntimeException('Oracle Arena API accepted an invalid round number.');
}

$rounds = [
    ['rock', 'scissors', 'win'],
    ['paper', 'scissors', 'loss'],
    ['scissors', 'scissors', 'draw'],
    ['paper', 'rock', 'win'],
    ['rock', 'paper', 'loss'],
];
foreach ($rounds as $index => [$player, $oracle, $expectedOutcome]) {
    $response = $controller->storeRound(new \PHPAML\Http\Request('POST', '/api/rounds', body: [
        'game_id' => $gameId,
        'round_number' => $index + 1,
        'player_choice' => $player,
        'oracle_choice' => $oracle,
        'outcome' => 'win', // The server must ignore an outcome supplied by the client.
    ]));
    if ($response->status() !== 201) {
        throw new RuntimeException('Oracle Arena API rejected a valid round.');
    }
    $storedOutcome = $connection->execute(
        'SELECT outcome FROM rounds WHERE game_id = (SELECT id FROM games WHERE public_id = :public_id) AND round_number = :round',
        ['public_id' => $gameId, 'round' => $index + 1],
    )->fetchColumn();
    if ($storedOutcome !== $expectedOutcome) {
        throw new RuntimeException('Oracle Arena API trusted an invalid client outcome.');
    }
}

$storedGame = $connection->execute('SELECT player_score, oracle_score, draws, rounds_played, status FROM games WHERE public_id = :public_id', ['public_id' => $gameId])->fetch();
if ($storedGame !== ['player_score' => 2, 'oracle_score' => 2, 'draws' => 1, 'rounds_played' => 5, 'status' => 'finished']) {
    throw new RuntimeException('Oracle Arena API did not persist the expected final score.');
}
$afterFinish = $controller->storeRound(new \PHPAML\Http\Request('POST', '/api/rounds', body: [
    'game_id' => $gameId,
    'round_number' => 5,
    'player_choice' => 'rock',
    'oracle_choice' => 'scissors',
]));
if ($afterFinish->status() !== 409) {
    throw new RuntimeException('Oracle Arena API accepted a round after the game finished.');
}
$leaderboard = json_decode($controller->leaderboard(new \PHPAML\Http\Request('GET', '/api/leaderboard'))->content(), true, 512, JSON_THROW_ON_ERROR);
if (($leaderboard['data'][0]['public_id'] ?? null) !== $gameId) {
    throw new RuntimeException('Oracle Arena leaderboard did not expose the finished game.');
}

fwrite(STDOUT, "✓ Oracle Arena: View rendering, reactive state, Data persistence and API rules" . PHP_EOL);
