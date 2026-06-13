# Maestro — Specs Design

> Version 1.0 — 2026-06-11

---

## 1. Principes

**Dense mais lisible.** L'interface affiche beaucoup d'information (statuts agents, coûts, pipeline) sans jamais sembler surchargée. On utilise des contrastes doux entre les niveaux de hiérarchie plutôt que des espaces vides.

**Sombre par défaut.** Le thème dark est le seul en V1. Il correspond à l'environnement de travail d'un dev (terminal, IDE), réduit la fatigue visuelle, et met en valeur les indicateurs colorés (vert = ok, violet = IA, jaune = gate).

**La couleur porte le sens.** Chaque couleur a une signification fixe dans toute l'app — pas de couleur décorative :
- Violet → IA / agent actif
- Vert → succès / validé
- Jaune → attente humaine (gate)
- Rouge → erreur / bloqué
- Gris → inactif / en attente

**Le pipeline est la star.** Tout l'UI gravite autour de la visualisation du pipeline d'agents. C'est l'information la plus importante, elle doit être visible d'un coup d'œil depuis le board.

---

## 2. Palette de couleurs

### Fonds (du plus sombre au plus clair)
```
--color-bg-base:      #0f0f13   /* fond principal */
--color-bg-surface:   #18181f   /* cards, colonnes */
--color-bg-elevated:  #23232e   /* task cards, inputs */
--color-bg-overlay:   #2a2a35   /* borders, séparateurs */
```

### Accents
```
--color-primary:      #7c3aed   /* violet — actions, liens actifs, CTA */
--color-primary-light:#a78bfa   /* violet clair — texte secondaire IA, badges */
--color-primary-muted:#2d1f5e   /* violet sombre — backgrounds actifs, states */

--color-success:      #4ade80   /* vert — agent terminé, validé, mergé */
--color-success-muted:#1a2f1a   /* vert sombre — backgrounds succès */

--color-warning:      #facc15   /* jaune — gate en attente, attention */
--color-warning-muted:#3f3010   /* jaune sombre — backgrounds gate */

--color-danger:       #f87171   /* rouge — erreur, bloqué, rejeté */
--color-danger-muted: #3f1f1f   /* rouge sombre — backgrounds erreur */

--color-neutral:      #94a3b8   /* gris bleu — chores, tags neutres */
```

### Texte
```
--color-text-primary:   #e0e0e0   /* titres, labels importants */
--color-text-secondary: #999999   /* texte courant */
--color-text-muted:     #666666   /* labels, placeholders */
--color-text-faint:     #444444   /* séparateurs textuels */
--color-text-white:     #ffffff   /* titres h1, valeurs métriques */
```

---

## 3. Typographie

Police unique : **system-ui** (SF Pro sur Mac, Segoe UI sur Windows, Roboto sur Linux). Pas de Google Fonts — chargement instantané, cohérence OS.

```
/* Hiérarchie */
--text-xs:   9px  / font-weight: 600  → badges, labels uppercase
--text-sm:  10px  / font-weight: 400  → metadata, coûts, timestamps
--text-base: 12px / font-weight: 400  → texte courant, descriptions
--text-md:  13px  / font-weight: 500  → labels, noms d'agents
--text-lg:  14px  / font-weight: 700  → titres de section, noms de tâches
--text-xl:  16px  / font-weight: 700  → titres de page
--text-2xl: 22px  / font-weight: 700  → métriques dashboard

/* Labels de section (uppercase) */
font-size: 9-10px, font-weight: 700, text-transform: uppercase, letter-spacing: 0.06-0.08em, color: --color-text-muted
```

---

## 4. Layout global

```
┌─────────────────────────────────────────────────────────┐
│  Sidebar 220px  │           Main content                 │
│  fixed          │  topbar 48px                           │
│                 │─────────────────────────────────────── │
│  [logo]         │                                        │
│  [nav items]    │  contenu scrollable                    │
│                 │                                        │
│  [project       │                                        │
│   switcher]     │                                        │
└─────────────────────────────────────────────────────────┘
```

### Sidebar
- Largeur : 220px fixe, non-collapsible en V1
- Fond : `--color-bg-surface`
- Border droite : `1px solid --color-bg-overlay`
- Logo en haut : icône ⚒️ + texte "Maestro" + badge "BETA"
- Nav groupée en sections (Workspace / Projet)
- Item actif : fond `--color-primary-muted`, texte `--color-primary-light`
- Project switcher en bas : bouton dropdown avec point de couleur

