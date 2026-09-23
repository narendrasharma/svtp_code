import { appUrl } from '../../appUrl';

export function mediaUrl(value) {
    if (!value) {
        return null;
    }

    const source = String(value);

    if (/^(https?:|data:|blob:)/i.test(source)) {
        return source;
    }

    return appUrl(source.startsWith('/') ? source : `/${source}`);
}

export function safeHref(value) {
    if (!value) {
        return null;
    }

    const href = String(value);

    if (/^https:\/\//i.test(href)) {
        return href;
    }

    return /^\/(?!\/)/.test(href) ? appUrl(href) : null;
}

export function displayName(item) {
    return item?.name || item?.title || '';
}

export function durationLabel(item, t) {
    const days = Number(item?.duration_days);

    if (!Number.isFinite(days) || days < 1) {
        return null;
    }

    return `${days} ${days === 1 ? t('common.day', 'day') : t('common.days', 'days')}`;
}
