# Solvex Report — Design System Specification (v2)

## 1. Design Direction: "Quiet Public-Records Office"

The Solvex Report user interface is designed around the concept of a **quiet public-records office**: calm, precise, trustworthy, and editorial. The platform functions as a civic ledger where clarity, density, and accountability supersede decorative novelty.

### What We Replaced
- **No decorative gradients or glowing blobs:** Gradients and soft multi-stop backgrounds have been removed in favor of clean, flat paper tones.
- **No floating drop shadows:** Replaced with crisp 1px hairline borders (`--clr-border: #E2DFD6`). Elevation shadows are strictly reserved for overlay layers (dropdown menus, modals, toasts).
- **No pill-shaped overload:** Radii are kept compact (3px to 8px). Rounded pills are used exclusively for functional status tags.
- **No artificial hover-lifts:** Removed `translateY(-2px)` transitions. Hover interactions rely exclusively on subtle color and border adjustments.
- **No Bootstrap blue:** Replaced default framework accents (`#0d6efd`) with an intentional civic teal and neutral ink palette.

---

## 2. Design Tokens

### Color Palette & Surfaces (Light Theme)
- **Page Background:** `--clr-page-bg: #F7F6F2` (Warm, non-glare paper surface)
- **Primary Card Surface:** `--clr-surface: #FFFFFF`
- **Sunken Well & Headers:** `--clr-sunken: #EFEDE7`
- **Hairline Border:** `--clr-border: #E2DFD6` (1px crisp dividing boundary)
- **Strong Hairline Border:** `--clr-border-strong: #CFCBBE`

### Color Palette & Surfaces (Dark Theme)
- **Page Background:** `--clr-page-bg: #121417` (Deep matte charcoal)
- **Primary Card Surface:** `--clr-surface: #1A1D23`
- **Sunken Well & Headers:** `--clr-sunken: #15181E`
- **Hairline Border:** `--clr-border: #2B303B`
- **Strong Hairline Border:** `--clr-border-strong: #3D4452`

### Ink & Typography Colors
| Token | Light Value | Dark Value | Usage |
|---|---|---|---|
| `--clr-ink-primary` | `#14213D` | `#F1F3F7` | Primary page headers, ledger titles, body text |
| `--clr-ink-secondary` | `#4A5568` | `#9DA7B8` | Descriptive labels, metadata, secondary text |
| `--clr-ink-tertiary` | `#718096` | `#6E7787` | Form help text, timestamps, table column headers |

### Civic Accent & Functional Status Colors
| Token | Hex Value | Purpose |
|---|---|---|
| `--clr-accent` | `#0B7A6E` | High-contrast links, active nav markers (AA compliant) |
| `--clr-accent-fill` | `#0E9F8E` | Primary action buttons and badges |
| `--clr-accent-subtle` | `#E6F5F3` | Subtle table row highlights, category badges |
| `--clr-status-submitted` | `#64748B` | Intake status tag (Slate) |
| `--clr-status-assigned` | `#6366F1` | Dispatched to staff (Indigo) |
| `--clr-status-in-progress` | `#2563EB` | Active resolution in progress (Blue) |
| `--clr-status-on-hold` | `#D97706` | Awaiting parts or scheduling (Amber) |
| `--clr-status-resolved` | `#059669` | Work completed, awaiting student confirmation (Green) |
| `--clr-status-closed` | `#6B7280` | Confirmed resolved or archived (Gray) |
| `--clr-status-reopened` | `#EA580C` | Marked "Still Broken" by student (Orange) |
| `--clr-status-rejected` | `#DC2626` | Refused with written justification (Red) |

---

## 3. Typography & Tabular Figures

- **Headings & Ledger Titles:** Editorial serif stack (`'Source Serif 4'`, `'Newsreader'`, `Georgia`, serif), weights 600 / 700.
- **Body & Controls:** `Inter`, `system-ui`, `-apple-system`, `sans-serif`, weight 400 (regular), 500 (medium), 600 (semibold). Body font size: 15px (`0.9375rem`), line-height: 1.6.
- **Reference Identifiers & Metrics:** `'JetBrains Mono'`, `'Consolas'`, monospace. Tabular figures (`font-variant-numeric: tabular-nums`) ensure vertical alignment across data grids.
- **Scale:**
  - 12px (`--fs-xs`), 13px (`--fs-sm`), 15px (`--fs-base`), 16px (`--fs-md`), 18px (`--fs-lg`), 20px (`--fs-xl`), 24px (`--fs-2xl`), 32px (`--fs-3xl`).

---

## 4. Spacing, Borders, and Radii

- **Base Grid:** 4px incremental scale (4px, 8px, 12px, 16px, 20px, 24px, 32px, 48px, 64px, 80px).
- **Corner Radii:**
  - Controls, inputs, and small badges: `--radius-sm: 3px`
  - Cards, tables, and dialogs: `--radius-md: 6px`
  - Modals and split layouts: `--radius-lg: 8px`
  - Status pills only: `--radius-full: 9999px`

---

## 5. Component Library & Implementation

### 1. Status Pills (`.status-pill`)
Rendered via `status_tag($status)` helper in `includes/ui.php`. Features a 6px status indicator dot with high-contrast text and a subtle 1px border.
```php
<?= status_tag('in_progress') ?>
```

### 2. Priority Badges (`.priority-tag`)
Rendered via `priority_tag($priority)` helper. Uppercase, compact badges indicating severity (`critical`, `high`, `medium`, `low`).
```php
<?= priority_tag('critical') ?>
```

### 3. Civic Ageing & SLA Indicators (`.days-chip`, `.sla-bar`)
Calculates the active elapsed duration. Automatically turns warning amber past 50% target SLA, and danger red when exceeding SLA targets:
```php
<?= days_chip($daysOpen, $targetDays) ?>
<?= sla_bar($daysOpen, $targetDays) ?>
```

### 4. Stat Cards (`.stat-card`)
Flat metric blocks displaying large tabular values with muted labels:
```php
<?= stat_block('Open Incidents', 142, 'inbox', 'primary') ?>
```

### 5. Buttons (`.btn-solvex`)
Flat surfaces with precise focus outlines. Styles include `.btn-primary` (civic teal), `.btn-secondary` (bordered paper), `.btn-ghost` (borderless utility), and `.btn-danger` (red destructive).

### 6. Empty States (`.empty-state`)
Standardized placeholder block rendered via `empty_state($title, $message, $icon, $ctaUrl, $ctaLabel)` helper.

---

## 6. Dark Theme & Accessibility Features

### Pre-Render Theme Initialization
Theme state is stored in `localStorage.getItem('solvex_theme')`. An inline script in `<head>` applies `data-theme="dark"` before the DOM paints, preventing theme flash.

### WCAG 2.2 AA Contrast Compliance
- All text-to-background combinations meet or exceed the 4.5:1 ratio requirement.
- Input focus outlines use high-visibility 2px solid accents with 2px offset.
- Screen readers are provided an explicit `#main-content` skip link at the top of the body.

### Media Query Adaptations
- **`prefers-reduced-motion: reduce`:** Animation durations and transition delays are forced to `0.01ms`.
- **`forced-colors: active`:** Preserves high-contrast system borders across buttons, cards, and input fields.
- **`@media print`:** Strips navigation, headers, footers, and background colors, delivering crisp monochrome incident records.

---

## 7. Living Styleguide

A developer-accessible component styleguide is available at:
```
http://localhost:8000/styleguide.php
```
*(Automatically disabled in production environments when `APP_ENV = 'production'`)*.
