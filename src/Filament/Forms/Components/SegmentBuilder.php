<?php

namespace Rayzenai\LaravelSms\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Schema as DbSchema;

/**
 * A compact, Alpine-driven query builder for segment conditions.
 *
 * The field's state is the condition tree itself:
 *   ['logic' => 'and'|'or', 'children' => [ ...condition|group nodes ]]
 *
 * All interaction (add/remove/nest) happens client-side; the tree is synced into
 * the form state, and a live match count is fetched from the host component's
 * previewSegmentCount() method (see {@see \Rayzenai\LaravelSms\Filament\Concerns\WithSegmentPreview}).
 * Evaluation stays in {@see \Rayzenai\LaravelSms\Segments\SegmentQuery}.
 */
class SegmentBuilder extends Field
{
    protected string $view = 'laravel-sms::forms.segment-builder';

    /** Operators offered per condition: stored key => displayed label. */
    public const OPERATORS = [
        '=' => '=',
        '!=' => '≠',
        '>' => '>',
        '>=' => '≥',
        '<' => '<',
        '<=' => '≤',
        'contains' => 'contains',
        'in' => 'in',
        'is_set' => 'is set',
        'is_empty' => 'is empty',
    ];

    /** Operators whose value input is meaningless and hidden. */
    public const NULLARY_OPERATORS = ['is_set', 'is_empty'];

    protected int $maxDepth = 2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(['logic' => 'and', 'children' => []]);
    }

    public function maxDepth(int $depth): static
    {
        $this->maxDepth = max(0, $depth);

        return $this;
    }

    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }

    /**
     * @return array<string, string>
     */
    public function getOperators(): array
    {
        return self::OPERATORS;
    }

    /**
     * @return array<int, string>
     */
    public function getNullaryOperators(): array
    {
        return self::NULLARY_OPERATORS;
    }

    /**
     * Column names of the configured user table, offered as a field type-ahead.
     *
     * @return array<int, string>
     */
    public function getUserColumns(): array
    {
        $class = config('laravel-sms.user_model.class');

        if (! is_string($class) || ! class_exists($class)) {
            return [];
        }

        try {
            $model = new $class;

            $columns = DbSchema::connection($model->getConnectionName())
                ->getColumnListing($model->getTable());

            return array_values(array_filter($columns, fn ($column) => ! $this->isHiddenColumn($column)));
        } catch (\Throwable) {
            return [];
        }
    }

    /** Substrings that mark a column as secret/noise and unfit for a segment. */
    protected const SENSITIVE_NEEDLES = ['password', 'token', 'secret', 'two_factor', 'api_key', 'remember'];

    /**
     * Columns not worth suggesting: identifiers (id / *_id) and anything
     * secret-ish (passwords, tokens, 2FA secrets). They add noise and must never
     * drive a segment.
     */
    protected function isHiddenColumn(string $column): bool
    {
        $column = strtolower($column);

        if ($column === 'id' || str_contains($column, '_id')) {
            return true;
        }

        foreach (self::SENSITIVE_NEEDLES as $needle) {
            if (str_contains($column, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map of column name => simplified type (string|number|boolean|date|datetime),
     * used to pick the right value input and validate it.
     *
     * @return array<string, string>
     */
    public function getColumnTypes(): array
    {
        $class = config('laravel-sms.user_model.class');

        if (! is_string($class) || ! class_exists($class)) {
            return [];
        }

        try {
            $model = new $class;

            $types = [];

            foreach (DbSchema::connection($model->getConnectionName())->getColumns($model->getTable()) as $column) {
                $types[$column['name']] = $this->normalizeType((string) ($column['type_name'] ?? $column['type'] ?? ''));
            }

            return $types;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Collapse a database type name to one of our simple value categories.
     */
    protected function normalizeType(string $type): string
    {
        $type = strtolower($type);

        return match (true) {
            str_contains($type, 'bool') => 'boolean',
            str_contains($type, 'int') || str_contains($type, 'serial') || str_contains($type, 'numeric')
                || str_contains($type, 'decimal') || str_contains($type, 'double') || str_contains($type, 'float')
                || str_contains($type, 'real') => 'number',
            str_contains($type, 'timestamp') || str_contains($type, 'datetime') => 'datetime',
            str_contains($type, 'date') => 'date',
            default => 'string',
        };
    }
}
