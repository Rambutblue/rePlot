<?php

declare(strict_types=1);

namespace App\Import;

final readonly class PostgresConnection
{
    private function __construct(
        public string $host,
        public int $port,
        public string $database,
        public ?string $user,
        public ?string $password,
    ) {
    }

    public static function fromUrl(string $url): self
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'], $parts['path'])) {
            throw new \InvalidArgumentException('DATABASE_URL must contain a host and a database name.');
        }

        return new self(
            $parts['host'],
            $parts['port'] ?? 5432,
            rawurldecode(ltrim($parts['path'], '/')),
            isset($parts['user']) ? rawurldecode($parts['user']) : null,
            isset($parts['pass']) ? rawurldecode($parts['pass']) : null,
        );
    }

    public function conninfo(): string
    {
        $values = ['host' => $this->host, 'port' => (string) $this->port, 'dbname' => $this->database, 'user' => $this->user];

        $pairs = [];
        foreach (array_filter($values, static fn (?string $value): bool => $value !== null) as $key => $value) {
            $pairs[] = sprintf("%s='%s'", $key, addcslashes($value, "'\\"));
        }

        return implode(' ', $pairs);
    }
}
