{{--
    Recursive group renderer for the segment builder.

    Params (all passed via @include):
      $groupExpr  Alpine expression for this group object (e.g. 'tree' or 'c0')
      $var        unique loop-variable name for this level's children
      $depth      remaining nesting allowed (0 = no more sub-groups)
      $isRoot     true for the outermost group (no header/remove of its own)
      $removeExpr Alpine statement that removes this group from its parent (null at root)
      $uid        unique id prefix (for the columns datalist)
      $operators  field operator options
--}}
<div @class(['sb-group' => ! $isRoot])>
    @unless ($isRoot)
        <div class="sb-ghead">
            <button type="button" class="sb-logic"
                    @click="toggleLogic({{ $groupExpr }})"
                    x-text="{{ $groupExpr }}.logic === 'and' ? 'ALL' : 'ANY'"></button>
            <span style="opacity:.6">of</span>
            <button type="button" class="sb-x" @click="{{ $removeExpr }}" title="Remove group">✕</button>
        </div>
    @endunless

    <template x-for="({{ $var }}, {{ $var }}_i) in {{ $groupExpr }}.children" :key="{{ $var }}_i">
        <div>
            {{-- Leaf condition --}}
            <div x-show="! {{ $var }}.children">
                {{-- Draft: editable, tick to confirm --}}
                <template x-if="! {{ $var }}.done">
                    <div class="sb-row">
                        <input class="sb-field" type="text" list="{{ $uid }}_cols" placeholder="field"
                               x-model="{{ $var }}.field">
                        <select x-model="{{ $var }}.op">
                            @foreach ($operators as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        {{-- boolean fields get a true/false dropdown --}}
                        <select class="sb-value" x-model="{{ $var }}.value"
                                x-show="! isNullary({{ $var }}.op) && isBooleanField({{ $var }})">
                            <option value="">—</option>
                            <option value="true">true</option>
                            <option value="false">false</option>
                        </select>
                        {{-- everything else: input type follows the column type --}}
                        <input class="sb-value" :type="valueInputType({{ $var }})" placeholder="value"
                               x-model="{{ $var }}.value"
                               x-show="! isNullary({{ $var }}.op) && ! isBooleanField({{ $var }})">
                        <button type="button" class="sb-ok"
                                @click="confirm({{ $var }})"
                                :disabled="! isComplete({{ $var }})"
                                title="Done">✓</button>
                        <button type="button" class="sb-x" @click="remove({{ $groupExpr }}, {{ $var }}_i)" title="Remove">✕</button>
                    </div>
                </template>

                {{-- Done: compact chip, click edit to reopen --}}
                <template x-if="!! {{ $var }}.done">
                    <div class="sb-row">
                        <span class="sb-chip" x-text="chipText({{ $var }})"></span>
                        <button type="button" class="sb-btn" @click="edit({{ $var }})" title="Edit">edit</button>
                        <button type="button" class="sb-x" @click="remove({{ $groupExpr }}, {{ $var }}_i)" title="Remove">✕</button>
                    </div>
                </template>
            </div>

            {{-- Nested group --}}
            <div x-show="!! {{ $var }}.children">
                @if ($depth > 0)
                    @include('laravel-sms::forms.segment-group', [
                        'groupExpr' => $var,
                        'var' => $var.'x',
                        'depth' => $depth - 1,
                        'isRoot' => false,
                        'removeExpr' => 'remove('.$groupExpr.', '.$var.'_i)',
                        'uid' => $uid,
                        'operators' => $operators,
                    ])
                @endif
            </div>
        </div>
    </template>

    <div class="sb-actions">
        <button type="button" class="sb-btn" @click="addCondition({{ $groupExpr }})">+ condition</button>
        @if ($depth > 0)
            <button type="button" class="sb-btn" @click="addGroup({{ $groupExpr }})">+ group ( )</button>
        @endif
    </div>
</div>
