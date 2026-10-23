import { type KeyboardEvent, useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { normalize } from './Autocomplete';
import { Icon } from './Icon';

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
  const dialogRef = useRef<HTMLDivElement>(null);
  const titleId = useId();

  const all = pinned ? [pinned, ...options] : options;
  const current = all.find((o) => o.value === value);

  // Hledání má být po otevření rovnou zaostřené; po zavření se zaostření vrací na tlačítko.
  useEffect(() => {
    // bez hledání se zaostří samotný popup, ať funguje Esc
    if (open) (searchRef.current ?? dialogRef.current)?.focus();
  }, [open, searchable]);

  const close = () => {
    setOpen(false);
    setQuery('');
    buttonRef.current?.focus();
  };
  const choose = (next: T) => {
    onChange(next);
    close();
  };
  const onKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      e.stopPropagation();
      close();
    }
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
      {open &&
        createPortal(
          <>
            <div ref={dialogRef} tabIndex={-1} className="select-popup" role="dialog" aria-modal="true" aria-labelledby={titleId} onKeyDown={onKeyDown}>
              <h3 id={titleId} className="select-popup-title">{title}</h3>
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
            </div>
            {/* křížek na místě kulatého tlačítka party (jako ve starém prototypu, kde se tlačítko při výběru mění na zavírací) */}
            <button type="button" className="header-button open select-popup-close" aria-label="Zavřít výběr" onClick={close}>
              <Icon name="close" />
            </button>
          </>,
          document.body,
        )}
    </>
  );
}
