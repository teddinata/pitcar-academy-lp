/// <reference path="../.astro/types.d.ts" />

interface ImportMetaEnv {
  readonly PUBLIC_LEAD_API_BASE_URL?: string;
  readonly PUBLIC_EDUCATION_CONSULTANT_WHATSAPP?: string;
  readonly PUBLIC_EDUCATION_CONSULTANT_WHATSAPP_DISPLAY?: string;
  readonly PUBLIC_GA_ID?: string;
  readonly PUBLIC_HOTJAR_ID?: string;
  readonly PUBLIC_META_PIXEL_ID?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}

interface Window {
  dataLayer?: unknown[];
  gtag?: (...args: unknown[]) => void;
  /** Meta pixel. The fourth argument carries eventID for CAPI deduplication. */
  fbq?: (
    command: string,
    eventName: string,
    params?: Record<string, unknown>,
    options?: { eventID?: string },
  ) => void;
  hj?: (...args: unknown[]) => void;
  trackFunnelEvent?: (
    eventName: string,
    params?: Record<string, unknown>,
  ) => void;
  /** Fires an event, then navigates once it is delivered or the timeout lapses. */
  trackFunnelEventThen?: (
    eventName: string,
    params: Record<string, unknown>,
    url: string,
    timeout?: number,
  ) => void;
  getLeadAttribution?: () => {
    landing_page: string;
    referrer: string | null;
    utm_source: string | null;
    utm_medium: string | null;
    utm_campaign: string | null;
    utm_content: string | null;
    utm_term: string | null;
  };
}
