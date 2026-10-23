/** Položka filtru statistik: hra nebo tag hry; `include` = jen takové hry (zobrazeno „+“), `exclude` = bez nich („−“). */
export type FilterItem =
  | { kind: 'game'; id: number; name: string; mode: 'include' | 'exclude' }
  | { kind: 'tag'; name: string; mode: 'include' | 'exclude' };

export const itemKey = (item: FilterItem): string => (item.kind === 'game' ? `game:${item.id}` : `tag:${item.name}`);

/** Přidá položku jako „+“; už vybraná se nezdvojí. */
export function addItem(items: FilterItem[], item: FilterItem): FilterItem[] {
  return items.some((i) => itemKey(i) === itemKey(item)) ? items : [...items, item];
}

export const removeItem = (items: FilterItem[], item: FilterItem): FilterItem[] => items.filter((i) => itemKey(i) !== itemKey(item));

/** Přepne „+“ ↔ „−“. */
export const toggleItem = (items: FilterItem[], item: FilterItem): FilterItem[] =>
  items.map((i) => (itemKey(i) === itemKey(item) ? { ...i, mode: i.mode === 'include' ? 'exclude' : 'include' } : i));

/** Parametry pro `GET /stats`; seřazené, ať je klíč dotazu stabilní. */
export function toStatsQuery(items: FilterItem[]) {
  const names = (kind: 'tag', mode: FilterItem['mode']) => items.flatMap((i) => (i.kind === kind && i.mode === mode ? [i.name] : [])).sort();
  const ids = (mode: FilterItem['mode']) => items.flatMap((i) => (i.kind === 'game' && i.mode === mode ? [i.id] : [])).sort((a, b) => a - b);
  return { tags: names('tag', 'include'), excludeTags: names('tag', 'exclude'), games: ids('include'), excludeGames: ids('exclude') };
}
