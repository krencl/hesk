/**
 * Ikona z písma Material Symbols Outlined (načítá se v index.html); `name` je název ligatury.
 * Písmo obsahuje jen vyjmenované ikony (`icon_names` v odkazu v index.html): nová ikona se musí doplnit i tam.
 */
export function Icon({ name, className = '' }: { name: string; className?: string }) {
  return <span className={`icon ${className}`.trim()} aria-hidden>{name}</span>;
}
