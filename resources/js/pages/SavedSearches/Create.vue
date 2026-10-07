<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

import AppLayout from '../../components/AppLayout.vue';
import ListingFilters from '../../components/ListingFilters.vue';

const props = defineProps({
    branches: { type: Array, required: true },
    propertyTypes: { type: Array, required: true },
    criteria: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});

// Seeded from the query string the listings page linked with, so the user is
// saving the search they were looking at.
const form = ref({
    property_type: props.criteria.property_type ?? '',
    region: props.criteria.region ?? '',
    min_bedrooms: props.criteria.min_bedrooms ?? '',
    max_price: props.criteria.max_price ?? '',
    name: '',
});

const processing = ref(false);

function save() {
    // Blank fields are dropped rather than sent empty, so the stored criteria
    // are the ones the user actually set.
    const query = Object.fromEntries(
        Object.entries(form.value).filter(([, value]) => value !== '' && value !== null),
    );

    router.post(
        '/saved-searches',
        query,
        {
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        },
    );
}

const fieldClasses =
    'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900';
</script>

<template>
    <Head title="Save this search" />

    <AppLayout
        heading="Save this search"
        subheading="We will alert you when a new listing matches."
    >
        <form class="flex flex-col gap-4" @submit.prevent="save">
            <!--
                `inline`: this page owns the only form, so the filters render
                without one. A nested form would double-fire `save`, because a
                submit event still bubbles to the outer form after `.prevent`.
            -->
            <ListingFilters
                v-model="form"
                :branches="branches"
                :property-types="propertyTypes"
                :processing="processing"
                :errors="errors"
                inline
                @submit="save"
                @reset="form = { ...form, property_type: '', region: '', min_bedrooms: '', max_price: '' }"
            />

            <p v-if="errors.criteria" class="text-sm text-red-700" role="alert">
                {{ errors.criteria }}
            </p>

            <div class="flex max-w-xs flex-col gap-1">
                <label for="name" class="text-xs font-medium text-slate-600">
                    Name (optional)
                </label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    maxlength="100"
                    placeholder="e.g. Green belt 3-bed"
                    :class="fieldClasses"
                />
                <p v-if="errors.name" class="text-xs text-red-700" role="alert">
                    {{ errors.name }}
                </p>
                <p class="text-xs text-slate-500">
                    Leave this blank and we will describe the search for you.
                </p>
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="processing"
                    class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:opacity-50"
                >
                    Save search
                </button>

                <a href="/saved-searches" class="text-sm text-slate-500 underline-offset-2 hover:underline">
                    Manage saved searches
                </a>
            </div>
        </form>
    </AppLayout>
</template>
