# Shree Vrindavan Tour Packages — User & Admin Manual

> Stack: Laravel 13 + Vue 3 + Inertia v2 + Tailwind.  
> Business: Mathura / Vrindavan / Braj tours, taxi, hotels, custom enquiries, festival tourism, WhatsApp conversion.

This manual is for **customers, staff, and admins**. For taxi-only deep ops see `docs/taxi-module.md`.

---

## 1. Quick start

### 1.1 Important URLs

| Who | URL | Notes |
|---|---|---|
| Public site | `/` | Home, packages, taxi, contact |
| Tour list | `/packages` | Filters: destination, city, category, tags, price, date, adults/children |
| Tour detail | `/packages/{slug}` | e.g. `/packages/braj-holi-festival-tour` |
| Book a tour | `/packages/{slug}/book` | 3 steps: Details → Review → Confirmation |
| Booking confirmation (signed link) | `/bookings/confirmation/{booking}` | Shareable, no login needed |
| Invoice / PDF + QR | `/bookings/{booking}/invoice`, `/bookings/{booking}/download` | QR check-in code included |
| Taxi enquiry | `/taxi` | Quote → choose vehicle → confirm |
| Taxi live tracking | `/taxi/track/{token}` | No login, read-only |
| Hotels | `/hotels`, `/hotels/{slug}` | Login required to book |
| Quotation link | `/q/{token}` | Customer Accept / Reject, no login |
| Contact / quick enquiry | `/contact` | Only `full_name + phone` |
| Customer account | `/account`, `/account/bookings`, `/account/taxi/bookings`, `/account/support` | Dashboard, invoices, reschedule, reviews |
| Vendor apply | `/vendor/apply` | Status at `/vendor/application` |
| Vendor portal | `/vendor` | Tours, hotels, taxi, finance, support |
| Driver portal | `/driver/taxi/dashboard` | Trips, offers, earnings, profile |
| Admin login | `/admin/login` | Staff only |
| Admin home | `/admin/dashboard` | Stats, needs-attention, recent activity |

Festival / family / senior tours are **not separate pages**. They are filters on `/packages`:
category `festival-tours`, tags `festival, pilgrimage, family, senior-friendly, weekend`.

### 1.2 Login / roles

* Customers register at `/register`, login at `/login`.
* Staff login at `/admin/login` (separate guard/view).
* Account types (`users.role`): `admin | customer | vendor`.
  * Vendor = customer promoted after approval of a Vendor Application.
  * Driver = **not a role** — a `drivers.user_id` link + `driver` middleware. Driver must have a normal user login first.
* Staff fine-grained permissions use Spatie (`/admin/roles`). See §6.

### 1.3 Admin navigation

`/admin` → sidebar (module + permission filtered), global search + `Ctrl+K`, breadcrumbs, notification bell, impersonation banner when impersonating a user.

Dashboard shows: bookings count, paid revenue/GBV, tours, pending reviews, users/destinations/places, recent bookings/packages, top destinations, 6-month chart, marketplace GBV vs commission vs vendor earnings vs refunds, `needs_attention` (overdue follow-ups, expiring quotes, vendor apps, KYC, tours awaiting approval, cancellations, withdrawals, open tickets, failed jobs), recent Activity Log.

---

## 2. Customer guide (public site)

### 2.1 Find a tour

1. Go to `/packages`. Filter by destination / city / place / category / tags / price / travel date / adults-children.
2. Open `/packages/{slug}`: gallery, day-wise itinerary, inclusions/exclusions, places, tags, reviews (5 per page).
3. Pick `travel_date, adults (1–30), children (0–30)` → live price via estimate.
4. Click **Book** or the floating **WhatsApp** button (`wa.me/<whatsapp_number>?text=...`).

Home page sections (hero, featured cities/destinations/hotels/tours/places, CTA) are managed at `/admin/homepage-sections`.

### 2.2 Book a tour package (guest allowed)

