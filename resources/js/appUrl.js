export function appUrl(path) {
    const base = document.querySelector('meta[name="app-base"]')?.content || '';
    return base + path;
}
