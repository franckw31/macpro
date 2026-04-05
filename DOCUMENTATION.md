# Documentation Technique - CardEvent

## 1. Vue d'ensemble du Projet
- **Nom de l'application** : CardEvent (anciennement Poker Timer)
- **Plateformes** : Application mobile iOS (Apple) et Web Backend
- **Technologies** : Swift & SwiftUI (Frontend iOS) / PHP & MySQL (Backend API)
- **Domaine/Hébergement** : viendez.com

L'application CardEvent permet aux joueurs de se connecter, de s'inscrire à des activités (comme des tournois de cartes/poker), de suivre leurs statistiques et de consulter leurs tickets de tombola.

---

## 2. Architecture Frontend (iOS / SwiftUI)

Le code source iOS se trouve principalement dans le dossier `CardEvent/CardEvent/`.

### Principes Généraux
- L'interface est codée avec **SwiftUI**.
- La gestion des requêtes réseau (appels API) s'effectue avec `URLSession` et le système `async/await` de Swift.

### Composants et Vues principales
- **`LoginView.swift`** : Écran d'authentification. L'application est correctement chartée sous le nom "CardEvent".
- **`PokerTimerViewModel.swift`** : Le coeur logique (ViewModel) qui gère l'état global et les appels réseau (récupération du statut d'inscription au démarrage avec `fetchRegistrationStatus()`, etc).
- **`PlayerProfileView.swift`** : Affichage du profil du joueur. Inclut également le bouton "Se déconnecter" qui déclenche une déconnexion locale sécurisée via `AuthService.shared.logout()`.
- **`TicketsListView.swift`** : Affiche la liste des tickets de tombola gagnés par le joueur, triés par mois. 
  - *Note technique* : Le chargement s'effectue via le modificateur `.onAppear { Task { await load() } }` et non `.task {}` afin d'éviter les bugs d'annulation (`NSURLErrorCancelled`) dus au cycle de vie interne des vues SwiftUI.

---

## 3. Architecture Backend (API PHP)

Les scripts PHP servant l'API se trouvent dans le dossier `api/` et à la racine.

### Gestion de l'Authentification (Contournement Firewall)
- **Problème initial** : Certains hébergeurs (notamment avec des règles de sécurité type mod_security ou firewall stricts) bloquent ou suppriment l'en-tête HTTP `Authorization: Bearer <token>`.
- **Solution en place** : Pour que les écritures en base de données fonctionnent (ex: lors d'une inscription via `register-activity.php`), l'application iOS envoie le token de sécurité par deux moyens alternatifs :
  1. À l'intérieur du **Payload JSON** (le corps de la requête POST).
  2. En **paramètre GET** dans l'URL (`?token=XYZ`).
  L'API PHP est conçue pour lire la variable `$input['token']` ou `$_GET['token']` en priorité si le Header est manquant temporairement.

### Fichiers PHP Majeurs
- **`api/auth.php` / `login.php`** : Génération des tokens et gestion des sessions membres sécurisées.
- **`api/register-activity.php`** : Enregistrement de la participation du joueur. Modifié pour accepter les tokens d'authentification de secours décrits ci-dessus.
- **`api/member-tickets.php`** : Service renvoyant les tickets de tombola validés de l'utilisateur.

---

## 4. Maintenance Globale et Dépannage

### Base de données (.sql, .php)
Le contexte de la base de données est testé avec les scripts `test_db.php` et `check_logs.php`. Assurez-vous des droits en écriture sur le serveur hôte.

### Erreurs iOS Fréquentes
- **Erreur de chargement "cancelled"** : Si une liste ne charge pas en affichant "cancelled", cela signifie que la vue SwiftUI a été modifiée avant la fin de la requête réseau. Vérifiez que la fonction de chargement utilise un contexte persistant (comme `.onAppear { Task { ... } }`).
- **Simulateur : "Application failed preflight checks"** : Ce blocage Xcode arrive souvent sur les processeurs de Mac (Silicon/Intel). Corrigé en effaçant les données du simulateur : *Simulator > Device > Erase All Content and Settings*.

### Compilation de l'App Store / Testflight
Assurez-vous d'ouvrir `CardEvent.xcworkspace` (et non `.xcodeproj` si vous utilisez des pods/dépendances majeures) et d'utiliser une équipe de développement ('Team') valide dans l'onglet **Signing & Capabilities** de la cible `CardEvent`. L'identifiant du Bundle (ex: `com...cardevent`) doit correspondre côté serveur (pour les notifications APNS éventuelles `api/apns_config.php`).
