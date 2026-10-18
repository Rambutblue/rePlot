import type { Map } from 'maplibre-gl';
import { api, type SearchResult } from './api';
import { fitBbox, selectParcel } from './selection';

const input = document.querySelector<HTMLInputElement>('#search-input')!;
const list = document.querySelector<HTMLUListElement>('#search-results')!;

let timer: number | undefined;
let controller: AbortController | undefined;
let results: SearchResult[] = [];

export function initSearch(map: Map): void {
  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => void search(input.value.trim()), 250);
  });

  input.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && results.length > 0) {
      choose(map, results[0]);
    } else if (event.key === 'Escape') {
      render([]);
    }
  });

  list.addEventListener('click', (event) => {
    const item = (event.target as HTMLElement).closest<HTMLElement>('[data-index]');
    if (item !== null) {
      choose(map, results[Number(item.dataset.index)]);
    }
  });
}

async function search(query: string): Promise<void> {
  controller?.abort();
  if (query === '') {
    render([]);
    return;
  }

  controller = new AbortController();
  try {
    render(await api.search(query, controller.signal));
  } catch (error) {
    if (!(error instanceof DOMException && error.name === 'AbortError')) {
      render([]);
    }
  }
}

function choose(map: Map, result: SearchResult): void {
  render([]);
  input.value = result.label;
  fitBbox(map, result.bbox);
  if (result.type === 'parcel') {
    void selectParcel(map, result.id);
  }
}

function render(found: SearchResult[]): void {
  results = found;
  list.replaceChildren(...found.map((result, index) => {
    const item = document.createElement('li');
    item.dataset.index = String(index);
    item.textContent = result.label;
    if (result.detail !== null) {
      const detail = document.createElement('small');
      detail.textContent = result.detail;
      item.append(detail);
    }
    return item;
  }));
  list.hidden = found.length === 0;
}
