/** „1 zápis“, „2 zápisy“, „5 zápisů“. */
export function playsLabel(count: number): string {
  if (count === 1) return '1 zápis';
  if (count >= 2 && count <= 4) return `${count} zápisy`;
  return `${count} zápisů`;
}

/** „1 výhra“, „2 výhry“, „5 výher“. */
export function winsLabel(count: number): string {
  if (count === 1) return '1 výhra';
  if (count >= 2 && count <= 4) return `${count} výhry`;
  return `${count} výher`;
}
