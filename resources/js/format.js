export function formatDuration(totalSeconds) {
    const seconds = Math.max(0, Math.round(totalSeconds || 0));
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

export function formatFileSize(bytes) {
    if (!bytes) return '';

    const units = ['Б', 'КБ', 'МБ', 'ГБ'];
    let size = bytes;
    let i = 0;
    while (size >= 1024 && i < units.length - 1) {
        size /= 1024;
        i++;
    }

    return `${size.toFixed(i === 0 || size >= 10 ? 0 : 1)} ${units[i]}`;
}
