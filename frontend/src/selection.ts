import type { Map } from 'maplibre-gl';
import { api, type Bbox, type ParcelDetail } from './api';
import { SOURCE } from './layers';

const panel = document.querySelector<HTMLElement>('#detail')!;
const panelBody = document.querySelector<HTMLElement>('#detail-body')!;

let hoveredId: number | string | undefined;
let hoveredLayer: string | undefined;
let selectedId: number | undefined;

export function initSelection(map: Map): void {
  for (const [layer, sourceLayer] of [['parcels-fill', 'parcels'], ['areas-fill', 'areas']]) {
    map.on('mousemove', layer, (event) => {
      const id = event.features?.[0]?.id;
      if (id === hoveredId && sourceLayer === hoveredLayer) {
        return;
      }
      clearHover(map);
      hoveredId = id;
      hoveredLayer = sourceLayer;
      map.setFeatureState({ source: SOURCE, sourceLayer, id }, { hover: true });
      map.getCanvas().style.cursor = 'pointer';
    });
    map.on('mouseleave', layer, () => clearHover(map));
  }

  map.on('click', (event) => {
    const [feature] = map.queryRenderedFeatures(event.point, { layers: ['parcels-fill', 'areas-fill'] });
    if (feature === undefined) {
      clearSelection(map);
    } else if (feature.layer.id === 'parcels-fill') {
      void selectParcel(map, Number(feature.id));
    } else {
      map.easeTo({ center: event.lngLat, zoom: 14 });
    }
  });

  document.querySelector('#detail-close')!.addEventListener('click', () => clearSelection(map));
}

export async function selectParcel(map: Map, id: number, zoomTo = false): Promise<void> {
  setSelected(map, id);
  panel.hidden = false;
  panelBody.textContent = 'Načítám…';

  try {
    const parcel = await api.parcel(id);
    if (selectedId !== id) {
      return;
    }
    renderDetail(parcel);
    if (zoomTo) {
      fitBbox(map, parcel.bbox);
    }
  } catch {
    panelBody.textContent = 'Detail parcely se nepodařilo načíst.';
  }
}

export function fitBbox(map: Map, bbox: Bbox): void {
  map.fitBounds(bbox, { padding: 80, maxZoom: 18 });
}

function clearSelection(map: Map): void {
  setSelected(map, undefined);
  panel.hidden = true;
}

function setSelected(map: Map, id: number | undefined): void {
  if (selectedId !== undefined) {
    map.setFeatureState({ source: SOURCE, sourceLayer: 'parcels', id: selectedId }, { selected: false });
  }
  selectedId = id;
  if (id !== undefined) {
    map.setFeatureState({ source: SOURCE, sourceLayer: 'parcels', id }, { selected: true });
  }

  const url = new URL(location.href);
  if (id === undefined) {
    url.searchParams.delete('parcel');
  } else {
    url.searchParams.set('parcel', String(id));
  }
  history.replaceState(history.state, '', url);
}

function clearHover(map: Map): void {
  if (hoveredId !== undefined && hoveredLayer !== undefined) {
    map.setFeatureState({ source: SOURCE, sourceLayer: hoveredLayer, id: hoveredId }, { hover: false });
  }
  hoveredId = undefined;
  hoveredLayer = undefined;
  map.getCanvas().style.cursor = '';
}

function renderDetail(parcel: ParcelDetail): void {
  const title = document.createElement('h2');
  title.textContent = `Parcela ${parcel.label}`;

  const list = document.createElement('dl');
  const rows: [string, string | null][] = [
    ['Katastrální území', `${parcel.cadastralArea.name} (${parcel.cadastralArea.code})`],
    ['Obec', parcel.municipality?.name ?? null],
    ['Výměra', parcel.areaM2 !== null ? `${parcel.areaM2.toLocaleString('cs-CZ')} m²` : null],
    ['Druh pozemku', parcel.landType?.name ?? null],
    ['Způsob využití', parcel.landUse?.name ?? null],
  ];
  for (const [term, value] of rows) {
    const dt = document.createElement('dt');
    dt.textContent = term;
    const dd = document.createElement('dd');
    dd.textContent = value ?? '–';
    list.append(dt, dd);
  }

  const link = document.createElement('a');
  link.href = parcel.vdpUrl;
  link.target = '_blank';
  link.rel = 'noopener';
  link.textContent = 'Zobrazit ve VDP ČÚZK';

  panelBody.replaceChildren(title, list, link);
}