1. On detail page → **Book** → `/packages/{slug}/book` (`Details → Review → Confirmation`).
2. Fill (required `*`):
   * `package_id*, travel_date* (≥ today), total_adults* (1–30), customer_name*, customer_phone*`
   * Optional: `total_children, customer_email, country, pickup_address, special_requests (max 1000 chars), coupon_code, addons [{addon_id, quantity 1–30}], gateway [razorpay,paytm]`
   * Server re-prices everything, checks blackout dates, `available_weekdays`, min/max advance days, `booking_enabled`.
3. Submit `POST /bookings` → redirect to signed confirmation.
4. Confirmation shows reference, package, dates, party, contact, price break-up (`base + addons + subtotal − discount + tax = total`), addon lines, payment link.
5. Payment page (`/bookings/{booking}/pay`): **currently manual** — copy says “No online payment is taken here — team reviews”. Razorpay `checkout.js` stub is ready but staff collect manually and record in Admin → Bookings → Payments. Invoice + QR PDF available to owner.
6. Invoice: `/bookings/{booking}/invoice` view, `/download` PDF (DomPDF + QR). Secure share links `/share/invoice/{booking}`, `/share/receipt/{payment}` (signed, no login).

### 2.3 Taxi booking

1. Go to `/taxi`:
   * Step 1 Trip: `trip_type [one_way, airport_transfer], pickup_at (date+time, > now), pickup_address (+ map pin), drop_address, passenger_count (1–60), luggage_count, airport_direction [airport_pickup|airport_drop] + flight_number/airline/terminal if transfer`.
   * `POST /taxi/quote` returns vehicle options `{vehicle, capacity, total, breakdown, rate_card, route distance_km/duration}` filtered by capacity.
2. Step 2 Choose vehicle → Step 3 Contact: `customer_name*, customer_phone*, customer_email?, special_instructions (2000 chars), vehicle_type_id*`.
3. `POST /taxi/book` → signed confirmation (`reference, pickup/drop, pickup_at, passengers/luggage, vehicle, total, status, payment_status`).
4. Track live at `/taxi/track/{token}` (read-only, expirable/revocable).
5. Logged-in users: changes at `/account/taxi/bookings` (cancel/reschedule quote, reviews at `/account/taxi/reviews/create/{booking}`).

Simple lead variant: `POST /taxi/enquiry` creates a CRM Lead (`service_type=taxi`) → “Our team will confirm”.

### 2.4 Hotel booking (login required)

1. `/hotels` → `/hotels/{slug}` → pick `room_type, rate_plan, check_in/out, rooms (1–10), adults (1–20), children`.
2. `/hotels/{slug}/book` pre-fills name/email/phone, shows meal/cancellation policy, terms checkbox if required.
3. Submit → `/hotel-bookings/{booking}/confirmation`. Manage at `/account/hotel-bookings` (quote/cancel/reschedule/review).

### 2.5 Custom trip / contact enquiry

* Quick (`/contact`): only `full_name*, phone*` → “Thank you”.
* Full custom trip (`tour_plan` via same `POST /enquiries`, also used by embedded forms): `tour_package_id?, full_name*, phone*, email?, pickup_drop*, hotel_category [budget|standard|deluxe|premium]*, adults*, children?, arrival_date* (≥ today), departure_date* (≥ arrival), message?, math captcha security_answer*`.
* Staff triage at `/admin/enquiries` → convert to Lead / Quotation / Booking.
* Contact info panel (email, phones, office address, Google Maps link) comes from `/admin/settings` → Contact.

### 2.6 Quotations

Customer opens `/q/{token}` (unguessable, no login) → items, totals, terms, `valid_until` → **Accept / Reject** (only if `sent/viewed` + not expired). Admin converts accepted quotes at `/admin/quotations/{id}/convert`.

### 2.7 Customer account

`/account`: dashboard, `/account/bookings` (+ detail, invoice download, reschedule request, review), `/account/hotel-bookings*`, `/account/taxi/bookings`, `/account/support*` (tickets + attachments), `/account/profile`, `/notifications` (in-app center, mark read). `/my-bookings` redirects here.

Notifications: in-app always; email only if user opted in per category; SMS/WhatsApp only when provider configured (see §7).

---

## 3. Admin guide — day-to-day SOPs

### 3.1 New enquiry → lead → quotation → booking → payment

