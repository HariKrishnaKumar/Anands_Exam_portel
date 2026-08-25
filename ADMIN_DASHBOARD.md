# Admin Dashboard Specification — Fluent 2 Enterprise Design

**Document Version:** 2.0  
**Last Updated:** August 21, 2026  
**Status:** Comprehensive Specification (All Components Defined)

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Component Specifications](#component-specifications)
4. [States & Behaviors](#states--behaviors)
5. [Accessibility](#accessibility)
6. [Responsiveness](#responsiveness)
7. [Implementation Notes](#implementation-notes)

---

## Overview

The Admin Dashboard is a comprehensive platform management interface built with:
- **Design System:** Fluent 2 Enterprise (Microsoft M365 inspired)
- **Layout:** Modular grid system with sidebar navigation
- **Typography:** Inter 400–700 weights, Segoe UI Variable fallback
- **Colors:** Fluent 2 semantic palette (accent blue, neutrals, semantic colors)
- **Spacing:** 8px design grid
- **Components:** Cards, modals, dropdowns, tables, charts, forms

---

## Architecture

### Layout Structure

```
┌─────────────────────────────────────────────┐
│         Top Navigation Bar (Fixed)          │  Height: 56px
├──────────────┬──────────────────────────────┤
│              │                              │
│   Sidebar    │      Main Content Area       │
│  (Fixed)     │     (Scrollable)             │
│              │                              │
│   200px      │                              │
│              │                              │
└──────────────┴──────────────────────────────┘
```

**Sidebar Navigation:**
- Logo/branding at top
- Hierarchical nav tree with sections (overview, studio, management, reports, settings, system)
- Collapsible on mobile (<1024px)
- Persistent scroll position
- Current page highlighting

**Top Navigation:**
- Breadcrumb or current page title (left)
- Quick-action button: "Create Assessment" (center-right)
- Theme toggle, notifications, profile menu (right)

---

## Component Specifications

### 1. Stat Cards (KPI Cards)

**Location:** Dashboard header, row 1  
**Count:** 4 cards  
**Purpose:** Platform metrics overview

| Metric | Cards | Content |
|--------|-------|---------|
| Institutions | 1 | Total colleges, trend (courses), "View All" link |
| Students | 1 | Total students, trend (+/- this month), "View All" link |
| Assessments | 1 | Total tests, active count, "View All" link |
| System Health | 1 | Status indicator, "Live Monitor" link |

**Card Structure:**
```
┌─────────────────────────────┐
│  [Icon]           [Menu ⋮]  │  Header with icon + menu button
├─────────────────────────────┤
│  42                          │  Large metric value
│  Students                    │  Label (e.g., "Students")
├─────────────────────────────┤
│  ↗ +3 (This month)          │  Trend with comparison
└─────────────────────────────┘
```

**States:**
- **Normal:** Clickable card, navigates to detail page (colleges, students, assessments, live_monitor)
- **Hover:** Subtle shadow increase, cursor pointer
- **Focus (keyboard):** Border highlight, focus ring
- **Menu Open:** Dropdown with "View All", "Export" options

**Dropdown Menu Actions:**
- Institutions: View All Colleges, Export Data
- Students: View All Students, Export List
- Assessments: View All Assessments, Export Data
- System Health: Live Monitor (navigates to system health page)

---

### 2. Charts & Analytics Cards

**Location:** Dashboard row 2  
**Count:** 3 charts  
**Chart Types:** Line (performance trend), Segmented ring (test status), Segmented ring (submission status)

#### Chart 1: Average Performance (Line Chart)
- **Data:** Performance trend over time (last 20 data points)
- **Gradient:** Blue → Purple → Green
- **Interaction:** Hover to see exact values
- **Animation:** Progressive cubic bezier curve on page load
- **SVG-based:** No external charting library

#### Chart 2: Assessment Distribution (Segmented Ring)
- **Data:** Active, Upcoming, Completed tests
- **Segments:** 48 equal segments, colored by status
- **Colors:** Active (green), Upcoming (amber), Completed (neutral)
- **Center Text:** Total test count
- **Animation:** Segment stagger on load

#### Chart 3: Submission Overview (Segmented Ring)
- **Data:** In Progress, Submitted, Evaluated submissions
- **Segments:** 48 segments, colored by status
- **Center Text:** Total submissions
- **Animation:** Segment stagger on load

---

### 3. Recent Activity Tables

**Location:** Dashboard row 3–4  
**Count:** 2 tables

#### Table 1: Recent Assessments
**Columns:** Title, Status Badge, Student Count, Start Time, Action  
**Status Badges:**
- 🟢 Active (green)
- 🟡 Upcoming (amber)
- ⚫ Completed (neutral)
- ⏸ Paused (pending)

**Actions:** "View", "Edit", "Results", "End Test" (context-dependent)

#### Table 2: Recent Students
**Columns:** Name, Email, Course, Batch, College, Registration Date  
**Row Actions:** Edit (pencil), Delete (trash, requires confirmation)  
**Search/Filter:** By college, course, batch; search by name/email/roll

---

### 4. Quick Action Cards

**Location:** Dashboard row (below tables)  
**Count:** 6 action cards  
**Purpose:** Fast navigation to common tasks

| Card | Icon | Label | Description | Link Target |
|------|------|-------|-------------|------------|
| 1 | Plus | Create Assessment | Build new online test | assessment_studio.php |
| 2 | Settings | Manage Assessments | View, pause, or end tests | assessment_management.php |
| 3 | Student | Add Student | Register or import students | students.php?action=add |
| 4 | Batch | Create Batch | New student batch group | batches.php |
| 5 | Course | Create Course | Add new course offering | courses.php |
| 6 | Building | Add Institution | Register new college | college_create.php |

**Card Style:** Gradient background (4 pastel colors rotate), icon + label + description  
**Interaction:** Hover to brighten, click to navigate

---

### 5. System Status Indicators

**Location:** Dashboard footer or sidebar footer  
**Count:** 6 indicators

| Indicator | Status | Detail |
|-----------|--------|--------|
| Database | ✅ Healthy | MySQL connection OK |
| Mail Service | ⚠️ Checking | SMTP authentication (may be disabled in dev) |
| Python API | ✅ Configured | Flask analytics service ready |
| Storage | ✅ Writable | Upload directory accessible |
| Theme | ✅ Active | Light/Dark mode available |
| Notifications | ✅ Active | Admin notification system ready |

**Display:** Color-coded dots (green = OK, yellow = warning, red = down)

---

## States & Behaviors

### Loading States
- **Tables:** Skeleton loaders for rows, fade-in on load
- **Charts:** Animated draw-in, data points appear progressively
- **Cards:** Fade-in with slight scale (0.95 → 1.0)

### Empty States
- **Tables:** Centered message "No data to display" with icon
- **Charts:** Placeholder "Insufficient data"

### Error States
- **Database Error:** Show alert banner at top, "Unable to load data"
- **API Error:** Stat card shows "—" with loading spinner
- **Connection Error:** Show reconnect button in affected sections

### Interactions

#### Stat Card Menu
```
Stat Card                    Stat Card (Hovered)
┌─────────────┐            ┌─────────────┐
│ [Icon] [⋮]  │            │ [Icon] [⋮]  │ ← Menu button highlights
│             │            │             │
│    42       │            │    42       │
└─────────────┘            │             │
                           └─────────────┘
                           (Mouse moves to ⋮)
                                  ↓
                           ┌──────────────┐
                           │ View All     │
                           │ Export Data  │
                           └──────────────┘
```

#### Profile Dropdown
```
Profile (Normal)             Profile (Clicked)
┌──────────────────┐        ┌──────────────────┐
│ [Avatar] Name    │        │ [Avatar] Name    │
│ Administrator ↓  │        │ Administrator ▲  │
└──────────────────┘        │                  │
                            │ Account Settings │
                            │ ───────────────  │
                            │ Sign Out         │
                            └──────────────────┘
```

---

## Accessibility

### Keyboard Navigation
- **Tab Order:** Top nav → Sidebar → Main content → Footer
- **Escape:** Close any open dropdown/modal
- **Enter/Space:** Activate buttons and card navigation
- **Arrow Keys:** Navigate table rows (optional enhancement)

### ARIA Attributes
- `role="button"` on clickable cards
- `aria-label="..."` on icon-only buttons
- `aria-hidden="true"` on decorative icons
- `aria-expanded="true/false"` on dropdown menus
- `aria-current="page"` on current nav item

### Color Contrast
- All text ≥ 4.5:1 contrast ratio (WCAG AA)
- Icon colors tested against backgrounds
- Focus indicators visible against all backgrounds

### Screen Readers
- All stat card values announced with labels
- Table headers properly marked with `<th>`
- Form labels associated with inputs via `for` attribute
- Skip link at page top

---

## Responsiveness

### Breakpoints

| Viewport | Width | Layout Changes |
|----------|-------|-----------------|
| Desktop | ≥1024px | Full sidebar visible, 3-column grid |
| Tablet | 768–1023px | Sidebar collapses to icon-only, 2-column grid |
| Mobile | <768px | Sidebar hidden (hamburger), 1-column grid |

### Responsive Components

**Stat Cards:**
- Desktop: 4 per row
- Tablet: 2 per row
- Mobile: 1 per row (stacked)

**Tables:**
- Desktop: Full table with all columns
- Tablet: Hide secondary columns, show on click
- Mobile: Card view, stack rows vertically

**Charts:**
- Desktop: Full size, interactive hover
- Tablet: Reduce padding, maintain interactivity
- Mobile: Stack vertically, touch-friendly

---

## Implementation Notes

### CSS Architecture
- **Variables:** Defined in CSS custom properties (--color-*, --space-*, --font-*)
- **Theme:** Light/Dark mode toggle updates CSS variables
- **Grid:** 8px base unit for all spacing (--space-1 = 8px, --space-2 = 16px, etc.)

### JavaScript Requirements
- **Chart Rendering:** Vanilla JS SVG generation (no Chart.js)
- **Dropdowns:** CSS + minimal JS for toggle behavior
- **Modals:** Click outside to close, ESC to close
- **Animations:** CSS animations where possible, JS for progressive elements

### File Structure
```
src/php/public/admin/
├── dashboard.php              # Main dashboard page
├── assessment_studio.php       # Test creation
├── assessment_management.php   # Test management
├── grading.php                 # Manual grading
├── reports.php                 # Advanced reports
├── students.php                # Student management
├── colleges.php                # Institution management
├── courses.php                 # Course management
├── batches.php                 # Batch management
├── live_monitor.php            # Real-time activity
├── notifications.php           # Admin notifications
├── settings.php                # Admin settings
├── activity_logs.php           # Audit trail
├── failed_logins.php           # Security logs
└── help.php                    # Help/documentation

assets/css/
├── admin.css                   # Admin theme (15 KB)
└── student.css                 # Student theme (12 KB)

src/php/includes/
├── admin_header.php            # Shared admin header/sidebar
├── admin_footer.php            # Shared admin footer
└── icons.php                   # Icon helper functions
```

---

## Changelog

**v2.0 (Aug 21, 2026):**
- ✅ Expanded documentation from starter version
- ✅ Defined all stat card actions with dropdown menus
- ✅ Documented chart types and animations
- ✅ Added responsive breakpoints and layouts
- ✅ Included accessibility guidelines (WCAG 2.2)
- ✅ Added keyboard navigation specs
- ✅ Documented system status indicators

**v1.0 (Jul 24, 2026):**
- Initial starter version (12 lines)
