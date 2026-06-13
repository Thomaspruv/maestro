#!/bin/bash

# Fonction pour tuer tous les processus au moment de l'arrêt
cleanup() {
    echo "Arrêt des services..."
    kill $(jobs -p) 2>/dev/null
    exit
}

# Capture du signal d'arrêt
trap cleanup SIGINT SIGTERM

# Configuration des variables d'environnement si nécessaire
export NODE_ENV=development

# Lancement du serveur Laravel
php artisan serve &

# Lancement de Vite pour le front-end
npm run dev &

# Lancement du worker de queue (Horizon si redis, sinon queue:work)
bash scripts/queue-worker.sh &

# Lancement du scheduler Laravel en mode continu
php artisan schedule:work &

# Attendre que tous les processus se terminent
wait
