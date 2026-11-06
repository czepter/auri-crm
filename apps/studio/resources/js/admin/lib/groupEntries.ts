type Entry = { body: string; author: string | null; at: string | null };

export function groupEntries<T extends Entry>(entries: T[]): (T & { count: number })[] {
    const minute = (entry: Entry) => entry.at?.slice(0, 16);
    const groups: (T & { count: number })[] = [];

    for (const entry of entries) {
        const last = groups.at(-1);

        if (last && last.body === entry.body && last.author === entry.author && minute(last) === minute(entry)) {
            last.count++;
        } else {
            groups.push({ ...entry, count: 1 });
        }
    }

    return groups;
}