1. **Enquiries** `/admin/enquiries`: quick + `tour_plan` leads. Delete spam via Delete. Call/WhatsApp customer (number in row).
2. **Leads** `/admin/leads`: assign owner, change status, add notes, schedule **Follow-ups** (`/admin/follow-ups` → complete/cancel, overdue shows on dashboard). Convert → Quotation or Booking.
3. **Quotations** `/admin/quotations`: Builder (items, taxes, terms), Revisions, Send (email + WhatsApp link), Accept/Reject/Expire manually, Convert to booking. Public link `/q/{token}`. Expiring quotes surface in `needs_attention`.
4. **Tour Bookings** `/admin/tour/bookings` (+ `/create`, `/desk`):
   * **Desk** = Reservation Desk for walk-in / phone / WhatsApp: server-priced, same validation as website.
   * Detail `/{id}`: status machine, cancellation requests (approve/reject), refunds (manual, payment-backed, never exceed paid), payments (record, due-date, reminder, receipt PDF), reschedule (history-preserving), notes, share invoice/receipt via email/WhatsApp.
   * Legacy alias `/admin/bookings/*` = same.
5. **Payments**: manual collection only (UPI/cash/bank). Record at Booking → Payments. Receipt PDF via `InvoiceService`. No auto-charge.
6. **After travel**: request review, moderate at `/admin/reviews` (approve/reject/delete).

### 3.2 Catalogue: locations → tours → availability

1. **Locations** `/admin/countries|states|cities|destinations|places`: CRUD + active toggle. Use lookups for dependent selects in tour/hotel forms. Never module-gated.
2. **Tours** `/admin/packages`:
   * Create/Edit: title, slug, city/destination/places, category, tags, gallery, itinerary (day-wise), inclusions/exclusions, pricing, min/max pax, advance-day window, weekdays, blackouts, booking toggle, vendor assignment.
   * Moderation: `approve / request-changes / reject`.
   * **AI draft itinerary** button (`AiContentController`) — review before publishing.
   * Addons `/{id}/addons`, Availability + blackouts `/{id}/availability`.
3. **Categories/Tags/Coupons**: `/admin/tour-categories`, `/tags`, `/coupons` (`code, discount_type`).
4. **Reviews** `/admin/reviews`: tour/hotel/taxi queues separately.

### 3.3 Taxi ops (requires `modules.taxi.enabled`)

Full reference: `docs/taxi-module.md`.

* Dashboard `/admin/taxi/dashboard`, Dispatch `/admin/taxi/dispatch` (eligible fleet, recommendations, manual assign/unassign, start/stop auto-dispatch), Tracking `/admin/taxi/tracking`.
* Bookings `/admin/taxi/bookings` (+ create/quote/store/convert/assign/status/payments/tracking-link). Changes `/admin/taxi/changes/{id}` (cancel, reschedule-quote, reschedule, refunds).
* Masters: Pricing (rate cards), Vehicle Types (Sedan/SUV…), Vehicles (+ documents verify, unavailable windows), Drivers (+ docs, availability/leave), Cancellation Policies + Settings, Reviews (moderate/reply/flag), Earnings (payable/void/adjustments), Plans, Payouts (mark-paid/cancel).
* Flow: `confirmed → driver_assigned → en_route → arrived → passenger_on_board → completed`, all audited.

### 3.4 Hotel ops (requires `modules.hotels.enabled`)

* Operations `/admin/hotel/operations`, Properties `/admin/hotel/properties` (publish/reject/deactivate, images), Bookings (create/status/cancel/refunds/reschedule), Reviews (moderate/reply).
* Inventory calendar (bulk/clear), Rate Plans + Seasons, Daily Rates (bulk/clear), Room Types / Units, Property Types, Amenities, Bed Types, Custom Fields, Charges, Settings.

### 3.5 Vendors, drivers, customers

