<?php

declare(strict_types=1);

namespace App\Views\Pages\Game;

use AML\Engine\Actions;
use AML\Engine\Api;
use AML\Engine\ClientAction;
use AML\Engine\ClientInstruction;
use AML\Engine\EffectPlan;
use AML\Engine\Effects;
use AML\Engine\StateRef;
use AML\View\Effect;
use AML\View\Page;
use AML\View\State;
use AML\View\View;
use function AML\View\{Button, Div, Footer, Heading, HStack, Input, Link, MainContent, Paragraph, Section, Span, Text, VStack};

final class GamePage extends Page
{
    private const LAST_ROUND = 5;

    #[State] public int $round = 0;
    #[State] public int $playerScore = 0;
    #[State] public int $oracleScore = 0;
    #[State] public int $draws = 0;
    #[State] public string $playerChoice = 'none';
    #[State] public string $oracleChoice = 'none';
    #[State] public string $verdict = 'Choisissez votre élément';
    #[State] public string $profile = 'L’Indéchiffrable';
    #[State] public string $profileDescription = 'Votre instinct refuse de suivre une trajectoire évidente.';
    #[State] public bool $started = false;
    #[State] public bool $finished = false;
    #[State] public bool $locked = false;
    #[State] public int $countdown = 0;
    #[State] public string $pendingChoice = 'none';
    #[State] public string $gameId = '';
    #[State] public array $savedRound = [];
    #[State] public array $syncError = [];
    #[State] public bool $syncing = false;
    #[State] public string $playerName = '';
    #[State] public bool $ready = false;

    private function startGame(): ClientInstruction
    {
        return Actions::sequence(
            Api::post('/api/games', ['player_name' => StateRef::to('playerName')])
                ->storeIn('gameId', 'id')
                ->errorIn('syncError')
                ->loadingIn('syncing'),
            ClientAction::set('ready', true),
        );
    }

    private function choose(string $choice): ClientInstruction
    {
        return Actions::sequence(
            ClientAction::set('pendingChoice', $choice),
            ClientAction::set('playerChoice', 'none'),
            ClientAction::set('oracleChoice', 'none'),
            ClientAction::set('verdict', 'Choix verrouillé'),
            ClientAction::set('started', false),
            ClientAction::set('countdown', 3),
            ClientAction::set('locked', true),
        );
    }

    #[Effect]
    protected function revealCountdown(): EffectPlan
    {
        return Effects::interval(
            700,
            Actions::when(
                'locked',
                'truthy',
                true,
                Actions::when(
                    'countdown',
                    'gt',
                    1,
                    ClientAction::decrement('countdown'),
                    Actions::sequence(
                        ClientAction::set('countdown', 0),
                        ClientAction::set('locked', false),
                        $this->resolvePendingChoice(),
                    ),
                ),
            ),
        );
    }

    private function resolvePendingChoice(): ClientInstruction
    {
        return Actions::when(
            'pendingChoice', 'eq', 'rock', $this->resolveRound('rock'),
            Actions::when(
                'pendingChoice', 'eq', 'paper', $this->resolveRound('paper'),
                $this->resolveRound('scissors'),
            ),
        );
    }

    private function resolveRound(string $choice): ClientInstruction
    {
        return Actions::when(
            'round', 'eq', 0,
            $this->resolve($choice, 'scissors', $this->outcome($choice, 'scissors'), 1),
            Actions::when(
                'round', 'eq', 1,
                $this->resolve($choice, 'rock', $this->outcome($choice, 'rock'), 2),
                Actions::when(
                    'round', 'eq', 2,
                    $this->resolve($choice, 'paper', $this->outcome($choice, 'paper'), 3),
                    Actions::when(
                        'round', 'eq', 3,
                        $this->resolve($choice, 'rock', $this->outcome($choice, 'rock'), 4),
                        $this->resolve($choice, 'scissors', $this->outcome($choice, 'scissors'), 5, true),
                    ),
                ),
            ),
        );
    }

