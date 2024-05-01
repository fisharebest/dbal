<?php

namespace Doctrine\DBAL\Platforms\MySQL;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

use function array_diff_assoc;
use function array_intersect_key;

/**
 * Compares schemas in the context of MySQL platform.
 *
 * In MySQL, unless specified explicitly, the column's character set and collation are inherited from its containing
 * table. So during comparison, an omitted value and the value that matches the default value of table in the
 * desired schema must be considered equal.
 */
class Comparator extends BaseComparator
{
    /** @var CharsetMetadataProvider */
    private $charsetMetadataProvider;

    /** @var CollationMetadataProvider */
    private $collationMetadataProvider;

    /** @internal The comparator can be only instantiated by a schema manager. */
    public function __construct(
        AbstractMySQLPlatform $platform,
        CharsetMetadataProvider $charsetMetadataProvider,
        CollationMetadataProvider $collationMetadataProvider
    ) {
        parent::__construct($platform);

        $this->charsetMetadataProvider   = $charsetMetadataProvider;
        $this->collationMetadataProvider = $collationMetadataProvider;
    }

    public function compareTables(Table $fromTable, Table $toTable): TableDiff
    {
        return parent::compareTables(
            $this->normalizeColumns($fromTable),
            $this->normalizeColumns($toTable),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function diffTable(Table $fromTable, Table $toTable)
    {
        return parent::diffTable(
            $this->normalizeColumns($fromTable),
            $this->normalizeColumns($toTable),
        );
    }

    private function normalizeColumns(Table $table): Table
    {
        $tableOptions = array_intersect_key($table->getOptions(), [
            'charset'   => null,
            'collation' => null,
        ]);

        $table = clone $table;

        foreach ($table->getColumns() as $column) {
            $originalOptions   = $column->getPlatformOptions();
            $normalizedOptions = $this->normalizeOptions($originalOptions);

            $overrideOptions = array_diff_assoc($normalizedOptions, $tableOptions);

            if ($overrideOptions === $originalOptions) {
                continue;
            }

            $column->setPlatformOptions($overrideOptions);
        }

        return $table;
    }

    /**
     * @param array<string,string> $options
     *
     * @return array<string,string|null>
     */
    private function normalizeOptions(array $options): array
    {
        if (isset($options['charset'])) {
            $options['charset'] = $this->charsetMetadataProvider->normalizeCharset($options['charset']);

            if (! isset($options['collation'])) {
                $options['collation'] = $this->charsetMetadataProvider->getDefaultCharsetCollation($options['charset']);
            }
        }

        if (isset($options['collation'])) {
            $options['collation'] = $this->collationMetadataProvider->normalizeCollation($options['collation']);

            if (! isset($options['charset'])) {
                $options['charset'] = $this->collationMetadataProvider->getCollationCharset($options['collation']);
            }
        }

        return $options;
    }
}
