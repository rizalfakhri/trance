export type Modality = 'image' | 'video';

export type OptionValue = string | number | boolean;

export interface OptionChoice {
    value: string | number;
    label: string;
    hint: string | null;
}

export interface OptionDefinition {
    key: string;
    label: string;
    type: 'select' | 'number' | 'boolean';
    choices: OptionChoice[];
    default: OptionValue | null;
    min: number | null;
    max: number | null;
    step: number | null;
}

export interface ModelCapabilities {
    id: string | null;
    label: string;
    modality: Modality;
    prompt: { maxLength: number };
    options: OptionDefinition[];
    references: { maxCount: number; acceptedMimeTypes: string[]; modality: Modality } | null;
    outputsPerRequest: number;
}

export interface ProviderCapabilities {
    provider: string;
    label: string;
    logoUrl: string | null;
    darkLogoUrl: string | null;
    enabled: boolean;
    models: ModelCapabilities[];
}

export interface GenerationQuote {
    currency: string;
    provider_cost: number;
    fee: number;
    fee_basis_points: number;
    total: number;
}
