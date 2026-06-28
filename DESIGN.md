# Design System Maestro

Stack : Tailwind CSS v3 + Alpine.js + Blade.
Thème : dark indigo. Ne jamais proposer de thème clair — l'app est dark-only.
Toutes les couleurs ci-dessous s'appliquent via des classes Tailwind custom ou des variables CSS dans `resources/css/app.css`.

---

## Couleurs

### Palette de base

```css
/* resources/css/app.css */
@layer base {
  :root {
    --maestro-bg:        #0F0F1A;
    --maestro-surface:   #1C1C2E;
    --maestro-surface-2: #242438;
    --maestro-border:    rgba(255, 255, 255, 0.07);
    --maestro-border-md: rgba(255, 255, 255, 0.12);

    --maestro-accent:    #6366F1;
    --maestro-accent-hover: #4F46E5;
    --maestro-accent-light: #E0E7FF;
    --maestro-accent-muted: #312E81;

    --maestro-text-primary:   #E2E8F0;
    --maestro-text-secondary: #94A3B8;
    --maestro-text-muted:     #475569;

    --maestro-success:  #4ADE80;
    --maestro-success-bg: #052E16;
    --maestro-success-border: rgba(74, 222, 128, 0.2);

    --maestro-info:     #7DD3FC;
    --maestro-info-bg:  #082F49;
    --maestro-info-border: rgba(125, 211, 252, 0.2);

    --maestro-warning:  #FCD34D;
    --maestro-warning-bg: #3B1900;
    --maestro-warning-border: rgba(252, 211, 77, 0.2);

    --maestro-danger:   #FCA5A5;
    --maestro-danger-bg: #450A0A;
    --maestro-danger-border: rgba(252, 165, 165, 0.2);

    --maestro-neutral:  #64748B;
    --maestro-neutral-bg: #1C1C2E;
    --maestro-neutral-border: rgba(100, 116, 139, 0.2);
  }
}
```

### Extension Tailwind

Fichier : `tailwind.config.js`

```js
theme: {
  extend: {
    colors: {
      maestro: {
        bg:        'var(--maestro-bg)',
        surface:   'var(--maestro-surface)',
        'surface-2': 'var(--maestro-surface-2)',
        accent:    'var(--maestro-accent)',
        'accent-hover': 'var(--maestro-accent-hover)',
        'accent-light': 'var(--maestro-accent-light)',
        'accent-muted': 'var(--maestro-accent-muted)',
        text:       'var(--maestro-text-primary)',
        muted:      'var(--maestro-text-secondary)',
        subtle:     'var(--maestro-text-muted)',
      },
    },
    borderColor: {
      DEFAULT: 'var(--maestro-border)',
      md: 'var(--maestro-border-md)',
    },
    fontFamily: {
      sans: ['Inter', 'ui-sans-serif', 'system-ui'],
      mono: ['JetBrains Mono', 'ui-monospace'],
    },
    borderRadius: {
      DEFAULT: '8px',
      lg: '12px',
      xl: '16px',
    },
  },
},
```

---

## Typographie

Importer dans `resources/views/components/layouts/app.blade.php` :

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
```

Règles :
- `font-weight: 400` pour le body
- `font-weight: 500` pour les headings et labels
- Ne jamais utiliser `font-weight: 600` ou `700`
- Taille minimale : `12px`

Composants Blade à créer dans `resources/views/components/ui/` :

```
heading-1.blade.php  → <h1 class="text-[22px] font-medium text-maestro-text">
heading-2.blade.php  → <h2 class="text-[18px] font-medium text-maestro-text">
heading-3.blade.php  → <h3 class="text-[16px] font-medium text-maestro-text">
label.blade.php      → <span class="text-[11px] font-medium uppercase tracking-wider text-maestro-subtle">
mono.blade.php       → <code class="font-mono text-[12px] text-maestro-accent bg-maestro-surface px-1.5 py-0.5 rounded">
```

---

## Composants UI

Créer dans `resources/views/components/ui/` :

### Badge statut — `badge.blade.php`

Props : `$status` (pending | running | completed | gate | blocked | skipped)

```blade
@props(['status' => 'pending'])

@php
$config = match($status) {
    'running'   => ['bg' => 'var(--maestro-info-bg)',     'text' => 'var(--maestro-info)',    'dot' => 'var(--maestro-info)',    'label' => 'Running'],
    'completed' => ['bg' => 'var(--maestro-success-bg)',  'text' => 'var(--maestro-success)', 'dot' => 'var(--maestro-success)', 'label' => 'Completed'],
    'gate'      => ['bg' => 'var(--maestro-warning-bg)',  'text' => 'var(--maestro-warning)', 'dot' => 'var(--maestro-warning)', 'label' => 'Gate'],
    'blocked'   => ['bg' => 'var(--maestro-danger-bg)',   'text' => 'var(--maestro-danger)',  'dot' => 'var(--maestro-danger)',  'label' => 'Blocked'],
    'skipped'   => ['bg' => 'var(--maestro-neutral-bg)',  'text' => 'var(--maestro-neutral)', 'dot' => '#334155',                'label' => 'Skipped'],
    default     => ['bg' => 'var(--maestro-neutral-bg)',  'text' => 'var(--maestro-neutral)', 'dot' => '#475569',                'label' => 'Pending'],
};
@endphp

<span style="background: {{ $config['bg'] }}; color: {{ $config['text'] }}; border: 0.5px solid {{ $config['bg'] }};"
      class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[12px] font-medium">
    <span class="w-1.5 h-1.5 rounded-full inline-block" style="background: {{ $config['dot'] }}"></span>
    {{ $config['label'] }}
