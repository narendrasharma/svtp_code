# Taxi Module — Operator & Buyer Guide

Reusable private-transfer / cab module for this marketplace: bookings, fleet,
pricing, dispatch, driver portal, live tracking, earnings & payouts,
cancellation/refund/reschedule, and post-trip reviews.

## 1. Module overview

| Area | What it does |
|---|---|
| Bookings | One-way / airport-transfer (round-trip, hourly, outstation present as trip types) taxi bookings with server-priced snapshots |
| Fleet | Vehicle types, vehicles (+ documents, unavailable windows), drivers (+ documents, availability/leave) |
| Pricing | Vendor rate cards + rental packages → immutable per-booking pricing snapshot |
| Dispatch | Manual assignment, Smart Dispatch recommendations, controlled auto-dispatch with driver offers |
| Driver Portal | Trips, offers, earnings, ratings, profile, live-location uploads |
| Tracking | Secure revocable customer tracking links (token-authenticated, read-only) |
| Finance | Manual payment collection, driver earnings from compensation plans, batch payouts |
| Changes | Cancellation policies, payment-backed refunds, reschedule history |
| Reviews | Booking-backed 1–5 ratings, moderation, vendor replies, driver/vendor aggregates |

## 2. Enabling Taxi

Admin → Taxi Settings, or `Setting::setValue('modules.taxi.enabled', '1')`.
When disabled: all `/admin/taxi/*`, `/vendor/taxi/*`, `/driver/taxi/*`,
`/account/taxi/*` and `/taxi/track/*` routes return 404, navigation hides,
and `taxi:expire-dispatch-offers` no-ops. No data is deleted.

## 3. Required permissions

Staff permissions (Admin → Roles): `taxi.dashboard.view`, `taxi.bookings.*`
(view/create/update/assign/status/cancel), `taxi.pricing.view/manage`,
`taxi.vehicle_types.view/manage`, `taxi.vehicles.view/create/update`,
`taxi.drivers.view/create/update/documents`, `taxi.cancellations.view/manage`,
`taxi.refunds.view/manage`, `taxi.reschedule.manage`,
`taxi.driver_earnings.view/manage`, `taxi.driver_payouts.view/manage`,
`taxi.reviews.view/moderate/reply`, `taxi.settings.manage`.
Vendors need no granular setup (ownership scoping is the model); drivers need a
linked `drivers.user_id` row; customers just need an account owning the booking.

## 4. Creating vendor / drivers / vehicles

1. Vendor registers → admin approves → `VendorProfile` active.
2. Vendor → Taxi → Drivers → create driver (link a login user to enable the Driver Portal).
3. Admin → Taxi → Vehicle Types (e.g. Sedan, SUV) → Vendor → Vehicles → create vehicle.
4. Driver availability/leave and vehicle unavailable windows block assignment automatically.

## 5. Vehicle types

Admin-owned catalogue (`name`, capacity, luggage). Bookings reference a type;
assignment validates passenger capacity.

## 6. Pricing / rate cards

Vendor (or Admin) → Taxi → Pricing → rate card per trip type + currency with
base fare, distance slab, night/waiting/driver-allowance, tax. Rental packages
cover hourly/outstation extras. Booking creation resolves the card server-side
and freezes an immutable `pricing_snapshot` — later card edits never rewrite history.

## 7. Booking flow

Create (admin/vendor desk or quotation conversion) → `confirmed` →
assign → `driver_assigned` → `en_route` → `arrived` → `passenger_on_board` →
`completed`. Every transition is server-validated and written to status history
with audit + customer/vendor/driver notifications.

## 8. Dispatch

Admin/Vendor → Taxi → Dispatch: booking buckets, eligible fleet, manual assign /
unassign / reassign with overlap, capacity and leave checks. Manual assignment
always supersedes pending auto-dispatch offers.

## 9. Smart Dispatch

`TaxiDispatchRecommendationService` ranks eligible driver+vehicle pairs
(distance/ETA-aware when routing is enabled). Decision support only — it never
assigns by itself.

