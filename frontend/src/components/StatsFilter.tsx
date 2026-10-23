import { useQuery } from '@tanstack/react-query';
import { api } from '../api';
import { type FilterItem, addItem, itemKey, removeItem, toggleItem } from '../lib/statsFilter';
import { useDebounced } from '../lib/useDebounced';
import { normalize } from './Autocomplete';
import { GameListItem } from './GameListItem';
import { Icon } from './Icon';

interface Props {
  partyId: number;
  items: FilterItem[];
  onChange: (items: FilterItem[]) => void;
  query: string;
  onQueryChange: (query: string) => void;
  /** Pole je zaostřené: prázdné pole nabízí všechny tagy; stránka v tu chvíli skryje grafy. */
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const RESULT_LIMIT = 20;

/**
 * Filtr statistik: hledání ve hrách party a v tagách her (stejný vzhled jako hledání v seznamu her). Vybraná hra nebo tag
 * se přidá jako „+“ (klepnutí na řádek, nebo tlačítko +) či rovnou jako „−“ (tlačítko −); klepnutím na štítek se „+“ a „−“ přepíná, křížek ho odebere.
 * Výsledky hledání (pod polem) nahrazují grafy, dokud se píše; zaostřené prázdné pole rovnou nabídne všechny tagy.
 */
export function StatsFilter({ partyId, items, onChange, query, onQueryChange, open, onOpenChange }: Props) {
  const text = query.trim();
  const debounced = useDebounced(text);
  const tags = useQuery({ queryKey: ['partyGameTags', partyId], queryFn: () => api.partyGameTags(partyId) });
  const games = useQuery({
    queryKey: ['games', partyId, 'statsFilter', debounced],
    queryFn: () => api.games(partyId, { q: debounced, sort: 'name', limit: RESULT_LIMIT }),
    enabled: debounced !== '',
  });

  const pick = (item: FilterItem, mode: FilterItem['mode'] = 'include') => {
    onChange(addItem(items, { ...item, mode }));
    onQueryChange('');
  };
  /** Tlačítka „+“ (jen takové hry) a „−“ (bez nich); klepnutí na celý řádek je jako „+“. */
  const modeButtons = (item: FilterItem) => (
    <>
      <button type="button" className="btn small" aria-label={`Přidat ${item.name} (jen s ním)`} onClick={() => pick(item, 'include')}>+</button>
      <button type="button" className="btn small negative" aria-label={`Přidat ${item.name} (vyřadit)`} onClick={() => pick(item, 'exclude')}>−</button>
    </>
  );
  const needle = normalize(text);
  const foundTags = (tags.data ?? []).filter((t) => normalize(t).includes(needle) && !items.some((i) => itemKey(i) === `tag:${t}`));
  const foundGames = text === '' || debounced !== text ? [] : (games.data ?? []).filter((g) => !items.some((i) => itemKey(i) === `game:${g.id}`));
  const searching = text !== '';
  const settled = debounced === text && !games.isFetching;

  return (
    <>
      <div className="search">
        <div className="search-row">
          <button type="button" className="search-icon" aria-label={text ? 'Smazat hledání' : 'Hledání'} onClick={() => onQueryChange('')}>
            <Icon name={text ? 'close' : 'search'} />
          </button>
          <input
            type="search"
            value={query}
            onChange={(e) => onQueryChange(e.target.value)}
            aria-label="Hledat hru nebo tag"
            placeholder="Hra nebo tag"
            autoComplete="off"
            enterKeyHint="search"
            onFocus={() => onOpenChange(true)}
            onBlur={() => onOpenChange(false)}
            onKeyDown={(e) => e.key === 'Escape' && e.currentTarget.blur()}
          />
        </div>
      </div>

      {items.length > 0 && (
        <div className="chips filter-chips" role="group" aria-label="Filtr statistik">
          {items.map((item) => {
            const sign = item.mode === 'include' ? '+' : '−';
            return (
              <span key={itemKey(item)} className={`filter-chip ${item.mode}`}>
                <button type="button" className="filter-chip-toggle" aria-label={`${item.name}: ${item.mode === 'include' ? 'jen s ním' : 'bez něj'}`} onClick={() => onChange(toggleItem(items, item))}>
                  {sign} {item.name}
                </button>
                <button type="button" className="filter-chip-remove" aria-label={`Odebrat ${item.name} z filtru`} onClick={() => onChange(removeItem(items, item))}>
                  <Icon name="close" />
                </button>
              </span>
            );
          })}
          <button type="button" className="chip" onMouseDown={(e) => e.preventDefault()} onClick={() => onChange([])}>
            <Icon name="filter_alt_off" />
            Zrušit filtr
          </button>
        </div>
      )}

      {(searching || open) && (
        <>
          {/* mouseDown nesmí vzít zaostření inputu, ať nabídka při výběru nezmizí a jde vybírat dál */}
          <ul className="list" onMouseDown={(e) => e.preventDefault()}>
            {foundTags.map((tag) => {
              const item: FilterItem = { kind: 'tag', name: tag, mode: 'include' };
              return (
                <li key={`tag:${tag}`} className="with-actions">
                  <button type="button" className="list-item" onClick={() => pick(item)}>
                    <span className="list-item-body">
                      <strong className="h5">{tag}</strong>
                      <span className="secondary">Tag her</span>
                    </span>
                  </button>
                  <span className="list-item-actions">{modeButtons(item)}</span>
                </li>
              );
            })}
            {foundGames.map((game) => {
              const item: FilterItem = { kind: 'game', id: game.id, name: game.name, mode: 'include' };
              return (
                <GameListItem key={game.id} imageUrl={game.imageUrl} name={game.name} onClick={() => pick(item)} actions={modeButtons(item)}>
                  <span className="secondary">Hra</span>
                </GameListItem>
              );
            })}
          </ul>
          {searching && settled && foundTags.length === 0 && foundGames.length === 0 && (
            <p className="empty"><span>Hledání „<b className="primary">{text}</b>“ nic nenašlo.</span></p>
          )}
        </>
      )}
    </>
  );
}
