export interface LeadAttribution {
  landing_page: string;
  referrer: string | null;
  utm_source: string | null;
  utm_medium: string | null;
  utm_campaign: string | null;
  utm_content: string | null;
  utm_term: string | null;
  /** Meta's own cookies, forwarded so the Conversions API can match. */
  fbp?: string | null;
  fbc?: string | null;
}

export interface LeadPayload {
  submission_id: string;
  name: string;
  whatsapp_number: string;
  domicile: string;
  goal: string;
  /** Replaces the old timeline + investment questions with one. */
  readiness: string;
  program_interest: string;
  source_cta: string;
  source: 'website';
  consent_at: string;
  attribution: LeadAttribution;
}

export interface LeadResponse {
  lead_code: string;
  score?: number | null;
  qualification?: string | null;
  whatsapp_url?: string | null;
  message?: string | null;
}

export class LeadSubmissionError extends Error {
  status?: number;
  retryable: boolean;

  constructor(message: string, options: { status?: number; retryable?: boolean } = {}) {
    super(message);
    this.name = 'LeadSubmissionError';
    this.status = options.status;
    this.retryable = options.retryable ?? true;
  }
}

const API_BASE_URL = import.meta.env.PUBLIC_LEAD_API_BASE_URL?.trim() ?? '';
const CONSULTANT_NUMBER = import.meta.env.PUBLIC_EDUCATION_CONSULTANT_WHATSAPP?.trim() ?? '';

/**
 * False while the Laravel lead API is not wired up. The form then hands the
 * consultant a full summary over WhatsApp instead of failing in front of the
 * visitor — no lead row is created, so treat those submissions as unstored.
 */
export const LEAD_API_CONFIGURED = API_BASE_URL !== '';

export type LeadStorageMode = 'api' | 'direct';

/**
 * `_fbp` is written by the pixel, `_fbc` by the pixel from an `fbclid` on the
 * landing URL. Sending them with the lead is what lets Meta tie a server-side
 * conversion back to the ad that produced it; without them the match falls
 * back to a hashed name and phone number alone.
 */
export function readMetaCookie(name: '_fbp' | '_fbc'): string | null {
  if (typeof document === 'undefined') return null;
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
  return match ? decodeURIComponent(match[1]) : null;
}

function getLeadEndpoint(): string {
  if (!API_BASE_URL) {
    throw new LeadSubmissionError('Sistem lead belum aktif di halaman ini.', { retryable: false });
  }

  const endpoint = new URL(`${API_BASE_URL.replace(/\/+$/, '')}/api/leads`);
  if (!['http:', 'https:'].includes(endpoint.protocol)) {
    throw new LeadSubmissionError('Lead API harus menggunakan HTTP atau HTTPS.', { retryable: false });
  }

  return endpoint.href;
}

function normalizeResponse(value: unknown): LeadResponse {
  const root = value && typeof value === 'object' ? value as Record<string, unknown> : {};
  const data = root.data && typeof root.data === 'object'
    ? root.data as Record<string, unknown>
    : root;

  if (typeof data.lead_code !== 'string' || !data.lead_code.trim()) {
    throw new LeadSubmissionError('Respons API tidak menyertakan lead_code.', { retryable: false });
  }

  return {
    lead_code: data.lead_code,
    score: typeof data.score === 'number' ? data.score : null,
    qualification: typeof data.qualification === 'string' ? data.qualification : null,
    whatsapp_url: typeof data.whatsapp_url === 'string' ? data.whatsapp_url : null,
    message: typeof data.message === 'string' ? data.message : null,
  };
}

