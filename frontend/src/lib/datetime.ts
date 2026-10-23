const pad = (n: number) => String(n).padStart(2, '0');

/** Date → hodnota pro <input type="datetime-local"> (lokální čas, bez zóny). */
export function toLocalInput(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** Hodnota z <input type="datetime-local"> → ISO 8601 v UTC pro API. */
export function fromLocalInput(value: string): string {
  return new Date(value).toISOString();
}

/** Kompaktní tvar jako ve starém prototypu: „6.2.2022 21:06“. */
export function formatDateTimeShort(iso: string): string {
  const d = new Date(iso);
  return `${d.getDate()}.${d.getMonth() + 1}.${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** Jen datum v kompaktním tvaru: „6.2.2022“. */
export function formatDateShort(iso: string): string {
  const d = new Date(iso);
  return `${d.getDate()}.${d.getMonth() + 1}.${d.getFullYear()}`;
}

