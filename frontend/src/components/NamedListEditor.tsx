import { useState } from 'react';
import { type EditItem, nextItemKey } from '../lib/namedItems';
import { Icon } from './Icon';

interface Props {
  label: string;
  items: EditItem[];
  onChange: (items: EditItem[]) => void;
  /** Podstatné jméno pro popisky, např. „rozšíření“ → „Rozšíření 1“. */
  noun: string;
  /** Totéž ve 4. pádě pro tlačítko Odebrat („Odebrat postavu 1“); výchozí je shodné s `noun`. */
  nounAccusative?: string;
  addLabel: string;
  emptyText: string;
  /** Vysvětlivka pod seznamem. */
  hint?: string;
  /** Nejvyšší délka názvu (výchozí 150). */
  maxLength?: number;
}

/** Editor seznamu pojmenovaných položek: samostatné pole pro každou, Enter přidá další, křížek odebere. */
export function NamedListEditor({ label, items, onChange, noun, nounAccusative = noun, addLabel, emptyText, hint, maxLength = 150 }: Props) {
  const [focusKey, setFocusKey] = useState<number | null>(null);
  const add = () => {
    const key = nextItemKey();
    setFocusKey(key);
    onChange([...items, { key, name: '' }]);
  };
  const noun1 = noun.charAt(0).toUpperCase() + noun.slice(1);

  return (
    <div className="field">
      <span className="field-label">{label}</span>
      {items.length === 0 && <span className="muted">{emptyText}</span>}
      {items.map((item, i) => (
        <div key={item.key} className="item-row">
          <input
            value={item.name}
            maxLength={maxLength}
            aria-label={`${noun1} ${i + 1}`}
            autoFocus={item.key === focusKey}
            onChange={(e) => onChange(items.map((x) => (x.key === item.key ? { ...x, name: e.target.value } : x)))}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                if (item.name.trim() !== '') add();
              }
            }}
          />
          <button type="button" className="icon-button" aria-label={`Odebrat ${nounAccusative} ${i + 1}`} onClick={() => onChange(items.filter((x) => x.key !== item.key))}>
            <Icon name="close" />
          </button>
        </div>
      ))}
      <div className="field-actions">
        <button type="button" className="btn small outline" onClick={add}>{addLabel}</button>
      </div>
      {hint && <span className="muted hint">{hint}</span>}
    </div>
  );
}