* **Vendor Applications** `/admin/vendor-applications`: approve/reject/resubmission-request, KYC verify. Documents `/admin/vendor-documents/{id}/verify|reject|download`. Plans `/admin/vendor-plans`. Finance `/admin/vendor-finances/{id}` (+ adjustments), Withdrawals (approve/reject/mark-paid), Payout Accounts (verify/reject, masked).
* **Customers** `/admin/customers`: store/search.
* **Users/Staff** `/admin/users` (view, impersonate, invite), `/admin/staff` (CRUD + role assign), `/admin/roles` (custom roles, `name [a-z0-9-]` + checkboxes from `StaffPermissions::grouped()`). Impersonation blocks sensitive actions.
* Driver records are managed under Taxi → Drivers (link a login user to enable Driver Portal).

### 3.6 Support, communications, marketing

* **Support** `/admin/support` (+ replies, internal notes, assign, status, attachments), Categories `/admin/support-categories`.
* **Messages** `/admin/messages/create|/messages`: direct user message. **Templates** `/admin/templates` (`sms_body, whatsapp_body, in_app_*`). **Logs** `/admin/communication-logs`. **Campaigns** (`+send/schedule/cancel`): bulk SMS/WhatsApp unlock only when provider configured; marketing opt-in respected.
* **Marketing**: Coupons, Promotional Popup, Banners (+ order), homepage merchandising, Menus (`/admin/menus` + items/reorder/addPages), Pages (`/admin/pages` — `about, faq, privacy, terms`), Homepage Sections (reorder, item search), Editor image upload.

### 3.7 System & settings

`/admin/settings` tabs: Basic (site_name, tagline, copyright), Logo/Favicon, Contact (phones, contact_email, website_url, office_address, google_maps_url), Social & **WhatsApp** (`whatsapp_number, whatsapp_message`), SEO (`meta_title/desc/keywords, og_image, index/follow, google_site_verification`), Marketplace (platform_commission %, minimum_withdrawal), Operations (reminders, follow-ups, quotation expiry + reminder days, payment/travel offsets, campaign schedule, digest off/daily/weekly, retention 30–730d, notify_lead_created), AI (enabled, provider openai/gemini/claude/azure, model, credentials, knowledge + agent-actions toggles).

* **AI Assistant** `/admin/ai-assistant`: chat + history; Knowledge CRUD + reindex/import; Actions queue (explicit confirm/reject, e.g. `tour-feature-editor`).
* **Modules** `/admin/modules`: toggle `tours|hotels|taxi` (disabled = 404 + hidden nav, no data loss).
* **Languages/Currencies**: `/admin/languages` (+ translations, RTL), `/admin/currencies` (+ exchange-rates).
* **Number Series** `/admin/number-series`: prefixes for booking/invoice/etc.
* **Reports** `/admin/reports` (+ CSV export: bookings, hotels, tours, taxi, customers, payments, leads, vendors, commission — never KYC/bank).
* **Activity Logs** `/admin/activity-logs`, **System Health** `/admin/system` (scheduler/queue, failed-jobs retry/destroy), **MCP Access** `/admin/mcp-access` (toggle, mint/revoke 90-day tokens, `POST /mcp`).
* **Search/Select-options**: `/admin/search`, `/admin/select-options`.

---

## 4. Vendor portal (for vendors)

`/vendor/apply` → approval → `/vendor`: dashboard, tours, hotel, taxi (fleet/pricing/dispatch per ownership scope), coupons, finance (earnings, withdrawals, payout accounts), support tickets, profile. AI content draft at `POST /vendor/ai/content`.

## 5. Driver portal (for drivers)

`/driver/taxi/*` (linked identity only, taxi module on): dashboard, trips (allowed status actions only), offers (accept/reject), earnings, reviews (own approved only), profile/availability, GPS upload. Operational fields only — no financials of others.

---

## 6. Roles & permissions (staff)

