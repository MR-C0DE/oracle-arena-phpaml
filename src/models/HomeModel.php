<?php

declare(strict_types=1);

namespace App\Models;

final class HomeModel
{
    public function __construct(
        private string $name = 'PHPAML',
        private string $description = 'Micro-framework MVC expérimental en PHP'
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
