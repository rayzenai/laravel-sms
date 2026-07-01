<?php

namespace Rayzenai\LaravelSms\Segments;

/**
 * Converts between the Filament Builder's block format and the clean condition
 * tree stored in the database (and understood by {@see SegmentQuery}).
 *
 * Filament block:  ['type' => 'condition'|'group', 'data' => [...]]
 * Stored node:     ['field'=>..,'op'=>..,'value'=>..]  or  ['logic'=>..,'children'=>[..]]
 */
class SegmentConditions
{
    /**
     * Filament Builder blocks -> clean tree children.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    public static function fromForm(array $blocks): array
    {
        $children = [];

        foreach ($blocks as $block) {
            $data = $block['data'] ?? [];

            if (($block['type'] ?? null) === 'group') {
                $children[] = [
                    'logic' => strtolower((string) ($data['logic'] ?? 'and')) === 'or' ? 'or' : 'and',
                    'children' => self::fromForm($data['children'] ?? []),
                ];

                continue;
            }

            $children[] = [
                'field' => $data['field'] ?? null,
                'op' => $data['op'] ?? '=',
                'value' => $data['value'] ?? null,
            ];
        }

        return $children;
    }

    /**
     * Clean tree children -> Filament Builder blocks (for editing).
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<int, array<string, mixed>>
     */
    public static function toForm(array $children): array
    {
        $blocks = [];

        foreach ($children as $node) {
            if (array_key_exists('children', $node)) {
                $blocks[] = [
                    'type' => 'group',
                    'data' => [
                        'logic' => $node['logic'] ?? 'and',
                        'children' => self::toForm($node['children'] ?? []),
                    ],
                ];

                continue;
            }

            $blocks[] = [
                'type' => 'condition',
                'data' => [
                    'field' => $node['field'] ?? null,
                    'op' => $node['op'] ?? '=',
                    'value' => $node['value'] ?? null,
                ],
            ];
        }

        return $blocks;
    }
}
