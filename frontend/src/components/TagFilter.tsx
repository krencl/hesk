export type TagFilterState = Record<string, 'include' | 'exclude'>;

const STATE_LABEL = { include: 'jen s tagem', exclude: 'bez tagu' } as const;

/**
 * Filtr podle tagů her: každé klepnutí na tag přepíná „bez filtru“ → „jen hry s tagem“ → „bez her s tagem“.
 * Vybraných „jen“ tagů stačí splnit kterýkoli, „bez“ vyřadí hru s kterýmkoli z nich.
 */
export function TagFilter({ tags, value, onChange }: { tags: string[]; value: TagFilterState; onChange: (value: TagFilterState) => void }) {
  const cycle = (tag: string) => {
    const { [tag]: current, ...rest } = value;
    onChange(current === undefined ? { ...rest, [tag]: 'include' } : current === 'include' ? { ...rest, [tag]: 'exclude' } : rest);
  };

  return (
    <div className="tag-filter">
      <div className="chips" role="group" aria-label="Filtr podle tagů her">
        {tags.map((tag) => {
          const state = value[tag];
          return (
            <button
              key={tag}
              type="button"
              className={`chip${state ? ` ${state}` : ''}`}
              aria-label={`${tag}: ${state ? STATE_LABEL[state] : 'bez filtru'}`}
              onClick={() => cycle(tag)}
            >
              {state === 'include' && '+ '}
              {state === 'exclude' && '− '}
              {tag}
            </button>
          );
        })}
      </div>
      <p className="muted hint">Klepnutím zahrneš jen hry s tagem, dalším klepnutím je vyřadíš.</p>
    </div>
  );
}
