import type { ExpressionSpecification, Map, VectorTileSource } from 'maplibre-gl';
import { tileUrl } from './api';

export const SOURCE = 'cadastre';

export const LAND_TYPES: { code: string; name: string; color: string }[] = [
  { code: 'ArableGround', name: 'orná půda', color: '#e8c96a' },
  { code: 'Hopgarden', name: 'chmelnice', color: '#9fb84f' },
  { code: 'Vineyard', name: 'vinice', color: '#a3577f' },
  { code: 'Garden', name: 'zahrada', color: '#b5dd6e' },
  { code: 'Orchard', name: 'ovocný sad', color: '#6fbf5a' },
  { code: 'Grassland', name: 'trvalý travní porost', color: '#d2ebae' },
  { code: 'Forest', name: 'lesní pozemek', color: '#2f7d3a' },
  { code: 'WaterArea', name: 'vodní plocha', color: '#4f8fd6' },
  { code: 'BuiltUpArea', name: 'zastavěná plocha a nádvoří', color: '#c8564f' },
  { code: 'OtherArea', name: 'ostatní plocha', color: '#b4b4b4' },
];

const landTypeColor: ExpressionSpecification = [
  'match',
  ['get', 'landType'],
  ...LAND_TYPES.flatMap(({ code, color }) => [code, color]),
  '#888888',
] as unknown as ExpressionSpecification;

const hovered: ExpressionSpecification = ['boolean', ['feature-state', 'hover'], false];
const selected: ExpressionSpecification = ['boolean', ['feature-state', 'selected'], false];

export function addCadastreLayers(map: Map, dataVersion: number): void {
  map.addSource(SOURCE, {
    type: 'vector',
    tiles: [tileUrl(dataVersion)],
    minzoom: 8,
    maxzoom: 16,
    attribution: '© <a href="https://cuzk.gov.cz">ČÚZK</a>',
  });

  map.addSource('ortofoto', {
    type: 'raster',
    tiles: ['https://ags.cuzk.gov.cz/arcgis1/rest/services/ORTOFOTO_WM/MapServer/tile/{z}/{y}/{x}'],
    tileSize: 256,
    maxzoom: 20,
    attribution: 'Ortofoto © <a href="https://cuzk.gov.cz">ČÚZK</a>',
  });

  const firstSymbolLayer = map.getStyle().layers.find((layer) => layer.type === 'symbol')?.id;

  map.addLayer({ id: 'ortofoto', type: 'raster', source: 'ortofoto', layout: { visibility: 'none' } }, firstSymbolLayer);

  map.addLayer({
    id: 'areas-fill',
    type: 'fill',
    source: SOURCE,
    'source-layer': 'areas',
    maxzoom: 14,
    paint: {
      'fill-color': '#1e3a8a',
      'fill-opacity': ['case', hovered, 0.12, 0.02],
    },
  }, firstSymbolLayer);

  map.addLayer({
    id: 'parcels-fill',
    type: 'fill',
    source: SOURCE,
    'source-layer': 'parcels',
    minzoom: 12,
    paint: {
      'fill-color': landTypeColor,
      'fill-opacity': ['interpolate', ['linear'], ['zoom'], 12, 0, 13, ['case', selected, 0.85, hovered, 0.7, 0.45]],
    },
  }, firstSymbolLayer);

  map.addLayer({
    id: 'parcels-line',
    type: 'line',
    source: SOURCE,
    'source-layer': 'parcels',
    minzoom: 13,
    paint: {
      'line-color': '#4b5563',
      'line-width': ['interpolate', ['linear'], ['zoom'], 13, 0.2, 17, 1],
    },
  }, firstSymbolLayer);

  map.addLayer({
    id: 'areas-line',
    type: 'line',
    source: SOURCE,
    'source-layer': 'areas',
    maxzoom: 14,
    paint: {
      'line-color': '#1e3a8a',
      'line-width': 1.2,
      'line-opacity': ['interpolate', ['linear'], ['zoom'], 12.5, 1, 14, 0],
    },
  }, firstSymbolLayer);

  map.addLayer({
    id: 'parcels-selected',
    type: 'line',
    source: SOURCE,
    'source-layer': 'parcels',
    paint: {
      'line-color': '#dc2626',
      'line-width': 3,
      'line-opacity': ['case', selected, 1, 0],
    },
  });

  map.addLayer({
    id: 'parcels-label',
    type: 'symbol',
    source: SOURCE,
    'source-layer': 'parcels',
    minzoom: 17,
    layout: {
      'text-field': ['get', 'label'],
      'text-font': ['Noto Sans Regular'],
      'text-size': 11,
    },
    paint: {
      'text-color': '#111827',
      'text-halo-color': '#ffffff',
      'text-halo-width': 1.5,
    },
  });
}

export function setDataVersion(map: Map, dataVersion: number): void {
  map.getSource<VectorTileSource>(SOURCE)?.setTiles([tileUrl(dataVersion)]);
}

export function setOrtofoto(map: Map, visible: boolean): void {
  map.setLayoutProperty('ortofoto', 'visibility', visible ? 'visible' : 'none');
}
