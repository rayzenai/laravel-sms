@php
    $statePath = $getStatePath();
    $columns = $field->getUserColumns();
    $columnTypes = $field->getColumnTypes();
    $operators = $field->getOperators();
    $nullary = $field->getNullaryOperators();
    $maxDepth = $field->getMaxDepth();
    $state = $getState();
    $state = (is_array($state) && isset($state['children'])) ? $state : ['logic' => 'and', 'children' => []];
    $uid = 'sb_'.\Illuminate\Support\Str::of($statePath)->slug('_');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @once
        <style>
            .sb { font-size: 0.8125rem; color: rgb(55 65 81); }
            .dark .sb { color: rgb(209 213 219); }
            .sb-toolbar, .sb-row, .sb-actions, .sb-ghead { display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap; }
            .sb-toolbar { margin-bottom: 0.375rem; }
            .sb-row { padding: 0.125rem 0; }
            .sb-group { border-left: 2px solid rgb(229 231 235); padding-left: 0.625rem; margin: 0.25rem 0 0.25rem 0.125rem; }
            .dark .sb-group { border-left-color: rgb(55 65 81); }
            .sb-ghead { margin: 0.125rem 0; }
            .sb input, .sb select {
                padding: 0.1875rem 0.4rem; border: 1px solid rgb(209 213 219); border-radius: 0.375rem;
                background: rgb(255 255 255); line-height: 1.1;
            }
            .dark .sb input, .dark .sb select { border-color: rgb(75 85 99); background: rgb(17 24 39); color: rgb(229 231 235); }
            .sb .sb-field { width: 10rem; }
            .sb .sb-value { width: 10rem; }
            .sb-x { color: rgb(156 163 175); cursor: pointer; border: 0; background: none; line-height: 1; padding: 0 0.25rem; }
            .sb-x:hover { color: rgb(239 68 68); }
            .sb-ok { color: rgb(13 148 136); cursor: pointer; border: 1px solid rgb(153 246 228); border-radius: 0.375rem; background: rgb(240 253 250); line-height: 1; padding: 0.1875rem 0.5rem; font-weight: 700; }
            .sb-ok:disabled { opacity: 0.4; cursor: not-allowed; }
            .dark .sb-ok { background: rgba(19,78,74,.4); border-color: rgb(17 94 89); color: rgb(94 234 212); }
            .sb-chip { background: rgb(240 253 250); border: 1px solid rgb(153 246 228); color: rgb(15 118 110); border-radius: 0.375rem; padding: 0.1875rem 0.5rem; font-weight: 600; }
            .dark .sb-chip { background: rgba(19,78,74,.4); border-color: rgb(17 94 89); color: rgb(94 234 212); }
            .sb-btn { color: rgb(13 148 136); cursor: pointer; border: 0; background: none; font-weight: 600; padding: 0.125rem 0.25rem; }
            .sb-btn:hover { text-decoration: underline; }
            .sb-logic {
                border: 1px solid rgb(209 213 219); border-radius: 0.375rem; padding: 0.0625rem 0.5rem;
                cursor: pointer; background: transparent; font-weight: 700; font-size: 0.6875rem; letter-spacing: 0.02em;
            }
            .dark .sb-logic { border-color: rgb(75 85 99); }
            .sb-count { margin-left: auto; font-weight: 600; color: rgb(13 148 136); }
            .sb-count.err { color: rgb(239 68 68); }
        </style>
    @endonce

    {{-- The whole component is inlined in x-data so it never depends on an external
         <script> executing (raw scripts inside Livewire components do not run).

         Each condition carries a `done` flag: a fresh row is a draft (editable, not
         evaluated); ticking it (✓) marks it done. Only done, complete conditions are
         pushed to the form state and counted — so half-typed rows never hit the DB. --}}
    <div class="sb" wire:ignore
         x-data="{
            tree: @js($state),
            statePath: @js($statePath),
            nullaryOps: @js($nullary),
            opLabels: @js($operators),
            columnTypes: @js($columnTypes),
            countLabel: '',
            countError: false,
            _timer: null,
            _lastKey: null,
            init() {
                this.normalize(this.tree);
                this.push();
                this.$watch('tree', () => { this.push(); this.refreshCount(); });
                this.refreshCount();
            },
            normalize(group) {
                (group.children || []).forEach((c) => {
                    if (c.children) { this.normalize(c); }
                    else if (c.done === undefined) { c.done = true; }
                });
            },
            isNullary(op) { return this.nullaryOps.includes(op); },
            fieldType(field) { return this.columnTypes[field] || 'string'; },
            listOrText(op) { return op === 'in' || op === 'contains'; },
            valueInputType(c) {
                if (this.listOrText(c.op)) return 'text';
                var t = this.fieldType(c.field);
                if (t === 'date') return 'date';
                if (t === 'datetime') return 'datetime-local';
                if (t === 'number') return 'number';
                return 'text';
            },
            isBooleanField(c) { return ! this.listOrText(c.op) && this.fieldType(c.field) === 'boolean'; },
            isComplete(c) {
                if (! c.field) return false;
                if (this.isNullary(c.op)) return true;
                if (c.value === '' || c.value === null || c.value === undefined) return false;
                if (this.listOrText(c.op)) return true;
                if (this.fieldType(c.field) === 'number') return ! isNaN(Number(c.value));
                return true;
            },
            isUsable(c) { return !! c.done && this.isComplete(c); },
            confirm(c) { if (this.isComplete(c)) c.done = true; },
            edit(c) { c.done = false; },
            chipText(c) {
                var label = this.opLabels[c.op] || c.op;
                return c.field + ' ' + label + (this.isNullary(c.op) ? '' : ' ' + c.value);
            },
            addCondition(group) { group.children.push({ field: '', op: '=', value: '', done: false }); },
            addGroup(group) { group.children.push({ logic: 'and', children: [] }); },
            remove(group, i) { group.children.splice(i, 1); },
            toggleLogic(group) { group.logic = (group.logic === 'and') ? 'or' : 'and'; },
            cleanGroup(group) {
                var children = [];
                (group.children || []).forEach((c) => {
                    if (c.children) {
                        var g = this.cleanGroup(c);
                        if (g.children.length) children.push(g);
                    } else if (this.isUsable(c)) {
                        var val = c.value;
                        if (! this.listOrText(c.op)) {
                            var t = this.fieldType(c.field);
                            if (t === 'number') { val = Number(val); }
                            else if (t === 'boolean') { val = (val === true || val === 'true' || val === '1' || val === 1); }
                        }
                        children.push({ field: c.field, op: c.op, value: val });
                    }
                });
                return { logic: group.logic, children: children };
            },
            cleanTree() { return this.cleanGroup(this.tree); },
            push() {
                this.$wire.set(this.statePath, this.cleanTree(), false);
            },
            refreshCount() {
                var clean = this.cleanTree();
                var key = JSON.stringify(clean);
                if (key === this._lastKey) return;
                this._lastKey = key;
                clearTimeout(this._timer);
                this._timer = setTimeout(async () => {
                    if (! clean.children.length) { this.countError = false; this.countLabel = ''; return; }
                    if (typeof this.$wire.previewSegmentCount !== 'function') { this.countLabel = ''; return; }
                    try {
                        const res = await this.$wire.previewSegmentCount(clean);
                        if (res && res.ok) {
                            this.countError = false;
                            this.countLabel = res.count + ' ' + (res.count === 1 ? 'user' : 'users') + ' match';
                        } else {
                            this.countError = true;
                            this.countLabel = (res && res.error) ? res.error : 'invalid';
                        }
                    } catch (e) {
                        this.countError = true;
                        this.countLabel = 'preview unavailable';
                    }
                }, 400);
            },
         }">
        <datalist id="{{ $uid }}_cols">
            @foreach ($columns as $col)
                <option value="{{ $col }}"></option>
            @endforeach
        </datalist>

        <div class="sb-toolbar">
            <span>Match</span>
            <button type="button" class="sb-logic"
                    @click="toggleLogic(tree)"
                    x-text="tree.logic === 'and' ? 'ALL' : 'ANY'"></button>
            <span>of the conditions</span>
            <span class="sb-count" :class="{ err: countError }" x-text="countLabel"></span>
        </div>

        @include('laravel-sms::forms.segment-group', [
            'groupExpr' => 'tree',
            'var' => 'c0',
            'depth' => $maxDepth,
            'isRoot' => true,
            'removeExpr' => null,
            'uid' => $uid,
            'operators' => $operators,
        ])
    </div>
</x-dynamic-component>
