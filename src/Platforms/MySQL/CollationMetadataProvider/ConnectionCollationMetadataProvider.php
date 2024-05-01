<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;

use function str_starts_with;
use function substr;

/** @internal */
final class ConnectionCollationMetadataProvider implements CollationMetadataProvider
{
    /** @var Connection */
    private $connection;

    /** @var bool */
    private $useUtf8mb3;

    public function __construct(Connection $connection, bool $useUtf8mb3)
    {
        $this->connection = $connection;
        $this->useUtf8mb3 = $useUtf8mb3;
    }

    public function normalizeCollation(string $collation): string
    {
        if ($this->useUtf8mb3 && str_starts_with($collation, 'utf8_')) {
            return 'utf8mb3' . substr($collation, 4);
        }

        if (! $this->useUtf8mb3 && str_starts_with($collation, 'utf8mb3_')) {
            return 'utf8' . substr($collation, 7);
        }

        return $collation;
    }

    /** @throws Exception */
    public function getCollationCharset(string $collation): ?string
    {
        $collation = $this->normalizeCollation($collation);

        $charset = $this->connection->fetchOne(
            <<<'SQL'
SELECT CHARACTER_SET_NAME
FROM information_schema.COLLATIONS
WHERE COLLATION_NAME = ?;
SQL
            ,
            [$collation],
        );

        if ($charset !== false) {
            return $charset;
        }

        return null;
    }
}
