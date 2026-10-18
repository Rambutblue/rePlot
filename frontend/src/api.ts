export type Bbox = [number, number, number, number];

export interface Status {
  dataVersion: number;
  total: number;
  done: number;
  failed: number;
  pending: number;
  importing: boolean;
  current: { code: number; name: string } | null;
}

export interface CadastralArea {
  code: number;
  name: string;
  bbox: Bbox | null;
}

export interface ParcelDetail {
  id: number;
  label: string;
  areaM2: number | null;
  landType: { code: string; name: string } | null;
  landUse: { code: string; name: string } | null;
  cadastralArea: { code: number; name: string };
  municipality: { code: number; name: string } | null;
  bbox: Bbox;
  vdpUrl: string;
}

export interface SearchResult {
  type: 'parcel' | 'area';
  id: number;
  label: string;
  detail: string | null;
  bbox: Bbox;
}

async function get<T>(path: string, signal?: AbortSignal): Promise<T> {
  const response = await fetch(path, { signal });
  if (!response.ok) {
    throw new Error(`${path} returned ${response.status}`);
  }
  return response.json();
}

export const api = {
  status: () => get<Status>('/api/status'),
  cadastralAreas: () => get<CadastralArea[]>('/api/cadastral-areas'),
  parcel: (id: number) => get<ParcelDetail>(`/api/parcels/${id}`),
  search: async (query: string, signal: AbortSignal) =>
    (await get<{ results: SearchResult[] }>(`/api/search?q=${encodeURIComponent(query)}`, signal)).results,
};

export function tileUrl(dataVersion: number): string {
  return `${location.origin}/api/tiles/{z}/{x}/{y}.pbf?v=${dataVersion}`;
}
