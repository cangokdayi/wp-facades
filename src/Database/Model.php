<?php

namespace Cangokdayi\WPFacades\Database;

use Cangokdayi\WPFacades\Traits\InteractsWithDatabase;

abstract class Model
{
    use InteractsWithDatabase;

    /**
     * Table name of this model, wpdb prefix will be added automatically.
     */
    protected string $table;

    /**
     * Table schema of this model for the column validations.
     */
    protected TableSchema $schema;

    /**
     * Primary key column of the model
     */
    protected string $primaryCol = 'id';

    /**
     * Guarded attributes
     */
    protected array $guarded = ['id'];

    /**
     * Attributes of this model, you can also define your default values here.
     */
    protected array $attributes = [];

    /**
     * Original attributes from the database record
     */
    private array $original = [];

    /**
     * @throws \InvalidArgumentException If there are guarded props in the given
     *                                   attributes
     * 
     * @throws \InvalidArgumentException If the primary key prop is wrong
     */
    public function __construct(array $attributes = [])
    {
        $this->table = $this->tableName($this->table);
        $this->schema = new TableSchema($this->table);

        $this->validatePrimaryKey($this->primaryCol);
        $this->fill($attributes);
    }

    /**
     * @throws \InvalidArgumentException If the given property is guarded
     * @throws \InvalidArgumentException If the given attribute is invalid
     */
    public function __set($attr, $value)
    {
        if (in_array($attr, $this->guarded)) {
            throw new \InvalidArgumentException(
                'You can\'t assign to guarded attributes'
            );
        }

        $this->validateAttribute($attr, $value);
        $this->attributes[$attr] = $this->castAttributeType($value);
    }

    public function __get($attr)
    {
        return $this->attributes[$attr] ?? null;
    }

    public function __isset($attr)
    {
        return isset($this->attributes[$attr]);
    }

    public function __unset($attr)
    {
        $this->attributes[$attr] = '';
    }

    /**
     * Fills the model with the given attributes
     * 
     * @throws \InvalidArgumentException If there are guarded columns in the 
     *                                   given attributes
     * 
     * @throws \InvalidArgumentException If the given attributes are invalid
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $attr => $value) {
            $this->{$attr} = $value;
        }

        return $this;
    }

    /**
     * Initiates the model from the given database entry
     * 
     * @throws \InvalidArgumentException If there are invalid attributes
     */
    public function init(?object $item): ?static
    {
        if (is_null($item)) return null;

        foreach ((array) $item as $attr => $value) {
            $this->validateAttribute($attr, $value);
            $this->attributes[$attr] = $value;
            $this->original[$attr] = $value;
        }

        return $this;
    }

    /**
     * Queries a model by the given primary key and returns the matching result
     * 
     * @return static|null
     */
    public static function find(int $primaryKey, array $cols = ['*']): ?static
    {
        return static::query()
            ->whereKey($primaryKey)
            ->first($cols);
    }

    /**
     * Returns all the models
     * 
     * @param int $limit Max items limit for paginated queries [optional]
     * @param int $offset Offset for paginated queries [optional]
     * @param array $columns Which columns to select [optional]
     * 
     * @return static[]
     */
    public static function all(
        int $limit = -1,
        int $offset = 0,
        array $columns = ['*']
    ): array {
        return static::query()
            ->limit($limit)
            ->offset($offset)
            ->get($columns);
    }

    /**
     * Returns a query builder
     */
    public static function query(): Query
    {
        return new Query(new static());
    }

    /**
     * Returns a query builder for the given "where equals" clause
     * 
     * @param int|string|bool|null|float $value Scalar types and null only.
     * 
     * @throws \InvalidArgumentException If the given value isn't scalar or null
     */
    public static function where(string $column, $value): Query
    {
        return static::query()->where(...func_get_args());
    }

    /**
     * Queries a model with the given condition and returns the first matching
     * result.
     * 
     * @param int|string|bool|null|float $value Scalar types and null only.
     * @throws \InvalidArgumentException If the given value isn't scalar or null
     */
    public static function firstWhere(string $column, $value): ?static
    {
        return static::where(...func_get_args())->first();
    }

    /**
     * Saves the model to the database and returns the inserted row ID
     * 
     * @throws \LogicException If there are invalid/missing attributes
     * @throws \LogicException On database errors
     */
    public function save(): int
    {
        $this->validateAttributes();
        $sql = $this->database()->insert(
            $this->table,
            $this->getAttributes()
        );

        if (false === $sql) {
            throw new \LogicException(
                'An error occurred while saving the model'
            );
        }

        $this->attributes[$this->primaryCol] = $this->database()->insert_id;

        return $this->attributes[$this->primaryCol];
    }

