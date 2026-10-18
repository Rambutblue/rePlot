<?php

declare(strict_types=1);

namespace App\Search;

final readonly class SearchQuery
{
    private const string PARCEL_PATTERN = '~(?<=^|[\s,])(?:(st)\.?\s*)?(\d+(?:/\d+)?)(?=$|[\s,])~iu';

    private function __construct(
        public ?string $parcelLabel,
        public ?string $areaName,
    ) {
    }

    public static function parse(string $input): ?self
    {
        $input = trim((string) preg_replace('/\s+/u', ' ', $input), " ,\t\n");
        if (mb_strlen($input) < 2 && !ctype_digit($input)) {
            return null;
        }

        if (preg_match(self::PARCEL_PATTERN, $input, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return new self(null, $input);
        }

        $label = ($match[1][0] !== '' ? 'st. ' : '').$match[2][0];
        $rest = trim(substr_replace($input, ' ', $match[0][1], \strlen($match[0][0])), ' ,');

        return new self($label, $rest === '' ? null : $rest);
    }

    public function isPrefix(): bool
    {
        return $this->parcelLabel !== null && !str_contains($this->parcelLabel, '/');
    }
}
