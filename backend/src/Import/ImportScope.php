<?php

declare(strict_types=1);

namespace App\Import;

final readonly class ImportScope
{
    /**
     * @param list<int> $cadastralAreaCodes
     */
    private function __construct(
        public array $cadastralAreaCodes,
        public ?int $districtCode,
    ) {
    }

    public static function fromEnv(string $cadastralAreas, string $district): self
    {
        $codes = [];
        foreach (preg_split('/[\s,;]+/', $cadastralAreas, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $code) {
            if (!ctype_digit($code)) {
                throw new \InvalidArgumentException(sprintf('Invalid cadastral area code "%s" in IMPORT_CADASTRAL_AREAS.', $code));
            }
            $codes[(int) $code] = (int) $code;
        }

        if ($codes !== []) {
            return new self(array_values($codes), null);
        }

        $district = trim($district);
        if ($district === '') {
            throw new \InvalidArgumentException('Set IMPORT_CADASTRAL_AREAS or IMPORT_DISTRICT.');
        }
        if (!ctype_digit($district)) {
            throw new \InvalidArgumentException(sprintf('Invalid district code "%s" in IMPORT_DISTRICT.', $district));
        }

        return new self([], (int) $district);
    }

    public function describe(): string
    {
        return $this->districtCode !== null
            ? sprintf('district %d', $this->districtCode)
            : sprintf('cadastral areas %s', implode(', ', $this->cadastralAreaCodes));
    }
}