    private function resolve(string $choice, string $oracleChoice, string $outcome, int $roundNumber, bool $last = false): ClientInstruction
    {
        $scoreAction = match ($outcome) {
            'win' => ClientAction::increment('playerScore'),
            'loss' => ClientAction::increment('oracleScore'),
            default => ClientAction::increment('draws'),
        };
        $verdict = match ($outcome) {
            'win' => 'Votre instinct prend l’avantage',
            'loss' => 'L’Oracle avait anticipé votre geste',
            default => 'Vos deux esprits se rencontrent',
        };

        $profileAction = $last
            ? Actions::sequence(
                ClientAction::set('profile', match ($choice) {
                    'rock' => 'Le Roc', 'paper' => 'Le Stratège', default => 'La Lame',
                }),
                ClientAction::set('profileDescription', match ($choice) {
                    'rock' => 'Vous avancez avec conviction et assumez vos décisions jusqu’au bout.',
                    'paper' => 'Vous cherchez le contre parfait et transformez l’observation en avantage.',
                    default => 'Vous tranchez rapidement, avant que l’adversaire ne puisse s’installer.',
                }),
            )
            : ClientAction::set('profile', StateRef::to('profile'));

        return Actions::sequence(
            ClientAction::set('started', true),
            ClientAction::set('playerChoice', $choice),
            ClientAction::set('oracleChoice', $oracleChoice),
            ClientAction::set('verdict', $verdict),
            $scoreAction,
            Api::post('/api/rounds', [
                'game_id' => StateRef::to('gameId'),
                'round_number' => $roundNumber,
                'player_choice' => $choice,
                'oracle_choice' => $oracleChoice,
                'outcome' => $outcome,
            ])->storeIn('savedRound')->errorIn('syncError')->loadingIn('syncing'),
            $profileAction,
            ClientAction::increment('round'),
            ClientAction::set('finished', $last),
        );
    }

    private function reset(): ClientInstruction
    {
        return Actions::sequence(
            Api::post('/api/games', ['player_name' => StateRef::to('playerName')])
                ->storeIn('gameId', 'id')
                ->errorIn('syncError')
                ->loadingIn('syncing'),
            ClientAction::set('round', 0),
            ClientAction::set('playerScore', 0),
            ClientAction::set('oracleScore', 0),
            ClientAction::set('draws', 0),
            ClientAction::set('playerChoice', 'none'),
            ClientAction::set('oracleChoice', 'none'),
            ClientAction::set('verdict', 'Choisissez votre élément'),
            ClientAction::set('profile', 'L’Indéchiffrable'),
            ClientAction::set('profileDescription', 'Votre instinct refuse de suivre une trajectoire évidente.'),
            ClientAction::set('started', false),
            ClientAction::set('finished', false),
            ClientAction::set('locked', false),
            ClientAction::set('countdown', 0),
            ClientAction::set('pendingChoice', 'none'),
        );
    }

    private function outcome(string $player, string $oracle): string
    {
        if ($player === $oracle) return 'draw';
        return match ($player) {
            'rock' => $oracle === 'scissors' ? 'win' : 'loss',
            'paper' => $oracle === 'rock' ? 'win' : 'loss',
            'scissors' => $oracle === 'paper' ? 'win' : 'loss',
            default => 'draw',
        };
    }

