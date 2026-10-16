import { useQuery } from '@tanstack/react-query';
import { api } from '../api';
import type { GameSort } from '../api/types';
import type { GameListSettings } from '../lib/listSettings';
import { Icon } from './Icon';

interface Props {
  partyId: number;
  settings: GameListSettings;
  onChange: (changes: Partial<GameListSettings>) => void;
  /** Filtr podle čísla seance (jen v seznamu her; ve správě her nedává smysl). */
  withSession?: boolean;
}

/** Hledání her podle názvu a rozbalovací filtr (řazení, seance); sdílí ho seznam her i správa her. */
export function GameSearch({ partyId, settings, onChange, withSession = false }: Props) {
  const sessions = useQuery({ queryKey: ['sessions', partyId], queryFn: () => api.sessions(partyId), enabled: withSession && settings.filterVisible });

  return (
    <div className="search">
      <div className="search-row">
        <button type="button" className="search-icon" aria-label={settings.query ? 'Smazat hledání' : 'Hledání'} onClick={() => onChange({ query: '' })}>
          <Icon name={settings.query ? 'close' : 'search'} />
        </button>
        <input
          type="search"
          value={settings.query}
          onChange={(e) => onChange({ query: e.target.value })}
          aria-label="Hledat hru"
          autoComplete="off"
          enterKeyHint="search"
        />
        <button
          type="button"
          className={`search-filter-icon${settings.filterVisible ? ' active' : ''}`}
          aria-label="Filtr"
          aria-pressed={settings.filterVisible}
          onClick={() => onChange({ filterVisible: !settings.filterVisible, sessionNumber: null })}
        >
          <Icon name={settings.filterVisible ? 'filter_alt_off' : 'filter_alt'} />
        </button>
      </div>
      {settings.filterVisible && (
        <div className={`search-filter${withSession ? '' : ' single'}`}>
          <label className="filter-label" htmlFor="sort">Řazení</label>
          <select id="sort" value={settings.sort} onChange={(e) => onChange({ sort: e.target.value as GameSort })}>
            <option value="activity">Aktivita</option>
            <option value="name">Název</option>
          </select>
          {withSession && (
            <>
              <label className="filter-label" htmlFor="session">Sezení</label>
              <select id="session" value={settings.sessionNumber ?? ''} onChange={(e) => onChange({ sessionNumber: e.target.value ? Number(e.target.value) : null })}>
                <option value="">Všechny</option>
                {sessions.data?.map((s) => <option key={s.id} value={s.number}>{s.number}</option>)}
              </select>
            </>
          )}
        </div>
      )}
    </div>
  );
}
