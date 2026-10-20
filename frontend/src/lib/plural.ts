/** „1 zápis“, „2 zápisy“, „5 zápisů“. */
export function playsLabel(count: number): string {
  if (count === 1) return '1 zápis';
  if (count >= 2 && count <= 4) return `${count} zápisy`;
  return `${count} zápisů`;
}
