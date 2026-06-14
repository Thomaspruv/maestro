# Vue Cockpit Temps Réel — Documentation d'Implémentation

## Résumé

Implémentation complète d'une vue cockpit temps réel pour la pipeline multi-agents de Maestro, affichant en temps réel :
- La progression de chaque agent (en attente, actif, validé, bloqué, etc.)
- Le statut des gates (en attente, approuvé, rejeté)
- Le coût cumulé et par agent
- Actions inline pour valider/rejeter les gates

## Fichiers Créés

### Services
- **`app/Services/PipelineCockpitService.php`** — Assemble un snapshot cohérent de l'état courant de la pipeline pour une tâche donnée. Responsabilités :
  - Construire une séquence de steps (agents + gates) dans l'ordre d'exécution
  - Mapper les statuts agents aux états cockpit (pending, running, completed, blocked, waiting_gate, skipped)
  - Calculer le coût cumulé et par agent
  - Garder à l'œil : agents "stale" (running depuis > 30min) → marqués comme "blocked"

### Événements Broadcast
- **`app/Events/GateStatusUpdated.php`** — Broadcast quand un gate change de statut (pending → approved/rejected)
- **`app/Events/AgentCostRecorded.php`** — Broadcast quand le coût d'un agent est finalisé (après completion)

### Composants Livewire
- **`app/Livewire/PipelineCockpit.php`** — Composant Livewire autonome du cockpit
  - `mount()` — Charge et vérifie l'autorisation utilisateur
  - Listeners Echo pour broadcast events (AgentRunUpdated, GatePending, GateStatusUpdated, AgentCostRecorded)
  - Actions `approveGate()` et `rejectGate()` — validation inline des gates
  - Throttle @ 1s pour les refreshes (limite de charge)

### Vues Blade
- **`resources/views/livewire/pipeline-cockpit.blade.php`** — Template principal du cockpit
  - Affichage du coût total et "Pipeline running" badge animé
  - Boucle sur `$snapshot['steps']` et appel à composants enfants
  - États finaux (done, error)

- **`resources/views/components/maestro/cockpit-agent-step.blade.php`** — Composant d'étape agent
  - Badge statut coloré (pending, running, completed, blocked, waiting_gate, skipped)
  - Icones et animations (pulse sur "running")
  - Coût au survol
  - Bouton "View Output" pour ouvrir le détail

- **`resources/views/components/maestro/cockpit-gate-step.blade.php`** — Composant d'étape gate
  - Badge statut gate (pending, approved, rejected)
  - Boutons Valider/Rejeter inline (si gate en attente)
  - Affichage du feedback

### Routes
- **`routes/web.php`** — Route pleine page GET `/projects/{project}/tasks/{task}/cockpit`

### Contrôleurs
- **`app/Http/Controllers/Tasks/TaskController.php`** — Ajout méthode `cockpit()` avec check policy

### Vues
- **`resources/views/tasks/cockpit.blade.php`** — Vue pleine page du cockpit

### Modifications Existantes
- **`app/Services/GateReviewService.php`** — Dispatch `GateStatusUpdated` event après approbation/rejet
- **`app/Jobs/RunAgentJob.php`** — Dispatch `AgentCostRecorded` event après enregistrement du coût
- **`resources/views/livewire/task-pipeline.blade.php`** — Lien vers cockpit pleine page (icône 📊)

## Architecture Décisions

### 1. Service Snapshot vs React in Real-Time
- **Approche** : Service `PipelineCockpitService::getSnapshot()` retourne un array structuré
- **Justification** : Découplage entre la source de vérité (DB) et la présentation. Facile à cacher, tester, et à étendre pour du polling ou du streaming.

### 2. Events Broadcast pour Mise à Jour Temps Réel
- **Canaux** : Private channel `task.{task_id}` (sécurisé, uniquement propriétaire tâche)
- **Événements** :
  - `AgentRunUpdated` (existant) — utilisé par TaskPipeline, aussi écouté par cockpit
  - `GateStatusUpdated` (nouveau) — après approval/rejection
  - `AgentCostRecorded` (nouveau) — après finalization de coût
