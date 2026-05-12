# Architecture technique — Bataille Navale

## Pourquoi cette restructuration ?

Le projet fonctionnait, mais tout le code (SQL, logique metier, authentification, affichage) etait melange dans les 39 fichiers PHP du dossier `public/`. Cela posait trois problemes concrets :

1. **Code duplique** — La meme fonction `groupShipCells()` (40 lignes) etait copiee-collee dans `get_shots.php` et `resolve_turn.php`. Si on corrige un bug dans l'une, il faut penser a corriger l'autre. En pratique, on oublie.
2. **Difficulte de maintenance** — Pour modifier la logique de victoire, il fallait chercher dans 3 fichiers differents (`resolve_turn.php`, `quit_game.php`, `get_shots.php`) sans savoir lequel contenait la version "correcte".
3. **Fichiers illisibles** — `play.php` faisait 1900 lignes : du PHP, du HTML, du CSS et 950 lignes de JavaScript melanges dans un seul fichier.

---

## Le pattern choisi : Repository / Service / Middleware

Ce n'est pas un framework. C'est une organisation du code en couches, ou chaque couche a un role precis.

```
public/play.php          ← Point d'entree : recoit la requete, appelle les couches, affiche le HTML
       │
       ├── Middleware     ← Verifie que l'utilisateur est connecte
       ├── Repository     ← Lit/ecrit dans la base de donnees
       ├── Service        ← Contient la logique metier (regles du jeu, calculs XP)
       └── config/        ← Constantes, connexion BDD
```

### Repository — Acces aux donnees

**Role** : Encapsuler toutes les requetes SQL sur une table donnee.

**Avant** (dans 20+ fichiers) :
```php
$stmt = $pdo->prepare("SELECT * FROM games WHERE id_Game=?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();
```

**Apres** (un seul appel) :
```php
$game = $gameRepo->findById($gameId);
```

