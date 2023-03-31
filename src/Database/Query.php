<?php

namespace Cangokdayi\WPFacades\Database;

use Cangokdayi\WPFacades\Traits\InteractsWithDatabase;

/**
 * Basic query builder for querying models 
 */
class Query
{
    use InteractsWithDatabase;

    private Model $model;

    private string $table;

    /**
     * Condition clauses
     */
    private array $conditions = [];

    /**
     * Amount of models to take
     */
    private int $limit = -1;

    /**
     * Offset for paginated queries
     */
    private int $offset = 0;

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->table = $model->getTable();
    }

    /**
     * Adds a basic "where equals" clause to the current query
     * 
     * @param int|string|bool|null|float $value Scalar types and null only.
     * 
     * @throws \InvalidArgumentException If the given value isn't scalar or null
     */
    public function where(string $column, $value): self
    {
        if (!$this->isValidType($value)) {
            throw new \InvalidArgumentException();
        }

        $this->conditions[] = [
            'column'   => $column,
            'value'    => esc_sql($value),
            'operator' => is_null($value) ? Operator::NULL : Operator::EQUAL
        ];

        return $this;
    }

    /**
     * Adds a basic "where does not equal" clause to the current query
     * 
     * @param int|string|bool|null|float $value Scalar types and null only.
     * 
     * @throws \InvalidArgumentException If the given value isn't scalar or null
     */
    public function whereNot(string $column, $value): self
    {
        if (!$this->isValidType($value)) {
            throw new \InvalidArgumentException();
        }

        $this->conditions[] = [
            'column'   => $column,
            'value'    => esc_sql($value),
            'operator' => is_null($value)
                ? Operator::NOT_NULL
                : Operator::NOT_EQUAL
        ];

        return $this;
    }

    /**
     * Adds a where clause for the primary key of this model
     * 
     * @param string|int $id Primary key
     */
    public function whereKey($id): self
    {
        return $this->where(
            $this->model->getPrimaryColumn(),
            $id
        );
    }

    /**
     * Sets the limit clause of the query
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Sets the offset clause for paginated queries
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Executes the query and returns the first matching model
     */
    public function first(array $columns = ['*']): ?Model
    {
        $query = $this->database()->get_row(
            $this->buildSql($columns),
            OBJECT
        );

        return $this->model->fresh()->init($query);
    }

    /**
     * Executes the query and returns the matching models
     * 
     * @param int $limit Amount of models to take, defaults to unlimited.
     * @return Model[]
     */
    public function get(array $columns = ['*']): array
    {
        $query = $this->database()->get_results(
            $this->buildSql($columns),
            OBJECT
        );

        return array_map(
            fn ($item) => $this->model->fresh()->init($item),
            $query ?? []
        );
    }

    /**
     * Returns true if the given value is scalar or null
     */
    private function isValidType($value): bool
    {
        return is_scalar($value)
            || is_null($value);
    }

    /**
     * Builds the where clauses for the SQL query
     */
    private function buildConditions(): string
    {
        $query = [];

        foreach ($this->conditions as $condition) {
            [$column, $value, $operator] = array_values($condition);

            $value = is_bool($value) ? intval($value) : $value;

            $query[] = is_null($value)
                ? "`{$column}` {$operator}"
                : "`{$column}` {$operator} '{$value}'";
        }

        return count($query)
            ? "WHERE " . implode(' AND ', $query)
            : '';
    }

    /**
     * Builds the final SQL query
     */
    private function buildSql(array $columns): string
    {
        $columns = implode(',', esc_sql($columns)) ?: '*';

        $limit = $this->limit > 0
            ? $this->limit
            : PHP_INT_MAX;

        $offset = max(0, $this->offset);

        return "SELECT {$columns} FROM {$this->table} 
                {$this->buildConditions()}
                LIMIT {$limit}
                OFFSET {$offset}";
    }
}
