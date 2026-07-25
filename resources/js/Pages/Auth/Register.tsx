import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';

/**
 * Customer registration page. Client validation mirrors server rules for
 * immediate feedback but never replaces the server (NFR-SE-004). Errors are
 * associated with their inputs for screen readers (WCAG 2.2 — NFR-IC).
 */
export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        identifier: '',
        password: '',
        display_name: '',
        locale: 'en',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/register');
    };

    return (
        <AppLayout title="Create your account">
            <Head title="Register" />
            <form onSubmit={submit} className="max-w-md space-y-4" noValidate>
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
                        aria-invalid={Boolean(errors.identifier)}
                        aria-describedby={errors.identifier ? 'identifier-error' : undefined}
                        className="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                    />
                    {errors.identifier && (
                        <p id="identifier-error" role="alert" className="mt-1 text-sm text-red-700">
                            {errors.identifier}
                        </p>
                    )}
                </div>

                <div>
                    <label htmlFor="password" className="block text-sm font-medium">
                        Password (min 12 characters)
                    </label>
                    <input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        required
                        minLength={12}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        aria-invalid={Boolean(errors.password)}
                        aria-describedby={errors.password ? 'password-error' : undefined}
                        className="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                    />
                    {errors.password && (
                        <p id="password-error" role="alert" className="mt-1 text-sm text-red-700">
                            {errors.password}
                        </p>
                    )}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded bg-blue-600 px-4 py-2 font-medium text-white disabled:opacity-60"
                >
                    {processing ? 'Creating…' : 'Create account'}
                </button>
            </form>
        </AppLayout>
    );
}
