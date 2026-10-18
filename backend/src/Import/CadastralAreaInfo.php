<?php

declare(strict_types=1);

namespace App\Import;

final readonly class CadastralAreaInfo
{
    public function __construct(
        public int $code,
        public string $name,
        public int $municipalityCode,
        public string $municipalityName,
    ) {
    }
}
