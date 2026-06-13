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

# Lancement de Horizon (ou fallback queue:work) pour la gestion des queues
if php artisan list --raw 2>/dev/null | grep -qx 'horizon'; then
    php artisan horizon &
else
    echo "⚠️  Horizon non disponible — utilisation de queue:work"
    php artisan queue:work redis --queue=default,agents,dev-agent &
fi

# Lancement du scheduler Laravel en mode continu
php artisan schedule:work &

# Attendre que tous les processus se terminent
wait