export async function submitLead(payload: LeadPayload): Promise<LeadResponse> {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), 12_000);

  try {
    const response = await fetch(getLeadEndpoint(), {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Submission-Id': payload.submission_id,
      },
      body: JSON.stringify(payload),
      signal: controller.signal,
    });

    const body = await response.json().catch(() => null);
    if (!response.ok) {
      const apiMessage = body && typeof body === 'object' && 'message' in body
        ? String(body.message)
        : 'Lead belum dapat disimpan.';
      throw new LeadSubmissionError(apiMessage, {
        status: response.status,
        retryable: response.status >= 500 || response.status === 408 || response.status === 429,
      });
    }

    return normalizeResponse(body);
  } catch (error) {
    if (error instanceof LeadSubmissionError) throw error;
    if (error instanceof DOMException && error.name === 'AbortError') {
      throw new LeadSubmissionError('Koneksi ke sistem lead melewati batas waktu.');
    }
    throw new LeadSubmissionError('Tidak dapat terhubung ke sistem lead.');
  } finally {
    window.clearTimeout(timeout);
  }
}

const goalLabels: Record<string, string> = {
  mechanic_career: 'Berkarier sebagai mekanik',
  upskill: 'Meningkatkan skill mekanik',
  open_workshop: 'Membuka / mengembangkan bengkel',
  automotive_knowledge: 'Belajar otomotif lebih serius',
};

const readinessLabels: Record<string, string> = {
  nearest_batch: 'Siap ikut batch terdekat',
  family_discussion: 'Perlu diskusi dengan orang tua / keluarga',
  need_payment_plan: 'Perlu opsi pembayaran / cicilan',
  exploring: 'Masih ingin memahami program',
};

const programLabels: Record<string, string> = {
  basic: 'Basic — Maintenance',
  advanced: 'Advance — General Repair',
  professional: 'Professional — Level 1 & 2',
  undecided: 'Belum yakin, perlu rekomendasi',
};

export function normalizeWhatsappNumber(value: string): string {
  // Backend stays the source of truth; this only removes the formatting people
  // habitually type so the consultant sees a dialable Indonesian number.
  let digits = value.replace(/\D/g, '');
  if (digits.startsWith('00')) digits = digits.slice(2);
  if (digits.startsWith('0')) return `62${digits.slice(1)}`;
  if (digits.startsWith('8')) return `62${digits}`;
  return digits;
}

export function buildWhatsAppMessage(
  payload: LeadPayload,
  response?: LeadResponse,
  options: { storage?: LeadStorageMode } = {},
): string {
  const statusLine = response?.lead_code
    ? `Kode lead: ${response.lead_code}`
    : options.storage === 'direct'
      ? 'Kode lead: belum tersedia (ringkasan dikirim langsung dari website)'
      : 'Status: data form belum tersimpan di sistem, mohon dicatat manual';

  const lines = [
    'Halo Education Consultant Pitcar Academy, saya sudah mengisi form konsultasi.',
    '',
    statusLine,
    `Nama: ${payload.name}`,
    `WhatsApp: ${payload.whatsapp_number}`,
    `Domisili: ${payload.domicile}`,
    `Program diminati: ${programLabels[payload.program_interest] ?? payload.program_interest}`,
    `Tujuan: ${goalLabels[payload.goal] ?? payload.goal}`,
    `Kesiapan: ${readinessLabels[payload.readiness] ?? payload.readiness}`,
    '',
    'Mohon bantu rekomendasikan program dan langkah berikutnya. Terima kasih.',
  ];

  return lines.join('\n');
}

function isSafeWhatsAppUrl(value: string): boolean {
  try {
    const url = new URL(value);
    return url.protocol === 'https:' && ['wa.me', 'api.whatsapp.com'].includes(url.hostname);
  } catch {
    return false;
  }
}

export function resolveWhatsAppUrl(
  payload: LeadPayload,
  response?: LeadResponse,
  options: { storage?: LeadStorageMode } = {},
): string | null {
  if (response?.whatsapp_url && isSafeWhatsAppUrl(response.whatsapp_url)) {
    return response.whatsapp_url;
  }

  const phone = normalizeWhatsappNumber(CONSULTANT_NUMBER);
  if (!/^\d{8,15}$/.test(phone)) return null;
  const message = buildWhatsAppMessage(payload, response, options);
  return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
}
