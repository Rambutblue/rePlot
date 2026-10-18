import { Map, NavigationControl, ScaleControl, setWorkerUrl } from 'maplibre-gl';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import 'maplibre-gl/dist/maplibre-gl.css';
import './style.css';
import { api, type Bbox, type Status } from './api';
import { addCadastreLayers, LAND_TYPES, setDataVersion, setOrtofoto } from './layers';
import { initSearch } from './search';
import { initSelection, selectParcel } from './selection';
import { watchStatus } from './status';

const STYLE_URL = 'https://tiles.openfreemap.org/styles/positron';
const JICIN: [number, number] = [15.35, 50.44];

setWorkerUrl(workerUrl);

const hasViewInUrl = location.hash !== '';
const parcelInUrl = Number(new URLSearchParams(location.search).get('parcel'));

const map = new Map({
  container: 'map',
  style: STYLE_URL,
  center: JICIN,
  zoom: 10,
  maxZoom: 20,
  hash: true,
});
map.addControl(new NavigationControl(), 'bottom-right');
map.addControl(new ScaleControl(), 'bottom-left');

map.on('load', async () => {
  const status = await api.status().catch((): Status => (
    { dataVersion: 0, total: 0, done: 0, failed: 0, pending: 0, importing: true, current: null }
  ));

  addCadastreLayers(map, status.dataVersion);
  initSelection(map);
  initSearch(map);
  watchStatus(status, (version) => setDataVersion(map, version));

  if (parcelInUrl > 0) {
    void selectParcel(map, parcelInUrl, !hasViewInUrl);
  } else if (!hasViewInUrl) {
    void fitImportedAreas();
  }
});

async function fitImportedAreas(): Promise<void> {
  const boxes = (await api.cadastralAreas()).flatMap((area) => (area.bbox !== null ? [area.bbox] : []));
  if (boxes.length === 0) {
    return;
  }
  const bounds: Bbox = [
    Math.min(...boxes.map((box) => box[0])),
    Math.min(...boxes.map((box) => box[1])),
    Math.max(...boxes.map((box) => box[2])),
    Math.max(...boxes.map((box) => box[3])),
  ];
  map.fitBounds(bounds, { padding: 40, animate: false });
}

const legend = document.querySelector<HTMLElement>('#legend-items')!;
legend.replaceChildren(...LAND_TYPES.map(({ name, color }) => {
  const item = document.createElement('li');
  const swatch = document.createElement('span');
  swatch.style.background = color;
  item.append(swatch, name);
  return item;
}));

document.querySelector<HTMLInputElement>('#ortofoto')!.addEventListener('change', (event) => {
  setOrtofoto(map, (event.target as HTMLInputElement).checked);
});
