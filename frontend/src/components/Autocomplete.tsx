import { type KeyboardEvent, type ReactNode, useId, useRef, useState } from 'react';
import { normalize } from '../lib/text';

interface Props {
  id: string;
  value: string;
  onChange: (value: string) => void;
  /** Nabízené hodnoty; po zadání textu se zužují na ty, které ho obsahují. */
  options: string[];
  /** Výběr z nabídky (výchozí: hodnota se vloží do inputu). */
  onPick?: (option: string) => void;
  /** Enter bez vybrané nabídky a opuštění inputu (pro tagy: napsaný text se přidá). */
  onCommit?: (value: string) => void;
  maxLength?: number;
  placeholder?: string;
  /** Obsah uvnitř rámečku před inputem (např. štítky tagů). */
  prefix?: ReactNode;
  /** Po výběru nechat nabídku otevřenou (a input zaostřený), aby šlo vybírat dál. */
  keepOpen?: boolean;
  /** Backspace v prázdném inputu (v rámečku s štítky odebere poslední). */
  onBackspaceEmpty?: () => void;
}

/**
 * Input s nabídkou (combobox): po zaostření se ukáže seznam známých hodnot a při psaní se zužuje.
 * Hodnotu, která v nabídce není, lze prostě napsat – nové se zakládá až při uložení formuláře.
 */
export function Autocomplete({ id, value, onChange, options, onPick, onCommit, maxLength, placeholder, prefix, keepOpen = false, onBackspaceEmpty }: Props) {
  const listId = useId();
  const inputRef = useRef<HTMLInputElement>(null);
  const [open, setOpen] = useState(false);
  const [filtering, setFiltering] = useState(false);
  const [active, setActive] = useState(-1);

  const query = normalize(value);
  const shown = filtering && query ? options.filter((o) => normalize(o).includes(query)) : options;
  const expanded = open && shown.length > 0;

  const pick = (option: string) => {
    if (onPick) onPick(option);
    else onChange(option);
    setOpen(keepOpen);
    setFiltering(false);
    setActive(-1);
  };

  const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && shown.length > 0) {
      e.preventDefault();
      setOpen(true);
      const last = shown.length - 1;
      setActive((a) => (e.key === 'ArrowDown' ? (a >= last ? 0 : a + 1) : a <= 0 ? last : a - 1));
    } else if (e.key === 'Enter') {
      if (expanded && active >= 0) {
        e.preventDefault();
        pick(shown[active]);
      } else if (onCommit) {
        e.preventDefault();
        onCommit(value);
      }
    } else if (e.key === 'Backspace' && value === '' && onBackspaceEmpty) {
      onBackspaceEmpty();
    } else if (e.key === 'Escape' && open) {
      e.preventDefault();
      setOpen(false);
    }
  };

  return (
    <div className="combo">
      {/* klik do rámečku mimo input (na volné místo vedle štítků) zaostří input */}
      <div className="combo-box" onMouseDown={(e) => { if (e.target !== inputRef.current && !(e.target as HTMLElement).closest('button')) { e.preventDefault(); inputRef.current?.focus(); } }}>
        {prefix}
        <input
          ref={inputRef}
          id={id}
          role="combobox"
          aria-expanded={expanded}
          aria-controls={listId}
          aria-autocomplete="list"
          aria-activedescendant={expanded && active >= 0 ? `${listId}-${active}` : undefined}
          autoComplete="off"
          value={value}
          maxLength={maxLength}
          placeholder={placeholder}
          onFocus={() => { setOpen(true); setFiltering(false); }}
          onBlur={() => { setOpen(false); setActive(-1); onCommit?.(value); }}
          onChange={(e) => { onChange(e.target.value); setOpen(true); setFiltering(true); setActive(-1); }}
          onKeyDown={onKeyDown}
        />
      </div>
      <ul id={listId} role="listbox" className="combo-list" hidden={!expanded}>
        {shown.map((o, i) => (
          // mouseDown nesmí přenést zaostření z inputu, jinak by se nabídka zavřela dřív než proběhne klik
          <li key={o} id={`${listId}-${i}`} role="option" aria-selected={i === active} onMouseDown={(e) => e.preventDefault()} onClick={() => pick(o)}>
            {o}
          </li>
        ))}
      </ul>
    </div>
  );
}
