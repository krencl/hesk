import { useQuery } from '@tanstack/react-query';
import { api } from '../api';
import { qk } from '../api/queryKeys';

/** Detail party podle id z adresy (členové, role, zkratka); sdílejí ho stránky správy party. */
export function usePartyDetail(id: number) {
  return useQuery({ queryKey: qk.party(id), queryFn: () => api.party(id) });
}
