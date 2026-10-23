/** Text pro porovnávání při hledání: bez diakritiky, malými písmeny a bez okrajových mezer. */
export const normalize = (text: string): string => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim();
