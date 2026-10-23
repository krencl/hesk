import { useQuery } from '@tanstack/react-query';
import { api } from '../api';
import type { GameSort } from '../api/types';
import type { GameListSettings } from '../lib/listSettings';
import { Icon } from './Icon';
import { type SelectOption, SelectPopup } from './SelectPopup';
import { qk } from '../api/queryKeys';

interface Props {
  partyId: number;
  settings: GameListSettings;
  onChange: (changes: Partial<GameListSettings>) => void;
  /** Filtr podle čísla seance (jen v seznamu her; ve správě her nedává smysl). */
  withSession?: boolean;
}

const SORT_OPTIONS: SelectOption<GameSort>[] = [
  { value: 'activity', label: 'Aktivita' },
  { value: 'name', label: 'Název' },
];

/** Hledání her podle názvu a filtr (řazení, seance) s výběrem v popupu; sdílí ho seznam her i správa her. */
export function GameSearch({ partyId, settings, onChange, withSession = false }: Props) {
  const sessions = useQuery({ queryKey: qk.sessions(partyId), queryFn: () => api.sessions(partyId), enabled: withSession && settings.filterVisible });

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
          <span className="filter-label">Řazení</span>
          <SelectPopup
            title="Řazení"
            value={settings.sort}
            options={SORT_OPTIONS}
            onChange={(sort) => onChange({ sort })}
            className="filter-select"
          />
          {withSession && (
            <>
              <span className="filter-label">Sezení</span>
              <SelectPopup
                title="Sezení"
                value={settings.sessionNumber}
                pinned={{ value: null, label: 'Všechny' }}
                options={(sessions.data ?? []).map((s) => ({ value: s.number as number | null, label: String(s.number) }))}
                onChange={(sessionNumber) => onChange({ sessionNumber })}
                className="filter-select"
              />
            </>
          )}
        </div>
      )}
    </div>
  );
}
