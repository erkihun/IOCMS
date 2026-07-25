import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

interface WelcomeProps {
    stats: {
        products: number;
        categories: number;
    };
}

export default function Welcome({ stats }: WelcomeProps) {
    return (
        <AppLayout title="Welcome to IOCMS">
            <Head title="Home" />
            <section aria-labelledby="intro-heading" className="space-y-4">
                <h2 id="intro-heading" className="text-xl font-semibold">
                    Trustworthy Ethiopian digital commerce
                </h2>
                <p className="max-w-2xl text-slate-600 dark:text-slate-300">
                    Transparent buying, reliable inventory, verified payment, accountable
                    fulfillment and privacy-respecting customer relationships — bilingual in
                    Amharic and English.
                </p>
                <dl className="mt-6 grid grid-cols-2 gap-4 sm:max-w-md">
                    <div className="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                        <dt className="text-sm text-slate-500">Published products</dt>
                        <dd className="text-2xl font-bold">{stats.products}</dd>
                    </div>
                    <div className="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                        <dt className="text-sm text-slate-500">Categories</dt>
                        <dd className="text-2xl font-bold">{stats.categories}</dd>
                    </div>
                </dl>
            </section>
        </AppLayout>
    );
}
