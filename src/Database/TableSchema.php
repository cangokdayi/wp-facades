<?php

namespace Cangokdayi\WPFacades\Database;

use Carbon\Carbon;
use Cangokdayi\WPFacades\Traits\InteractsWithDatabase;

class TableSchema
{
    use InteractsWithDatabase;

    /**
     * Table name
     */
    private string $table;

    /**
     * Parsed schema of the columns
     */
    private array $columns = [];

    /**
     * Raw schema of the columns
     */
    private array $schema = [];

    /**
     * Primary key of this table
     */
    private string $primaryKey;

    /**
     * @param string $tableName With the wpdb prefix
     */
    public function __construct(string $tableName)
    {
        $this->table = $tableName;
        $schema = $this->database()->get_results(
            "SHOW COLUMNS FROM {$this->table}",
            ARRAY_A
        );

        foreach ($schema as $column) {
            $column = array_change_key_case($column, CASE_LOWER);
            $name = $column['field'];
            $type = $column['type'];
            $key = $column['key'];

            $isPrimaryKey = 'PRI' === strtoupper($key);

            $this->schema[$name] = $column;
            $this->columns[$name] = [
                'type'       => $this->parseTypeDescription($type),
                'length'     => $this->getCharacterLength($type),
                'unsigned'   => str_contains($type, 'unsigned'),
                'nullable'   => 'yes' === strtolower($column['null']),
                'primary'    => $isPrimaryKey,
                'unique'     => 'UNI' === strtoupper($key) || $isPrimaryKey,
                'default'    => $column['default'],
                'auto_inc'   => str_contains('auto_increment', $column['extra'])
            ];

            if ($isPrimaryKey) {
                $this->primaryKey = $name;
            }
        }
    }

    /**
     * Returns the properties of the given column
     * 
     * @param boolean $raw When set to TRUE, un-parsed schema of the column will
     *                     be returned instead, defaults to false.
     */
    public function getColumn(string $column, bool $raw = false)
    {
        return $raw ? $this->schema[$column] : $this->columns[$column];
    }

    /**
     * Returns the column names of the table
     */
    public function getColumns(): array
    {
        return array_keys($this->columns);
    }

    /**
     * Returns true if the given column is nullable
     */
    public function isNullable(string $column): bool
    {
        return $this->columns[$column]['nullable'];
    }

    /**
     * Returns true if the given column is the primary key
     */
    public function isPrimary(string $column): bool
    {
        return $this->columns[$column]['primary'];
    }

    /**
     * Returns true if the given column has a unique constraint
     */
    public function isUnique(string $column): bool
    {
        return $this->columns[$column]['unique'];
    }

    /**
     * Returns true if the given value is valid for the column's type & length
     * 
     * @throws \InvalidArgumentException If the given column is unrecognized
     */
    public function validateColumn(string $column, $value): bool
    {
        if (!in_array($column, array_keys($this->columns))) {
            throw new \InvalidArgumentException(
                sprintf(
                    "The %s column doesn't exist in %s table",
                    $column,
                    $this->table
                )
            );
        }

        $type = $this->columns[$column]['type'];
        $length = $this->columns[$column]['length'];
        $isValidLength = $length >= strlen($value) || -1 === $length;
        $isNull = empty($value) || is_null($value);

        switch ($this->getDataType($type)) {
            case 'string':
                $isValidType = $this->validateStringType($column, $value);
                break;

            case 'number':
                $isValidType = $this->validateNumericType($column, $value);
                break;

            case 'datetime':
                $isValidType = $this->validateDateType($type, $value);
                break;

            default:
                $isValidType = false;
        }

        return $isValidType && $isValidLength
            ?: ($isNull && $this->isNullable($column));
    }

    /**
     * Finds the character length from the given type description string
     * such as "varchar(255)" or "int(10)" etc.
     */
    private function getCharacterLength(string $type): int
    {
        preg_match('/\((\d*?)\)/', $type, $matches);

        return $matches[1] ?? -1;
    }

    /**
     * Removes the character length and other words from the given 
     * type description text.
     * 
     * Example: 
     * ```
     * parseTypeDescription("int(10) unsigned");
     * 
     * Outputs: "int"
     * ```
     */
    private function parseTypeDescription(string $type): string
    {
        preg_match('/[a-zA-Z_]*/', $type, $matches);

        return $matches[0];
    }

    /**
     * Extracts the available values from the given enum/set type string
     * 
     * @param string $type Raw type string. i.e: enum('abc', 'def') or set(...)
     */
    private function extractEnumValues(string $type): array
    {
        preg_match_all(
            '/(?!enum|set)\b\w+/i',
            $type,
            $values,
            PREG_PATTERN_ORDER
        );

        return $values[0];
    }

    private function validateNumericType(string $column, $value): bool
    {
        $type = $this->columns[$column]['type'];
        $isBoolean = in_array($type, ['bool', 'boolean']);
        $isUnsigned = $this->columns[$column]['unsigned'];

        return is_numeric($value)
            && (!$isBoolean ?: ($value >= 0 || 1 >= $value))
            && (!$isUnsigned ?: (intval($value) >= 0));
    }

    private function validateStringType(string $column, $value): bool
    {
        $type = $this->columns[$column]['type'];
        $rawType = $this->schema[$column]['type'];
        $isEnum = in_array($type, ['enum', 'set']);

        return $isEnum
            ? in_array($value, $this->extractEnumValues($rawType))
            : is_scalar($value);
    }

    private function validateDateType(string $type, $value): bool
    {
        $formats = [
            'YEAR'      => 'Y',
            'DATE'      => 'Y-m-d',
            'TIME'      => 'H:i:s',
            'DATETIME'  => 'Y-m-d H:i:s',
            'TIMESTAMP' => 'Y-m-d H:i:s.u',
        ];

        try {
            $format = $formats[strtoupper($type)];

            if ($type == 'time') {
                Carbon::createFromTimeString($value);
            } else {
                Carbon::rawCreateFromFormat($format, $value);
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function listDataTypes(): array
    {
        return [
            'number' => [
                'tinyint', 'bit', 'bool', 'boolean',
                'smallint', 'mediumint', 'int', 'dec',
                'integer', 'bigint', 'float', 'double',
                'double precision', 'decimal'
            ],
            'string' => [
                'char', 'varchar', 'binary', 'varbinary',
                'tinyblob', 'tinytext', 'text', 'blob',
                'mediumtext', 'mediumblob', 'longtext',
                'longblob', 'enum', 'set'
            ],
            'datetime' => [
                'date', 'datetime', 'timestamp', 'time', 'year'
            ]
        ];
    }

    /**
     * Finds the data type category of the given column type
     */
    private function getDataType(string $columnType): ?string
    {
        $types = $this->listDataTypes();
        $type = null;

        foreach ($types as $category => $values) {
            if (in_array($columnType, $values)) {
                $type = $category;
                break;
            }
        }

        return $type;
    }
}