| Role | Access |
|---|---|
| `super-admin` (protected) | Everything incl. roles/modules/critical settings. Cannot be renamed/deleted/stripped. |
| `administrator` | All operational except `roles.manage, modules.manage, campaigns.send, communications.manage_templates` |
| `operations-manager` | Dashboard, tours+approve, locations, bookings, vendors/KYC, finance view + `payments.record`, CRM full, support full, comms send/logs, taxi+hotel ops (no taxi refunds/settings, no hotel bookings/settings) |
| `booking-executive` | Tours view, bookings CRUD+cancel+reschedule, leads/quotations (no send/accept), taxi view/create/update/status |
| `support-agent` | `users.view+impersonate`, tours/bookings/vendors view, `support.view+reply` |
| `content-manager` | Tours create/update, coupons, `content.pages, homepage.manage, content.menus, content.seo` |
| `finance-manager` | Reports, bookings view, `finance.view/refunds/withdrawals/adjustments`, taxi payouts, `payments.record`, currencies |
| Custom (`/admin/roles`) | Any subset of `StaffPermissions::grouped()` |

Groups: `general, users, tours, locations, bookings, vendors, finance, crm, marketing, support, taxi (28 keys), hotels (30 keys), communications, content, system`. Legacy `role=admin` with zero Spatie roles keeps full access; once a staff role is assigned, enforcement is strict. `customer/vendor` always fail staff checks.

---

## 7. Payments, WhatsApp, notifications — what staff must know

* **Payments are manual.** Razorpay/Paytm keys live in `config/services.php` but checkout is a stub. Collect via UPI/cash/bank → record in Admin → Booking → Payments → receipt PDF. Refunds manual, payment-backed. No gateway auto-charge.
* **WhatsApp is manual (`wa.me` links).** Set number + default message at `/admin/settings` → Social & WhatsApp. Buttons across site (`WhatsAppFloatButton`, TopBar, Footer, “Plan My Trip on WhatsApp”) open `https://wa.me/<number>?text=...`. No auto-send; shares logged with `provider=whatsapp-manual`.
* **Email is active** (Postmark/Resend/SES via `config/services`): booking created/status/refund, payment recorded/due, travel reminders, quotation sent/expiring/accepted, lead assigned/follow-up, ticket assigned/reply, taxi confirmed/driver_assigned/status/cancel/refund/reschedule/tracking-link, hotel created/cancel/reschedule/refund, vendor KYC/payout/withdrawal, invitations, digests. In-app center at `/notifications`.
* **SMS disabled until provider set** (`SMS_PROVIDER` null = disabled; future MSG91/Twilio). Templates already carry `sms_body`/`whatsapp_body`.

---

## 8. Daily / weekly checklists

**Daily (ops):** Dashboard `needs_attention` → overdue follow-ups → expiring quotes → new enquiries/leads → tour booking statuses → taxi dispatch buckets + tracking → support open/high tickets → failed jobs.
**Weekly:** review moderation, vendor applications/KYC, withdrawals/payouts, inventory/rate sanity, homepage/banners/popups, reports export, communication logs.
**Scheduler (server):** single cron `* * * * * php artisan schedule:run` drives taxi offer expiry (5 min), `ops:cleanup` (daily 03:00), reminders/digests, retention.

---

## 9. Troubleshooting FAQ

| Symptom | Check |
|---|---|
| Taxi/Hotel pages 404 | `/admin/modules` toggles + staff permission |
| Cannot assign taxi driver | availability, leave, capacity, overlapping trip, same vendor |
| Tracking shows nothing | link revoked/expired, trip in live status, location freshness |
| Review button missing | booking `completed` + owned by customer + inside window |
| Map blank | provider `none` by design; add Google keys in settings |
| Payment page says “team reviews” | by design — manual collection active |
| Email not received | mail provider config + user opt-in per category + communication logs |
| SMS/WhatsApp campaign locked | provider not configured — use manual `wa.me` share |
| Staff sees empty menu | role lacks permission; super-admin check |
| Impersonated action blocked | sensitive actions blocked while impersonating by design |

---

## 10. Data & safety

* Dev DB is MySQL `tour_canyon_new`. **Never** `migrate:fresh/refresh/reset` or `db:wipe` on it (blocked in code). Normal changes: `php artisan migrate` only. Tests run on SQLite `:memory:` via `composer test-safe` / `php artisan test`.
* Genuine local rebuild only: `bash scripts/backup-db.sh` then `ALLOW_DESTRUCTIVE_DB_COMMANDS=1 php artisan migrate:fresh`.
* Reports export ops figures only, never KYC/bank. Payout accounts masked.
