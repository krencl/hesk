const pad = (n: number) => String(n).padStart(2, '0');

/** Date → hodnota pro <input type="datetime-local"> (lokální čas, bez zóny). */
export function toLocalInput(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** Hodnota z <input type="datetime-local"> → ISO 8601 v UTC pro API. */
export function fromLocalInput(value: string): string {
  return new Date(value).toISOString();
}

export function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('cs-CZ', { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('cs-CZ', { day: 'numeric', month: 'long', year: 'numeric' });
}
