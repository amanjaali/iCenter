# ALLVA Accounting — brand assets

Drop this folder into your project (e.g. `public/brand/` or `src/assets/brand/`).

## Files

### logo/
| File | Use |
|---|---|
| `allva-accounting-primary.svg` | Marketing pages, headers on light backgrounds |
| `allva-accounting-reverse.svg` | Same, on navy |
| `allva-accounting-onecolour-navy.svg` | Single-colour print / fax / stamps |
| `allva-accounting-onecolour-white.svg` | Single-colour on dark |
| `allva-accounting-compact.svg` | App top bar, light |
| `allva-accounting-compact-reverse.svg` | App top bar, dark |
| `allva-accounting-stacked.svg` | Login and empty states |
| `allva-accounting-sidebar.svg` | Navy sidebar header |
| `invoice-mark.svg` | Invoices, statements, PDF documents |
| `allva-symbol*.svg` | Symbol alone — avatars, loaders, watermarks |

### icons/
| File | Use |
|---|---|
| `app-icon.svg` + `app-icon.png` (1024) | iOS / Android / desktop app icon |
| `favicon.svg` + `favicon-32.png` / `favicon-16.png` | Browser tab |

### tokens
- `tokens.css` — CSS custom properties
- `tokens.json` — same values for Tailwind config or JS themes

## Fonts

Outfit (display), Sora (body), IBM Plex Mono (data). All free on Google Fonts:

```html
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Sora:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
```

## Rules

1. Never restyle the ALLVA symbol or wordmark for a product — the product name sits beside it, behind a hairline rule.
2. Product name is Sora weight 300, sentence case, never bolder than the ALLVA wordmark.
3. Signal blue (`--allva-signal`) marks the leading half of the symbol and the active UI state only. It is not a general highlight colour.
4. App icons and favicons use the symbol alone on navy — never the wordmark.
5. Below 24px the symbol goes single-colour white.

## Example

```html
<link rel="icon" href="/brand/icons/favicon.svg">
<img src="/brand/logo/allva-accounting-sidebar.svg" alt="ALLVA Accounting" height="40">
```
