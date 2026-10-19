<?php

declare(strict_types=1);

namespace App\Import;

final readonly class ImportScope
{
    /**
     * @param list<int> $cadastralAreaCodes
     * @param list<int> $districtCodes
     */
    private function __construct(
        public array $cadastralAreaCodes,
        public array $districtCodes,
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
            return new self(array_values($codes), []);
        }

        $districts = [];
        foreach (preg_split('/[\s,;]+/', $district, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $code) {
            if (!ctype_digit($code)) {
                throw new \InvalidArgumentException(sprintf('Invalid district code "%s" in IMPORT_DISTRICT.', $code));
            }
            $districts[(int) $code] = (int) $code;
        }

        if ($districts === []) {
            throw new \InvalidArgumentException('Set IMPORT_CADASTRAL_AREAS or IMPORT_DISTRICT.');
        }

        return new self([], array_values($districts));
    }

    public function describe(): string
    {
        return $this->districtCodes !== []
            ? sprintf('districts %s', implode(', ', $this->districtCodes))
            : sprintf('cadastral areas %s', implode(', ', $this->cadastralAreaCodes));
    }
}
