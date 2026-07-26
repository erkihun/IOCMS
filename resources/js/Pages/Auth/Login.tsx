import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';

/**
 * Sign-in page. Errors are associated with inputs for screen readers (WCAG 2.2).
 * The server returns a single generic error for any failure so the UI cannot
 * reveal whether an account exists (FR-IAM-002).
 */
export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        identifier: '',
        password: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    const genericError = (errors as Record<string, string>).error ?? errors.identifier;

    return (
        <AppLayout title="Sign in">
            <Head title="Sign in" />
            <form onSubmit={submit} className="max-w-md space-y-4" noValidate>
                {genericError && (
                    <p role="alert" className="rounded bg-red-50 px-3 py-2 text-sm text-red-800">
                        {genericError}
                    </p>
                )}
                <div>
                    <label htmlFor="identifier" className="block text-sm font-medium">
                        Email or phone
                    </label>
                    <input
                        id="identifier"
                        type="text"
                        autoComplete="username"
                        required
                        value={data.identifier}
                        onChange={(e) => setData('identifier', e.target.value)}
                        className="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                    />
                </div>
                <div>
                    <label htmlFor="password" className="block text-sm font-medium">
                        Password
                    </label>
                    <input
                        id="password"
                        type="password"
                        autoComplete="current-password"
                        required
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        className="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                    />
                </div>
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded bg-blue-600 px-4 py-2 font-medium text-white disabled:opacity-60"
                >
                    {processing ? 'Signing in…' : 'Sign in'}
                </button>
            </form>
        </AppLayout>
    );
}
