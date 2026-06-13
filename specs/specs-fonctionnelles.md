# Maestro — Specs Fonctionnelles

> Version 2.0 — 2026-06-11
> Outil d'orchestration d'agents IA pour la production d'applications

---

## 1. Vision & Problème

### Problème

Les solo devs et petites équipes qui développent avec l'IA jonglent entre des outils qui ne se parlent pas : Jira pour les tickets, Claude pour les specs, Cursor pour le code, GitHub pour les PRs. Le résultat : du copier-coller manuel, des specs incomplètes, et des agents IA sous-exploités parce que le contexte est perdu entre chaque outil.

### Solution

Maestro est un outil de gestion de tâches avec orchestration d'agents IA intégrée. Tu décris ce que tu veux construire, les agents s'en occupent dans l'ordre — spec, technique, code, tests, PR. Toi tu valides aux étapes clés et tu merges.

### Utilisateur principal

Développeur solo ou petite équipe (2-3 devs) qui construit un SaaS et veut accélérer le développement avec des agents IA sans perdre le contrôle.

---

## 2. Fonctionnalités

---

### 2.1 Compte utilisateur `P0`

**Inscription / Connexion**
- Inscription par email + mot de passe
- Connexion avec session persistante

**Settings du compte**
- Modifier son email et mot de passe
- Ajouter / modifier / révoquer sa clé API Claude (Anthropic)
  - La clé est chiffrée en base (AES-256)
  - Elle est partagée entre tous les projets du compte
  - Un indicateur "Clé valide ✓" vérifie la clé via un ping API au moment de l'enregistrement
- Définir un budget mensuel global (alerte email si dépassé)
- Voir le coût total du mois en cours sur tous les projets

---

### 2.2 Création de projet — Wizard 4 étapes `P0`

La création d'un projet se fait via un wizard guidé. Les données sont sauvegardées en brouillon à chaque étape (l'utilisateur peut reprendre plus tard).

---

#### Étape 1 — Informations du projet

- Nom du projet (obligatoire)
- Description courte (optionnelle)
- Connexion GitHub :
  - OAuth GitHub → sélection du dépôt dans la liste des repos accessibles
  - Branche principale (défaut : `main`)
  - Option "Lire le contexte depuis le repo" : Maestro lit automatiquement `README.md`, `CLAUDE.md`, et les fichiers dans `docs/architecture/` pour pré-remplir le contexte du projet

---

#### Étape 2 — Contexte du projet

Le contexte est la description que tous les agents recevront en permanence. Il comprend :

- **Stack technique** : description libre (ex: "Laravel 10, Livewire 3, Alpine.js, Tailwind CSS, PostgreSQL")
- **Conventions de code** : description libre ou chargée depuis le repo (ex: "Services dans app/Services/, UUID dans les URLs, Pest pour les tests")
- **Modules existants** : liste des fonctionnalités déjà en place (ex: "NPS, CSAT, Avis Google, Action Hub")
- **Design system** : description des composants UI (ex: "composants x-ui.*, couleur primaire #9DF46D")
- **Contraintes spécifiques** : tout ce que les agents doivent absolument respecter

> Ce contexte sera mis en cache via le prompt caching Anthropic pour réduire les coûts.

---

#### Étape 3 — Workflow & Gates

L'utilisateur configure son pipeline d'agents pour chaque type de tâche.

**Types de tâches configurables :** Feature, Bug, Amélioration, Chore

**Pour chaque type de tâche, on peut :**
- Activer / désactiver chaque agent
- Réordonner les agents (drag & drop)
- Configurer les gates de validation :

| Gate | Déclencheur | Description |
|------|-------------|-------------|
| Gate Specs | Après PM Agent | Thomas relit les specs fonctionnelles |
| Gate Technique | Après Tech Lead | Thomas valide l'approche avant de coder |
| Gate Merge | Avant Doc Agent | Thomas relit la PR et merge sur GitHub |

**Mode de validation par défaut par type de tâche :**

| Type | Mode suggéré | Description |
|------|-------------|-------------|
| Feature | Manuel 🔴 | Toutes les gates actives |
| Bug | Semi-auto 🟡 | Gate merge uniquement |
| Amélioration | Semi-auto 🟡 | Gate merge uniquement |
| Chore | Full-auto 🟢 | Aucune gate |

Le mode peut être écrasé tâche par tâche au moment de la création.

**Pipelines par défaut :**

