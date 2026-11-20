/**
 * Counts page views and how long each page is on screen, for the analytics
 * page in the admin. No cookie and no personal data: the visitor id is a
 * random value kept in localStorage, and the entry (utm tags, ad click ids,
 * referrer) is the first page of the browser session. Failures are silent:
 * analytics must never get in the way of the page.
 */

const base = (import.meta.env.PUBLIC_API_BASE_URL ?? import.meta.env.PUBLIC_LEAD_API_BASE_URL ?? '').trim().replace(/\/$/, '');
// The learning area is for students who already bought; it is not traffic.
const skip = /^\/belajar(\/|$)/.test(location.pathname);

function uuid(): string {
  if (crypto.randomUUID) return crypto.randomUUID();
  const b = crypto.getRandomValues(new Uint8Array(16));
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

function kept(storage: () => Storage, key: string, make: () => string): string {
  try {
    const value = storage().getItem(key);
    if (value) return value;
    const fresh = make();
    storage().setItem(key, fresh);
    return fresh;
  } catch {
    return make();
  }
}

if (base && !skip) {
  const visitor = kept(() => localStorage, 'pa_vid', uuid);
  const entry = JSON.parse(
    kept(() => sessionStorage, 'pa_entry', () => {
      const q = new URLSearchParams(location.search);
      const pick = (k: string) => q.get(k) || undefined;
      return JSON.stringify({
        utm_source: pick('utm_source'),
        utm_medium: pick('utm_medium'),
        utm_campaign: pick('utm_campaign'),
        fbclid: pick('fbclid'),
        gclid: pick('gclid'),
        ttclid: pick('ttclid'),
        referrer: document.referrer || undefined,
      });
    }),
  );
  const id = uuid();

  fetch(`${base}/api/visits`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, visitor, path: location.pathname, entry }),
    keepalive: true,
  }).catch(() => {});

  // Only time on screen counts: a tab in the background is not reading.
  let shownAt: number | null = document.visibilityState === 'visible' ? performance.now() : null;
  let total = 0;
  const url = `${base}/api/visits/${id}/time`;
  const seconds = () => Math.round((total + (shownAt === null ? 0 : performance.now() - shownAt)) / 1000);

  // A plain-text beacon needs no CORS preflight, so it still leaves while the page closes.
  // active: true = still on screen, false = closed or left, undefined = tab switched away.
  const send = (active?: boolean) => {
    const body = JSON.stringify({ seconds: seconds(), active });
    if (!navigator.sendBeacon?.(url, new Blob([body], { type: 'text/plain' }))) {
      fetch(url, { method: 'POST', body, keepalive: true }).catch(() => {});
    }
  };

  // While the page is on screen it says so every minute; the admin counts it as on the site now.
  let beat: number | undefined;
  const startBeat = () => {
    window.clearInterval(beat);
    beat = window.setInterval(() => send(true), 60_000);
  };

  const pause = () => {
    window.clearInterval(beat);
    if (shownAt !== null) {
      total += performance.now() - shownAt;
      shownAt = null;
    }
  };

  if (shownAt !== null) startBeat();
  document.addEventListener('visibilitychange', () => {
    // Switching tabs saves the time but keeps the visitor counted until the last beat is two minutes old.
    if (document.visibilityState === 'hidden') {
      pause();
      return send();
    }
    shownAt = performance.now();
    send(true);
    startBeat();
  });
  window.addEventListener('pagehide', () => {
    pause();
    send(false);
  });
}
