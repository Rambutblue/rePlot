<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\PostgresConnection;
use PHPUnit\Framework\TestCase;

final class PostgresConnectionTest extends TestCase
{
    public function testConninfoIsBuiltFromDatabaseUrl(): void
    {
        $connection = PostgresConnection::fromUrl('postgresql://replot:secret@db:5433/replot?serverVersion=17&charset=utf8');

        self::assertSame("host='db' port='5433' dbname='replot' user='replot'", $connection->conninfo());
        self::assertSame('secret', $connection->password);
    }

    public function testValuesAreDecodedAndQuoted(): void
    {
        $connection = PostgresConnection::fromUrl("postgresql://us%40er:p%40ss@localhost/my%20d'b");

        self::assertSame("host='localhost' port='5432' dbname='my d\\'b' user='us@er'", $connection->conninfo());
        self::assertSame('p@ss', $connection->password);
    }

    public function testUrlWithoutDatabaseIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PostgresConnection::fromUrl('postgresql://localhost');
    }
}
