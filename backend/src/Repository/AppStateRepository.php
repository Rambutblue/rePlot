<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final readonly class AppStateRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function dataVersion(): int
    {
        return (int) $this->connection->fetchOne('SELECT data_version FROM app_state');
    }
}
