<script setup>
import { Link, router } from '@inertiajs/vue3';

import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import { formatPrice } from '../../format';

defineProps({
    alerts: { type: Array, required: true },
    flash: { type: String, default: null },
});

function markAllRead() {
    router.post('/saved-searches/alerts/read', {}, { preserveScroll: true });
}

const cardClasses = 'rounded-lg border border-slate-200 bg-white p-4';
</script>

<template>
    <AppLayout
        heading="Alerts"
        subheading="New listings that matched one of your saved searches."
    >
        <p v-if="flash" class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ flash }}
        </p>

        <div v-if="alerts.length > 0" class="mb-4 flex justify-end">
            <button
                type="button"
                class="text-sm text-slate-500 underline-offset-2 hover:underline"
                @click="markAllRead"
            >
                Mark all as read
            </button>
        </div>

        <p v-if="alerts.length === 0" class="text-sm text-slate-500">
            No alerts yet. Save a search and we will let you know when something
            new matches it.
        </p>

        <ul v-else class="flex flex-col gap-3">
            <li
                v-for="alert in alerts"
                :key="alert.id"
                :class="[cardClasses, alert.is_read ? 'opacity-70' : 'border-slate-900/10']"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-medium text-slate-900">
                            {{ alert.address_line_1 ?? 'A listing we could not find' }}
                        </p>
                        <p class="text-sm text-slate-500">
                            <template v-if="alert.price">
                                {{ formatPrice(alert.price) }} ·
                            </template>
                            <template v-if="alert.reference">
                                {{ alert.reference }}
                            </template>
                            <span v-if="!alert.is_read" class="ml-1 font-medium text-slate-900">
                                New
                            </span>
                        </p>
                    </div>

                    <Link
                        v-if="alert.is_live"
                        :href="`/listings/${alert.listing_id}`"
                        class="text-sm font-medium text-slate-900 underline-offset-2 hover:underline"
                    >
                        View listing
                    </Link>
                    <span v-else class="text-sm text-slate-400">
                        No longer available
                    </span>
                </div>
            </li>
        </ul>
    </AppLayout>
</template>
