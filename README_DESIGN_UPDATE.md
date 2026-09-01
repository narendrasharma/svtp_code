# Shree Vrindavan Tour Packages — Festive Frontend Redesign

## Apply it
Copy everything in this zip into your project at the **same paths** (overwrite originals),
then add the routes listed in `ROUTES_TO_ADD.txt` to `routes/web.php`. After that:

```bash
npm install
npm run build      # or `npm run dev` while working locally
php artisan optimize:clear
```
Rebuild the same way on your Nginx/AWS box, then reload PHP-FPM/Nginx if you cache config.

## What's included
```
resources/css/app.css                          Festive palette, glassmorphism, animations, top bar, decor
resources/js/festiveAssets.js                   All placeholder content in one file (images, shlokas, FAQs, etc.)
resources/js/Components/HeroCarousel.vue        Full-image hero slider (autoplay, ken-burns, dots/arrows)
resources/js/Components/PeacockDecor.vue        Floating peacock feathers + flying birds overlay (hero)
resources/js/Components/ShlokaTicker.vue        Scrolling shloka strip
resources/js/Components/TrustBadgeMarquee.vue   Rolling trust-badge strip (honest, no fake brand logos)
resources/js/Components/TopBar.vue              Utility bar: phone/email left, socials right
resources/js/Components/Logo.vue                New SVG lotus wordmark logo
resources/js/Components/PackageCard.vue         Image tour cards, lazy-load, festive badge
resources/js/Layouts/AppLayout.vue              Top bar + nav (with logo) + expanded footer
resources/js/Pages/Home.vue                     Hero, circuits, tours, why-us, services, attractions,
                                                 best-time table, gallery preview, testimonials, blog
                                                 preview, FAQ, trust marquee
resources/js/Pages/Packages/Index.vue           Filters + grid + why-book-with-us + destinations + FAQ
resources/js/Pages/Packages/Show.vue            Gallery, itinerary, spiritual note, pricing calculator
resources/js/Pages/Static/Destinations.vue      NEW — Explore Braj overview page
resources/js/Pages/Static/Gallery.vue           NEW — photo gallery with click-to-enlarge
resources/js/Pages/Static/Testimonials.vue      NEW — full reviews page with rating breakdown
resources/js/Pages/Static/Terms.vue             NEW — dummy Terms & Conditions
resources/js/Pages/Static/Privacy.vue           NEW — dummy Privacy Policy
resources/js/Pages/Static/SpiritualWisdom.vue   (from an earlier batch — not linked in nav; add the route
                                                 in ROUTES_TO_ADD.txt only if you want it live)
resources/js/Pages/Blog/Index.vue               NEW — travel blog listing (dummy posts)
tailwind.config.js                              Festive color tokens, for any Tailwind use
ROUTES_TO_ADD.txt                               Every route line you need to paste into web.php
```
Nothing else in your project changes — auth, admin, booking, controllers, migrations untouched.

## About the images
Placeholder photography (Prem Mandir, Banke Bihari, Lathmar Holi, Ganga Aarti, Taj Mahal) comes from
**Wikimedia Commons** under CC BY-SA/CC BY licenses — free for commercial use with attribution, wired in
through `festiveAssets.js` so swapping in your own photography later is a five-minute job. Keep a credit
line somewhere (footer or an `/attributions` page): *"Temple & festival photography via Wikimedia Commons
contributors, CC BY-SA / CC BY."*

## About the icons
Everything currently uses **Bootstrap Icons** (`bi-*`), which your project already has wired up and
working — that's why nothing broke. If you specifically want **Font Awesome** icons too, add this to the
`<head>` of `resources/views/app.blade.php`:
```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
```
Then any new icon can use `fa-*` classes (e.g. `<i class="fa-solid fa-om"></i>`) alongside the existing
`bi-*` ones — I kept everything on Bootstrap Icons in this batch to avoid depending on a CDN link you
haven't added yet.

## The logo
`Components/Logo.vue` is a simple SVG lotus mark + festive-gradient wordmark, used in the navbar and
footer — no image file needed, it's inline SVG so it stays crisp at any size. Treat it as a placeholder:
good enough to make the site feel finished, but if you want a professionally designed brand mark, that's
worth commissioning separately.

## Your checklist — saved and mapped to what's done
I've kept your full "Website Features, Services & Design Checklist" on file. It's a genuinely large
product roadmap (destination explorer pages, services page, taxi/vehicle booking, custom trip builder, AI
trip planner, full booking + payments, admin panel, CRM, SEO architecture, interactive map — 20 sections
in total) — realistically weeks of backend + frontend work, not something to build in one pass. Here's
what's covered so far vs. what's next, using **your own checklist's "Version 1 — Launch First" priorities**
as the sequence:

**Done (this batch + earlier ones):**
- Vibrant homepage with hero, trip finder, popular tours, why-us, services teaser, attractions, best-time
  table, gallery preview, testimonials, blog preview, FAQ, trust marquee
- Tour package detail page: gallery, itinerary, inclusions/exclusions, pricing calculator, WhatsApp/Book CTAs
- Tours directory with filters, why-book-with-us, destinations, FAQ
- About company (existing page, not yet redesigned — see below)
- Destinations overview page (single page covering Vrindavan/Mathura/Govardhan/Barsana/etc. at a glance)
- Gallery, Blog listing, Testimonials, Terms, Privacy — all new pages with placeholder content
- Logo, top bar (phone/email/socials), peacock feather + flying bird decorative motifs, animations throughout

**Next up, in priority order (say which to tackle first):**
1. **Individual destination pages** (one page per place — Vrindavan, Mathura, Govardhan, Barsana, Gokul —
   with timings, map, nearby attractions, tips, related packages) instead of one overview page
2. **Services page** + **Vehicle & Taxi booking section** (the checklist's own vehicle card fields:
   capacity, AC, luggage, starting price, WhatsApp booking)
3. **Custom Trip Builder** (multi-step form → WhatsApp/quote)
4. Redesign **About** and **Contact** pages to match the new look, add a **Foreign Tourist** section and a
   **Senior Citizen & Family Tours** package category
5. **Mobile bottom bar** (Call | WhatsApp | Packages | Taxi) + general mobile pass
6. SEO basics: meta descriptions, Open Graph images, sitemap, structured data

**Deliberately not started yet** (these are real backend systems, not frontend styling — they need their
own scoped build): AI trip planner, full booking/payment system with Razorpay, admin panel expansion,
CRM/lead pipeline, interactive Braj map. Your checklist itself lists these as Version 2 / later phases —
happy to start on any of them whenever you're ready to move past frontend-only work.
