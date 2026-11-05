# Solvex Report — Design System Documentation

Solvex Report features a bespoke, accessible, civic design system engineered in vanilla CSS (`public/assets/css/solvex.css`). It draws inspiration from GitHub Issues and Linear (calm density and status pills), FixMyStreet (card-based public tracking), and Stripe (stat metrics).

---

## 1. Design Tokens & Color Palette

### Brand Colors
* **Primary Teal:** `--clr-primary: #0E9F8E` (Main branding, CTAs, focus indicators)
* **Primary Hover:** `--clr-primary-hover: #0C8A7A`
* **Primary Light:** `--clr-primary-light: #E6F7F5` (Subtle badges, tint fills)
* **Deep Navy:** `--clr-navy: #0B2545` (Headings, primary card titles, dark navigation)
* **Accent Amber:** `--clr-accent: #F59E0B` (Warning states, ageing chips past 50%)

### Semantic Palette
* **Success Green:** `--clr-success: #10B981` (Resolved status, verified badges)
* **Danger Red:** `--clr-danger: #EF4444` (Critical priorities, expired alerts, rejection)
* **Warning Amber:** `--clr-warning: #F59E0B` (On-hold status, approaching SLA)
* **Info Blue:** `--clr-info: #3B82F6` (In-progress tickets, helper hints)

### Neutral Gray Scale
* `50`: `#F9FAFB` | `100`: `#F3F4F6` | `200`: `#E5E7EB` | `300`: `#D1D5DB`
* `400`: `#9CA3AF` | `500`: `#6B7280` | `600`: `#4B5563` | `700`: `#374151`
* `800`: `#1F2937` | `900`: `#111827`

---

## 2. Typography

* **Headings:** `Plus Jakarta Sans`, 700 / 800 weight, line height 1.25.
* **Body / UI:** `Inter`, 400 / 500 / 600 weight, line height 1.6.
* **Mono / Codes:** `JetBrains Mono`, `Consolas`, monospace (used for ticket reference `SVX-YYYY-NNNNN`).

---

## 3. Core Component Library

### 1. Buttons (`.btn-solvex`)
* `.btn-primary`: Solid teal fill with subtle lift (`translateY(-1px)`) and teal glow shadow.
* `.btn-secondary`: Crisp neutral border with soft hover background.
* `.btn-accent`: Warm amber callout for urgent actions.
* `.btn-ghost`: Borderless utility button for secondary controls.

### 2. Form Controls (`.form-control-solvex`, `.form-select-solvex`)
* Radiused inputs (`border-radius: 10px`) with 1.5px neutral border.
* High-visibility 3px teal glow outline on `:focus-visible`.
* Integrated `.is-invalid` and `.is-valid` styles with `.invalid-feedback` text.

### 3. Password Strength Meter (`.strength-meter`, `.strength-meter-fill`)
* 4-segment animated indicator checking length, mixed casing, digits, and special symbols:
  * Weak (Red) &rarr; Fair (Amber) &rarr; Good (Blue) &rarr; Strong (Green).

### 4. Status Pills (`.status-pill`)
Calm, rounded status pills featuring a 6px status dot:
* `submitted`: Slate (`#64748B`)
* `assigned`: Indigo (`#6366F1`)
* `in_progress`: Blue (`#3B82F6`)
* `on_hold`: Amber (`#F59E0B`)
* `resolved`: Green (`#10B981`)
* `closed`: Gray (`#9CA3AF`)
* `reopened`: Orange (`#F97316`)
* `rejected`: Red (`#EF4444`)

### 5. Priority Tags (`.priority-tag`)
Uppercase high-visibility badges:
* `critical`: Solid crimson red (`#EF4444`)
* `high`: Orange fill (`#FED7AA`)
* `medium`: Soft amber (`#FEF3C7`)
* `low`: Blue-gray (`#F1F5F9`)

### 6. "Days Open" Ageing Chip (`.days-chip`)
* Normal: Neutral slate pill with clock glyph (`#F3F4F6`).
* Warning: Amber background when open past 50% target SLA.
* Danger: Red background when open past target SLA.

### 7. Interactive Toast System (`.toast-container`, `.toast-message`)
* Fixed top-right notifications with automatic timeout, dismiss cross, and color-coded left borders.

---

## 4. Accessibility & Responsive Breakpoints

* **WCAG 2.1 AA Compliance:** Minimum 4.5:1 text-to-background contrast ratio maintained across all elements.
* **Visible Focus:** `:focus-visible` ring on all interactive elements.
* **Prefers Reduced Motion:** `@media (prefers-reduced-motion: reduce)` disables all smooth animations and transitions.
* **Breakpoints Tested:**
  * **Desktop (1280px+):** Full 3-column stats grid, side-by-side hero card preview, expansive navbar.
  * **Tablet (768px - 991px):** Re-flowed 3-column stats, wrapped action buttons, comfortable touch targets.
  * **Mobile (360px - 575px):** Stacked single-column layouts, touch-friendly 44px minimum tap targets, full-width action buttons.
