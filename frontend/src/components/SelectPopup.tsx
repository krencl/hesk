import { useRef, useState } from 'react';
import { normalize } from './Autocomplete';
import { Icon } from './Icon';
import { Popup } from './Popup';

export interface SelectOption<T extends string | number | null> {
  value: T;
  label: string;
}

interface Props<T extends string | number | null> {
  title: string;
  value: T;
  options: SelectOption<T>[];
  onChange: (value: T) => void;
  /** Volba, která se ukazuje vždy a nejde jí hledat (např. „Všechny“ = žádný filtr). */
  pinned?: SelectOption<T>;
  className?: string;
  /** Hledání v popupu (výchozí zapnuto); u krátkých seznamů, jako jsou role, se vypíná. */
  searchable?: boolean;
}

/**
 * Náhrada za `<select>` podle starého prototypu: tlačítko s aktuální volbou otevře stránku, která překryje obsah;
 * nahoře je hledání (ihned zaostřené), pod ním volby. Křížek, Esc nebo výběr popup zavře.
 */
export function SelectPopup<T extends string | number | null>({ title, value, options, onChange, pinned, className = '', searchable = true }: Props<T>) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const buttonRef = useRef<HTMLButtonElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);

  const all = pinned ? [pinned, ...options] : options;
  const current = all.find((o) => o.value === value);

  const close = () => {
    setOpen(false);
    setQuery('');
  };
  const choose = (next: T) => {
    onChange(next);
    close();
  };
  const q = normalize(query);
  const matching = options.filter((o) => !q || normalize(o.label).includes(q));
  // vybraná volba zůstane vidět i mimo hledání (zeslabená), ať je jasné, co je zrovna nastaveno
  const selectedHidden = options.find((o) => o.value === value && !matching.includes(o));
  const shown = [...(pinned ? [pinned] : []), ...matching, ...(selectedHidden ? [selectedHidden] : [])];

  return (
    <>
      <button type="button" ref={buttonRef} className={`select-button ${className}`.trim()} aria-haspopup="dialog" aria-label={title} onClick={() => setOpen(true)}>
        <span className="select-button-text">{current?.label ?? ''}</span>
        <Icon name="arrow_drop_down" />
      </button>
      {open && (
        // hledání se po otevření zaostří hned; bez něj samotný popup (kvůli Esc); zavřením se zaostření vrací na tlačítko
        <Popup title={title} onClose={close} initialFocusRef={searchRef} returnFocusRef={buttonRef} closeLabel="Zavřít výběr">
          {searchable && <input
            ref={searchRef}
            type="search"
            className="select-popup-search"
            placeholder="Hledat…"
            aria-label={`Hledat – ${title}`}
            autoComplete="off"
            autoFocus
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />}
          <ul className="select-popup-list" role="listbox" aria-label={title}>
            {shown.map((o) => {
              const selected = o.value === value;
              const faded = selected && o !== pinned && !matching.includes(o);
              return (
                <li key={String(o.value)} role="presentation">
                  <button type="button" role="option" aria-selected={selected} className={`select-option${faded ? ' faded' : ''}`} onClick={() => choose(o.value)}>
                    <Icon name={selected ? 'radio_button_checked' : 'radio_button_unchecked'} className="select-option-icon" />
                    {o.label}
                  </button>
                </li>
              );
            })}
            {matching.length === 0 && <li role="presentation" className="select-popup-empty">Nic nenalezeno.</li>}
          </ul>
        </Popup>
      )}
    </>
  );
}
