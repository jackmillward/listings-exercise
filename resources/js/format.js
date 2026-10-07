const priceFormatter = new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    maximumFractionDigits: 0,
});

export function formatPrice(price) {
    return priceFormatter.format(price);
}

export function formatDate(iso) {
    if (!iso) {
        return null;
    }

    return new Intl.DateTimeFormat('en-GB', { dateStyle: 'long' }).format(new Date(iso));
}

export function summariseCriteria(search) {
    const parts = [];

    if (search.property_type_label) {
        parts.push(search.property_type_label.toLowerCase());
    }

    if (search.min_bedrooms) {
        parts.push(`${search.min_bedrooms}+ beds`);
    }

    if (search.region) {
        parts.push(search.region);
    }

    if (search.max_price) {
        parts.push(`up to ${formatPrice(search.max_price)}`);
    }

    return parts.length > 0 ? parts.join(' · ') : 'Any listing';
}

