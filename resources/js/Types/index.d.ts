import type { PageProps as InertiaPageProps } from '@inertiajs/core';

/** Authenticated actor shape shared with every Inertia page (typed props, LLD §3.8). */
export interface AuthUser {
    public_id: string;
    display_name: string;
    type: 'customer' | 'staff' | 'seller' | 'machine';
    locale: 'en' | 'am';
    permissions: string[];
    scopes: Scope[];
    mfa_enrolled: boolean;
}

export interface Scope {
    type: string;
    id: string | null;
}

export interface FlashMessages {
    success?: string;
    error?: string;
    info?: string;
}

/** Locale-neutral, safe error contract surfaced to the UI (LLD §3.7). */
export interface ApiError {
    code: string;
    message: string;
    correlation_id: string;
    fields: Record<string, string[]>;
}

export interface SharedProps {
    app: {
        name: string;
        locale: 'en' | 'am';
        available_locales: string[];
        currency: string;
    };
    auth: {
        user: AuthUser | null;
    };
    flash: FlashMessages;
    features: Record<string, boolean>;
    correlation_id: string;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> =
    T & SharedProps & InertiaPageProps;

/** Standard page lifecycle states each page must handle (LLD §3.8 / SRS §16). */
export type LoadState =
    | 'initial'
    | 'loading'
    | 'empty'
    | 'error'
    | 'validation'
    | 'success'
    | 'permission-denied'
    | 'out-of-scope'
    | 'retry'
    | 'conflict';
