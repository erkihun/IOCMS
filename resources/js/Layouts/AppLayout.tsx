import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren, ReactNode } from 'react';
import type { PageProps } from '@/Types';

interface AppLayoutProps {
    title?: string;
    header?: ReactNode;
}

/**
 * Storefront/base layout with semantic landmarks, skip link and keyboard-operable
 * navigation (WCAG 2.2 AA — NFR-IC). Reads shared props (auth, locale, flash).
 */
export default function AppLayout({ title, header, children }: PropsWithChildren<AppLayoutProps>) {
    const { props } = usePage<PageProps>();
    const { app, auth, flash } = props;

    return (
        <div className="flex min-h-screen flex-col">
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-blue-600 focus:px-3 focus:py-2 focus:text-white"
            >
                Skip to content
            </a>

            <header className="border-b border-slate-200 dark:border-slate-800">
                <nav
                    aria-label="Primary"
                    className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3"
                >
                    <Link href="/" className="text-lg font-semibold">
                        {app.name}
                    </Link>
                    <div className="flex items-center gap-4 text-sm">
                        <span aria-hidden className="text-slate-500">
                            {app.locale.toUpperCase()} · {app.currency}
                        </span>
                        {auth.user ? (
                            <Link href="/account" className="font-medium text-blue-600">
                                {auth.user.display_name}
                            </Link>
                        ) : (
                            <Link href="/login" className="font-medium text-blue-600">
                                Sign in
                            </Link>
                        )}
                    </div>
                </nav>
            </header>

            {flash.success && (
                <div role="status" className="bg-green-50 px-4 py-2 text-sm text-green-800">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div role="alert" className="bg-red-50 px-4 py-2 text-sm text-red-800">
                    {flash.error}
                </div>
            )}

            {header && (
                <div className="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900">
                    <div className="mx-auto max-w-6xl px-4 py-6">{header}</div>
                </div>
            )}

            <main id="main" className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
                {title && <h1 className="mb-6 text-2xl font-bold">{title}</h1>}
                {children}
            </main>

            <footer className="border-t border-slate-200 py-6 text-center text-sm text-slate-500 dark:border-slate-800">
                <p>{app.name} — Integrated Online Commerce &amp; Marketplace System</p>
            </footer>
        </div>
    );
}
