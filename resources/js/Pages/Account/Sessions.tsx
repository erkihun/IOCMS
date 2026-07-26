import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

interface SessionRow {
    public_id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_active_at: string | null;
    is_current: boolean;
}

interface SessionsProps {
    sessions: { data: SessionRow[] };
}

/**
 * Active-session management (FR-IAM-008). Revoking a session is a destructive
 * action confirmed before the server-side revalidated request (LLD §3.8).
 */
export default function Sessions({ sessions }: SessionsProps) {
    const revoke = (id: string) => {
        if (confirm('Revoke this session? Any device using it will be signed out.')) {
            router.delete(`/account/sessions/${id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout title="Active sessions">
            <Head title="Sessions" />
            {sessions.data.length === 0 ? (
                <p className="text-slate-600">No active sessions.</p>
            ) : (
                <ul className="divide-y divide-slate-200 rounded border border-slate-200">
                    {sessions.data.map((s) => (
                        <li key={s.public_id} className="flex items-center justify-between p-4">
                            <div>
                                <p className="font-medium">
                                    {s.ip_address ?? 'Unknown IP'}
                                    {s.is_current && (
                                        <span className="ml-2 rounded bg-green-100 px-2 py-0.5 text-xs text-green-800">
                                            This device
                                        </span>
                                    )}
                                </p>
                                <p className="text-sm text-slate-500">{s.user_agent ?? 'Unknown device'}</p>
                                <p className="text-xs text-slate-400">Last active: {s.last_active_at ?? '—'}</p>
                            </div>
                            {!s.is_current && (
                                <button
                                    type="button"
                                    onClick={() => revoke(s.public_id)}
                                    className="rounded border border-red-300 px-3 py-1 text-sm text-red-700 hover:bg-red-50"
                                >
                                    Revoke
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
