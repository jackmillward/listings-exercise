<script setup>
import { Link, router } from '@inertiajs/vue3';

import AppLayout from '../../components/AppLayout.vue';
import { summariseCriteria } from '../../format';

const props = defineProps({
    savedSearches: { type: Array, required: true },
    flash: { type: String, default: null },
});

// "View" runs the search: the criteria come back from the server as query
// parameters, so the link cannot disagree with the search it was saved from.
function searchUrl(criteria) {
    const params = new URLSearchParams(criteria);

    return params.toString() ? `/?${params}` : '/';
}

function destroy(id) {
    router.delete(`/saved-searches/${id}`, { preserveScroll: true });
}

const cardClasses = 'rounded-lg border border-slate-200 bg-white p-4';
</script>

<template>
    <AppLayout
        heading="Saved searches"
        subheading="We will alert you when a new listing matches one of these."
    >
        <p v-if="flash" class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ flash }}
        </p>

        <p v-if="savedSearches.length === 0" class="text-sm text-slate-500">
            You have not saved any searches yet. Set some filters on the
            <Link href="/" class="font-medium text-slate-900 underline">listings page</Link>
            to start getting alerts.
        </p>

        <ul v-else class="flex flex-col gap-3">
            <li
                v-for="savedSearch in savedSearches"
                :key="savedSearch.id"
                :class="cardClasses"
                class="flex flex-wrap items-center justify-between gap-3"
            >
                <div>
                    <p class="font-medium text-slate-900">
                        {{ savedSearch.name || summariseCriteria(savedSearch) }}
                    </p>
                    <p v-if="savedSearch.name" class="text-sm text-slate-500">
                        {{ summariseCriteria(savedSearch) }}
                    </p>
                </div>

                <div class="flex items-center gap-4 text-sm">
                    <Link
                        :href="searchUrl(savedSearch.criteria)"
                        class="font-medium text-slate-900 underline-offset-2 hover:underline"
                    >
                        View results
                    </Link>
                    <button
                        type="button"
                        class="text-slate-500 underline-offset-2 hover:text-red-700 hover:underline"
                        @click="destroy(savedSearch.id)"
                    >
                        Delete
                    </button>
                </div>
            </li>
        </ul>
    </AppLayout>
</template>
