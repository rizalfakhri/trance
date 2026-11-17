import type { ModelCapabilities, OptionChoice, OptionDefinition, OptionValue } from '@/types/providers';

export function optionChoices(option: OptionDefinition): OptionChoice[] {
    if (option.type === 'select') {
        return option.choices;
    }

    if (option.type === 'boolean') {
        return [
            { value: 'true', label: 'On', hint: null },
            { value: 'false', label: 'Off', hint: null },
        ];
    }

    const choices: OptionChoice[] = [];

    for (let value = option.min ?? 0; value <= (option.max ?? 0); value += option.step ?? 1) {
        choices.push({ value, label: String(value), hint: null });
    }

    return choices;
}

export function parseOptionValue(option: OptionDefinition, value: string): OptionValue {
    if (option.type === 'boolean') {
        return value === 'true';
    }

    return option.type === 'number' ? Number(value) : value;
}

export function formatOptionValue(option: OptionDefinition, value: OptionValue | undefined): string {
    const choice = optionChoices(option).find((candidate) => String(candidate.value) === String(value));

    return choice?.label ?? String(value ?? '');
}

export function defaultOptionValues(model: ModelCapabilities): Record<string, OptionValue> {
    const values: Record<string, OptionValue> = {};

    for (const option of model.options) {
        const fallback = optionChoices(option)[0]?.value;
        const value = option.default ?? (fallback === undefined ? null : parseOptionValue(option, String(fallback)));

        if (value !== null) {
            values[option.key] = value;
        }
    }

    return values;
}