</span>
```

### Boutons — `button.blade.php`

Props : `$variant` (primary | secondary | danger | ghost), `$size` (sm | md)

```blade
@props(['variant' => 'secondary', 'size' => 'md'])

@php
$base = 'inline-flex items-center gap-2 font-medium rounded-lg transition-colors cursor-pointer';
$sizes = ['sm' => 'px-3 py-1.5 text-[12px]', 'md' => 'px-4 py-2 text-[13px]'];
$variants = [
    'primary'   => 'bg-maestro-accent text-white hover:bg-maestro-accent-hover border-0',
    'secondary' => 'bg-transparent text-maestro-muted border border-white/10 hover:bg-maestro-surface',
    'danger'    => 'bg-transparent text-[var(--maestro-danger)] border border-[var(--maestro-danger-border)] hover:bg-[var(--maestro-danger-bg)]',
    'ghost'     => 'bg-transparent text-maestro-accent border-0 hover:bg-maestro-accent/10',
];
@endphp

<button {{ $attributes->merge(['class' => "$base {$sizes[$size]} {$variants[$variant]}"]) }}>
    {{ $slot }}
</button>
```

### Card — `card.blade.php`

```blade
<div {{ $attributes->merge(['class' => 'bg-maestro-surface border border-white/[0.07] rounded-lg p-4']) }}>
    {{ $slot }}
</div>
```

### Metric card — `metric-card.blade.php`

Props : `$label`, `$value`, `$sub` (optionnel), `$subColor` (success | info | warning | danger)

```blade
@props(['label', 'value', 'sub' => null, 'subColor' => 'success'])
@php
$subColors = [
    'success' => 'var(--maestro-success)',
    'info'    => 'var(--maestro-info)',
    'warning' => 'var(--maestro-warning)',
    'danger'  => 'var(--maestro-danger)',
];
@endphp
<div class="bg-maestro-surface rounded-lg p-4">
    <p class="text-[11px] font-medium uppercase tracking-wider text-maestro-subtle mb-1">{{ $label }}</p>
    <p class="text-[24px] font-medium text-maestro-text">{{ $value }}</p>
    @if($sub)
        <p class="text-[12px] mt-0.5" style="color: {{ $subColors[$subColor] }}">{{ $sub }}</p>
    @endif
</div>
```

### Agent row — `role-row.blade.php`

Props : `$emoji`, `$name`, `$model`, `$status`

```blade
@props(['emoji', 'name', 'model', 'status' => 'pending'])
<div class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg bg-maestro-surface border border-white/[0.06]">
    <div class="w-7 h-7 rounded-md bg-maestro-accent-muted flex items-center justify-center text-[13px] flex-shrink-0">
        {{ $emoji }}
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-[13px] font-medium text-maestro-text">{{ $name }}</p>
        <p class="text-[11px] text-maestro-subtle">{{ $model }}</p>
    </div>
    <x-ui.badge :status="$status" />
</div>
```

---

## Layout global

Fichier : `resources/views/components/layouts/app.blade.php`

Structure :
```
<body class="bg-maestro-bg text-maestro-text min-h-screen">
    <x-sidebar />
    <main class="ml-56">
        <x-topbar />
        <div class="p-6">
            {{ $slot }}
        </div>
    </main>
</body>
```

### Sidebar — `resources/views/components/sidebar.blade.php`

- Largeur : `224px` (14rem / `w-56`)
- Background : `#0F0F1A` (même que la page — pas de contraste latéral)
- Bordure droite : `1px solid var(--maestro-border)`
- Nav items : `text-[13px]`, padding `px-3 py-2`, arrondi `rounded-lg`
- Item actif : `bg-maestro-accent/10 text-maestro-accent`
- Item hover : `hover:bg-maestro-surface text-maestro-text`
- Logo en haut : texte "Maestro" en `text-[16px] font-medium text-maestro-text`

### Topbar — `resources/views/components/topbar.blade.php`

- Hauteur : `52px`
- Background : `var(--maestro-bg)` avec `border-b border-white/[0.07]`
- Contenu : breadcrumb à gauche, actions à droite

---

## Application aux vues existantes

Appliquer le design system à ces fichiers en priorité :

1. `resources/views/livewire/global-dashboard.blade.php` — utiliser `<x-metric-card>` pour les KPIs
2. `resources/views/livewire/pipeline-cockpit.blade.php` — utiliser `<x-role-row>` et `<x-badge>`
3. `resources/views/livewire/task-pipeline.blade.php` — remplacer les badges inline par `<x-badge>`
4. `resources/views/livewire/kanban-board.blade.php` — cards avec `<x-card>`
5. `resources/views/livewire/project-wizard.blade.php` — inputs et boutons avec les composants ui

---

## Checklist

- [ ] Variables CSS dans `resources/css/app.css`
- [ ] Extension Tailwind dans `tailwind.config.js`
- [ ] Import fonts Inter + JetBrains Mono dans le layout
- [ ] `resources/views/components/ui/badge.blade.php`
- [ ] `resources/views/components/ui/button.blade.php`
- [ ] `resources/views/components/ui/card.blade.php`
- [ ] `resources/views/components/ui/metric-card.blade.php`
- [ ] `resources/views/components/ui/role-row.blade.php`
- [ ] `resources/views/components/ui/heading-1.blade.php` + heading-2, heading-3, label, mono
- [ ] Layout `app.blade.php` — structure sidebar + main
- [ ] `resources/views/components/sidebar.blade.php`
- [ ] `resources/views/components/topbar.blade.php`
- [ ] Refacto `global-dashboard.blade.php`
- [ ] Refacto `pipeline-cockpit.blade.php`
- [ ] Refacto `task-pipeline.blade.php`
- [ ] Refacto `kanban-board.blade.php`
- [ ] Refacto `project-wizard.blade.php`
