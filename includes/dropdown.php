<?php
/**
 * includes/dropdown.php
 * ─────────────────────────────────────────────
 * Reusable Alpine.js custom dropdown.
 * Matches the design of your original dropdowns
 * but adapted to the current theme tokens.
 *
 * Usage:
 *   renderDropdown(
 *     name:     'status',
 *     options:  ['draft'=>'Draft', 'sent'=>'Sent', 'paid'=>'Paid'],
 *     selected: $inv['status'] ?? '',
 *     placeholder: 'Select status'
 *   );
 *
 * Or with value/label pairs:
 *   renderDropdown(
 *     name:    'state_code',
 *     options: ['01'=>'Johor', '02'=>'Kedah', ...],
 *     selected: $company['state_code'] ?? ''
 *   );
 * ─────────────────────────────────────────────
 */

function renderDropdown(
    string $name,
    array  $options,
    string $selected     = '',
    string $placeholder  = 'Select...',
    bool   $required     = false,
    string $extraClasses = ''
): void {
    // Build options JSON for Alpine
    $alpineOptions = [];
    foreach ($options as $value => $label) {
        $alpineOptions[] = ['value' => (string)$value, 'text' => $label];
    }
    $optionsJson  = json_encode($alpineOptions, JSON_HEX_APOS | JSON_HEX_QUOT);
    $selectedJson = json_encode($selected);
    $req          = $required ? 'required' : '';

    echo <<<HTML
<div x-data="{
        open: false,
        value: {$selectedJson},
        options: {$optionsJson},
        get label() {
            const found = this.options.find(o => o.value === this.value);
            return found ? found.text : '{$placeholder}';
        }
     }"
     class="relative {$extraClasses}">

    <!-- Visible button -->
    <button type="button"
            @click="open = !open"
            @keydown.escape="open = false"
            class="w-full h-9 px-3 rounded-lg bg-white border border-slate-200 text-left flex items-center justify-between text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 transition hover:border-slate-300">
        <span x-text="label" :class="value === '' ? 'text-slate-400' : 'text-slate-800'"></span>
        <svg class="w-4 h-4 text-slate-400 transition-transform shrink-0"
             :class="open ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <!-- Dropdown list -->
    <div x-show="open"
         @click.outside="open = false"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute z-30 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden origin-top"
         style="display:none">
        <ul class="max-h-56 overflow-y-auto py-1">
            <template x-for="item in options" :key="item.value">
                <li>
                    <button type="button"
                            @click="value = item.value; open = false"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-700 transition-colors flex items-center justify-between"
                            :class="value === item.value ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-700'">
                        <span x-text="item.text"></span>
                        <svg x-show="value === item.value" class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                </li>
            </template>
        </ul>
    </div>

    <!-- Hidden native select for form submission & browser validation -->
    <select name="{$name}" x-model="value" {$req}
            class="absolute opacity-0 pointer-events-none w-0 h-0 top-0 left-0" tabindex="-1"
            aria-hidden="true">
        <option value="" disabled><?= htmlspecialchars($placeholder) ?></option>
        HTML;

    foreach ($options as $value => $label) {
        $sel = ($selected === (string)$value) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars((string)$value) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
    }

    echo <<<HTML
    </select>

</div>
HTML;
}