### Topbar
- Hauteur : 48px
- Fond : `--color-bg-surface`
- Border bas : `1px solid --color-bg-overlay`
- Titre de page à gauche, actions à droite

### Content
- Padding : 20px
- Max-width : aucun (full width)

---

## 5. Composants

### Cards de tâche (Kanban)

```
┌────────────────────────────────┐
│ FEATURE                        │  ← type pill (violet/rouge/gris)
│ Titre de la tâche              │  ← 12px, font-weight 500
│ ● PM  ● UX  ● TL  ○ ...       │  ← pipeline pills
│ 🔴 Manuel          €0.04/0.11  │  ← mode + coût
└────────────────────────────────┘
```

- Fond : `--color-bg-elevated`
- Border : `1px solid --color-bg-overlay`
- Border-radius : 8px
- Hover : border-color → `--color-primary`
- Border violette sur la card active (en cours)
- Padding : 10px 11px

### Pipeline pills (dans les cards)

Petits tags colorés pour chaque agent. Taille 8px, border-radius 99px, padding 1px 5px.

| État | Fond | Texte | Indicateur |
|------|------|-------|------------|
| En attente | `#1e1e28` | `#555` | — |
| En cours | `--primary-muted` | `--primary-light` | ● animé |
| Terminé | `--success-muted` | `--success` | ✓ |
| Gate | `--warning-muted` | `--warning` | ⏸ |
| Erreur | `--danger-muted` | `--danger` | ✗ |

### Boutons

```
/* Primary */
background: #7c3aed, color: white, padding: 7px 14px, border-radius: 7px, font-size: 12px, font-weight: 600

/* Ghost */
background: transparent, border: 1px solid --bg-overlay, color: #999, même padding

/* Danger */
background: --danger-muted, color: --danger, border: 1px solid #5a2020
```

Pas d'ombre, pas d'animation complexe — juste un changement de fond au hover.

### Inputs & Selects

```
background: --color-bg-surface
border: 1px solid --color-bg-overlay
border-radius: 7px
padding: 8px 10px
color: --color-text-primary
font-size: 12px

:focus → border-color: --color-primary (pas de box-shadow)
```

### Badges / Chips

Petits tags inline, border-radius 99px, padding 2px 7px, font-size 9-10px, font-weight 700.

Types :
- Type de tâche : Feature (violet), Bug (rouge), Amélioration (orange), Chore (gris)
- Mode : 🔴 Manuel, 🟡 Semi-auto, 🟢 Full-auto
- Priorité : Critical (rouge), High (orange), Medium (jaune), Low (gris)
- Modèle IA : fond sombre, texte muted

### Stat cards (dashboard)

```
┌─────────────────┐
│ LABEL           │  ← 10px uppercase muted
│ 22px bold white │  ← valeur principale
│ sous-texte      │  ← 10px colored
└─────────────────┘
```

- Fond : `--color-bg-surface`
- Border : `1px solid --color-bg-overlay`
- Border-radius : 10px
- Padding : 14px 16px

### Section headers

```
font-size: 10px, font-weight: 700, text-transform: uppercase, letter-spacing: 0.06em, color: #666
```

### Séparateurs / borders

Toujours `1px solid #2a2a35`. Jamais plus épais.

---

## 6. Vue Board (Kanban)

**4 colonnes égales**, scrollables verticalement si beaucoup de tâches :
- Backlog (gris)
- In Progress (violet)
- In Review (jaune)
- Done (vert, opacité réduite sur les cards)

**Header de colonne** : titre coloré en uppercase + compteur badge gris.

**Barre de stats en haut** : 4 stat cards sur une ligne (En cours / En review / Terminées ce mois / Coût du mois).

**Barre de status en bas** (footer fixe) : agents en cours, gates en attente, coût du jour / mois.

---

## 7. Vue Tâche

**Deux colonnes :**

**Gauche (340px fixe)** — Pipeline :
- Header : "Pipeline" + compteur "X/Y agents · €X.XX dépensés"
- Liste verticale d'étapes reliées par une ligne verticale (timeline)
- Chaque étape : dot coloré + card cliquable
- Gates représentées comme des blocs inter-étapes avec fond jaune sombre
- Gate active : border jaune + box-shadow subtle

**Droite (flex-1)** — Output viewer :
- Header : nom de l'agent + badge statut + bouton "✏️ Éditer"
- Corps : fond `--color-bg-surface`, police monospace optionnelle pour le code
- Mode édition : le texte devient éditable inline (contenteditable ou textarea styled)

