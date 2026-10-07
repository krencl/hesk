/** Ikona z písma Material Symbols Outlined (načítá se v index.html); `name` je název ligatury. */
export function Icon({ name, className = '' }: { name: string; className?: string }) {
  return <span className={`icon ${className}`.trim()} aria-hidden>{name}</span>;
}