## 10. Auto-dispatch / offers

Settings → enable auto + offer flow. Operator starts auto-dispatch on a
confirmed booking → top-ranked driver gets a time-boxed offer → accept performs
the normal assignment flow under row locks; reject/timeout advances to the next
candidate (max attempts configurable). Scheduler expires stale offers:

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

(`taxi:expire-dispatch-offers` runs every 5 minutes; idempotent, chunked,
module-aware.)

## 11. Driver Portal

`/driver/taxi/*` (linked driver identity only): dashboard, trips with allowed
status actions, offer accept/reject, earnings, ratings, profile/availability.
Drivers see operational fields only — no financials, no other drivers' data.

## 12. Live location

Drivers upload GPS via Driver Portal; staleness threshold and retention days in
settings; nightly `ops:cleanup` purges raw pings only (bookings never deleted).

## 13. Maps / Google setup

Settings → Map provider `none` (default, coordinates-only — everything works) or
`google` (browser key for display, server key for routing/ETA). Keys live in
settings, never in code; server key is never sent to the browser. Invalid/missing
keys degrade gracefully to coordinates + freshness states.

## 14. Customer tracking

Per-booking secure links (`/taxi/track/{token}`), single-active, revocable,
expirable. Token viewers see trip status + driver position only during live
statuses; terminal states show `ended`. **Read-only**: tokens cannot cancel,
reschedule, pay or review.

## 15. Driver earnings

Compensation plans (fixed / per-trip / percentage / slab) per vendor.
Completion records exactly one immutable earning row; adjustments are explicit
append-only rows; paid payout history is never mutated silently.

## 16. Payouts

Batch payable earnings → payout → mark-paid with external reference (manual,
gateway-neutral). Cancel only before payment.

## 17. Cancellation / refund / reschedule

Scoped cancellation policies (platform default → vendor/trip-type overrides)
with free cutoff, fixed/percentage/non-refundable fees, min/max caps, no-show
fees. Server-calculated quotes; idempotent cancellation with immutable snapshot;
assignment/offers cleaned up; tracking exposure ends. Refunds are payment-backed
(never exceed paid amount), explicitly allocated to payments, manual settlement
only. Reschedule preserves agreed pricing and records history.

## 18. Reviews

Completed trips → customer rates 1–5 (overall + optional dimensions) + optional
plain-text comment. Pending → admin approve/reject/hide; vendors reply to
approved reviews and flag abuse (flag never hides). Drivers see own approved
feedback only. Aggregates are query-based (no stale stored averages) with a
public-visibility gate.

## 19. Scheduler

One cron (`schedule:run`) drives: `taxi:expire-dispatch-offers` (5 min),
`ops:cleanup` (daily 03:00, incl. location retention). All taxi jobs are
idempotent and safe to re-run.

## 20. Queue requirements

None. All taxi notifications are synchronous database (+ optional mail by user
preference); `QUEUE_CONNECTION=database` default works; no Redis required.

## 21. Demo seeder

```bash
php artisan db:seed --class=TaxiDemoSeeder
```

Creates clearly-fake demo vendor/customer/fleet, one pending / one active / one
completed booking plus an approved review. Idempotent; never runs automatically.

## 22. Common troubleshooting

| Symptom | Check |
|---|---|
| Taxi pages 404 | `modules.taxi.enabled` setting; staff permission |
| Cannot assign driver | availability, leave, capacity, overlapping trip, same vendor |
| No dispatch candidates | fleet active + pricing/vehicle-type setup; radius setting |
| Offers never arrive | `taxi.dispatch.*` settings; scheduler cron running |
| Tracking shows nothing | link not revoked/expired; trip in live status; location freshness |
| Review button missing | booking `completed` + owned by customer + inside review window |
| Map blank | provider `none` by design; add Google keys for maps |
| Settings page validation error | fill all required fields incl. new Reviews section |
