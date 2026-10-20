/** Zkratka odvozená z názvu: „Deskobraní“ → „De“, „Pátek večer“ → „PV“. */
export function deriveShortcut(name: string): string {
  const words = name.trim().split(/\s+/).filter(Boolean);
  if (words.length === 0) return '?';
  if (words.length === 1) {
    const [first, second = ''] = Array.from(words[0]);
    return first.toLocaleUpperCase('cs-CZ') + second.toLocaleLowerCase('cs-CZ');
  }
  return words.slice(0, 2).map((w) => Array.from(w)[0].toLocaleUpperCase('cs-CZ')).join('');
}

/** Zkratka party do kulatého tlačítka v hlavičce: zadaná zkratka, jinak odvozená z názvu. */
export function partyShortcut(party: { name: string; shortName?: string | null }): string {
  return party.shortName?.trim() || deriveShortcut(party.name);
}
