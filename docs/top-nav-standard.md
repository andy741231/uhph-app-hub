# App Suite Top Navigation Standard

All UHPH App Hub sub-applications with a top navigation bar must follow this
spec. Token values are canonical; implementation details may differ per stack
(plain CSS in `flipbook`, Tailwind in `doc-review`) but the rendered result must
match. If a change is needed, update this file first, then each app's nav.

## Container

- White background (`#FFFFFF`), sticky, `top: 0`, above page content (`z-index`
  ≥ 50 in Tailwind apps, `z-index: 100` in flipbook's scale).
- Bottom border `1px solid #E2E8F0` (`--gray-200` / `border-gray-200`) plus a
  subtle shadow (`--shadow` / `shadow-sm`).
- Inner width `max-width: 1280px` centered, horizontal padding `1rem–1.5rem`,
  height `64px` (`h-16`).
- The nav stays white even when the app renders a dark page — do not add
  `dark:` variants to nav elements.

## Brand (left)

- App icon + app name, linking to the app's home page.
- Color: UH red `#C8102E` (`--primary` / `uh-red`).
- Icon ~`1.5rem`/`h-7 w-7`; name `font-size: 1.25rem`, `font-weight: 700`.
- The brand icon is the app's identity and MAY differ per app; everything else
  in the nav is standardized.

## Primary links (desktop, left of brand group)

- Gray text `#475569` (`--gray-600` / `text-gray-600`), `14px`, medium weight.
- Hover: darker text + light gray pill background (`hover:bg-gray-100`).
- Active page: red text `#C8102E` on `bg-uh-red/10` pill, with
  `aria-current="page"`.

## Account controls (right)

Order, left to right:

1. **All Applications** — shown only when the Hub reports
   `application_count > 1`; links to the Hub base URL; a real cross-app
   anchor, not an Inertia/SPA visit.
2. App-specific primary action(s) if any (e.g. Flipbook's "New Flipbook" red
   `btn-primary`).
3. **Sign out** — native `POST` form with CSRF token (required for the
   coordinated Hub logout chain); styled as a secondary button.

Labels (exact casing): `All Applications`, `Sign out`.

### Anonymous state

Apps that render the nav to signed-out visitors (e.g. Flipbook's public
gallery) show only: the brand (left) and a **Sign in** control (right) — a
`btn-secondary`-style link to the app's login page with the
`fa-right-to-bracket` icon. No New/primary action, no account controls.

Secondary/outline button style: white background, `1px` border `#CBD5E1`
(`--gray-300` / `border-gray-300`), gray `#334155` text, `8px` radius,
small size (~`0.375rem 0.875rem` padding, `13–14px` font), `hover` lightens to
`#F8FAFC`.

## Iconography

Font Awesome is the standard icon library for all apps (`fa-solid` style;
`<i class="fa-solid fa-…" aria-hidden="true"></i>` markup). Flipbook loads FA
6.5.1 via cdnjs; Document Reviewer bundles `@fortawesome/fontawesome-free`
(FA 7.x) via npm/Vite. Use the canonical FA names below — every one exists in
both FA 6 and FA 7.

| Purpose | Font Awesome icon |
| --- | --- |
| App launcher / All Applications | `fa-grip` |
| Sign out | `fa-right-from-bracket` |
| Sign in (anonymous) | `fa-right-to-bracket` |
| Mobile menu open | `fa-bars` |
| Mobile menu close | `fa-xmark` |
| Create/new action | `fa-plus` |

Decorative icons get `aria-hidden="true"`.

## Mobile (below 768px / `md` breakpoint)

- Nav links and controls collapse behind a hamburger toggle: `44×44px` target,
  `aria-expanded`, `aria-controls` pointing at the menu container.
- Open menu: full-width white dropdown below the header, links/buttons
  full-width with `min-height: 44px`; do not show the username.
- Menu closes on Escape, outside click, and link navigation.
- No-JS fallback: if the toggle script fails to load, controls must remain
  visible/wrapped — never rely on JS to reveal navigation.

## Accessibility

- `:focus-visible` red outline (`#C8102E`, 2–3px) on every nav link/button.
- All interactive targets ≥ `44×44px`.
- No horizontal overflow at 375px.

## References

- Flipbook: `flipbook/includes/header.php`, `flipbook/assets/css/app.css`
  (`.navbar*` classes), `flipbook/assets/js/nav.js`
- Document Reviewer: `doc-review/resources/js/Layouts/DocReviewLayout.vue`
- App Hub shell: `app-hub/resources/views/layouts/app.blade.php` (`.topbar`)
