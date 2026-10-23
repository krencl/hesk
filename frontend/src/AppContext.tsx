import { useQuery, useQueryClient } from '@tanstack/react-query';
import { createContext, type ReactNode, useCallback, useContext, useMemo, useState } from 'react';
import { api } from './api';
import { ApiError } from './api/client';
import type { Me, Party } from './api/types';
import { LoadingSplash, ServerDownSplash } from './components/Splash';
import { loadActivePartyId, resolveActiveParty, saveActivePartyId } from './lib/activeParty';

interface AppState {
  me: Me;
  parties: Party[];
  activeParty: Party | null;
  setActiveParty: (id: number) => void;
}

const Ctx = createContext<AppState | null>(null);

export function useApp(): AppState {
  const value = useContext(Ctx);
  if (!value) throw new Error('useApp must be used within <AppProvider>');
  return value;
}

/** Aktivní parta; vyhodí chybu mimo kontext, kde už je parta zaručená (viz RequireParty). */
export function useActiveParty(): Party {
  const { activeParty } = useApp();
  if (!activeParty) throw new Error('No active party');
  return activeParty;
}

export function useCurrentUserQuery() {
  return useQuery({
    queryKey: ['me'],
    queryFn: async () => {
      try {
        return await api.me();
      } catch (e) {
        if (e instanceof ApiError && e.status === 401) return null;
        throw e;
      }
    },
  });
}

export function AppProvider({ me, children }: { me: Me; children: ReactNode }) {
  const queryClient = useQueryClient();
  const [storedId, setStoredId] = useState<number | null>(loadActivePartyId);
  const partiesQuery = useQuery({ queryKey: ['parties'], queryFn: api.parties });
  const parties = partiesQuery.data;

  const activeParty = useMemo(() => (parties ? resolveActiveParty(parties, storedId) : null), [parties, storedId]);

  const setActiveParty = useCallback(
    (id: number) => {
      saveActivePartyId(id);
      setStoredId(id);
      void queryClient.invalidateQueries({ predicate: (q) => q.queryKey[0] !== 'me' && q.queryKey[0] !== 'parties' });
    },
    [queryClient],
  );

  const value = useMemo(() => ({ me, parties: parties ?? [], activeParty, setActiveParty }), [me, parties, activeParty, setActiveParty]);
  // Bez seznamu part by se zbytečně ukázalo „Zatím nejsi v žádné partě“; chyba dalšího načtení už stávající data neschová.
  if (!parties) return partiesQuery.isError ? <ServerDownSplash /> : <LoadingSplash />;
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}
