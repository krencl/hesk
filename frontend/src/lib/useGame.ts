import { useQuery } from '@tanstack/react-query';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { qk } from '../api/queryKeys';

/** Hra v aktivní partě (sdílená hra + údaje party); bez `gameId` se nenačítá. Klíč začíná `qk.game(gameId)`, aby ho šlo zneplatnit podle hry. */
export function useGame(gameId: number | null | undefined) {
  const party = useActiveParty();
  return useQuery({ queryKey: qk.game(gameId, party.id), queryFn: () => api.game(party.id, gameId!), enabled: gameId != null });
}
