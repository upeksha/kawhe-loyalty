# UI System

Design tokens, shared components, and layout rules for Kawhe Loyalty surfaces.

## Surfaces

| Surface | Layout | CSS entry |
|---------|--------|-----------|
| Merchant admin | `<x-merchant-layout>` | Tailwind + `resources/css/app.css` |
| Onboarding wizard | `<x-onboarding-step-layout>` | Same as merchant |
| Customer join / card | `<x-customer-layout>` | `customer.css` + program CSS variables |

## Brand tokens (merchant)

Defined in `tailwind.config.js`:

- **brand** — primary green (`brand-500` ≈ `#3d7659`)
- **accent** — secondary highlights
- **stone** — neutral grays for chrome and text
- **Font** — Figtree (400–700)

Merchant UI uses `<x-ui.*>` components with stone backgrounds (`bg-stone-50`) and brand accents.

## Program theme (customer)

Customer pages derive colors from the loyalty program (fallback: store defaults).

**Resolver:** `App\Support\ProgramBranding::resolve($program, $store)` → `ProgramTheme`

**Defaults:**

- Background: `#1F2937`
- Brand: `#3d7659`

The resolver computes accessible text, card gradients, input styles, button contrast, and status-card variants from the two hex colors.

### CSS variables

Injected on `.customer-page` via `<x-customer-layout>`:

| Variable | Purpose |
|----------|---------|
| `--program-bg` | Page background |
| `--program-brand` | Primary actions |
| `--program-brand-focus` | Input focus ring |
| `--program-card-bg` | Join card gradient |
| `--program-card-text` | Join card body text |
| `--program-input-*` | Form field styling |
| `--program-status-*` | Limit/archived status cards |

Full list: `ProgramTheme::cssVariables()` in `app/Support/ProgramBranding.php`.

### Customer CSS classes

Defined in `resources/css/customer.css`:

- **Layout:** `.customer-page`, `.customer-shell`, `.customer-shell--centered`
- **Typography:** `.customer-muted`, `.customer-card-title`, `.customer-card-body`
- **Cards:** `.customer-card--gradient`, `.customer-card--plain`, `.customer-card--status`
- **Controls:** `.customer-btn-primary`, `.customer-btn-secondary`, `.customer-input`

Legacy `.join-*` aliases remain during migration; prefer `.customer-*` in new code.

## Shared merchant components

Located in `resources/views/components/ui/`:

| Component | Use |
|-----------|-----|
| `<x-ui.button>` | Primary actions (variants: primary, secondary, danger, ghost) |
| `<x-ui.card>` | Bordered content panel |
| `<x-ui.badge>` | Status pills |
| `<x-ui.input>` | Form fields (merchant forms) |
| `<x-ui.table>` | Data tables |
| `<x-ui.page-header>` | Page title + optional actions slot |
| `<x-ui.stat-card>` | Dashboard metric tile |
| `<x-ui.empty-state>` | Zero-data states with CTA slot |
| `<x-ui.alert>` | Inline notices (info, success, warning, danger) |
| `<x-ui.section-panel>` | Admin/merchant content section shell |
| `<x-ui.page-hero>` | Eyebrow + title + description + quick actions |
| `<x-ui.quick-link>` | Compact admin shortcut tile |
| `<x-ui.admin-metric>` | Colored KPI tile (label + large value) |
| `<x-ui.select>` | Filter dropdown styling |
| `<x-ui.action-tile>` | Merchant dashboard quick action pill |
| `<x-ui.readiness-row>` | Wallet/setup readiness checklist row |
| `<x-ui.chip-button>` | Onboarding preset / quick-fill pill button |

Admin layout uses `<x-admin-layout>` with mobile sidebar (`x-admin.sidebar` partial). Merchant tools reuse the same `x-ui.*` components on dashboard, support logs, customers, stores, and scanner pages.

### Color mode

Merchant, admin, onboarding, and customer surfaces are **light-mode only**. Do not add `dark:` Tailwind variants to product UI — they are unused (Tailwind `darkMode` is not configured) and create maintenance noise. Legacy `dark:` classes were removed from `flash-messages`; Laravel default pages (`welcome`, vendor pagination) are unchanged.