**En-tête de tâche** (entre topbar et les deux colonnes) :
- Titre, chips type/mode/priorité/module
- Description courte
- Barre de progression linéaire (gradient violet)

---

## 8. Wizard de création de projet

**Layout** : centré, max-width 680px, pas de sidebar.

**Progress bar en haut** : 4 étapes numérotées reliées par une ligne. Étape active en violet, complétées en vert, futures en gris.

**Chaque étape** : titre + description courte + formulaire + bouton "Continuer →".

**Étape 3 (Workflow)** : builder drag & drop avec les agents comme blocs déplaçables. Gates représentées comme des séparateurs entre les blocs. Toggles pour activer/désactiver.

**Étape 4 (Agents)** : accordéon par agent. Chaque item : nom + sélecteur de modèle + textarea pour le prompt + bouton "Tester".

**Panel d'estimation** (étape 4, colonne droite) : breakdown des coûts en temps réel, mis à jour quand on change les modèles.

---

## 9. Formulaire de création de tâche

**Layout** : modal ou page dédiée en deux colonnes.

**Gauche** : formulaire (titre, description, type, priorité, mode).

**Droite** : panel d'estimation qui se met à jour en temps réel selon les choix (type → pipeline différent, modèles différents).

L'estimation s'affiche sous forme de tableau avec une barre de progression relative par agent, coût estimé à droite, total en bas avec fourchette basse/haute.

---

## 10. Page Settings compte

Layout simple, une colonne centrée (max-width 560px).

**Sections séparées par des titres uppercase :**
- Profil (nom, email)
- Clé API Claude
  - Input de type password avec bouton "Afficher"
  - Indicateur "✓ Clé valide" en vert après vérification
  - Lien "Obtenir une clé → console.anthropic.com"
- Budget mensuel
  - Input nombre + "€ / mois"
  - "Vous serez alerté à 80% et 100% de ce budget"
- Danger zone (supprimer le compte)

---

## 11. Animations & micro-interactions

**Minimaliste.** L'app est un outil pro, pas un site marketing. Les animations ont une fonction.

| Élément | Animation |
|---------|-----------|
| Agent "En cours" dot | Pulse (box-shadow glow) 1.5s infinite |
| Agent "En cours" pipeline | Shimmer subtil sur la card |
| Gate active | Légère box-shadow jaune pulsée |
| Transition card hover | border-color 150ms ease |
| Apparition d'un nouvel agent run | fade-in + slide-down 200ms |
| Progress bar tâche | Transition width 300ms ease |

Pas de transitions de page, pas d'animations complexes. Tout ce qui n'est pas fonctionnel est supprimé.

---

## 12. Responsive

En V1, l'app est **desktop-only** (min-width 1200px). Pas de version mobile.

Raison : l'utilisateur principal travaille sur son Mac/PC quand il développe. Une version mobile n'apporte pas de valeur en V1.

Un message s'affiche sur les petits écrans : *"Maestro est optimisé pour un écran de 1200px ou plus."*

---

## 13. États vides

**Board vide (aucune tâche)** :
```
Icône neutre
"Aucune tâche dans ce pipeline"
"Créez votre première tâche pour démarrer"
[Bouton "+ Nouvelle tâche"]
```

**Colonne vide** :
Zone avec border dashed `1px dashed #2a2a35`, texte centré gris muted. Pas d'icône.

**Output viewer vide** (aucun agent sélectionné) :
```
"← Sélectionne un agent pour voir son output"
```

**Projet sans tâches** :
Illustration simple SVG (quelques lignes géométriques violet/gris), texte d'invitation.

---

## 14. Iconographie

Pas de bibliothèque d'icônes. On utilise uniquement des **emojis Unicode** pour les icônes fonctionnelles :

| Usage | Emoji |
|-------|-------|
| Agent PM | 🧠 |
| Agent UX | 🎨 |
| Tech Lead | ⚙️ |
| Security | 🔒 |
| Dev Agent | 💻 |
| QA Agent | 🧪 |
| PR Expert | 📝 |
| Doc Agent | 📚 |
| Gate | ⏸ |
| Succès | ✅ |
| Erreur | 🚫 |
| En cours | 🔄 |
| En attente | ⏳ |
| Coût | 💰 |
| GitHub | 🔗 |

Les emojis sont consistants cross-platform car on cible uniquement macOS/Linux desktop.
