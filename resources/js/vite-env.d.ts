/// <reference types="vite/client" />

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
    glob: <T = unknown>(pattern: string) => Record<string, () => Promise<T>>;
}

/** Ziggy's global route() helper (typed loosely; server is authoritative). */
declare function route(name?: string, params?: unknown, absolute?: boolean): string;
