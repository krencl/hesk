import { useState } from 'react';
import { normalize } from '../lib/text';
import { Autocomplete } from './Autocomplete';
import { Icon } from './Icon';

interface Props {
  id: string;
  tags: string[];
  onChange: (tags: string[]) => void;
  /** Dosud použité tagy party (našeptávání). */
  options: string[];
}

/** Tagy jako štítky s odebráním; psaný text se přidá Enterem, čárkou nebo opuštěním pole, nabídka vynechává už přidané. */
export function TagInput({ id, tags, onChange, options }: Props) {
  const [text, setText] = useState('');

  // Přidá tagy; velikost písmen se sjednotí s už existujícím tagem a duplicity se zahodí.
  const add = (names: string[]) => {
    const next = [...tags];
    for (const raw of names) {
      const name = raw.trim();
      if (!name) continue;
      const canonical = options.find((o) => normalize(o) === normalize(name)) ?? name;
      if (!next.some((t) => normalize(t) === normalize(canonical))) next.push(canonical);
    }
    if (next.length !== tags.length) onChange(next);
  };

  const type = (value: string) => {
    if (!value.includes(',')) return setText(value);
    const parts = value.split(',');
    add(parts.slice(0, -1));
    setText(parts[parts.length - 1]);
  };
  const commit = (value: string) => { add([value]); setText(''); };

  return (
    <Autocomplete
      id={id}
      value={text}
      onChange={type}
      onPick={commit}
      onCommit={commit}
      options={options.filter((o) => !tags.some((t) => normalize(t) === normalize(o)))}
      maxLength={100}
      placeholder={tags.length === 0 ? 'Přidat tag' : ''}
      keepOpen
      onBackspaceEmpty={() => onChange(tags.slice(0, -1))}
      prefix={tags.map((t) => (
        <span key={t} className="tag">
          {t}
          {/* mouseDown nesmí vzít zaostření inputu, ať nabídka zůstane otevřená */}
          <button type="button" aria-label={`Odebrat tag ${t}`} onMouseDown={(e) => e.preventDefault()} onClick={() => onChange(tags.filter((x) => x !== t))}><Icon name="close" /></button>
        </span>
      ))}
    />
  );
}