Feature :
```
PM → [Gate Specs] → UX + Tech Lead (parallèle) → [Gate Technique] → Security → Dev → QA → PR Expert → [Gate Merge] → Doc
```

Bug :
```
Tech Lead → Security → Dev → QA → PR Expert → [Gate Merge] → Doc
```

Amélioration :
```
PM → Tech Lead → Security → Dev → QA → PR Expert → [Gate Merge] → Doc
```

Chore :
```
Tech Lead → Dev → PR Expert → [Gate Merge]
```

---

#### Étape 4 — Configuration des agents

Pour chaque agent actif du projet, l'utilisateur peut :

- Choisir le modèle : Sonnet (intelligent) / Haiku (rapide et économique)
- Voir et modifier le prompt système (pré-rempli avec le prompt par défaut de Maestro)
- Cliquer "Tester cet agent" : envoie une requête de test avec un exemple fictif et affiche la réponse

**Modèles par défaut recommandés :**

| Agent | Modèle par défaut | Raison |
|-------|------------------|--------|
| PM Agent | claude-sonnet-4-6 | Raisonnement produit complexe |
| UX Agent | claude-sonnet-4-6 | Nuance UX et design |
| Tech Lead | claude-sonnet-4-6 | Décisions d'architecture |
| Security Reviewer | claude-haiku-4-5 | Checklist mécanique |
| Dev Agent | claude-sonnet-4-6 | Génération de code qualité |
| QA Agent | claude-haiku-4-5 | Tests mécaniques |
| PR Expert | claude-haiku-4-5 | Review checklist |
| Doc Agent | claude-haiku-4-5 | Documentation mécanique |

---

#### Récapitulatif (fin du wizard)

- Résumé du projet (nom, repo, pipeline actif)
- Estimation du coût moyen par tâche selon la config
- Bouton "Créer le projet et aller au board"

---

### 2.3 Gestion du board `P0`

**Colonnes Kanban :**
- Backlog
- In Progress
- In Review
- Done

**Chaque tâche affiche :**
- Type (Feature / Bug / Amélioration / Chore) — code couleur
- Titre
- Pipeline pills : chaque agent avec son statut (✓ done, ● running, ⏸ gate, gris waiting)
- Mode (🔴 / 🟡 / 🟢)
- Coût engagé / coût estimé

**Filtres :**
- Par type
- Par priorité
- Par statut

---

### 2.4 Création de tâche `P0`

**Formulaire :**
- Titre (obligatoire)
- Description courte (quelques phrases — pas une spec, c'est le PM Agent qui s'en charge)
- Type : Feature / Bug / Amélioration / Chore
- Priorité : Critical / High / Medium / Low
- Mode de validation : Manuel 🔴 / Semi-auto 🟡 / Full-auto 🟢 (pré-rempli selon le défaut configuré)

**Panel d'estimation (à droite du formulaire) :**
Affiché en temps réel pendant que l'utilisateur remplit le formulaire :
- Détail par agent : modèle, tokens estimés, coût estimé
- Total fourchette basse / haute
- Rappel : "Prompt caching actif — économie ~70% sur le contexte projet"
- Projection mensuelle : coût si X tâches par mois

**Lancement :** bouton "▶ Lancer le pipeline" → la tâche passe en In Progress et le premier agent démarre.

---

### 2.5 Vue tâche — Pipeline temps réel `P0`

Deux colonnes :

**Colonne gauche — Pipeline :**
- Liste des agents dans l'ordre d'exécution
- Chaque agent : icône de statut animée, nom, modèle, durée, coût
- Gates représentées visuellement entre les agents
- Gate active : boutons Valider / Éditer + Valider / Rejeter avec feedback

**Colonne droite — Output viewer :**
- Affiche l'output de l'agent sélectionné dans la colonne gauche
- Bouton "✏️ Éditer" : active un éditeur de texte riche inline
- Les modifications sont sauvegardées comme `edited_output` et transmises aux agents suivants

**Statuts d'un agent :**
- ⏳ En attente
- 🔄 En cours (animation)
- ✅ Terminé
- ⚠️ Terminé avec réserves
- 🚫 Bloqué / Erreur
- ⏸ En attente de validation humaine

**Informations de la tâche en haut :**
- Titre, type, mode, priorité, module concerné
- Barre de progression (agents complétés / total)
- Lien GitHub branch + PR quand disponibles
- Statut PR (open / changes requested / approved / merged)