- **Fallback** : Mode polling @ 5s si `BROADCAST_CONNECTION=log` (développement local)

### 3. Throttle Livewire pour Prévenir Surcharge
- **Throttle** : `#[Throttle('1s')]` sur `refreshSnapshot()`
- **Justification** : Évite les refreshes excessives si beaucoup de broadcast reçus d'un coup

### 4. Séparation Pleine Page vs Drawer
- **Cockpit pleine page** : Route `/tasks/{task}/cockpit` — meilleure UX pour suivi détaillé, plus d'espace
- **Lien depuis drawer** : Bouton icône 📊 dans task-pipeline.blade.php

### 5. Statut "Blocked" pour Agents Stale
- **Définition** : Agent en statut `running` depuis > 30min
- **Raison** : Évite de rester infiniment sur "running" si timeout/crash silencieux

## Détails de Sécurité

### Authorization
- **Controller** : Policy check `authorize('update', $task)` dans `cockpit()`
- **Composant Livewire** : Policy check dans `mount()`
- **Données** : Snapshot ne retourne que les données de la tâche spécifique

### Validation des Actions
- **approveGate / rejectGate** :
  1. Vérifier gate appartient à la tâche
  2. Vérifier gate est en statut `pending` (sinon dispatcher error)
  3. Déléguer à `GateReviewService`

### Events
- **Channel** : `private:task.{taskId}` — seul propriétaire tâche peut écouter
- **Broadcast data** : Pas de secrets, slugs/statuts/coûts seulement

## Points d'Intégration avec Existant

| Existant | Utilisé par Cockpit |
|----------|---------------------|
| `AgentRun`, `Gate`, `Task` modèles | Source de vérité pour snapshot |
| `OrchestratorService::getPipelineForTask()` | Séquence agents |
| `GateReviewService::approve/reject()` | Logique gate |
| `AgentRunUpdated` event | Trigger refresh cockpit |
| `GatePending` event | Trigger refresh cockpit |
| `PipelineActivity` class | Détermine polling vs broadcast |
| `TaskController` | Route cockpit |

## Cas Limites Gérés

1. **Pipeline pending** (non démarrée) → "Pipeline not started" message
2. **Agent timeout** (> 30min statut running) → marké "blocked"
3. **Gate déjà approuvé** → try to approve → error dispatch
4. **Ré-exécution agent après rejet** → nouvel AgentRun, coût ajouté
5. **Mode polling (BROADCAST_CONNECTION=log)** → `wire:poll.5s` activé
6. **Budget dépassé** → lecture flag du module Coûts, warning inline

## Testing

### Tests Créés
- **`tests/Unit/Services/PipelineCockpitServiceTest.php`** — Tests du service snapshot
- **`tests/Feature/Livewire/PipelineCockpitTest.php`** — Tests du composant Livewire

### À Tester Manuellement
1. Démarrer une pipeline, voir le cockpit s'afficher et se mettre à jour
2. Approver/Rejeter un gate depuis le cockpit
3. Vérifier le coût cumulé se met à jour
4. Tester mode polling (BROADCAST_CONNECTION=log)
5. Tester accès refusé (utilisateur non propriétaire)

## Checklist de Déploiement

- [x] Service snapshot complet
- [x] Events broadcast créés et dispatcher
- [x] Composant Livewire avec listeners et throttle
- [x] Vues Blade (cockpit, agent-step, gate-step)
- [x] Route et controller
- [x] Lien depuis task-pipeline
- [x] Tests unitaires et Feature
- [x] Vérification sécurité (authorization, validation)
- [ ] Test manuel en local
- [ ] Merge PR et déploiement

## Notes Futures

- **Granularité coût temps réel** : Actuellement coût finalisé post-completion. Si besoin coût partiel pendant run, intégrer token intermédiaires API.
- **Historique multi-run** : Possible extension : afficher tous les runs d'un agent (tentatives échouées).
- **Intégration mobile** : Cockpit conçu desktop-first. Mobile support possible mais out-of-scope.
