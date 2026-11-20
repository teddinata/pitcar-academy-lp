/**
 * Refreshes printed online-class prices from the LMS on every visit, so a
 * price saved in the admin shows without rebuilding the site.
 *
 * An element marked with livePriceAttrs() (src/lib/livePrices.ts) names a
 * class and the prices the build printed for it. Inside it, each of those
 * strings (price, strike price, saving) is swapped for the current one, in
 * text, link targets and labels. The nearest marked ancestor decides which
 * class a string belongs to, so a block about another class can sit inside a
 * page about this one. If the API does not answer, the printed prices stay.
 */

type Built = { price: number; strike: number };
type Live = { price: number; strike: number | null; students: number | null };

// Same floor as the build (MIN_CLASSMATES in src/lib/livePrices.ts).
const MIN_CLASSMATES = 10;

const rupiah = (n: number) => 'Rp' + Math.round(n).toLocaleString('id-ID');
const base = (import.meta.env.PUBLIC_API_BASE_URL ?? import.meta.env.PUBLIC_LEAD_API_BASE_URL ?? '').trim().replace(/\/$/, '');
const ATTRIBUTES = ['href', 'aria-label', 'title'];

async function fetchPrice(slug: string): Promise<Live | null> {
  try {
    const res = await fetch(`${base}/api/checkout/courses/${encodeURIComponent(slug)}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) return null;
    const data = (await res.json()) as { course?: { price?: unknown; strike_price?: unknown; students?: unknown } };
    const price = data.course?.price;
    const strike = data.course?.strike_price;
    const students = data.course?.students;
    if (typeof price !== 'number' || !Number.isInteger(price) || price <= 0) return null;
    return {
      price,
      strike: typeof strike === 'number' ? strike : null,
      students: typeof students === 'number' && Number.isInteger(students) && students >= 0 ? students : null,
    };
  } catch {
    return null;
  }
}

/** Old string to new string. Empty when nothing a visitor would see has changed. */
function changes(built: Built, live: Live): Map<string, string> {
  // Same rule as the build: a missing or lower strike price keeps the printed one.
  const strike = live.strike !== null && live.strike > live.price ? live.strike : built.strike;
  const map = new Map<string, string>();
  if (live.price !== built.price) map.set(rupiah(built.price), rupiah(live.price));
  if (built.strike > 0 && strike !== built.strike) map.set(rupiah(built.strike), rupiah(strike));
  const savedBefore = built.strike - built.price;
  const savedNow = strike - live.price;
  if (savedBefore > 0 && savedNow > 0 && savedNow !== savedBefore) map.set(rupiah(savedBefore), rupiah(savedNow));
  return map;
}

/** One pass, so a new price that equals another old string is not replaced twice. */
function replacer(map: Map<string, string>): (text: string) => string {
  const escape = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  // A price must not be the tail or head of a longer number.
  const pattern = new RegExp(`(?<![\\d.])(${[...map.keys()].map(escape).join('|')})(?![\\d.])`, 'g');
  return (text) => text.replace(pattern, (match) => map.get(match) ?? match);
}

function apply(scope: HTMLElement, built: Built, live: Live) {
  const map = changes(built, live);
  if (map.size === 0) return;
  const swap = replacer(map);
  const own = (el: Element | null) => el?.closest('[data-live-price]') === scope;

  const walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT, {
    acceptNode: (node) => {
      const parent = node.parentElement;
      if (!parent || parent.closest('script, style, noscript') || !own(parent)) return NodeFilter.FILTER_REJECT;
      return NodeFilter.FILTER_ACCEPT;
    },
  });
  for (let node = walker.nextNode(); node; node = walker.nextNode()) {
    const next = swap(node.nodeValue ?? '');
    if (next !== node.nodeValue) node.nodeValue = next;
  }

  for (const el of [scope, ...scope.querySelectorAll<HTMLElement>('*')]) {
    if (!own(el)) continue;
    for (const name of ATTRIBUTES) {
      const value = el.getAttribute(name);
      if (value === null) continue;
      // WhatsApp links carry the price URL-encoded; "Rp99.000" encodes to itself.
      const next = swap(value);
      if (next !== value) el.setAttribute(name, next);
    }
    // Checkout links report this number to analytics.
    if (el.dataset.price === String(built.price)) el.dataset.price = String(live.price);
  }

  if (scope === document.body) document.title = swap(document.title);
}

/** "Bergabung dengan N siswa lain" lines (ClassmateCount.astro) take today's count. */
function applyClassmates(live: Map<string, Live | null>) {
  for (const line of document.querySelectorAll<HTMLElement>('[data-classmates]')) {
    const students = live.get(line.dataset.classmates!)?.students;
    if (students == null) continue;
    const count = line.querySelector('[data-classmates-count]');
    if (count) count.textContent = students.toLocaleString('id-ID');
    line.hidden = students < MIN_CLASSMATES;
  }
}

async function refresh() {
  if (!base) return;
  const scopes = [...document.querySelectorAll<HTMLElement>('[data-live-price]')];
  const lines = [...document.querySelectorAll<HTMLElement>('[data-classmates]')];
  if (scopes.length === 0 && lines.length === 0) return;
  const slugs = [...new Set([...scopes.map((s) => s.dataset.livePrice!), ...lines.map((l) => l.dataset.classmates!)])];
  const live = new Map(await Promise.all(slugs.map(async (slug) => [slug, await fetchPrice(slug)] as const)));
  applyClassmates(live);

  for (const scope of scopes) {
    const price = live.get(scope.dataset.livePrice!);
    const [builtPrice, builtStrike] = (scope.dataset.liveBuilt ?? '').split('/').map(Number);
    if (!price || !builtPrice) continue;
    apply(scope, { price: builtPrice, strike: builtStrike || 0 }, price);
  }
}

refresh();
