# Analytics Card Redesign — Enterprise SaaS

> **Date**: 2026-07-26
> **Scope**: `admin/dashboard.php` — Analytics Grid (Row 2)
> **Type**: UI/UX Visual Refinement Only
> **Status**: ✅ Implemented

---

## Objective

Upgrade every analytics widget from a generic admin template to an Enterprise SaaS dashboard. The result should resemble Stripe, Linear, Vercel, OpenAI Platform, and GitHub Enterprise.

**No business logic, APIs, routes, backends, databases, or functionality was modified.**

---

## Changes Summary

### Removed
- **Chart.js** — Doughnut charts (`assessmentDistChart`, `submissionChart`) and all related Chart.js initialization code
- **Chart.js CDN** — Removed `cdn.jsdelivr.net/npm/chart.js` from `dashboard.php` (still available on other pages via `reports.php`)
- **`$chartData`** — PHP data array no longer needed (ring data lives in HTML `data-*` attributes)
- **Old card layout** — Inline legend dots, chart wrapper with canvas, simplified header/bottom

### Added
- **Segmented Progress Rings** — 48-segment SVG rings replacing both doughnut charts
- **Enterprise card design** — 22px radius, 32px padding, glass surface, subtle border
- **Custom legends** — Per-item colored dot, label, percentage, count, hover state
- **Animations** — Ring draw (900ms ease-out), card hover lift (4px), center scale (1.02), legend fade
- **Lucide Icons** — CDN added, `Building2` replaces `College` icon, `ChartPie`/`Users` in card headers
- **Responsive layout** — Rings stack vertically below 1100px, scale down at 768px

---

## Segmented Progress Ring Specification

| Property | Value |
|----------|-------|
| Segments | 48 |
| Stroke width | 12px |
| Segment arc | ~2.5° |
| Gap between segments | 5° |
| Line cap | Round |
| Radius | 70px (viewBox centerline) |
| ViewBox | `0 0 200 200` |
| Background ring | `#E8EDF5` (light) / `#252D3A` 35% (dark) |

### Gradient Colors

| Category | Gradient |
|----------|----------|
| Primary (Info) | `#4F8CFF` → `#6C63FF` |
| Success | `#22C55E` → `#34D399` |
| Warning | `#F59E0B` → `#FBBF24` |
| Danger | `#EF4444` → `#FB7185` |
| Info (Cyan) | `#06B6D4` → `#38BDF8` |

### Center Content
- Large number (30px, 700): percentage of dominant category
- Medium label (13px, 600): dominant category name
- Small subtitle (12px, 500): total count

### Empty State
- Shows `0%` / `No Data` — never leaves an empty ring.

---

## Card Specification

| Property | Value |
|----------|-------|
| Border radius | 22px |
| Body padding | `16px 32px 32px` |
| Header padding | `20px 32px 0` |
| Background | `--surface-card` |
| Border | `--glass-border` |
| Hover: translateY | `-4px` |
| Hover: shadow | `0 12px 28px rgba(0,0,0,0.35)` |
| Hover: border | `rgba(79, 140, 255, 0.2)` |

### Light Mode
| Token | Value |
|-------|-------|
| Background | `#F7F9FC` |
| Card | `#FFFFFF` |
| Border | `#E8EDF5` |
| Primary text | `#101828` |
| Secondary | `#667085` |
| Muted | `#98A2B3` |

### Dark Mode
| Token | Value |
|-------|-------|
| Background | `#090B10` |
| Card | `#151B26` |
| Elevated | `#1A2230` |
| Border | `rgba(255,255,255,0.06)` |
| Primary text | `#FFFFFF` |
| Secondary | `#B7C1CF` |
| Muted | `#7D8793` |

---

## Animation & Micro-interactions

### Page Load
1. Background segments fade in clockwise (each ~12ms stagger, ~576ms total)
2. Colored segments fade in clockwise on top (each ~12ms stagger, ~576ms total)
3. Total draw animation: ~900ms

### Card Hover
| Element | Effect |
|---------|--------|
| Card | Lifts 4px, shadow deepens, border glows blue |
| Ring segments | Filter `drop-shadow(0 0 8px rgba(79,140,255,0.15))` |
| Center content | Scales to 1.02 |
| Legend | Opacity reduces to 0.85 |

### Legend Items
- Rounded hover state (10px radius)
- Background tint on hover (`--gray-5` light / `rgba(255,255,255,0.04)` dark)

---

## Icon System

| Usage | Icon | Source |
|-------|------|--------|
| College stat card | `Building2` | Lucide |
| Assessment Distribution header | `ChartPie` | Lucide |
| Submission Overview header | `Users` | Lucide |

**Lucide loaded via**: `https://unpkg.com/lucide@latest` (deferred)

---

## Data Flow

1. PHP reads counts from DB (`$testStatusCounts`, `$submissionCounts`)
2. Data passed to HTML via `data-*` attributes on `.ring-container`:
   - `data-values`, `data-labels`, `data-colors`, `data-gradients`, `data-total`
3. JavaScript reads attributes and builds SVG rings via `buildSegmentedRing()`
4. No API calls, no backend changes, no business logic modification

---

## Files Modified

| File | Changes |
|------|---------|
| `src/php/public/admin/dashboard.php` | Replaced doughnut HTML + JS with ring containers + builder |
| `assets/css/admin.css` | New card styles, ring styles, legend, animations, responsive |
| `src/php/includes/admin_header.php` | Added Lucide CDN |
| `tests/analytics-grid.spec.js` | Updated test expectations for new padding/gap values |
| `tests/fixtures/analytics-grid-test.html` | Updated fixture to match new card structure |

## Files Created

| File | Description |
|------|-------------|
| `ANALYTICS_CARD_REDESIGN.md` | This document |

---

## Quality Targets

✓ Stripe Dashboard — card design, segmented rings, hover states
✓ Linear Insights — gradient rings, minimal layout, animation
✓ GitHub Insights — SVG rendering, empty state, responsive
✓ Vercel Analytics — glass surface, typography, spacing
✓ OpenAI Platform — enterprise feel, lucide icons, subtle glow