---

### 2.6 Gates de validation humaine `P0`

Quand un gate est atteint, le pipeline s'arrête automatiquement. L'utilisateur reçoit une notification. Il peut :

1. **Lire** l'output de l'agent concerné
2. **Éditer** l'output directement dans l'interface (correction d'une spec, ajout d'une contrainte)
3. **Valider** → le pipeline reprend immédiatement
4. **Rejeter avec feedback** → l'agent reçoit le feedback et régénère (max 2 tentatives avant escalade manuelle)

Les outputs édités sont transmis aux agents suivants comme s'ils avaient été produits par l'agent. L'historique des versions (original / édité) est conservé.

---

### 2.7 Estimation et suivi des coûts `P0`

**Avant exécution :**
Détail par agent avec tokens estimés, modèle, et coût estimé. Total en fourchette.

**Pendant l'exécution :**
Coût réel en cours, mis à jour après chaque agent.

**Après exécution :**
- Coût réel par agent
- Coût total de la tâche
- Comparaison estimation vs réel

**Dashboard coûts (vue dédiée) :**
- Coût total du mois
- Coût par type de tâche (Feature plus cher que Bug, etc.)
- Agents les plus coûteux
- Projection fin de mois
- Historique mois par mois
- Alerte si budget mensuel dépassé

---

### 2.8 Génération de code & GitHub `P0`

**Processus Dev Agent :**
1. Clone du repo sur le serveur (ou pull si déjà cloné)
2. Création d'une branche : `feature/[task-uuid-court]-[slug-titre]`
3. Génération du code par Claude Code CLI en utilisant la spec PM + approche Tech Lead + contraintes Security
4. Boucle de validation (max 3 tentatives) :
   - `php artisan` — vérification syntaxe PHP
   - `./vendor/bin/pest` — exécution des tests
   - `npm run build` — compilation frontend
5. Si tout passe → push de la branche + ouverture PR GitHub
6. Si échec après 3 tentatives → notification + rapport d'erreur détaillé → Thomas peut relancer, ouvrir dans Cursor, ou abandonner

**Dans l'app, la tâche affiche :**
- Nom de la branche créée (lien GitHub)
- Lien vers la PR
- Statut PR (open / changes requested / approved / merged)
- Résultat des tests (nb passés / échoués)
- Rapport d'erreur si échec Dev Agent

---

### 2.9 Configuration des agents `P1`

Dans les settings du projet, pour chaque agent :
- Activer / désactiver
- Modifier le prompt système (éditeur de texte avec highlight)
- Choisir le modèle (Sonnet / Haiku / Opus)
- Tester l'agent avec un exemple personnalisé
- Voir l'historique des modifications du prompt

---

### 2.10 Contexte projet auto-mis à jour `P1`

Après chaque merge :
- Le Doc Agent met à jour le contexte du projet (nouvelles fonctionnalités, nouveaux composants)
- Ce contexte enrichi est utilisé par tous les agents sur les tâches suivantes
- Historique des mises à jour visible dans les settings

---

### 2.11 Notifications `P1`

- Notification browser quand un gate attend validation
- Email quand un gate attend validation (optionnel, configurable)
- Notification quand une PR est prête à merger
- Notification quand un Dev Agent échoue après 3 tentatives
- Résumé quotidien : tâches en cours, gates en attente, PRs ouvertes (optionnel)

---

### 2.12 Changelog automatique `P1`

- Après chaque merge, le Doc Agent génère une entrée de changelog
- Format : date, type, module, description courte
- Changelog global visible dans le projet
- Export en markdown

---

### 2.13 Multi-projets `P2`

- Gérer plusieurs projets depuis le même compte
- Chaque projet a son propre contexte, pipeline et configuration d'agents
- Dashboard global : toutes les tâches en cours sur tous les projets

---

## 3. User Stories

### Onboarding

- En tant que dev, je veux créer un compte avec mon email, pour accéder à Maestro
- En tant que dev, je veux ajouter ma clé API Claude dans les settings de mon compte, pour que tous mes projets l'utilisent sans avoir à la reconfigurer
- En tant que dev, je veux créer un projet en liant mon repo GitHub, pour que les agents aient accès au contexte de mon codebase
- En tant que dev, je veux que Maestro lise mon CLAUDE.md pour pré-remplir le contexte, pour ne pas avoir à tout resaisir
- En tant que dev, je veux configurer mon pipeline (agents actifs + gates), pour adapter le workflow à mon style de travail
- En tant que dev, je veux voir une estimation du coût moyen par tâche avant de valider la config, pour éviter les surprises

