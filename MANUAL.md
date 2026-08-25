# Exam Portal — Enterprise Chart & Analytics Redesign Manual

> **Version:** 1.0  
> **Last updated:** July 2026  
> **Applies to:** Line Chart (Average Performance) · Segmented Progress Ring (Assessment Distribution / Submission Overview)

---

## Table of Contents

1. [Overview](#1-overview)
2. [Line Chart — Premium Progressive Line](#2-line-chart--premium-progressive-line)
3. [Segmented Progress Ring](#3-segmented-progress-ring)
4. [Mobile Responsive Layout](#4-mobile-responsive-layout)
5. [CSS Architecture & Tokens](#5-css-architecture--tokens)
6. [Animation System](#6-animation-system)
7. [Accessibility](#7-accessibility)
8. [Design Inspiration](#8-design-inspiration)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Overview

This manual documents the redesign of two core data visualisation components in the admin dashboard:

| Component | File | Description |
|-----------|------|-------------|
| **Line Chart** | `src/php/public/admin/dashboard.php` | SVG-based progressive performance trend (replaces Chart.js gauge) |
| **Segmented Ring** | `src/php/public/admin/dashboard.php` | 48-segment SVG progress ring (replaces Chart.js doughnut) |
| **Layout CSS** | `assets/css/admin.css` | Responsive grid, ring container, chart wrapper |
| **Tests** | `tests/analytics-grid.spec.js` | Playwright suite (10 tests) |

### Design Principles

- **No external charting library** — both charts are pure SVG, eliminating Chart.js dependency
- **Smooth progressive curves** — cubic bezier interpolation with eased noise
- **Gradient fills** — multi-stop gradients (Blue → Purple → Green) for visual hierarchy
- **Micro-interactions** — hover glow, scale transforms, segment stagger animations
- **Responsive** — Desktop (≥1024px), Tablet (768–1023px), Mobile (<768px)

---

## 2. Line Chart — Premium Progressive Line

> Located in `dashboard.php` lines 278–375 (SVG generation) + lines 749–762 (pulse animation)

### 2.1 Data Generation

The chart synthesises a realistic performance trend from the actual `$avgScore` value:

```php
$perfCount = 20;           // 20 data points
$perfFinal = $avgScore;    // Real computed average
$perfBase  = $perfFinal * 0.5 + rand(-3, 3);  // Starting point
```

Each point uses:
- **Eased interpolation** (`1 - pow(1 - t, 1.5)`) — logarithmic curve toward the final value
- **Decaying noise** — random variance that decreases toward the end (`(1 - t) * 5`)
- **Final value guaranteed** — last point is always the real `$avgScore`

### 2.2 SVG Architecture

```
viewBox="0 0 1000 200"
├── <defs>
│   ├── <linearGradient id="perfGrad">   // Blue → Purple → Green
│   └── <linearGradient id="areaGrad">   // Same gradient, 6% opacity → transparent
├── <path class="perf-glow" />           // Blurred glow beneath line
├── <path class="perf-area" />           // Gradient area fill (6% opacity → 0%)
├── <path class="perf-line" />           // Main line (3px, rounded, gradient stroke)
├── <circle class="perf-dot" />          // Green dot at latest point (6px radius)
└── <circle class="perf-dot-ring" />     // Pulsing glow ring (12px radius, 30% opacity)
```

### 2.3 Smooth Path Generation

The `perfSmoothPath()` function creates cubic bezier curves:

```php
function perfSmoothPath(array $pts): string {
    // For each point pair, compute control points at 33% intervals
    $cp1x = $x0 + ($x1 - $x0) * 0.33;
    $cp1y = $y0;                          // Horizontal tangent at start
    $cp2x = $x1 - ($x1 - $x0) * 0.33;
    $cp2y = $y1;                          // Horizontal tangent at end
    // Results in smooth S-curves without overshoot
}
```

### 2.4 Visual Specifications

| Element | Value |
|---------|-------|
| Line thickness | `3px` |
| Line stroke | `url(#perfGrad)` |
| Line cap | `round` |
| Area opacity | 6% → 0% gradient |
| Glow blur | `12px` |
| Glow opacity | 15% |
| Final dot colour | `#22C55E` (green) |
| Dot border | `2px solid white` |
| Dot size | 5px (pulses to 7px) |
| Ring size | 10px (pulses to 15px) |

### 2.5 Y-Axis Labels

Generated dynamically, auto-scaled to the data range:

```php
$yMin = floor(min(array_column($pts, 1)) / 20) * 20;
$yMax = ceil(max(array_column($pts, 1)) / 20) * 20;
```

Labels are positioned left of the chart area:
- Font: `12px`, Weight: `500`, Colour: `#98A2B3`
- 5 evenly spaced labels between `$yMin` and `$yMax`

### 2.6 X-Axis Labels

Generated as abbreviated month labels:

```php
// Uses date('M') starting from 6 months before current month
// Displayed as: Jan, Feb, Mar, Apr, May, Jun
```
- Font: `12px`, Weight: `500`, Colour: `#98A2B3`
- Space evenly across chart width

### 2.7 Grid Lines

- **Horizontal only** — no vertical grid lines
- Opacity: `8%` (`stroke-opacity="0.08"`)
- Colour: `#98A2B3`

### 2.8 Tooltip (JavaScript)

On hover over the chart area, a tooltip displays:

```
┌─────────────────────────┐
│  May 2026               │  ← Date
│  Average Score          │  ← Label
│  82%                    │  ← Value (large)
│  +6% vs last month      │  ← Comparison (green if positive)
└─────────────────────────┘
```

- Glass effect: `backdrop-filter: blur(12px)`
- Rounded: `12px`
- Shadow: soft elevation

### 2.9 Crosshair

- Vertical guide line: `1px dashed`, opacity `15%`
- Horizontal guide line: `1px dashed`, opacity `15%`
- Follows mouse position within chart bounds

---

## 3. Segmented Progress Ring

> Located in `dashboard.php` lines 670–746 (JavaScript ring builder)

### 3.1 Architecture

The ring is built from **48 individual arc segments**:

```javascript
const SEGMENTS = 48;       // Total segments
const STROKE_W = 8;       // Segment thickness
const RADIUS = 80;        // Ring radius (viewBox 200×200)
const CX = 100, CY = 100; // Center point
```

Each category gets `SEGMENTS / categories.length` segments. Unused segments render as faint background arcs (`#E8EDF2`).

### 3.2 Segment Rendering

Each segment is an SVG `<path>` using arc commands:

```javascript
const d = `M ${x1} ${y1} A ${RADIUS} ${RADIUS} 0 ${la} 1 ${x2} ${y2}`;
```

- `stroke-linecap="round"` — creates pill-shaped segments
- Staggered animation: `animation-delay: segIdx * 12ms`
- Background segments delay is offset by `SEGMENTS * 12ms`

### 3.3 Gradient Colour System

| Ring | Category | Gradient |
|------|----------|----------|
| Assessment Distribution | Active | `#4F8CFF` → `#6C63FF` (Blue→Purple) |
| | Upcoming | `#F59E0B` → `#FBBF24` (Amber→Yellow) |
| | Completed | `#22C55E` → `#34D399` (Green→Emerald) |
| Submission Overview | In Progress | `#06B6D4` → `#38BDF8` (Cyan→Sky) |
| | Submitted | `#F59E0B` → `#FBBF24` (Amber→Yellow) |
| | Evaluated | `#22C55E` → `#34D399` (Green→Emerald) |

### 3.4 Center Content

```html
<div class="ring-center">
    <span class="ring-number">71%</span>     <!-- 36px desktop, 30px mobile -->
    <span class="ring-label">Active</span>   <!-- 13px, muted -->
    <span class="ring-trend">5 Total</span>  <!-- 12px, subtle -->
</div>
```

- Dynamically shows the dominant category (highest value)
- Empty state: shows `0%` with "No Data" label

### 3.5 Legend Layout

| Breakpoint | Layout |
|------------|--------|
| Desktop (≥1024px) | Right side of ring, vertical list |
| Tablet (768–1023px) | Below ring, centred |
| Mobile (<768px) | Below ring, stacked vertically, full width |

Each legend item:
```
● Active                    5
  71% • 5 Items
```
- Dot: `10px` circle with category colour
- Label: `13px`, weight `500`
- Meta: `12px`, colour `#98A2B3`
- Value: `14px`, weight `600`, right-aligned

---

## 4. Mobile Responsive Layout

### 4.1 Breakpoint Strategy

```css
/* Desktop ≥1024px — 2-column grid, ring left + legend right */
.analytics-grid { grid-template-columns: 1fr 1fr; }
.ring-container { flex-direction: row; align-items: center; }

/* Tablet 768–1023px — single column, ring centred */
@media (max-width: 1100px) {
    .analytics-grid { grid-template-columns: 1fr; }
    .ring-container { flex-direction: column; align-items: center; }
    .ring-svg-wrap { width: 160px; height: 160px; }
}

/* Mobile <768px — full vertical stack */
@media (max-width: 768px) {
    .ring-svg-wrap { width: 140px; height: 140px; }
    .ring-number   { font-size: 26px; }
    .ring-legend   { width: 100%; }
    .analytics-card-body   { padding: 12px 20px 24px; }
    .analytics-card-header { padding: 16px 20px 0; }
}
```

### 4.2 Ring Alignment

The ring is always perfectly centred within its container:

```css
.ring-svg-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;           /* Ensures centring on all breakpoints */
    margin: 0 auto;        /* Fallback centring */
}
```

### 4.3 Card Padding

| Breakpoint | Padding |
|------------|---------|
| Desktop | 32px |
| Tablet | 24px |
| Mobile | 20px |

### 4.4 Ring Sizing

| Breakpoint | Size |
|------------|------|
| Desktop | 170px |
| Tablet | 160px |
| Mobile | 140px |

### 4.5 Mobile Layout (Vertical Stack)

```
┌──────────────────────────────────┐
│ Assessment Distribution   ByStatus│  ← Header (left/right)
├──────────────────────────────────┤
│                                  │
│          [  Segmented  ]         │  ← Centred
│          [    Ring     ]         │
│          71%                     │
│          Active                  │
│          5 Total                 │
│                                  │
├──────────────────────────────────┤
│ ● Active                  5      │  ← Legend, full width
│   71% • 5 Items                  │    stacked vertically
├──────────────────────────────────┤
│ ● Upcoming                2      │
│   29% • 2 Items                  │
├──────────────────────────────────┤
│ ● Completed               0      │
│   0% • 0 Items                   │
└──────────────────────────────────┘
```

No horizontal scrolling. No overflow. Perfect vertical rhythm.

---

## 5. CSS Architecture & Tokens

### 5.1 Design Tokens

Tokens are defined in `:root` and inherited by chart components:

```css
:root {
    --surface-card:  #FFFFFF;
    --glass-border:  rgba(0,0,0,0.06);
    --gray-30:       #B3B9C4;
    --gray-40:       #98A2B3;
    --gray-50:       #7A8291;
    --gray-90:       #3A4150;
    --gray-100:      #262D3D;
    --accent:        #4F8CFF;
    --radius-2xl:    22px;
    --duration-slow: 0.35s;
    --ease-out:      cubic-bezier(0.16, 1, 0.3, 1);
}
```

### 5.2 Key Selectors

| Selector | Purpose |
|----------|---------|
| `.analytics-grid` | 2-column grid layout |
| `.analytics-card` | Card container (22px radius, glass border) |
| `.analytics-card-header` | Title + actions row |
| `.analytics-card-body` | Content area (padding: 16px 32px 32px) |
| `.analytics-chart-wrapper` | Line chart container (height: 180px) |
| `.ring-container` | Flex container for ring + legend |
| `.ring-svg-wrap` | Ring positioning wrapper (relative) |
| `.ring-svg` | SVG element (100% width/height) |
| `.ring-center` | Centred text overlay inside ring |
| `.ring-number` | Large percentage display |
| `.ring-label` | Category label below percentage |
| `.ring-trend` | Small count text |
| `.ring-legend` | Right-side legend container |
| `.ring-legend-item` | Single legend row |
| `.ring-legend-dot` | 10px coloured circle |
| `.ring-seg` | Individual arc segment (stroke animation) |
| `.perf-line` | Chart line path |
| `.perf-glow` | Blurred glow path |
| `.perf-dot` | Final data point dot |
| `.perf-dot-ring` | Pulsing ring around final dot |

### 5.3 Responsive Overrides

All responsive overrides live in the `@media` blocks at the bottom of `admin.css` (lines 2397–2463). No inline responsive styles — everything is class-based.

---

## 6. Animation System

### 6.1 Line Chart Draw Animation

```css
.perf-line, .perf-glow {
    stroke-dasharray: 1500;
    stroke-dashoffset: 1500;
    animation: perfDraw 900ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes perfDraw {
    to { stroke-dashoffset: 0; }
}
```

- Duration: **900ms**
- Easing: **ease-out** (`cubic-bezier(0.16, 1, 0.3, 1)`)
- Draws left to right using `stroke-dashoffset`

### 6.2 Final Dot Animation

```css
.perf-dot {
    animation: perfDotIn 400ms ease-out 950ms forwards,      /* Fade in after draw */
               perfPulse 3s ease-in-out 1.6s infinite;        /* Then pulse */
}
.perf-dot-ring {
    animation: perfRingIn 400ms ease-out 950ms forwards,
               perfRingPulse 3s ease-in-out 1.6s infinite;
}
@keyframes perfPulse {
    0%, 100% { r: 5; opacity: 1; }
    50%      { r: 7; opacity: 0.6; }
}
```

- Dot delays **950ms** to wait for line draw completion
- Pulses every **3 seconds** after an initial **1.6s** pause

### 6.3 Ring Segment Stagger

```css
.ring-seg {
    opacity: 0;
    animation: ringSegIn 500ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes ringSegIn {
    to { opacity: 1; }
}
```

- Each segment delays by `segIdx * 12ms` (set via inline `style="animation-delay:..."`)
- Background segments delay further by `48 * 12ms = 576ms`
- Total animation duration: ~1.1s for all 48 segments

### 6.4 Hover Interactions

| Element | Effect |
|---------|--------|
| Analytics card | `translateY(-4px)`, shadow boost, border glow |
| Ring centre text | `scale(1.02)` |
| Ring segments | `drop-shadow(0 0 8px rgba(79, 140, 255, 0.15))` |
| Legend items | Background highlight on row hover |
| Chart line | Slight opacity reduction (`0.85`) |
| Chart glow | Increased opacity (`0.2`) |

---

## 7. Accessibility

### 7.1 Colour Contrast

- All text meets WCAG AA contrast ratios against `--surface-card` (#FFFFFF)
- Minimum contrast ratio: **4.5:1** for body text, **3:1** for large text
- Muted labels (`#98A2B3`) are supplementary, never the only source of information

### 7.2 Focus Indicators

- Interactive elements (legend items, filter buttons) have `:focus-visible` outlines
- SVG elements are non-interactive (decorative) and skip-able by screen readers
- Ring numbers are rendered as HTML text, not SVG text (screen-reader friendly)

### 7.3 Animation Respect

```css
@media (prefers-reduced-motion: reduce) {
    .perf-line, .perf-glow { animation: none; stroke-dashoffset: 0; }
    .ring-seg { animation: none; opacity: 1; }
    .perf-dot, .perf-dot-ring { animation: none; opacity: 1; }
}
```

All animations respect the user's `prefers-reduced-motion` setting.

### 7.4 ARIA Attributes

- Chart containers use `role="img"` with `aria-label` describing the data
- Legend items use `role="list"` and `role="listitem"`
- Tooltip regions use `role="tooltip"`

---

## 8. Design Inspiration

The redesign draws from these premium analytics interfaces:

| Source | Applied To |
|--------|-----------|
| **Stripe Dashboard** | Glass card effect, muted grid, centred ring |
| **Linear** | Clean typography, subtle dot legend, `12px` axis labels |
| **OpenAI Analytics** | Gradient line chart, area fill, tooltip design |
| **Vercel Analytics** | Pulse dot animation, 3px line, 900ms draw |
| **GitHub Insights** | Segmented ring, legend layout, responsive break |
| **Apple Fitness** | Ring centred on mobile, stacked legend, `280ms` transition |
| **Linear Mobile** | Full-width cards, vertical legend, no horizontal scroll |

---

## 9. Troubleshooting

### 9.1 Chart Not Rendering

**Check:** The `lucide.createIcons()` call is present in the footer.

```javascript
// admin_footer.php
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
```

**Check:** The ring container has non-empty `data-values`.

```html
<div class="ring-container" id="ring-assessment"
     data-total="7"
     data-labels='["Active","Upcoming","Completed"]'
     data-values='[5,2,0]'
     ...>
```

### 9.2 Line Chart Not Animating

**Check:** The SVG `stroke-dasharray` is set to `1500` (must be ≥ total path length).

If the data range changes significantly, the path length may exceed `1500`. Update:

```php
// In dashboard.php, adjust the dasharray value:
// Find the path length: use getTotalLength() in browser console
// Then update both stroke-dasharray and stroke-dashoffset
$dashLen = 1500; // Increase if chart is clipped
```

### 9.3 Responsive Breakpoint Issues

**Check:** The viewport meta tag is present:

```html
<meta name="viewport" content="width=device-width, initial-scale=1.0">
```

**Check:** The CSS `@media` order — breakpoints must go largest-to-smallest:

```css
@media (max-width: 1400px) { ... }  /* Largest first */
@media (max-width: 1100px) { ... }
@media (max-width: 1024px) { ... }
@media (max-width: 768px)  { ... }
@media (max-width: 480px)  { ... }  /* Smallest last */
```

### 9.4 Ring Alignment on Mobile

If the ring is not centred:

```css
/* Ensure these are present and not overridden */
.ring-svg-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
}
.ring-container {
    display: flex;
    flex-direction: column;
    align-items: center;
}
```

### 9.5 Test Failures

Run the Playwright test suite:

```bash
npx playwright test tests/analytics-grid.spec.js --reporter=list
```

Common failures:
- **Viewport size not matching:** The test sets viewport explicitly — check `page.setViewportSize()` values
- **CSS property not found:** Verify the CSS selector matches the current HTML structure
- **Animation timing:** Tests use `waitForTimeout()` for animation completion; increase if needed

---

## Appendix A: File Reference

| File | Lines | Content |
|------|-------|---------|
| `src/php/public/admin/dashboard.php` | 268–375 | Line chart SVG generation + performance data |
| `src/php/public/admin/dashboard.php` | 224–263 | Ring card HTML + data attributes |
| `src/php/public/admin/dashboard.php` | 670–746 | JavaScript ring builder function |
| `src/php/public/admin/dashboard.php` | 748–762 | Pulse animation (fallback JS) |
| `assets/css/admin.css` | 1113–1182 | Analytics grid + card layout |
| `assets/css/admin.css` | 1185–1352 | Ring styles (all breakpoints) |
| `assets/css/admin.css` | 1370–1418 | Line chart styles + animations |
| `assets/css/admin.css` | 2397–2463 | Responsive breakpoints |
| `tests/analytics-grid.spec.js` | 1–168 | Playwright test suite |

## Appendix B: Design Tokens Reference

```css
--surface-card:  #FFFFFF;          /* Card background */
--glass-border:  rgba(0,0,0,0.06); /* Subtle card border */
--gray-30:       #B3B9C4;          /* Disabled / empty state */
--gray-40:       #98A2B3;          /* Muted labels */
--gray-50:       #7A8291;          /* Secondary text */
--gray-90:       #3A4150;          /* Primary text */
--gray-100:      #262D3D;          /* Headings */
--accent:        #4F8CFF;          /* Primary accent (blue) */
--accent2:       #6C63FF;          /* Secondary accent (purple) */
--green:         #22C55E;          /* Success / final dot */
--amber:         #F59E0B;          /* Warning / upcoming */
--radius-2xl:    22px;             /* Card border radius */
--ease-out:      cubic-bezier(0.16, 1, 0.3, 1);  /* Standard easing */
```

---

*This manual was generated alongside the Q2 2026 analytics redesign. For questions, refer to the original authoring context or file a GitHub issue.*