### Page header example

```blade
<x-ui.page-header title="Loyalty Cards">
    <x-slot name="actions">
        <x-ui.button href="..." variant="primary" size="sm">Add card</x-ui.button>
    </x-slot>
</x-ui.page-header>
```

### Empty state example

```blade
<x-ui.empty-state
    heading="No loyalty cards yet"
    description="Create your first card to get a join link and QR code."
>
    <x-ui.button href="..." variant="primary">Add Loyalty Card</x-ui.button>
</x-ui.empty-state>
```

## Customer layout

```blade
<x-customer-layout :program="$program" :store="$store" title="Get your card" centered>
    <x-slot name="back">...</x-slot>
    <x-slot name="hero">...</x-slot>
    {{-- main card content --}}
    @push('scripts') ... @endpush
</x-customer-layout>
```

Props:

- `program` / `store` — branding source (either may be null on error pages)
- `title` — browser tab prefix
- `centered` — vertically center content (landing, error pages)

## Rules for new UI work

1. **No inline color math in Blade** — use `ProgramBranding` for customer pages.
2. **Reuse components** — extend `x-ui.*` before adding one-off markup.
3. **Mobile first** — customer shell max-width 28rem; safe-area padding on notched devices.
4. **Loading states** — disable submit buttons + swap label on customer forms.
5. **Accessibility** — focus rings on buttons/inputs; `role="alert"` on alerts.
6. **Don't rename routes or API keys** from UI-only tasks.

## Phase roadmap

- **Phase 0:** tokens, `ProgramBranding`, customer layout, base UI components, join migration
- **Phase 1:** loyalty card page (`card/show`) on customer layout + loyalty CSS tokens
- **Phase 2:** admin panel polish — shared `x-ui.*` components, mobile nav, support log filters
- **Phase 3:** merchant dashboard + tools polish (`action-tile`, `readiness-row`, shared filters/tables)
- **Phase 4:** onboarding wizard polish, scanner parity, dark-mode audit ✅

### Loyalty card tokens (Phase 1)

Additional CSS variables for the customer card view:

| Variable | Purpose |
|----------|---------|
| `--program-loyalty-surface` | Wallet panel / secondary surfaces |
| `--program-loyalty-inner` | Stamp empty state, transaction rows |
| `--program-loyalty-divider` | In-card dividers |
| `--program-loyalty-card-bg` | Main card gradient |
| `--program-card-muted-on-bg` | Muted text on page background |
| `--program-brand-glow-*` | Decorative brand alpha overlays |

Use `<x-customer-layout :account="$account" shell="card">` for the card page. Pass `:manifest-href="route('card.manifest', ...)"` for PWA manifest scoping.

### Merchant dashboard (Phase 3)

**`<x-ui.page-hero>`** — dashboard intro with eyebrow, title, description, and an actions slot for `<x-ui.action-tile>` links (Scanner, Stores, Support, etc.).

**`<x-ui.stat-card layout="horizontal">`** — summary KPI with optional inline Chart.js sparkline in the `chart` slot (used for Active customers, Rewards earned/redeemed).

**`<x-ui.readiness-row>`** — wallet/setup checklist row. Props: `label`, `value`, `state` (`ready` | `attention`).

**Pages migrated:** `dashboard`, `merchant/support/index`, `merchant/customers/index`, `stores/index`, `programs/index`, `scanner/index`.

### Onboarding + scanner (Phase 4)

**Onboarding wizard** — `onboarding-step-layout` uses `x-ui.section-panel`; steps use `x-ui.alert`, `x-ui.chip-button`, `x-ui.readiness-row` (with dynamic `status` slot), and `x-ui.button` on card-ready actions.

**Scanner** — `page-hero` intro, badge-aligned status chip, 44px+ tap targets on primary actions, shared alert styling for store-switch and scan feedback.

**Dark-mode audit** — product surfaces standardized on light tokens; flash messages use `x-ui.alert` instead of Flowbite `dark:` markup.