    /**
     * Updates the model in the database
     * 
     * @throws \LogicException If there are invalid/missing attributes
     * @throws \LogicException On database errors
     */
    public function update(): void
    {
        $this->validateAttributes();
        $primaryKey = $this->attributes[$this->primaryCol]
            ?? $this->fetchPrimaryKey();

        $sql = $this->database()->update(
            $this->table,
            $this->getAttributes(),
            [$this->primaryCol => $primaryKey]
        );

        if (false === $sql) {
            throw new \LogicException(
                'An error occurred while updating the model'
            );
        }
    }

    /**
     * Updates the model in database if it exists otherwise creates it
     * 
     * @throws \LogicException On database errors
     */
    public function saveOrUpdate(): void
    {
        if (!$this->exists()) {
            $this->save();
            return;
        }

        $this->update();
    }

    /**
     * Deletes this model from the database
     */
    public function delete(): void
    {
        $id = $this->attributes[$this->primaryCol] ?? null;

        if (is_null($id)) {
            throw new \BadMethodCallException(
                'You can\'t delete non-existing models'
            );
        }

        $this->database()->delete(
            $this->table,
            [$this->primaryCol => $id]
        );
    }

    /**
     * Returns all the attributes as array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Validates the attributes of this model against its table schema
     * 
     * @throws \LogicException
     */
    protected function validateAttributes(): void
    {
        $schema = $this->schema;
        $columns = $schema->getColumns();
        $unrecognizedAttributes = array_diff(
            array_keys($this->attributes),
            $columns
        );

        if (!empty($unrecognizedAttributes)) {
            $atts = implode(', ', $unrecognizedAttributes);

            throw new \LogicException(
                "Following attributes are unrecognized: $atts"
            );
        }

        foreach ($columns as $col) {
            $attr = $this->castAttributeType(
                $this->attributes[$col] ?? null
            );

            $isPrimary = $schema->isPrimary($col);
            $isNull = !strlen($attr) || is_null($attr);
            $isValid = $schema->validateColumn($col, $attr);

            if (!$isPrimary && $isNull && !$schema->isNullable($col)) {
                throw new \InvalidArgumentException(
                    "The \"$col\" attribute cannot be null"
                );
            }

            if (!$isPrimary && !$isValid) {
                throw new \InvalidArgumentException(
                    "The \"$col\" attribute is not valid"
                );
            }
        }
    }

    /**
     * Validates the given attribute 
     * 
     * @throws \InvalidArgumentException If the given attribute is invalid
     */
    protected function validateAttribute(string $attr, $value): void
    {
        $isValid = $this->schema->validateColumn(
            $attr,
            $this->castAttributeType($value)
        );

        if (!$isValid) {
            throw new \InvalidArgumentException(
                "The \"$attr\" attribute is not valid"
            );
        }
    }

    /**
     * Validates the primary key column of the model's table
     * 
     * @throws \InvalidArgumentException If the given column is not the primary
     *                                   key of the table
     */
    protected function validatePrimaryKey(string $column): void
    {
        if (!$this->schema->isPrimary($column)) {
            throw new \InvalidArgumentException(
                "The column \"{$column}\" is not the primary key 
                 of \"{$this->table}\" table"
            );
        }
    }

    /**
     * Returns the unguarded attributes of the model for save/update operations.
     */
    protected function getAttributes(): array
    {
        return array_filter(
            $this->attributes,
            fn ($key) => !in_array($key, $this->guarded),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Converts some value types like bool/null to int/string
     */
    protected function castAttributeType($value)
    {
        if (is_bool($value)) {
            $value = (int) $value;
        }

        return $value;
    }

    /**
     * Returns the total model count from the database
     */
    public function getTotalItems(): int
    {
        return $this->getRowCount($this->table);
    }

    /**
     * Returns the primary column name of this model
     */
    public function getPrimaryColumn(): string
    {
        return $this->primaryCol;
    }

    /**
     * Returns true if this model exists in the database
     */
    public function exists(): bool
    {
        $query = $this->buildQueryFromAttributes();

        return isset($this->attributes[$this->primaryCol])
            ?: is_object($query->first());
    }

    /**
     * Returns the table name of this model
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Returns the table schema of this model
     */
    public function getSchema(): TableSchema
    {
        return $this->schema;
    }

    /**
     * Returns a new instance of this model
     */
    public function fresh(): static
    {
        return new static();
    }

    /**
     * Builds a query builder with all of the original attributes of this model
     * as where conditions.
     */
    public function buildQueryFromAttributes(): Query
    {
        $query = static::query();

        foreach ($this->original as $attr => $val) {
            $query = $query->where($attr, $val);
        }

        return $query;
    }

    /**
     * Queries the current model and returns its primary key from the database
     * 
     * Some existing models are initiated without their primary keys present so
     * update() would fail and this method takes care of that problem.
     */
    private function fetchPrimaryKey(): ?int
    {
        $model = $this->buildQueryFromAttributes()->first();

        return is_object($model)
            ? $model->{$this->primaryCol}
            : null;
    }
}
