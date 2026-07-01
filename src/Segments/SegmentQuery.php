<?php

namespace Rayzenai\LaravelSms\Segments;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Turns a saved segment's condition tree into a safe Eloquent query.
 *
 * This is the single place where a segment becomes SQL. Everything that could
 * be attacker-influenced is constrained here:
 *
 *  - Field names must be real columns on the target model's table (checked
 *    against the live schema) — an unknown column throws, never interpolates.
 *  - Operators come from a fixed allowlist.
 *  - Values always travel as query bindings, never string-concatenated.
 *
 * The tree shape (see the design doc):
 *   group     = ['logic' => 'and'|'or', 'children' => [ ...nodes ]]
 *   condition = ['field' => string, 'op' => string, 'value' => mixed]
 */
class SegmentQuery
{
    /** Binary comparison operators: op => SQL operator. */
    protected const BINARY = [
        '=' => '=',
        '!=' => '!=',
        '>' => '>',
        '>=' => '>=',
        '<' => '<',
        '<=' => '<=',
    ];

    protected string $modelClass;

    /** @var array<string, mixed> */
    protected array $tree;

    /** @var array<int, string>|null Columns of the target table, loaded lazily. */
    protected ?array $columns = null;

    /**
     * @param  array<string, mixed>  $tree  A root group node.
     */
    public function __construct(string $modelClass, array $tree)
    {
        if (! is_a($modelClass, Model::class, true)) {
            throw new InvalidArgumentException("Segment model [{$modelClass}] is not an Eloquent model.");
        }

        $this->modelClass = $modelClass;
        $this->tree = $tree;
    }

    /**
     * @param  array<string, mixed>  $tree
     */
    public static function for(string $modelClass, array $tree): self
    {
        return new self($modelClass, $tree);
    }

    /**
     * The number of users the segment currently matches.
     */
    public function count(): int
    {
        return $this->builder()->count();
    }

    /**
     * The users the segment currently matches.
     *
     * @return Collection<int, Model>
     */
    public function users(): Collection
    {
        return $this->builder()->get();
    }

    /**
     * Build (but don't execute) the Eloquent query for this segment.
     */
    public function builder(): Builder
    {
        /** @var Model $model */
        $model = new $this->modelClass;

        $this->columns = Schema::connection($model->getConnectionName())
            ->getColumnListing($model->getTable());

        $query = $model->newQuery();

        // An empty tree matches everyone.
        $children = $this->tree['children'] ?? null;

        if (! empty($children)) {
            $this->applyNode($query->getQuery(), $this->tree, 'and');
        }

        return $query;
    }

    /**
     * Apply a single node (group or condition) to the query with a boolean joiner.
     */
    protected function applyNode(\Illuminate\Database\Query\Builder $query, array $node, string $boolean): void
    {
        if (array_key_exists('children', $node)) {
            $childBoolean = $this->normalizeLogic($node['logic'] ?? 'and');

            $query->where(function ($nested) use ($node, $childBoolean) {
                foreach ($node['children'] as $child) {
                    $this->applyNode($nested, $child, $childBoolean);
                }
            }, null, null, $boolean);

            return;
        }

        $this->applyCondition($query, $node, $boolean);
    }

    /**
     * Apply a leaf condition to the query.
     */
    protected function applyCondition(\Illuminate\Database\Query\Builder $query, array $node, string $boolean): void
    {
        $field = $node['field'] ?? null;
        $op = $node['op'] ?? '=';
        $value = $node['value'] ?? null;

        $this->assertField($field);

        switch ($op) {
            case 'is_set':
                $query->whereNotNull($field, $boolean);

                return;

            case 'is_empty':
                $query->whereNull($field, $boolean);

                return;

            case 'contains':
                $query->where($field, 'like', '%'.$value.'%', $boolean);

                return;

            case 'in':
                $query->whereIn($field, $this->splitList($value), $boolean);

                return;
        }

        if (! isset(self::BINARY[$op])) {
            throw new InvalidArgumentException("Unsupported segment operator [{$op}].");
        }

        $query->where($field, self::BINARY[$op], $value, $boolean);
    }

    /**
     * A field is only allowed if it's a real column on the target table.
     */
    protected function assertField(mixed $field): void
    {
        if (! is_string($field) || $field === '') {
            throw new InvalidArgumentException('A segment condition is missing its field.');
        }

        if (! in_array($field, $this->columns ?? [], true)) {
            throw new InvalidArgumentException("Unknown segment field [{$field}].");
        }
    }

    /**
     * Split a comma-separated "in" value into a clean list of terms.
     *
     * @return array<int, string>
     */
    protected function splitList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        return collect(explode(',', (string) $value))
            ->map(fn ($term) => trim($term))
            ->filter(fn ($term) => $term !== '')
            ->values()
            ->all();
    }

    protected function normalizeLogic(mixed $logic): string
    {
        return strtolower((string) $logic) === 'or' ? 'or' : 'and';
    }
}
