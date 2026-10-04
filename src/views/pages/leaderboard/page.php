<?php

declare(strict_types=1);

namespace App\Views\Pages\Leaderboard;

use AML\Engine\Api;
use AML\Engine\EffectPlan;
use AML\Engine\Effects;
use AML\Engine\StateRef;
use AML\View\CollectionItem;
use AML\View\Computed;
use AML\View\Effect;
use AML\View\Page;
use AML\View\State;
use AML\View\View;
use function AML\View\{Div, Each, Heading, HStack, Link, MainContent, Paragraph, Section, Span, Text, VStack};

final class LeaderboardPage extends Page
{
    #[State] public array $rankings = [];
    #[State] public array $loadError = [];
    #[State] public bool $loading = false;

    #[Computed(dependencies: ['rankings'], operation: 'count')]
    protected function rankingCount(): int
    {
        return count($this->rankings);
    }

    #[Effect]
    protected function loadLeaderboard(): EffectPlan
    {
        return Effects::run(
            Api::get('/api/leaderboard')
                ->storeIn('rankings', 'data')
                ->errorIn('loadError')
                ->loadingIn('loading'),
        );
    }

    public function body(): View
    {
        return MainContent(
            Section(
                HStack(
                    Link('ORACLE ARENA', '/')->class('ranking-brand'),
                    HStack(
                        Link('ACCUEIL', '/'),
                        Link('JOUER', '/game')->class('ranking-play-link'),
                    )->class('ranking-nav'),
                )->class('ranking-topbar'),
                VStack(
                    Text('ARCHIVES DE L’ORACLE')->class('ranking-kicker'),
                    Heading('Tableau des duels', 1),
                    Paragraph('Les dix meilleures parties terminées, enregistrées par PHPAML Data et servies par l’API.'),
                )->class('ranking-heading'),
                Div(
                    Div(Text('#'), Text('JOUEUR'), Text('VICTOIRES'), Text('ORACLE'), Text('ÉGALITÉS'))->class('ranking-row', 'ranking-labels'),
                    Each(
                        StateRef::to('rankings', $this->rankings),
                        key: 'public_id',
                        render: static fn (CollectionItem $item): View => Div(
                            Span('•')->class('rank-mark'),
                            Span($item->text('player_name'))->class('duel-id'),
                            Span($item->text('player_score'))->class('ranking-score', 'ranking-score-player'),
                            Span($item->text('oracle_score'))->class('ranking-score'),
                            Span($item->text('draws'))->class('ranking-score'),
                        )->class('ranking-row'),
                    ),
                    Text('Chargement des archives…')->class('ranking-status')->showWhen('loading', true),
                    VStack(
                        Text('AUCUN DUEL TERMINÉ')->class('empty-title'),
                        Paragraph('Terminez une partie pour ouvrir le classement.'),
                        Link('COMMENCER UN DUEL', '/game')->class('ranking-play-link'),
                    )->class('ranking-empty')->showWhen(StateRef::to('rankingCount', 0), 0),
                )->class('ranking-table'),
                HStack(Text('PHPAML VIEW'), Text('API'), Text('DATA'))->class('ranking-stack'),
            )->class('ranking-frame'),
        )->class('ranking-page');
    }
}