    public function body(): View
    {
        $round = StateRef::to('round', $this->round);
        $finished = StateRef::to('finished', $this->finished);
        $locked = StateRef::to('locked', $this->locked);
        $ready = StateRef::to('ready', $this->ready);

        return MainContent(
            Div()->class('ambient', 'ambient-one'),
            Div()->class('ambient', 'ambient-two'),
            Section(
                HStack(
                    HStack(Span('◉')->class('brand-mark'), Text('Oracle Arena')->class('brand-name'))->class('brand'),
                    HStack(Span('Une partie · cinq manches')->class('duel-label'), Span('Prêt')->class('online-status'))->class('session-meta'),
                )->class('topbar'),
                Section(
                    VStack(
                        Text('IDENTIFIEZ LE COMBATTANT')->class('entry-label'),
                        Heading('Votre nom dans l’arène', 1),
                        Paragraph('Choisissez un pseudonyme public de 2 à 24 caractères.'),
                        Input('player_name', value: $this->playerName)
                            ->bindClient('playerName')
                            ->placeholder('Votre pseudonyme')
                            ->minLength(2)
                            ->maxLength(24)
                            ->required('Entrez un pseudonyme.'),
                        Button('ENTRER DANS L’ARÈNE')
                            ->onClick($this->startGame())
                            ->disabledWhen('playerName', '')
                            ->class('entry-button'),
                    )->class('entry-panel')->showWhen($ready, false),
                    Div(
                    HStack(
                        VStack(Text('VOUS')->class('fighter-label'), Text(StateRef::to('playerScore', 0))->class('score-number'))->class('score-block', 'player-score'),
                        VStack(Text('MANCHE')->class('round-label'), HStack(Text($round)->class('round-current'), Text('/ ' . self::LAST_ROUND)->class('round-total'))->class('round-count'))->class('round-block'),
                        VStack(Text('ORACLE')->class('fighter-label'), Text(StateRef::to('oracleScore', 0))->class('score-number'))->class('score-block', 'oracle-score'),
                    )->class('scoreboard'),
                    Div(
                        VStack(
                            Text('VOTRE ARME')->class('choice-caption'),
                            Div(Text(StateRef::to('playerChoice', 'none'))->class('sr-only'))
                                ->class('gesture-icon', 'player-symbol')
                                ->classWhen('playerChoice', 'is-rock', 'rock')
                                ->classWhen('playerChoice', 'is-paper', 'paper')
                                ->classWhen('playerChoice', 'is-scissors', 'scissors'),
                        )->class('reveal-side'),
                        VStack(
                            Span('VS')->class('versus')->showWhen($locked, false),
                            Text(StateRef::to('countdown', $this->countdown))->class('countdown')->showWhen($locked, true),
                            Text(StateRef::to('verdict', $this->verdict))->class('verdict'),
                            Text(StateRef::to('draws', 0))->class('draw-count')->attribute('aria-label', 'Nombre d’égalités'),
                        )->class('reveal-center')->classWhen('locked', 'is-counting'),
                        VStack(
                            Text('ARME DE L’ORACLE')->class('choice-caption'),
                            Div(Text(StateRef::to('oracleChoice', 'none'))->class('sr-only'))
                                ->class('gesture-icon', 'oracle-symbol')
                                ->classWhen('oracleChoice', 'is-rock', 'rock')
                                ->classWhen('oracleChoice', 'is-paper', 'paper')
                                ->classWhen('oracleChoice', 'is-scissors', 'scissors'),
                        )->class('reveal-side'),
                    )->class('arena')->classWhen('started', 'is-awake'),
                    VStack(
                        Text('CHOISISSEZ VOTRE FORCE')->class('choice-title'),
                        HStack(
                            $this->choiceButton('PIERRE', 'Impact lourd', 'rock'),
                            $this->choiceButton('PAPIER', 'Défense tactique', 'paper'),
                            $this->choiceButton('CISEAUX', 'Frappe précise', 'scissors'),
                        )->class('choices'),
                        Text('Un clic suffit. Les deux gestes apparaissent en même temps.')->class('choice-note'),
                    )->class('choice-panel')->showWhen($finished, false),
                    VStack(
                        Text('ANALYSE TERMINÉE')->class('choice-title'),
                        Text(StateRef::to('profile', $this->profile))->class('profile-title'),
                        Text(StateRef::to('profileDescription', $this->profileDescription))->class('profile-description'),
                        Paragraph('Votre signature instinctive a été révélée. Rejouez avec une autre stratégie pour découvrir un nouveau profil.'),
                        Button('REJOUER LE DUEL')->onClick($this->reset())->class('replay-button'),
                    )->class('final-panel')->showWhen($finished, true)->transition('scale', 260),
                    )->class('game-content')->showWhen($ready, true),
                )->class('game-shell'),
                Footer(
                    Text('Conçu avec PHPAML View'),
                    HStack(
                        Text('Sauvegarde…')->class('save-status')->showWhen('syncing', true),
                        Link('CLASSEMENT', '/leaderboard')->class('footer-link'), Span('•'), Span('Data + API + View'),
                    ),
                )->class('game-footer'),
            )->class('page-frame'),
        )->class('oracle-page');
    }

    private function choiceButton(string $name, string $description, string $choice): View
    {
        return Button($name)
            ->onClick($this->choose($choice))
            ->disabledWhen('locked', true)
            ->class('choice-button', 'choice-' . $choice)
            ->attribute('aria-label', "Choisir {$name}")
            ->attribute('data-description', $description);
    }
}
