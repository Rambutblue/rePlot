import { api, type Status } from './api';

const banner = document.querySelector<HTMLElement>('#status')!;

const POLL_MS = 5000;

export function watchStatus(initial: Status, onDataVersion: (version: number) => void): void {
  let version = initial.dataVersion;
  render(initial);

  const poll = async (): Promise<void> => {
    try {
      const status = await api.status();
      render(status);
      if (status.dataVersion !== version) {
        version = status.dataVersion;
        onDataVersion(version);
      }
      if (!status.importing) {
        return;
      }
    } catch {
      showBanner('Server je nedostupný, zkouším znovu…');
    }
    setTimeout(poll, POLL_MS);
  };

  if (initial.importing || initial.total === 0) {
    setTimeout(poll, POLL_MS);
  }
}

function render(status: Status): void {
  if (status.total === 0) {
    showBanner('Import dat se připravuje…');
  } else if (status.importing) {
    const current = status.current !== null ? `, právě ${status.current.name}` : '';
    showBanner(`Probíhá import: ${status.done} z ${status.total} katastrálních území${current}`);
  } else if (status.failed > 0) {
    showBanner(`${status.failed} katastrálních území se nepodařilo importovat.`);
  } else {
    banner.hidden = true;
  }
}

function showBanner(text: string): void {
  banner.textContent = text;
  banner.hidden = false;
}