**Pourquoi ?**
- Si la structure de la table `games` change (renommage de colonne, ajout d'un champ), on modifie **un seul fichier** (`GameRepository.php`) au lieu de 20.
- Les requetes SQL sont testables independamment du reste.
- On evite les erreurs de copier-coller (oubli d'un champ, mauvais nom de colonne).

**Repositories crees :**

| Classe | Table(s) | Methodes principales |
|--------|----------|----------------------|
| `GameRepository` | `games`, `game_players` | `findById()`, `getStatus()`, `updateStatus()`, `getAlivePlayerIds()`, `setPlayerStatus()` |
| `BoardRepository` | `player_boards` | `getBoard()`, `getAllBoards()`, `saveBoard()`, `countValidated()` |
| `FriendRepository` | `friends` | `relationExists()`, `sendRequest()`, `getAcceptedFriends()` |
| `SkinRepository` | `skin_themes`, `skin_purchases`, `skin_active` | `getAllThemes()`, `purchase()`, `equip()`, `getActiveThemes()` |

### Service — Logique metier

**Role** : Contenir les regles du jeu et les calculs qui ne sont pas du SQL.

**Exemple concret — detection de victoire :**

**Avant** (duplique dans `resolve_turn.php` et `quit_game.php`) :
```php
// 15 lignes de code pour verifier les survivants, gerer les equipes, determiner le gagnant...
$survivors = $pdo->prepare("SELECT id_player FROM game_players WHERE id_game=? AND player_status='in_game'")->...;
if (count($survivors) === 1) { ... }
// etc.
```

**Apres** :
```php
$result = GameLogicService::checkVictory($survivors);
if ($result['finished']) {
    // La logique est centralisee, on ne peut pas avoir de divergence
}
```

**Services crees :**

| Classe | Responsabilite |
|--------|---------------|
| `GameLogicService` | Regroupement des cellules de navires, validation du placement (regles FR/BE), detection de victoire |
| `RewardService` | Calcul XP/Gold, montee de niveau, generation du recap de fin de partie |

### Middleware — Authentification

**Role** : Verifier que l'utilisateur est connecte avant d'executer le reste du code.

**Avant** (repete dans 30+ fichiers) :
```php
session_start();
if (empty($_SESSION['uid'])) {
    header("Location: login.php");
    exit;
}
```

**Apres** :
```php
AuthMiddleware::requireAuth();       // Redirige vers login.php
// ou
AuthMiddleware::requireAuthJson();   // Renvoie {"error": "..."} pour les endpoints AJAX
```

**Pourquoi deux methodes ?**
Les pages HTML (play.php, game.php) doivent rediriger le navigateur. Les endpoints AJAX (shoot.php, resolve_turn.php) doivent renvoyer du JSON. Un seul middleware gere les deux cas.

---

## Separation JS / PHP

**Probleme** : `play.php` contenait 950 lignes de JavaScript inline dans une balise `<script>`. Ce JS utilisait des variables PHP injectees directement (`<?= $gameId ?>`, `<?= $taille ?>`).

**Solution** : Extraire le JS dans des fichiers `.js` externes et passer les variables PHP via un objet `GAME_CONFIG`.

```php
<!-- Dans play.php -->
<script>
    const GAME_CONFIG = {
        gameId:   <?= (int)$gameId ?>,
        gridSize: <?= (int)$taille ?>,
        myId:     <?= (int)$myId ?>
    };
</script>
<script src="assets/js/play.js"></script>
```

```javascript
// Dans play.js — pas de PHP, fichier JS pur
const gameId = GAME_CONFIG.gameId;
```

**Avantages :**
- Le fichier JS est mis en **cache par le navigateur** — il n'est pas retelecharge a chaque chargement de page, contrairement au JS inline qui est regenre a chaque requete PHP.
- On peut lire et modifier le JS independamment du PHP.
- Les editeurs de code offrent l'autocompletion et le linting pour les fichiers `.js` (pas pour du JS dans une balise `<script>` PHP).

| Vue PHP | Fichier JS | Lignes extraites |
|---------|-----------|-----------------|
| `play.php` | `assets/js/play.js` | ~580 |
| `place_ships_view.php` | `assets/js/place_ships.js` | ~290 |
| `game.php` | `assets/js/lobby.js` | ~120 |
| `list_games.php` | `assets/js/list_games.js` | ~100 |

---

## Centralisation des constantes

**Probleme** : La duree d'un tour (`7 secondes`) etait definie dans deux fichiers differents sous deux noms differents :
- `$TURN_DURATION = 7` dans `play.php`
- `const ROUND_DURATION = 7` dans `resolve_turn.php`

Si on veut passer a 10 secondes, il faut penser a modifier les deux. Si on n'en modifie qu'un, le timer JS et la resolution serveur sont desynchronises.

**Solution** : `config/constants.php` definit toutes les constantes en un seul endroit.

```php
const ROUND_DURATION    = 7;
const TYPE_BATTLEROYALE = 1;
const TYPE_TEAM         = 2;
const TYPE_SOLO         = 3;
const PUBLIC_MODE_ID    = 1;
```

---

## Principes appliques

| Principe | Application concrete |
|----------|---------------------|
| **DRY** (Don't Repeat Yourself) | `groupShipCells()` ecrite une seule fois au lieu de deux. Recap XP centralise au lieu d'etre copie dans 3 fichiers. |
| **SRP** (Single Responsibility Principle) | Un Repository ne fait que du SQL. Un Service ne fait que de la logique. Un Middleware ne fait que de l'auth. |
| **Separation des preoccupations** | PHP genere le HTML et les donnees. JS gere l'interactivite. Ils communiquent via `GAME_CONFIG`. |
| **Convention over configuration** | L'autoload PSR-4 de Composer (`App\` → `src/`) permet d'ajouter une classe sans toucher a la configuration. |

---

## Structure finale du projet

```
04.GAME_BN/
├── config/
│   ├── db.php              ← Connexion PDO
│   ├── constants.php       ← Constantes globales (ROUND_DURATION, types de partie)
│   └── xp.php              ← (ancien, remplace par RewardService)
├── src/
│   ├── Entity/             ← Objets metier (User, Avatar)
│   ├── Repository/         ← Acces base de donnees
│   │   ├── GameRepository.php
│   │   ├── BoardRepository.php
│   │   ├── FriendRepository.php
│   │   └── SkinRepository.php
│   ├── Service/            ← Logique metier
│   │   ├── GameLogicService.php
│   │   └── RewardService.php
│   └── Middleware/         ← Controles d'acces
│       └── AuthMiddleware.php
├── public/                 ← Points d'entree (1 fichier = 1 page ou 1 endpoint)
│   ├── assets/
│   │   ├── css/
│   │   └── js/
│   │       ├── play.js
│   │       ├── place_ships.js
│   │       ├── lobby.js
│   │       └── list_games.js
│   ├── play.php
│   ├── game.php
│   ├── resolve_turn.php
│   └── ...
└── vendor/                 ← Autoload Composer (PSR-4)
```