### Workflow quotidien

- En tant que dev, je veux créer une tâche avec un titre et une description courte, pour déclencher le pipeline sans écrire une spec moi-même
- En tant que dev, je veux voir l'estimation de coût avant de lancer, pour décider si j'ajuste le pipeline
- En tant que dev, je veux lire et corriger la spec PM avant que le Tech Lead commence, pour m'assurer qu'elle est correcte
- En tant que dev, je veux valider l'approche technique, pour avoir la main sur les décisions d'architecture
- En tant que dev, je veux recevoir une notification quand une PR est prête, pour ne pas surveiller GitHub en permanence
- En tant que dev, je veux voir les résultats de tests dans l'app, pour savoir si la branche est saine avant de la tirer

### Contrôle des coûts

- En tant que dev, je veux voir le coût réel de chaque tâche, pour suivre ma consommation mensuelle
- En tant que dev, je veux définir un budget mensuel avec alerte, pour ne pas dépasser sans le savoir
- En tant que dev, je veux passer une tâche en full-auto, pour que les bugs simples soient traités sans interruption

---

## 4. Workflows complets

### Workflow Feature — Mode Manuel

```
Thomas crée la tâche (titre + description)
        ↓
[Estimation coût affichée] → Thomas confirme le lancement
        ↓
PM Agent génère la spec (user stories, critères d'acceptance)
        ↓
⏸️ GATE 1 — Thomas relit, édite si besoin, valide
        ↓
UX Agent + Tech Lead tournent en parallèle
        ↓
⏸️ GATE 2 — Thomas valide l'approche technique
        ↓
Security Reviewer (automatique)
        ↓
Dev Agent génère le code + boucle de validation (tests, compile)
        ↓
QA Agent génère les tests complémentaires
        ↓
PR Expert rédige la review + description PR
        ↓
PR ouverte sur GitHub
        ↓
⏸️ GATE 3 — Thomas relit la PR et merge sur GitHub
        ↓
Doc Agent met à jour CHANGELOG + contexte projet
        ↓
Tâche → Done ✅
```

### Workflow Bug — Mode Semi-auto

```
Thomas crée le bug (titre + description de reproduction)
        ↓
Tech Lead identifie le fichier et la cause
        ↓
Security check (si touche auth ou data)
        ↓
Dev Agent corrige + tests passent
        ↓
PR Expert rédige la PR
        ↓
PR ouverte
        ↓
⏸️ GATE — Thomas merge
        ↓
Doc Agent
```

### Workflow Chore — Mode Full-auto

```
Thomas crée le chore
        ↓
Tech Lead décrit la tâche
        ↓
Dev Agent exécute
        ↓
PR ouverte
        ↓
Notification Thomas (merge quand il veut)
```

---

## 5. Cas limites & gestion d'erreurs

**Dev Agent échoue après 3 tentatives**
- Pipeline s'arrête, Thomas notifié avec le rapport d'erreur
- Options : Relancer (nouvelle approche), Ouvrir dans Cursor, Abandonner

**Gate rejeté**
- L'agent reçoit le feedback et régénère
- Maximum 2 régénérations avant passage en manuel obligatoire
- Historique de toutes les versions conservé

**Clé API invalide ou quota dépassé**
- Erreur affichée dans la vue tâche
- Lien vers les settings compte pour mettre à jour la clé
- Pipeline mis en pause (pas d'annulation)

**Tests en échec sur la PR**
- PR ouverte avec badge "⚠️ Tests échoués"
- Thomas notifié
- Il peut déclencher une nouvelle passe du Dev Agent depuis l'app

**Coût dépassant un seuil**
- Alerte si une tâche dépasse 2x l'estimation initiale
- Alerte si le budget mensuel du compte est atteint à 80% puis à 100%

---

## 6. Hors scope V1

- Multi-utilisateurs / équipes
- Intégration Slack / Discord / email externe
- Support de plusieurs branches en parallèle sur la même tâche
- Rollback automatique d'une PR
- Agents personnalisés (les 8 agents sont fixes en V1, seulement le prompt est éditable)
- Support des repos non-GitHub (GitLab, Bitbucket)
- Import depuis Jira / Notion / Linear
