# Timer (iOS SwiftUI)

Application iOS Swift inspirée de `/newtimer/index.php` (cardevent de blinds poker).

## Fonctionnalités incluses

- Compte à rebours par niveau
- Start / Pause
- Reset tournoi
- Reprise du niveau courant
- Ajustement du temps (-1 min / +1 min)
- Navigation niveau précédent / suivant (à l'arrêt)
- Affichage blindes actuelles, ante, et prochaine blinde
- Édition de la structure des blinds
- Persistance locale (`UserDefaults`) de :
  - structure des niveaux
  - état du cardevent (niveau courant, temps restant, running)

## Structure

- `Timer/TimerApp.swift`
- `Timer/Models/BlindLevel.swift`
- `Timer/ViewModels/PokerTimerViewModel.swift`
- `Timer/Views/ContentView.swift`
- `Timer/Views/EditStructureView.swift`

## Ouvrir dans Xcode

1. Crée un nouveau projet **iOS App** dans Xcode nommé **Timer** (SwiftUI).
2. Remplace les fichiers Swift générés par ceux du dossier `cardevent/Timer`.
3. Lance sur simulateur iPhone.

> Ce template reprend la logique cardevent/blinds. Les parties PHP/MySQL/WebSocket de `index.php` ne sont pas portées ici.
