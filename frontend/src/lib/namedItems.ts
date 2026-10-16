import type { NamedItemInput } from '../api/types';

/** Řádek editoru seznamu (rozšíření, postavy): `key` je jen klientský klíč, `id` mají už uložené položky. */
export interface EditItem {
  key: number;
  id?: number;
  name: string;
}

let counter = 0;
export const nextItemKey = (): number => ++counter;

export const toEditItems = (items: { id: number; name: string }[]): EditItem[] => items.map((i) => ({ key: nextItemKey(), id: i.id, name: i.name }));

/** Pro API: bez prázdných názvů, uložené položky si drží `id` (přejmenování místo smazání a založení). */
export function fromEditItems(items: EditItem[]): NamedItemInput[] {
  return items
    .map((i) => ({ ...(i.id !== undefined ? { id: i.id } : {}), name: i.name.trim() }))
    .filter((i) => i.name !== '');
}
