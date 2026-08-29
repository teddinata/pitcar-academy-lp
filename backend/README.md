# Pitcar Academy — Lead API

Laravel service behind the Astro landing page in the parent directory. It owns
everything the browser must not: storage, deduplication, scoring, consultant
routing, and the WhatsApp deep link.

The request/response contract is `../docs/lead-api-contract.md`. That file is
the source of truth — the frontend is already built against it.

## Running locally

```bash
cd backend
composer install
php artisan migrate
php artisan serve            # http://127.0.0.1:8000
```

Then point the landing page at it and rebuild:

```dotenv
# ../.env
PUBLIC_LEAD_API_BASE_URL=http://127.0.0.1:8000
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP=628xxxxxxxxxx
```

Without `PUBLIC_LEAD_API_BASE_URL` the landing page skips the API entirely and
opens WhatsApp with the full summary instead. That keeps the site deployable
before this service is live, but no lead row is created — see "Deploy order".

## Endpoint

```http
POST /api/leads
```

- `201 Created` — new lead.
- `200 OK` — replay of a `submission_id` already stored. Same `lead_code`, no
  second notification, no rescore.
- `422` — validation, with `{ "message": ..., "errors": { field: [...] } }`.
- `429` — rate limited, carries `Retry-After`.
- `413` — payload larger than `LEAD_MAX_PAYLOAD_BYTES`.

Response body is unwrapped:

```json
{
  "lead_code": "PA-2026-000123",
  "score": 78,
  "qualification": "qualified",
  "whatsapp_url": "https://wa.me/628xxxxxxxxxx?text=...",
  "message": "Lead berhasil dibuat"
}
```

## How it fits together

| Piece | File | Responsibility |
| --- | --- | --- |
| Validation | `app/Http/Requests/StoreLeadRequest.php` | Enums, lengths, URL and consent checks |
| Phone numbers | `app/Support/WhatsAppNumber.php` | Server-side normalisation, the last word |
| Orchestration | `app/Services/LeadIntake.php` | One transaction: idempotency, score, code, routing |
| Scoring | `app/Services/LeadScorer.php` | Config-driven points, versioned, reasons stored |
| Lead codes | `app/Services/LeadCodeGenerator.php` | Row-locked counter, safe under concurrency |
| Routing | `app/Services/ConsultantRouter.php` | Program/domicile/capacity match |
| Deep link | `app/Services/WhatsAppLinkBuilder.php` | `wa.me` URL from a server-held number |
| Follow-up | `app/Jobs/NotifyNewLead.php` | Queued, runs after commit |

Two rules the code enforces on purpose:

1. **The browser cannot influence its own lead.** `score`, `qualification`,
   `status`, `lead_code` and `assigned_consultant_id` are not mass assignable;
   they are written with `forceFill` from server-computed values only. A request
   that posts them is accepted and those keys ignored.
2. **A failed integration never costs a stored lead.** `NotifyNewLead` is
   dispatched after the transaction commits. If it fails, the lead still exists
   and the visitor still got their WhatsApp link.

## Configuration

All in `config/leads.php`, driven by env:

| Variable | Meaning |
| --- | --- |
| `LEAD_CODE_PREFIX` | Lead code prefix, default `PA` |
| `LEAD_SCORING_VERSION` | Stamped on every lead; bump when rules change |
| `LEAD_RATE_LIMIT_PER_IP` | Requests per minute per IP (default 10) |
| `LEAD_RATE_LIMIT_PER_WHATSAPP` | **Leads** per hour per number (default 3) |
| `LEAD_FALLBACK_CONSULTANT_WHATSAPP` | Used when no consultant row matches |
| `LEAD_ALLOWED_ORIGINS` | Comma separated CORS origins |
| `LEAD_RETENTION_DAYS` | Anonymisation window; unset means keep forever |

The per-number limit counts *leads*, not requests: an idempotent retry of a
submission already stored is exempt, so a visitor recovering from a network
error is never locked out.

CORS note: with several allowed origins the header is only sent for a matching
origin. With exactly one configured origin the header is emitted statically —
that is normal CORS behaviour and the browser still enforces the match.

## Consultants

Routing reads `education_consultants`. Seed the real roster before staging:

```bash
php artisan db:seed --class=EducationConsultantSeeder
```

`programs` and `domiciles` are JSON arrays; leaving them null means "no
restriction". If no row is eligible the lead is still stored and the link falls
back to `LEAD_FALLBACK_CONSULTANT_WHATSAPP`. If that is unset too, the lead is
stored and `whatsapp_url` comes back `null` — the frontend then asks the visitor
to copy the summary instead of dropping them.

## Scoring

Points live in `config/leads.php` so sales can retune without a code deploy.
Current values come from the product brief and **are not final** — they need
sign-off. Every awarded rule is written to `scoring_reasons`:

```json
[{"rule": "timeline", "value": "nearest_batch", "points": 25}]
```

Thresholds: `hot` >= 80, `qualified` >= 60, `nurture` >= 40, else `low_intent`.
Score is capped at 100.

## Privacy

`leads:apply-retention-policy` anonymises name, number, domicile, landing page
and referrer on leads past `LEAD_RETENTION_DAYS`, keeping the non-identifying
columns reporting needs. It is a no-op until that value is set. Run
`--dry-run` first. Schedule it daily once the retention period is agreed.

`NotifyNewLead` logs the lead code, score, qualification and attribution — never
the name or number.

## Tests

```bash
php artisan test
```

Covers the happy path and response shape, all validation branches, idempotent
replay, lead-code sequencing, rate limiting (including the retry exemption),
mass-assignment protection, CORS, and retention.

## Deploy order

1. Deploy this service, run migrations, seed consultants.
2. Set `LEAD_ALLOWED_ORIGINS` to the real landing page origins — no localhost.
3. Set `PUBLIC_LEAD_API_BASE_URL` on the frontend build and redeploy it.
4. Verify a real submission reaches the database before WhatsApp opens.

Until step 3 the landing page runs in WhatsApp-direct mode and no leads are
stored.

## Not built yet

Deliberately out of scope for the intake endpoint, needed before the sales team
can work leads: dashboard and auth, consultant assignment UI, follow-up status
transitions and notes, lost reasons, CSV export, SLA alerting, and the real CRM
or Slack integration behind `NotifyNewLead`.
