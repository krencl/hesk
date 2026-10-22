import { useQuery } from '@tanstack/react-query';
import { useActiveParty } from '../AppContext';
import { api } from '../api';

/** Hra v aktivní partě (sdílená hra + údaje party); bez `gameId` se nenačítá. Klíč začíná `['game', gameId]`, aby ho šlo zneplatnit podle hry. */
export function useGame(gameId: number | null | undefined) {
  const party = useActiveParty();
  return useQuery({ queryKey: ['game', gameId, party.id], queryFn: () => api.game(party.id, gameId!), enabled: gameId != null, retry: false });
}
