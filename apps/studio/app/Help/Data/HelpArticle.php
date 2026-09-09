<?php

namespace App\Help\Data;

final readonly class HelpArticle
{
    public function __construct(
        public string $slug,
        public string $titel,
        public string $art,
        public string $html,
    ) {}
}
