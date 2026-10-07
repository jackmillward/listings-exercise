<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    heading: { type: String, required: true },
    subheading: { type: String, default: null },
});

const page = usePage();

// Shared by HandleInertiaRequests so the badge is right on every page rather
// than only the one that happens to query for it.
const unreadAlertsCount = computed(() => page.props.unreadAlertsCount ?? 0);

const navLinkClasses =
    'rounded-lg px-3 py-2 text-sm text-slate-600 transition hover:bg-white hover:text-slate-900';
</script>

<template>
    <div class="mx-auto max-w-5xl px-4 py-10">
        <header class="mb-8">
            <div class="flex items-center justify-between gap-4">
                <Link
                    href="/"
                    class="text-sm font-medium text-slate-500 transition hover:text-slate-900"
                >
                    Street Listings
                </Link>

                <nav class="flex items-center gap-1">
                    <Link href="/saved-searches" :class="navLinkClasses">Saved searches</Link>
                    <Link href="/saved-searches/alerts" :class="navLinkClasses">
                        Alerts
                        <span
                            v-if="unreadAlertsCount > 0"
                            class="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-slate-900 px-1.5 py-0.5 text-xs font-medium text-white"
                        >
                            {{ unreadAlertsCount }}
                        </span>
                    </Link>
                </nav>
            </div>

            <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ heading }}</h1>
            <p v-if="subheading" class="mt-1 text-slate-500">{{ subheading }}</p>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
