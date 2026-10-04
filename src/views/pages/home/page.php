<?php

declare(strict_types=1);

namespace App\Views\Pages\Home;

use AML\View\Page;
use AML\View\View;
use function AML\View\{Div, Heading, HStack, Link, MainContent, Paragraph, Section, Span, Text, VStack};

final class HomePage extends Page
{
    public function body(): View
    {
        return MainContent(
            Div()->class('landing-noise'),
            Section(
                HStack(
                    Text('ORACLE ARENA')->class('brand-name'),
                    Text('PHPAML VIEW')->class('landing-kicker'),
                )->class('landing-topbar'),
                VStack(
                    Text('PIERRE · PAPIER · CISEAUX')->class('eyebrow'),
                    Heading('Battez l’Oracle.', 1),
                    Paragraph('Cinq manches. Trois choix. Une seule question : peut-il prévoir votre prochain geste ?'),
                    HStack(
                        VStack(Div()->class('weapon', 'weapon-rock'), Text('PIERRE'))->class('weapon-item'),
                        VStack(Div()->class('weapon', 'weapon-paper'), Text('PAPIER'))->class('weapon-item'),
                        VStack(Div()->class('weapon', 'weapon-scissors'), Text('CISEAUX'))->class('weapon-item'),
                    )->class('weapons'),
                    Link('JOUER MAINTENANT', '/game')->class('launch-button'),
                    Link('Voir le classement', '/leaderboard')->class('leaderboard-link'),
                    Text('Sans inscription · Environ une minute')->class('launch-note'),
                )->class('simple-hero'),
            )->class('landing-frame'),
        )->class('landing-page');
    }
}
