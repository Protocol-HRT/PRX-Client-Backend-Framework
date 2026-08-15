# Frontend Setup — Operator Guide

Status: current as of 2026-08-15

What to configure in the admin panel so a new company's frontend (a separate website that reads this backend's API) comes out fully branded. The frontend itself contains **no** branding — everything below is the source of truth.

## Launch checklist (Settings)

1. **Brand** (`Settings → Brand`) — company name, tagline, logos (default/dark/light), favicon, hero image, site URL, optional announcement bar.
2. **Theme** (`Settings → Theme`) — brand colors and font names; the website applies them automatically.
   - *Frontend template*: which layout variant the website should use (leave `default` unless your web team provides others).
   - *Custom CSS*: optional style overrides applied on top of the website's styling — no developer needed for small visual tweaks.
3. **Contact** (`Settings → Contact`) — support/sales email, phone, address, business hours, social links. Feeds the contact page and footer.
4. **SEO & Analytics** (`Settings → SEO & Analytics`) — default page title/description, social-share image, GA4 / GTM / Facebook Pixel / TikTok Pixel IDs.
   - *Custom tracking scripts*: paste complete `<script>` tags from any other vendor (head or body placement). ⚠️ These run on every page of the website — only paste scripts from vendors you trust.
   - *Allow indexing*: keep **off** until launch.
5. **Billing** — choose the checkout path (provider embed vs. local gateway) and configure merchant accounts if local.
6. **Integrations** — prescribe-rx credentials for clinical flows.

## Content

- **Pages** — create a page with slug `home` (this is the website's homepage), then about, FAQ, legal pages, etc. Build them from sections; a starter home page can be seeded by your developer.
- **Menus & Layout** — create menus (e.g. `main-nav`, footer menus) and place them in the header/footer regions. Menu links to pages/products/posts follow the item automatically if its slug changes.
- **Catalog / Blog / FAQ / Profiles** — populate as normal; the website lists whatever is published.

## API access for the website

In **API Clients**, create a client for the website, copy the token to the web team, and (recommended for production) set its *Allowed origins* to the website's domain. Revoking the token immediately cuts that frontend off.

## Where the website runs

The website is its own application on its own domain (e.g. `www.company.com`); this admin/API typically lives on a subdomain (e.g. `api.company.com`). Ask your web team to follow `docs/frontend/dev.md`.
