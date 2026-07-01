<?php

namespace Rayzenai\LaravelSms\Tests\Unit\Segments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use Rayzenai\LaravelSms\Segments\SegmentConditions;
use Rayzenai\LaravelSms\Segments\SegmentQuery;
use Rayzenai\LaravelSms\Tests\TestCase;

class SegmentQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('segment_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('orders_count')->default(0);
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        SegmentUser::insert([
            ['name' => 'Aarav', 'country' => 'Nepal', 'is_active' => true, 'orders_count' => 5, 'phone' => '+9779800000001', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Bimala', 'country' => 'Nepal', 'is_active' => false, 'orders_count' => 0, 'phone' => '+9779800000002', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Chirag', 'country' => 'India', 'is_active' => true, 'orders_count' => 2, 'phone' => null, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Deepa', 'country' => null, 'is_active' => true, 'orders_count' => 9, 'phone' => '+9779800000004', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     */
    protected function segmentCount(array $children, string $logic = 'and'): int
    {
        return SegmentQuery::for(SegmentUser::class, ['logic' => $logic, 'children' => $children])->count();
    }

    public function test_empty_tree_matches_everyone(): void
    {
        $this->assertSame(4, SegmentQuery::for(SegmentUser::class, [])->count());
        $this->assertSame(4, $this->segmentCount([]));
    }

    public function test_equals_operator(): void
    {
        $this->assertSame(2, $this->segmentCount([
            ['field' => 'country', 'op' => '=', 'value' => 'Nepal'],
        ]));
    }

    public function test_not_equal_and_comparison_operators(): void
    {
        $this->assertSame(1, $this->segmentCount([
            ['field' => 'country', 'op' => '!=', 'value' => 'Nepal'],
        ]));

        $this->assertSame(2, $this->segmentCount([
            ['field' => 'orders_count', 'op' => '>=', 'value' => 5],
        ]));

        $this->assertSame(2, $this->segmentCount([
            ['field' => 'orders_count', 'op' => '<', 'value' => 3],
        ]));
    }

    public function test_boolean_and_combines_conditions(): void
    {
        $this->assertSame(1, $this->segmentCount([
            ['field' => 'country', 'op' => '=', 'value' => 'Nepal'],
            ['field' => 'is_active', 'op' => '=', 'value' => true],
        ], 'and'));
    }

    public function test_top_level_or(): void
    {
        $this->assertSame(3, $this->segmentCount([
            ['field' => 'country', 'op' => '=', 'value' => 'India'],
            ['field' => 'orders_count', 'op' => '>=', 'value' => 5],
        ], 'or'));
    }

    public function test_nested_groups_precedence(): void
    {
        // (country = Nepal OR country = India) AND is_active = true
        $count = $this->segmentCount([
            ['logic' => 'or', 'children' => [
                ['field' => 'country', 'op' => '=', 'value' => 'Nepal'],
                ['field' => 'country', 'op' => '=', 'value' => 'India'],
            ]],
            ['field' => 'is_active', 'op' => '=', 'value' => true],
        ], 'and');

        // Aarav (Nepal, active) + Chirag (India, active); Bimala inactive.
        $this->assertSame(2, $count);
    }

    public function test_contains_operator(): void
    {
        $this->assertSame(1, $this->segmentCount([
            ['field' => 'name', 'op' => 'contains', 'value' => 'eep'],
        ]));
    }

    public function test_in_operator_splits_comma_list(): void
    {
        $this->assertSame(3, $this->segmentCount([
            ['field' => 'country', 'op' => 'in', 'value' => 'Nepal, India'],
        ]));
    }

    public function test_is_set_and_is_empty_operators(): void
    {
        $this->assertSame(3, $this->segmentCount([
            ['field' => 'phone', 'op' => 'is_set', 'value' => null],
        ]));

        $this->assertSame(1, $this->segmentCount([
            ['field' => 'country', 'op' => 'is_empty', 'value' => null],
        ]));
    }

    public function test_unknown_field_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown segment field');

        $this->segmentCount([
            ['field' => 'password', 'op' => '=', 'value' => 'x'],
        ]);
    }

    public function test_injection_attempt_via_field_name_fails_safely(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // A crafted "field" that would be dangerous if interpolated is simply
        // not a real column, so it's rejected before touching SQL.
        $this->segmentCount([
            ['field' => 'name) OR (1=1', 'op' => '=', 'value' => 'x'],
        ]);
    }

    public function test_unsupported_operator_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported segment operator');

        $this->segmentCount([
            ['field' => 'name', 'op' => 'DROP', 'value' => 'x'],
        ]);
    }

    public function test_users_returns_matching_models(): void
    {
        $users = SegmentQuery::for(SegmentUser::class, ['logic' => 'and', 'children' => [
            ['field' => 'country', 'op' => '=', 'value' => 'Nepal'],
        ]])->users();

        $this->assertCount(2, $users);
        $this->assertEqualsCanonicalizing(['Aarav', 'Bimala'], $users->pluck('name')->all());
    }

    public function test_conditions_round_trip_through_form_format(): void
    {
        $tree = [
            ['logic' => 'or', 'children' => [
                ['field' => 'country', 'op' => '=', 'value' => 'Nepal'],
                ['field' => 'country', 'op' => '=', 'value' => 'India'],
            ]],
            ['field' => 'is_active', 'op' => '=', 'value' => true],
        ];

        $blocks = SegmentConditions::toForm($tree);
        $this->assertSame('group', $blocks[0]['type']);
        $this->assertSame('condition', $blocks[1]['type']);

        $this->assertEquals($tree, SegmentConditions::fromForm($blocks));
    }
}

class SegmentUser extends Model
{
    protected $table = 'segment_users';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];
}
