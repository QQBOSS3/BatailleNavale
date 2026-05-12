# Documentation Technique - Bataille Navale

---

## 1. Stack technique

| Couche | Technologie | Details |
|--------|------------|---------|
| **Langage backend** | PHP 8+ (vanilla, sans framework) | Pas de Symfony/Laravel, tout est fait a la main |
| **Base de donnees** | MariaDB / MySQL | Hebergee sur Hostinger (IP 72.60.185.73) |
| **Acces BDD** | PDO | Prepared statements, mode exception, fetch ASSOC, connexion persistante |
| **Frontend** | HTML5 / CSS3 / JavaScript vanilla | Aucune librairie (pas de jQuery, React, etc.) |
| **Temps reel** | AJAX Polling (fetch API) | Pas de WebSocket - polling a intervalles reguliers |
| **Autoload** | Composer PSR-4 | Namespace `App\` mappe vers `src/` |
| **Hebergement** | Hostinger | Serveur mutualisee |
| **Design** | Theme nautique / steampunk | Palette cuivre (brass), ocean, pixel art |
| **Police** | Pixelbasel.ttf | Police pixel personnalisee |
| **Son** | Audio HTML5 | 7 pistes mp3 en boucle, volume BDD |

---

## 2. Architecture du projet

```
BN/
|-- composer.json              # Autoload PSR-4 : App\ -> src/
|-- index.html                 # Page d'entree statique (redirige vers public/)
|-- config/
|   |-- db.php                 # Connexion PDO (MariaDB)
|   |-- xp.php                 # Constantes XP/Gold + fonctions de progression
|   |-- constants.php          # Constantes globales (ROUND_DURATION, types de partie, modes)
|
|-- src/                       # Code metier (MVC leger)
|   |-- Entity/
|   |   |-- User.php           # Entite utilisateur (id, email, pseudo, mdp, avatar, niveau, xp, gold)
|   |   |-- Avatar.php         # Entite avatar (id, name, mimeType, data BLOB)
|   |-- Middleware/
|   |   |-- AuthMiddleware.php # Controle d'acces (requireAuth, requireAuthJson, suivi activite)
|   |-- Repository/
|   |   |-- UserRepository.php    # CRUD utilisateurs (findByEmail, create, delete)
|   |   |-- AvatarRepository.php  # Lecture avatars (findAll, findById)
|   |   |-- BoardRepository.php   # Plateaux de jeu (getBoard, getAllBoards, saveBoard, countValidated)
|   |   |-- FriendRepository.php  # Relations amis (sendRequest, getAcceptedFriends, updateStatus)
|   |   |-- GameRepository.php    # Parties (findById, getStatus, addPlayer, getSurvivors, etc.)
|   |   |-- SkinRepository.php    # Skins (getAllThemes, purchase, equip, unequip, getActiveThemes)
|   |-- Service/
|       |-- AuthService.php       # Authentification (login, verification hash)
|       |-- FlashService.php      # Messages flash en session
|       |-- GameLogicService.php  # Logique de jeu (flood-fill, validation placement, victoire)
|       |-- RewardService.php     # Systeme XP/Gold/niveaux (version OOP de grantXp)
|
|-- public/                    # Point d'entree web (DocumentRoot)
|   |-- index.php              # Page d'accueil / hub principal
|   |-- login.php              # Connexion
|   |-- register.php           # Inscription
|   |-- logout.php             # Deconnexion
|   |-- delete_account.php     # Suppression de compte
|   |-- update_info.php        # Modification profil
|   |-- update_options.php     # Sauvegarde options (volume, langue, theme)
|   |-- update_avatar.php      # Changement d'avatar
|   |-- get_avatar.php         # Sert une image avatar (binaire)
|   |-- avatars.php            # Galerie d'avatars
|   |-- create_game.php        # Creation de partie
|   |-- game.php               # Lobby / salle d'attente
|   |-- list_games.php         # Liste des parties (interface sonar)
|   |-- join_game.php          # Rejoindre une partie
|   |-- leave_game.php         # Quitter le lobby
|   |-- kick_player.php        # Expulser un joueur (createur)
|   |-- start_game.php         # Lancer la partie (createur)
|   |-- place_ships_view.php   # Interface de placement des navires
|   |-- place_ships.php        # Validation serveur du placement
|   |-- check_ready.php        # Polling : tous les joueurs ont place ?
|   |-- wait_for_players.php   # Page d'attente entre placement et combat
|   |-- play.php               # Interface de combat
|   |-- shoot.php              # Tirer sur une case
|   |-- resolve_turn.php       # Resolution de tour (hits/miss/sunk/winner)
|   |-- get_shots.php          # Etat complet du jeu (JSON)
|   |-- check_game_status.php  # Polling : statut du lobby
|   |-- check_status.php       # Verif presence joueur
|   |-- quit_game.php          # Abandon en cours de partie
|   |-- shop.php               # Boutique de skins
|   |-- get_friends.php        # Liste d'amis (HTML fragment)
|   |-- search_friend.php      # Recherche de joueur
|   |-- send_friend_request.php # Envoyer demande d'ami
|   |-- validate_friend.php    # Accepter/refuser demande
|   |-- invite_to_game.php     # Inviter un ami en partie
|   |-- get_game_invites.php   # Invitations recues
|   |-- respond_game_invite.php # Repondre a une invitation
|   |-- accept_invite.php      # Accepter une invitation (status invited -> in_game)
|   |-- get_players.php        # Liste joueurs du lobby (HTML)
|   |-- credits.php            # Page credits
|   |
|   |-- assets/
|       |-- css/style.css      # Feuille de style unique (~1930 lignes)
|       |-- js/
|       |   |-- app.js         # JavaScript global (235 lignes)
|       |   |-- lobby.js       # JS du lobby (144 lignes)
|       |   |-- place_ships.js # JS du placement de navires (336 lignes)
|       |   |-- play.js        # JS du combat (908 lignes)
|       |   |-- list_games.js  # JS du sonar (110 lignes)
|       |-- fonts/Pixelbasel.ttf
|       |-- sound/             # 7 musiques de fond (mp3)
|       |-- img/
|           |-- Avatar/        # 54 images (9 avatars x 6 variantes)
|           |-- Fond/          # Fonds d'ecran thematiques
|           |-- button/        # Boutons thematiques (5 dossiers)
|           |-- ship/          # Sprites navires (6 dossiers)
|           |-- game/          # Backgrounds de combat
|           |-- lobby/         # Backgrounds de lobby
|           |-- skin/          # Backgrounds de boutique
```

---

## 3. Base de donnees - Tables

### 3.1 Gestion des utilisateurs

#### `users`
| Colonne | Type | Description |
|---------|------|-------------|
| ID_Users | INT, PK, AUTO_INCREMENT | Identifiant unique |
| Email | VARCHAR | Email (unique) |
| Password | VARCHAR | Hash BCRYPT ou ARGON2ID |
| Pseudo | VARCHAR | Nom d'affichage (min 3 caracteres) |
| BirthDay | DATE | Date de naissance |
| Avatar | INT | ID de l'avatar choisi (1-9) |
| niveau | INT | Niveau actuel (defaut 1) |
| xp | INT | XP actuel dans le niveau |
| Gold | INT | Monnaie virtuelle |
| Online | TINYINT | 0=hors ligne, 1=en ligne |
| Created_At | TIMESTAMP | Date de creation |
| active_theme | INT | Theme actif (legacy) |

#### `avatar`
| Colonne | Type | Description |
|---------|------|-------------|
| ID_Avatar | INT, PK | Identifiant |
| Avatar | BLOB | Image binaire |
| Name | VARCHAR | Nom de l'avatar |
| mime_type | VARCHAR | Type MIME (image/png, image/jpeg) |

#### `option`
| Colonne | Type | Description |
|---------|------|-------------|
| ID_Option | INT, PK, AUTO_INCREMENT | Identifiant |
| Volume | INT | Volume sonore 0-100 |
| Languages | VARCHAR | Langue (fr, en) |
| Colorblind | TINYINT | Mode daltonien (0/1) |
| Theme | VARCHAR | Theme UI (normal, noel, halloween, ete) |
| ID_Users | INT, FK | Reference vers users |

#### `ratio`
| Colonne | Type | Description |
|---------|------|-------------|
| ID_Profil | INT, PK | = ID_Users |
| Win | INT | Nombre de victoires |
| Defeat | INT | Nombre de defaites |
| Game_Played | INT | Total de parties jouees |

### 3.2 Systeme de jeu

#### `games`
| Colonne | Type | Description |
|---------|------|-------------|
| id_Game | INT, PK, AUTO_INCREMENT | Identifiant de la partie |
| id_game_mode | INT, FK | Public (1) ou Prive (2) |
| id_game_type | INT, FK | BattleRoyal (1), Team (2), Solo (3) |
| id_team_mode | INT, FK | Taille d'equipe (1=1v1, 2=2v2, 3=3v3, 4=4v4) |
| id_version | INT, FK | Regles : Francaise (1) ou Belge (2) |
| created_at | TIMESTAMP | Date de creation |
| status | ENUM | preparation, placement, in_progress, finished |
| winner_id | INT, FK | ID du gagnant (NULL si en cours) |
| id_creator | INT, FK | Capitaine / createur de la partie |
| current_round | INT | Numero du tour actuel |
| taille_grille | INT | Taille du plateau (5-25, defaut 10) |
| last_turn_timestamp | INT | Timestamp UNIX du dernier tour |

#### `game_players`
| Colonne | Type | Description |
|---------|------|-------------|
| id_GP | INT, PK, AUTO_INCREMENT | Identifiant |
| id_game | INT, FK | Reference vers games |
| id_player | INT, FK | Reference vers users |
| team_number | INT, NULLABLE | Equipe (1, 2, 3, 4) ou NULL (BR) |
| player_status | VARCHAR | in_game, dead, left, invited |

#### `player_boards`
| Colonne | Type | Description |
|---------|------|-------------|
| id | INT, PK, AUTO_INCREMENT | Identifiant |
| game_id | INT, FK | Reference vers games |
| player_id | INT, FK | Reference vers users |
| board_json | TEXT | Grille 2D en JSON (0=vide, >0=navire) |
| validated | TINYINT | 1 si le joueur a confirme son placement |

**Format board_json** (exemple grille 10x10) :
```json
[
  [0,0,0,1,1,1,1,1,0,0],
  [0,0,0,0,0,0,0,0,0,0],
  [0,2,0,0,0,0,0,0,0,0],
  [0,2,0,0,0,4,4,4,0,0],
  [0,2,0,0,0,0,0,0,0,0],
  [0,2,0,0,0,0,0,0,0,0],
  [0,0,0,0,3,3,3,0,0,0],
  [0,0,0,0,0,0,0,0,0,0],
  [0,0,0,0,0,0,5,5,0,0],
  [0,0,0,0,0,0,0,0,0,0]
]
```
Chaque chiffre >0 est un ID de navire unique. Les cellules adjacentes avec le meme ID forment un navire.

#### `shots`
| Colonne | Type | Description |
|---------|------|-------------|
| id_shot | INT, PK, AUTO_INCREMENT | Identifiant |
| id_game | INT, FK | Reference vers games |
| id_player | INT, FK | Tireur |
| target_id | INT, FK | Cible |
| target_x | INT | Coordonnee X (0 a taille-1) |
| target_y | INT | Coordonnee Y (0 a taille-1) |
| result | VARCHAR, NULLABLE | NULL (en attente), hit, miss, sunk |
| state | VARCHAR | pending, resolved |
| created_at | TIMESTAMP | Horodatage du tir |
| turn | INT | Numero du tour |

### 3.3 Tables de configuration

#### `mode`
| id_Mode | name |
|---------|------|
| 1 | Public |
| 2 | Private |

#### `type`
| id_Type | name |
|---------|------|
| 1 | BattleRoyal |
| 2 | Team |
| 3 | Solo |

#### `team_mode`
| id_Team | team_size |
|---------|-----------|
| 1 | 1 (= 1 vs 1) |
| 2 | 2 (= 2 vs 2) |
| 3 | 3 (= 3 vs 3) |
| 4 | 4 (= 4 vs 4) |

#### `version`
| id_Version | name |
|------------|------|
| 1 | Francaise (espacement obligatoire) |
| 2 | Belge (navires collables) |

#### `rules`
| Colonne | Description |
|---------|-------------|
| ID_Rules | PK |
| French | Texte des regles francaises (FR) |
| Belgium | Texte des regles belges (FR) |
| BattleRoyal | Texte BR (FR) |
| Team | Texte equipe (FR) |
| French_en, Belgium_en, BattleRoyal_en, Team_en | Versions anglaises |

### 3.4 Social

#### `friends`
| Colonne | Type | Description |
|---------|------|-------------|
| ID_Friends | INT, PK | Identifiant |
| Sender_ID | INT, FK | Demandeur |
| Receiver_ID | INT, FK | Destinataire |
| Status | VARCHAR | Pending, Accepted, Rejected |

#### `game_invites`
| Colonne | Type | Description |
|---------|------|-------------|
| ID | INT, PK | Identifiant |
| id_game | INT, FK | Partie concernee |
| sender_id | INT, FK | Inviteur |
| receiver_id | INT, FK | Invite |
| status | VARCHAR | pending, accepted, rejected |
| created_at | TIMESTAMP | Date |

### 3.5 Boutique / Cosmetiques

#### `skin_themes`
| Colonne | Type | Description |
|---------|------|-------------|
| id | INT, PK | Identifiant |
| category | ENUM('avatar','bateau','fond') | Categorie du skin |
| name | VARCHAR | Nom affiche (ex: "Cosmique") |
| price | INT | Prix en Gold |
| folder_name | VARCHAR | Dossier des assets (ex: "cosmique") |
| image_prefix | VARCHAR | Suffixe des fichiers (ex: "cosmique") |

#### `skin_purchases`
| Colonne | Type | Description |
|---------|------|-------------|
| id | INT, PK | Identifiant |
| id_user | INT, FK | Acheteur |
| id_theme | INT, FK | Theme achete |
| purchased_at | TIMESTAMP | Date d'achat |

#### `skin_active`
| Colonne | Type | Description |
|---------|------|-------------|
| id_user | INT, PK (composite) | Utilisateur |
| category | VARCHAR, PK (composite) | Categorie (avatar/bateau/fond) |
| id_theme | INT, FK | Theme actif pour cette categorie |

> Cle primaire composite (id_user, category) = un seul skin actif par categorie par joueur.

### 3.6 Divers

#### `traductions`
| Colonne | Description |
|---------|-------------|
| id | PK |
| lang | Langue (fr, en) |
| key | Cle de traduction |
| value | Texte traduit |

#### `update`
| Colonne | Description |
|---------|-------------|
| ID_Update | PK |
| Last_version | Version precedente |
| New_version | Notes de mise a jour (EN) |
| New_version_fr | Notes de mise a jour (FR) |

#### `credit`
| Colonne | Description |
|---------|-------------|
| ID_Credit | PK |
| Credit | Texte des credits |

#### `shop_items` / `user_purchases`
Tables legacy pour un ancien systeme de boutique (non utilise activement).

---

## 4. Configuration

### 4.1 config/db.php - Connexion BDD

```php
date_default_timezone_set('Europe/Paris');

$dsn = "mysql:host=72.60.185.73;dbname=BatailleNavale;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => true,
];
$pdo = new PDO($dsn, $user, $pass, $options);
```

**Points cles :**
- Connexion **persistante** (reutilisee entre requetes)
- Charset **utf8mb4** (support emoji)
- **Prepared statements natifs** (pas d'emulation)
- Toute erreur PDO lance une **exception**

### 4.2 config/constants.php - Constantes globales

```php
const ROUND_DURATION    = 7;   // Duree d'un tour en secondes
const TYPE_BATTLEROYALE = 1;
const TYPE_TEAM         = 2;
const TYPE_SOLO         = 3;
const PUBLIC_MODE_ID    = 1;
```

Ce fichier centralise les constantes partagees entre plusieurs endpoints (resolve_turn, shoot, create_game, etc.).

### 4.3 config/xp.php - Systeme de progression

**Constantes :**
| Constante | Valeur | Description |
|-----------|--------|-------------|
| XP_WIN | 50 | XP gagne en cas de victoire |
| XP_LOSE | 25 | XP gagne en cas de defaite |
| GOLD_WIN | 100 | Or gagne en victoire |
| GOLD_LOSE | 25 | Or gagne en defaite |
| GOLD_LEVEL_UP | 200 | Bonus or a chaque montee de niveau |

**Formule de niveau :**
```
XP requis pour niveau n -> n+1 = floor(100 * 1.02^(n-1))
```

| Niveau | XP requis |
|--------|-----------|
| 1 -> 2 | 100 |
| 5 -> 6 | 108 |
| 10 -> 11 | 119 |
| 20 -> 21 | 148 |
| 50 -> 51 | 261 |

**Fonction `xpRequiredForLevel(int $level): int`**
- Retourne l'XP necessaire pour passer du niveau `$level` au suivant.

**Fonction `grantXp(PDO $pdo, int $playerId, int $xpGain, int $goldGain, bool $isWin): array`**
- Ajoute XP et Gold au joueur
- Gere les **montees de niveau multiples** (boucle while)
- Chaque level-up accorde +200 Gold bonus
- Met a jour la table `ratio` (Win/Defeat/Game_Played)
- Retourne : `['niveau', 'xp', 'gold', 'leveled_up']`

---

## 5. Backend PHP - Endpoints par domaine

### 5.1 Authentification

#### `login.php` - Connexion
| | |
|---|---|
| **Type** | Page + Form Handler |
| **Methode** | GET (affiche formulaire) / POST (traite connexion) |
| **Validation** | email + password via `AuthService::login()` (password_verify) |
| **Actions** | Regenere session ID, stocke `$_SESSION['uid']`, met Online=1 en BDD |
| **Redirect** | -> index.php (succes) ou erreur flash |

#### `register.php` - Inscription
| | |
|---|---|
| **Type** | Page + Form Handler |
| **Methode** | GET / POST |
| **Validation** | Email (filter_var), Pseudo (min 3 car.), MDP (12+ car., 1 maj, 1 min, 1 chiffre, 1 special), date naissance, avatar obligatoire |
| **Actions** | Hash BCRYPT via `UserRepository::create()`, INSERT users |
| **Redirect** | -> index.php |

#### `logout.php` - Deconnexion
| | |
|---|---|
| **Type** | Action (redirect) |
| **Actions** | Met Online=0, detruit session + cookies |
| **Redirect** | -> login.php |

#### `delete_account.php` - Suppression de compte
| | |
|---|---|
| **Type** | Action (redirect) |
| **Actions** | Suppression en cascade : shots -> player_boards -> game_players -> users |
| **Methode** | POST |

### 5.2 Profil & Options

#### `update_info.php` - Modification de profil
| | |
|---|---|
| **Type** | Page + Form Handler |
| **Methode** | GET / POST |
| **Champs** | Email, Pseudo, Password (optionnel) |
| **Hash** | ARGON2ID pour le nouveau mot de passe |

#### `update_options.php` - Sauvegarde des parametres
| | |
|---|---|
| **Type** | API (texte) |
| **Methode** | POST |
| **Parametres** | volume (0-100), languages (fr/en), colorblind (0/1), theme (normal/noel/halloween/ete) |
| **SQL** | INSERT ou UPDATE dans table `option` |

#### `update_avatar.php` - Changement d'avatar
| | |
|---|---|
| **Type** | API (texte) |
| **Methode** | POST |
| **Parametre** | avatar_id |
| **Action** | UPDATE users SET Avatar |

#### `get_avatar.php` - Serveur d'image avatar
| | |
|---|---|
| **Type** | Image binaire |
| **Methode** | GET (?id=X) |
| **Reponse** | Content-Type dynamique + donnees BLOB |

### 5.3 Creation & Lobby

#### `create_game.php` - Creation de partie
| | |
|---|---|
| **Type** | Action (redirect) |
| **Methode** | GET |
| **Parametres** | mode (public/private), type (1vs1/2vs2/3vs3/4vs4/br), locale (fr/be), size (5-25) |
| **Mapping** | Traduit les parametres frontend en IDs BDD (mode, type, team_mode) |
| **SQL** | INSERT games + INSERT game_players (createur, equipe 1) |
| **Redirect** | -> game.php?id=X |

**Mapping des types :**
```
'1vs1' -> type=Solo(3),    team_mode=1
'2vs2' -> type=Team(2),    team_mode=2
'3vs3' -> type=Team(2),    team_mode=3
'4vs4' -> type=Team(2),    team_mode=4
'br'   -> type=BattleRoyal(1), team_mode=NULL
```

#### `game.php` - Lobby / Salle d'attente
| | |
|---|---|
| **Type** | Page (HTML + JS avec auto-refresh) |
| **Methode** | GET (?id=X) |
| **Affichage** | Capitaine (couronne, avatar 120px) + Equipage (grille de cartes) |
| **Polling** | check_game_status.php toutes les 2 secondes |
| **Actions JS** | launchGame(), quitGame(), openSidebar(), inviteFriend() |
| **Redirect auto** | Vers place_ships_view.php quand status passe a 'placement' |

#### `list_games.php` - Liste des parties (Sonar)
| | |
|---|---|
| **Type** | Page (HTML + JS) |
| **Methode** | GET (normal) / GET (?ajax=1 pour refresh partiel) |
| **Interface** | Radar sonar circulaire avec balayage rotatif (6s) |
| **Affichage** | Parties = "blips" positionnes en angle dore (137.508 degres) |
| **Interaction** | Clic sur blip = carte de la partie, bouton "Rejoindre" |
| **Refresh** | Auto toutes les 5 secondes via AJAX |

#### `join_game.php` - Rejoindre une partie
| | |
|---|---|
| **Type** | Action (redirect) |
| **Methode** | GET (?id=X) |
| **Verifications** | Partie en 'preparation', pas deja dedans, pas pleine |
| **Equipe auto** | Attribution automatique a l'equipe la moins remplie |
| **Redirect** | -> game.php?id=X |

#### `leave_game.php` - Quitter le lobby
| | |
|---|---|
| **Type** | Action (redirect) |
| **Logique** | Si createur : supprime la partie entiere. Sinon : DELETE game_players |

#### `kick_player.php` - Expulser un joueur
| | |
|---|---|
| **Type** | Action (redirect) |
| **Securite** | Seul le createur peut kick |
| **Action** | UPDATE game_players SET player_status='left' |

#### `start_game.php` - Lancer la partie
| | |
|---|---|
| **Type** | API (JSON) |
| **Methode** | POST |
| **Securite** | Seul le createur |
| **Verifications** | Nombre minimum de joueurs (1v1=2, 2v2=4, etc.) |
| **Action** | UPDATE games SET status='placement' |

### 5.4 Placement des navires

#### `place_ships_view.php` - Interface de placement
| | |
|---|---|
| **Type** | Page (HTML + JS complexe) |
| **Methode** | GET (?id=X) |
| **Grille** | Taille dynamique depuis games.taille_grille |
| **Flotte** | 5 navires : taille 5, 4, 3.1, 3.2, 2 |
| **Regles** | FR = espacement obligatoire (adjacence interdite) / BE = navires collables |
| **Interactions** | Clic pour placer, R pour tourner, placement aleatoire, timer 60s |
| **Skins** | Images de navires thematiques (cosmique, enfer, etc.) |
| **Soumission** | POST JSON vers place_ships.php |
| **Polling** | check_ready.php toutes les 2 secondes |

#### `place_ships.php` - Validation serveur
| | |
|---|---|
| **Type** | API (JSON) |
| **Methode** | POST (Content-Type: application/json) |
| **Payload** | `{ game_id, ships: [[...], [...], ...] }` (grille 2D) |
| **Validation serveur** | Verifie regles FR/BE (diagonales, adjacences) |
| **SQL** | INSERT/UPDATE player_boards (board_json, validated=1) |
| **Transition** | Si tous les joueurs ont valide -> UPDATE games status='in_progress' |
| **Reponse** | `{ success, message, game_started }` |

#### `check_ready.php` - Tous prets ?
| | |
|---|---|
| **Type** | API (JSON) |
| **Methode** | GET (?id=X) |
| **Logique** | Compare nb de boards valides vs nb de joueurs actifs |
| **Transition** | Si pret -> UPDATE games status='in_progress', UPDATE game_players status='in_game' |
| **Reponse** | `{ ready, total, readyCount, status }` |

#### `wait_for_players.php` - Attente apres placement
| | |
|---|---|
| **Type** | Page (HTML + JS) |
| **Methode** | GET (?id=X) |
| **Role** | Affichee apres que le joueur a valide son placement, en attendant les autres |
| **Polling** | check_ready.php toutes les 2 secondes |
| **Redirect auto** | Vers play.php quand tous les joueurs sont prets |

### 5.5 Combat

#### `play.php` - Interface de combat
| | |
|---|---|
| **Type** | Page (HTML + JS massif ~1000 lignes JS embarque) |
| **Methode** | GET (?id=X) |
| **Duree du tour** | 7 secondes (constante ROUND_DURATION dans config/constants.php) |
| **Composants UI** | Grilles 3D (perspective), minimap, journal de combat, timer, barre de stress |
| **Effets visuels** | Boulet de canon, explosions, ondulations, revelations de navires coules |
| **Polling** | get_shots.php toutes les 3 secondes |
| **Son** | Musique de fond + effets sonores (Web Audio API) |
| **Fin de partie** | Modal victoire/defaite avec recap XP/Gold anime |

#### `shoot.php` - Tirer
| | |
|---|---|
| **Type** | API (JSON) |
| **Methode** | POST |
| **Parametres** | game_id, x, y, target_id (optionnel) |
| **Validations** | Partie en cours, cible valide (pas soi, pas coequipier, pas mort), pas de doublon |
| **Action** | INSERT shots (result=NULL, state='pending') |
| **Reponse** | `{ success, message }` |

#### `resolve_turn.php` - Resolution de tour
| | |
|---|---|
| **Type** | API (JSON) |
| **Methode** | POST (JSON: `{ game_id }`) |
| **Declenchement** | Apres 7 secondes depuis last_turn_timestamp |
| **Processus** |
1. Recupere tous les tirs `state='pending'`
2. Pour chaque tir : compare (target_x, target_y) au board_json de la cible
3. Si cellule > 0 : result = 'hit'. Sinon : result = 'miss'
4. Detection de navire coule via **flood-fill orthogonal** : si toutes les cellules d'un navire sont touchees -> result = 'sunk'
5. Si tous les navires d'un joueur sont coules -> player_status = 'dead'
6. Si un seul joueur/equipe survit -> status = 'finished', winner_id = survivant
7. Distribution XP/Gold via `grantXp()`
8. Incremente current_round, met a jour last_turn_timestamp

| **Reponse** | `{ finished, message, wait }` |

**Algorithme flood-fill (groupShipCells)** :
```
1. Collecter toutes les cellules occupees (valeur > 0)
2. Pour chaque cellule non visitee, lancer un flood-fill orthogonal
3. Chaque composante connexe = un navire
4. Si toutes les cellules d'une composante sont dans les hits -> navire coule
```

#### `get_shots.php` - Etat complet du jeu
| | |
|---|---|
| **Type** | API (JSON) |
| **Methode** | GET (?game_id=X) |
| **Optimisation** | 4 requetes SQL seulement (au lieu de 10+) |
| **Requetes** | 1. games (status/winner) 2. shots (tous) 3. player_boards (tous) 4. game_players (status/skins) |
| **Calculs PHP** | Tri des tirs (my_shots, shots_on_me, all_shots), detection sunk via flood-fill, recap XP |
| **Reponse complete** | `{ success, finished, winner_id, winner_team, dead_players, my_board, my_shots, shots_on_me, all_shots, sunk_cells, sunk_ships, sunk_cells_me, sunk_ships_me, ship_skins, last_turn_timestamp, recap }` |

#### `quit_game.php` - Abandon
| | |
|---|---|
| **Type** | API (JSON) |
| **Methode** | POST (JSON: `{ game_id }`) |
| **Actions** | Met player_status='dead', distribue XP_LOSE + GOLD_LOSE, verifie fin de partie |
| **Reponse** | `{ success, recap }` |

### 5.6 Social

#### `send_friend_request.php` - Demande d'ami
| | |
|---|---|
| **Type** | Action (redirect) |
| **Methode** | POST (friend_id) |
| **Verifications** | Pas soi-meme, pas de relation existante |
| **Action** | INSERT friends (Status='Pending') |

#### `validate_friend.php` - Accepter/Refuser
| | |
|---|---|
| **Type** | Action (redirect) |
| **Methode** | POST (id_friends, action=accept/reject) |
| **Securite** | Seul le destinataire peut repondre |

#### `get_friends.php` - Liste d'amis
| | |
|---|---|
| **Type** | API (HTML fragment) |
| **Methode** | GET (?invite_mode=1&game_id=X optionnel) |
| **Affichage** | Pseudo + statut en ligne + bouton inviter si mode invitation |

#### `search_friend.php` - Recherche
| | |
|---|---|
| **Type** | API (HTML fragment) |
| **Methode** | POST (pseudo) |
| **Reponse** | HTML avec bouton "Ajouter" si pas deja ami |

### 5.7 Invitations

#### `invite_to_game.php` - Inviter en partie
| | |
|---|---|
| **Type** | API (texte) |
| **Methode** | POST (game_id, friend_id) |
| **Securite** | Seul le createur de la partie |

#### `get_game_invites.php` - Invitations recues
| | |
|---|---|
| **Type** | API (HTML fragment) |
| **Affichage** | Liste des invitations avec boutons accepter/refuser |

#### `respond_game_invite.php` - Repondre
| | |
|---|---|
| **Type** | Action (redirect) |
| **Methode** | POST (invite_id, action=accept/reject) |
| **Accept** | UPDATE invite + INSERT game_players |

#### `accept_invite.php` - Accepter une invitation directe
| | |
|---|---|
| **Type** | Action (redirect) |
| **Methode** | POST (invite_id) |
| **Logique** | Passe le player_status de 'invited' a 'in_game' dans game_players |
| **Redirect** | -> game.php?id=X |

### 5.8 Boutique

#### `shop.php` - Boutique de skins
| | |
|---|---|
| **Type** | Page + Form Handler |
| **Methode** | GET (affichage) / POST (action=buy/equip/unequip, theme_id) |
| **Achat** | Verifie Gold suffisant, deduit le prix, INSERT skin_purchases |
| **Equipement** | INSERT INTO skin_active ... ON DUPLICATE KEY UPDATE |
| **Desequipement** | DELETE FROM skin_active WHERE id_user AND category |
| **Affichage** | Groupe par categorie (avatar, fond, bateau) avec previsualisations |

---

## 6. Entites & Services (src/)

### 6.1 Entity/User.php
```php
namespace App\Entity;

class User {
    public function __construct(
        int $id, string $email, string $pseudo, string $password,
        ?int $avatarId, int $niveau, int $xp, int $gold, bool $online
    )
    // Getters : getId(), getEmail(), getPseudo(), getPassword(),
    //           getAvatarId(), getNiveau(), getXp(), getGold(), isOnline()
}
```

### 6.2 Entity/Avatar.php
```php
namespace App\Entity;

class Avatar {
    public function __construct(int $id, string $name, string $mimeType, string $data)
    // Getters : getId(), getName(), getMimeType(), getData()
}
```

### 6.3 Repository/UserRepository.php
| Methode | Description |
|---------|-------------|
| `findByEmail(string $email): ?User` | Recherche par email |
| `create(string $email, string $pseudo, string $password, string $birthDay): User` | Cree un compte (hash BCRYPT) |
| `deleteUser(int $userId): bool` | Suppression en cascade (shots, boards, game_players, users) |
| `hydrate(array $row): User` | Mappe une ligne BDD vers un objet User |

### 6.4 Repository/AvatarRepository.php
| Methode | Description |
|---------|-------------|
| `findAll(): array` | Tous les avatars |
| `findById(int $id): ?Avatar` | Un avatar par ID |

### 6.5 Middleware/AuthMiddleware.php

Remplace l'ancien `AuthService::requireLogin()` pour le controle d'acces. Gere aussi le suivi d'activite en base (champ `last_activity`).

| Methode | Description |
|---------|-------------|
| `static requireAuth(): void` | Verifie la session, redirige vers login.php si non connecte (pages HTML) |
| `static requireAuthJson(): void` | Verifie la session, renvoie une erreur JSON si non connecte (endpoints API) |
| `static getUserId(): int` | Retourne l'ID de l'utilisateur connecte |
| `private static touchActivity(): void` | Met a jour `last_activity` en BDD (max 1 fois par 30s) |

**Constante :** `ONLINE_THRESHOLD = 120` (2 minutes d'inactivite = hors ligne)

### 6.6 Service/AuthService.php
| Methode | Description |
|---------|-------------|
| `login(string $email, string $password): bool` | Verifie le hash, stocke uid en session, regenere le session ID |

### 6.7 Service/FlashService.php
| Methode | Description |
|---------|-------------|
| `static add(string $type, string $message): void` | Ajoute un message flash en session |
| `static getAll(): array` | Retourne et vide tous les messages flash |

### 6.8 Repository/BoardRepository.php
| Methode | Description |
|---------|-------------|
| `getBoard(int $gameId, int $playerId): array` | Plateau d'un joueur (board_json decode en tableau 2D) |
| `getAllBoards(int $gameId): array` | Tous les plateaux d'une partie, indexes par player_id |
| `saveBoard(int $gameId, int $playerId, array $board): void` | Sauvegarde ou met a jour le plateau (INSERT ... ON DUPLICATE KEY UPDATE) |
| `countValidated(int $gameId): int` | Nombre de plateaux valides pour une partie |

### 6.9 Repository/FriendRepository.php
| Methode | Description |
|---------|-------------|
| `relationExists(int $userId, int $friendId): bool` | Verifie si une relation existe (dans les deux sens) |
| `sendRequest(int $senderId, int $receiverId): void` | Insere une demande d'ami (Status='Pending') |
| `findRequestById(int $requestId): ?array` | Recupere une demande par son ID |
| `updateStatus(int $requestId, string $status): void` | Met a jour le statut d'une demande |
| `getAcceptedFriends(int $userId): array` | Liste des amis acceptes avec pseudo et statut en ligne |

### 6.10 Repository/GameRepository.php
| Methode | Description |
|---------|-------------|
| `findById(int $gameId): ?array` | Recupere une partie par son ID |
| `getStatus(int $gameId): ?string` | Statut d'une partie |
| `getCreatorId(int $gameId): ?int` | ID du createur |
| `updateStatus(int $gameId, string $status): void` | Met a jour le statut |
| `setFinished(int $gameId, ?int $winnerId): void` | Marque la partie comme terminee |
| `countActivePlayers(int $gameId): int` | Joueurs actifs (non partis) |
| `countPlayers(int $gameId): int` | Tous les joueurs |
| `isPlayerInGame(int $gameId, int $playerId): bool` | Verifie si un joueur est dans la partie |
| `getSurvivors(int $gameId): array` | Survivants avec leur equipe |
| `getAlivePlayerIds(int $gameId): array` | IDs des joueurs en vie |
| `setPlayerStatus(int $gameId, int $playerId, string $status): void` | Change le statut d'un joueur |
| `getPlayerTeam(int $gameId, int $playerId): ?int` | Equipe d'un joueur |
| `addPlayer(int $gameId, int $playerId, ?int $teamNumber, string $status): void` | Ajoute un joueur a la partie |
| `getTeamDistribution(int $gameId): array` | Distribution des equipes |
| `getPlayersWithInfo(int $gameId): array` | Joueurs avec pseudo, avatar et skin (pour le lobby) |

### 6.11 Repository/SkinRepository.php
| Methode | Description |
|---------|-------------|
| `getAllThemes(): array` | Tous les themes tries par categorie puis prix |
| `findThemeById(int $themeId): ?array` | Un theme par ID |
| `getOwnedThemeIds(int $userId): array` | IDs des themes achetes par un joueur |
| `purchase(int $userId, int $themeId, int $price): void` | Enregistre l'achat (deduit le Gold + INSERT skin_purchases) |
| `equip(int $userId, string $category, int $themeId): void` | Equipe un skin (INSERT ... ON DUPLICATE KEY UPDATE) |
| `unequip(int $userId, string $category): void` | Desequipe un skin (DELETE) |
| `getActiveThemes(int $userId): array` | Themes actifs indexes par categorie |

### 6.12 Service/GameLogicService.php

Regroupe toute la logique de jeu cote serveur.

| Methode | Description |
|---------|-------------|
| `static groupShipCells(array $board): array` | Flood-fill orthogonal : groupe les cellules occupees en navires (composantes connexes) |
| `static validatePlacementFrench(array $board): ?string` | Valide les regles francaises (diagonales + adjacences interdites). Retourne un message d'erreur ou null |
| `static checkVictory(array $survivors): array` | Determine si la partie est finie. Gere BR (team_number NULL) et equipes. Retourne `{finished, winner_id, winner_team, is_br}` |
| `static isPlayerDead(array $board, array $hitSet): bool` | Verifie si tous les navires d'un joueur sont coules |
| `static buildSunkShipInfo(array $cells): array` | Construit les infos d'un navire coule (orientation, taille, cellules triees) |

### 6.13 Service/RewardService.php

Version objet du systeme de recompenses. Utilisee par `resolve_turn.php` et `quit_game.php` a la place de la fonction procedurale `grantXp()` de `config/xp.php`.

**Constantes :** `XP_WIN=50`, `XP_LOSE=25`, `GOLD_WIN=100`, `GOLD_LOSE=25`, `GOLD_LEVEL_UP=200`

| Methode | Description |
|---------|-------------|
| `static xpRequiredForLevel(int $level): int` | XP necessaire pour passer au niveau suivant (meme formule que xp.php) |
| `grantXp(int $playerId, int $xpGain, int $goldGain, bool $isWin): array` | Ajoute XP/Gold, gere les level-ups multiples, met a jour le ratio. Retourne `{niveau, xp, gold, leveled_up}` |
| `buildRecap(int $playerId, bool $isWinner): array` | Construit le recap de fin de partie pour l'animation XP (pourcentages, bonus, etc.) |

---

## 7. Frontend - JavaScript

### 7.1 app.js (fichier global - 235 lignes)

**Gestion d'avatar :**
- `openAvatarMenu()` - Ouvre la modale avatar
- `closeAvatarMenu()` - Ferme la modale
- `changeAvatar(id)` - POST vers update_avatar.php

**Options :**
- `toggleOptionsMenu()` - Ouvre/ferme le panneau options
- `autoSaveOptions()` - Sauvegarde auto sur chaque changement (POST update_options.php)

**Popups :**
- `openUpdatePopup()` / `closeUpdatePopup()` - Notes de mise a jour
- `openRulesPopup()` / `closeRulesPopup()` - Regles du jeu
- `showRuleTab(tab)` - Onglets : fr, be, br, team

**Amis :**
- `openFriendsMenu()` / `closeFriendsMenu()` - Menu lateral amis
- `refreshFriendsList()` - **Polling 5s** via get_friends.php
- Formulaire recherche -> search_friend.php

**Invitations :**
- `refreshGameInvites()` - **Polling 5s** via get_game_invites.php

**Mode de jeu :**
- `openGamemodeModal()` / `closeGamemodeModal()` - Modale de selection
- `showStep(step)` - Navigation entre etapes (choix mode -> action)
- `selectMode(mode)` - Selectionne : br, solo, 2vs2, 3vs3, 4vs4, private
- `setLocale(locale)` - Choisit regles FR ou BE
- `doCreate()` - Redirige vers create_game.php avec les parametres
- `doJoin()` - Redirige vers list_games.php avec filtres

### 7.2 lobby.js (fichier externe - 144 lignes)

**Polling (2s) :** check_game_status.php
- Detecte changement de statut (preparation -> placement -> in_progress)
- Met a jour la liste des joueurs (capitaine + equipage) dynamiquement
- Redirect auto vers la bonne page selon le statut

**Fonctions :**
- `launchGame()` - Confirmation + POST start_game.php
- `openSidebar()` / `closeSidebar()` - Panel d'invitation
- `loadAllFriends()` - Charge la liste d'amis avec invite_mode
- `searchAndInvite()` - Recherche + invitation
- `inviteFriend(fid)` - POST invite_to_game.php
- `quitGame()` - POST leave_game.php
- `navalAlert(title, text)` - Modale personnalisee (alerte)
- `navalConfirm(title, text, actionLabel, onConfirm, style)` - Modale de confirmation

### 7.3 place_ships.js (fichier externe - 336 lignes)

**Polling (2s) :** check_ready.php -> redirect vers play.php quand pret

**Logique de placement :**
- `initPlacementLogic()` - Initialise la grille et les evenements
- Etat : fleet = `{2: 1, '3.1': 1, '3.2': 1, 4: 1, 5: 1}`, currentSize, orientation
- `handlePreview(x, y, show)` - Previsualisation du navire au survol
- `onCellClick(x, y)` - Placer ou retirer un navire
- `getShipCells(x, y, size, ori)` - Calcule les coordonnees des cellules
- `checkValid(cells)` - Valide le placement (pas de chevauchement, regles FR/BE)
- `placeShip(cells, size, ori)` - Ajoute le navire au board
- `removeShip(id)` - Retire un navire
- `randomPlacement()` - Placement aleatoire complet
- `isFleetComplete()` - Tous les navires sont places ?

**Interactions :**
- Touche R : rotation H/V
- Timer 60 secondes avec auto-completion

### 7.4 play.js (fichier externe - 908 lignes)

**Systeme de logging :**
- `addLog(html, type)` - Ajoute une entree au journal de combat
- `logShot(shooterId, targetId, x, y, result)` - Formate un message de tir
- `logSunk(shooterId, targetId, shipSize)` - Message navire coule
- `logDead(playerId)` - Message elimination
- `pName(id)` - Nom du joueur par ID

**Effets visuels :**
- `triggerCannonball(cell, isHit)` - Animation boulet de canon (chute 0.45s)
- `triggerRipple(cell, type)` - Ondulation sur miss (0.8s)
- `triggerHitExplosion(cell)` - Flash + particules sur hit
- `triggerSunkExplosion(cells)` - Explosion multi-cellules
- `flashTargeted(targetId)` - Pulse de la grille ciblee

**Tir :**
- `onShoot(x, y, cell, targetId)` - Handler principal de tir
  1. Ajoute classe 'aiming'
  2. POST shoot.php
  3. Declenche animation appropriee
  4. Met a jour l'etat de la cellule

**Gestion du tour :**
- `resolveTurn()` - Appelle resolve_turn.php
- `startTimer()` - Timer de 7 secondes
- `updateTimerDisplay()` - Mise a jour du compteur

**Minimap & Stress :**
- `updateMinimap(shotsOnMe)` - Met a jour la minimap avec les tirs recus
- `getStressLevel(hp, total)` - Calcule le niveau 0/1/2/3
- `updateStress(hp, total)` - Applique les animations de stress
  - Niveau 1 (50-25% HP) : bordure orange pulsante
  - Niveau 2 (25-10% HP) : bordure rouge intense
  - Niveau 3 (<10% HP) : battement cardiaque + vignette rouge
- `playHeartbeat(bpm)` - Son de battement cardiaque (1200ms -> 800ms)

**Rafraichissement (3s) :**
- `refreshGrids()` - Polling get_shots.php
  - Met a jour les grilles ennemies (hit/miss/sunk)
  - Applique les images de navires coules avec skins du proprietaire
  - Detecte la fin de partie
  - Mode spectateur si le joueur est mort

**Navires coules :**
- `getShipImgSrc(size, ori, sunkCountForSize, ownerId)` - Chemin de l'image selon le skin du proprietaire
- `placeSunkShipImage(gridSelector, ship, ownerId)` - Superpose l'image du navire coule
- `applySunkOverride(data)` - Applique les overlays depuis les donnees serveur

**Fin de partie :**
- `endGame(recap)` - Affiche la modale victoire/defaite
  - Compteurs animes (XP, Gold)
  - Affichage progressif avec delais

### 7.5 list_games.js (fichier externe - 110 lignes)

**Animation sonar :**
- `requestAnimationFrame` pour la rotation du balayage (6s/tour)
- Blips illumines au passage du balayage (ping + glow)
- Fondu progressif apres passage
- `positionBlips()` - Positionne les blips en angle dore
- `bindBlipClicks()` - Clic = afficher la carte de la partie

**Refresh (5s) :**
- Fetch AJAX avec ?ajax=1
- Reconstruit les blips + re-bind les clics

---

## 8. Frontend - CSS (style.css - ~1930 lignes)

### 8.1 Design System (variables CSS)

```css
:root {
  --bg-dark: #0a1628;        /* Fond principal */
  --card-bg: #B87333;         /* Fond carte (cuivre) */
  --accent: #5eead4;          /* Couleur d'accent (cyan) */
  --danger: #ef4444;          /* Rouge erreur */
  --success: #10b981;         /* Vert succes */
  --text: #e5e9f0;            /* Texte clair */
  --ocean-deep: #071520;      /* Ocean profond */
  --ocean-mid: #0d2137;       /* Ocean moyen */
  --brass: #c8933e;           /* Laiton */
  --brass-light: #eac040;     /* Laiton clair */
  --brass-dark: #7a5a24;      /* Laiton fonce */
  --wood: #3a2008;            /* Bois */
  --wood-light: #5a3a14;      /* Bois clair */
  --rope: #8b7355;            /* Cordage */
}
```

### 8.2 Composants principaux

| Composant | Classe(s) | Description |
|-----------|-----------|-------------|
| Panneau joueur | `.player-panel` | Avatar, pseudo, niveau, XP bar, gold |
| Grille de jeu | `.grid`, `.cell` | CSS Grid, etats: hit, miss, sunk, aiming |
| Minimap | `.minimap` | Radar fixe en haut a gauche |
| Journal combat | `.combat-log` | Fixe en bas a droite, scrollable |
| Capitaine | `.captain-card` | Avatar 120px, couronne flottante |
| Equipage | `.crew-grid`, `.crew-card` | Grille flex de cartes joueurs |
| Modales | `.naval-modal`, `.nv-overlay` | Systeme de modales nautiques |
| Boutons image | `.btn-img` | Boutons avec images thematiques |
| Panel options | `.options-panel` | Panneau lateral avec slider/selects |
| Menu amis | `.friends-menu` | Panel lateral glissant |

### 8.3 Animations CSS (24 nommees)

| Nom | Duree | Effet | Utilise dans |
|-----|-------|-------|-------------|
| pulse-gold | 3.5s | Halo dore pulsant | Bouton central mode de jeu |
| captainGlow | 4s | Ombre doree pulsante | Carte du capitaine |
| crownFloat | 3s | Flottement vertical | Emoji couronne |
| aimPulse | 0.8s | Ombre interne pulsante | Cellule en cours de visee |
| cannonballFall | 0.45s | Chute avec changement d'echelle | Boulet de canon |
| trailFade | 0.35s | Trainee de fumee | Sillage du boulet |
| cannonImpact | 0.4s | Cercle d'impact expansif | Splash du tir |
| rippleWave | 0.8s | Ondulation concentrique | Miss (rate) |
| sunkShipReveal | 0.8s | Apparition + desaturation | Navire coule |
| flicker | 0.6s | Clignotement de flamme | Cellule touchee |
| logFadeIn | 0.3s | Glissement + apparition | Entrees du journal |
| nvModalIn | 0.3s | Zoom + translation | Modales |
| minimapPulse | 0.5s | Pulsation echelle | Minimap sous attaque |
| stress1Pulse | 2s | Bordure orange | Stress niveau 1 |
| stress2Pulse | 1.2s | Bordure rouge | Stress niveau 2 |
| stress3Heartbeat | 0.8s | Battement multi-echelle | Stress niveau 3 |
| vignetteBreath | 1.2s | Vignette radiale | Danger a l'ecran |
| vignetteCritical | 0.8s | Vignette rouge intense | Danger critique |
| lantern-glow | 2.5s | Opacite pulsante | Message d'attente |
| wave-drift | infini | Deplacement du fond | Vagues |
| sonar-ping | infini | Pulsation + opacite | Sonar |
| compass-sway | infini | Balancement | Boussole |
| gentle-bob | infini | Flottement | Elements marins |

---

## 9. Systeme de skins

### 9.1 Les 3 categories

| Categorie | Effet | Exemple chemin |
|-----------|-------|---------------|
| **avatar** | Change l'apparence de l'avatar sur toutes les pages | `assets/img/Avatar/{id}{prefix}.png` -> `Avatar/3cosmique.png` |
| **fond** | Change le fond d'ecran + les boutons thematiques | `assets/img/Fond/bg_{prefix}.png`, `assets/img/lobby/Lobby1{prefix}.png` |
| **bateau** | Change l'apparence des navires (placement + coules) | `assets/img/ship/{folder}/{size}_{ori}_{prefix}.png` |

### 9.2 Themes disponibles

| Nom | Prefix | Dossier | Prix Gold |
|-----|--------|---------|-----------|
| Cosmique | cosmique | cosmique | 5000 |
| Enfer | enfer | enfer | 5000 |
| Fantome | fantome | fantome | 5000 |
| Florale | fleur (avatar/fond) / florale (bateau) | florale | 5000 |
| Neon | neon | neon | 3000 |

### 9.3 Flux d'achat

```
1. shop.php affiche tous les skin_themes groupes par categorie
2. Joueur clique "Acheter" (POST action=buy, theme_id=X)
3. Verification : Gold >= price
4. UPDATE users SET Gold = Gold - price
5. INSERT INTO skin_purchases (id_user, id_theme)
6. Confirmation + affichage bouton "Equiper"
```

### 9.4 Flux d'equipement

```
Equiper :
  INSERT INTO skin_active (id_user, category, id_theme) VALUES (?, ?, ?)
  ON DUPLICATE KEY UPDATE id_theme = VALUES(id_theme)
  -> Un seul skin actif par categorie

Desequiper :
  DELETE FROM skin_active WHERE id_user = ? AND category = ?
  -> Retour au theme par defaut
```

### 9.5 Rendu dynamique

**Avatar** (toutes les pages) :
```php
$src = 'assets/img/Avatar/' . $user['Avatar'] . $activeAvatarPrefix . '.png';
// Ex: Avatar/5cosmique.png (avec skin) ou Avatar/5.png (defaut)
```

**Fond** (index, game, place_ships_view, play, shop, list_games) :
```php
// Index :    assets/img/Fond/bg_{prefix}.png
// Lobby :    assets/img/lobby/Lobby1{prefix}.png
// Play :     assets/img/game/Game1{prefix}.png
// Shop :     assets/img/skin/Skin1{prefix}.png
// Defaut :   assets/img/bg-home.png (etc.)
```

**Boutons thematiques** (index.php uniquement) :
```php
// Mapping fond -> dossier boutons
$fondToBtnFolder = ['fleur' => 'florale', 'cosmique' => 'cosmique', ...];
// Chemin : assets/img/button/{folder}/{type}_{folder}.png
// Ex: assets/img/button/florale/friend_florale.png
```

**Navires** (placement + combat) :
```php
// Avec skin : assets/img/ship/{folder}/{size}_{ori}_{prefix}.png
// Defaut :    assets/img/ship/defaut/{size}_{ori}.png
// Ex: assets/img/ship/cosmique/5_horizontal_cosmique.png
```

**Skins adversaires** (play.php - navires coules) :
- get_shots.php renvoie `ship_skins` = `{ player_id: { folder, prefix } }`
- Quand un navire ennemi est coule, JS utilise le skin du **proprietaire** (pas le tien)

---

## 10. Flux de jeu complet

```
[INSCRIPTION]  register.php
       |
       v
[CONNEXION]  login.php
       |  -> $_SESSION['uid'], Online=1
       v
[ACCUEIL]  index.php
       |  (hub : amis, options, boutique, mode de jeu)
       |
       |-- [BOUTIQUE] shop.php (acheter/equiper skins)
       |-- [AMIS] get_friends.php, search_friend.php, send_friend_request.php
       |
       v  (clic "Jouer")
[CHOIX MODE]  Modal gamemode dans index.php
       |
       +-- [CREER]  create_game.php -> INSERT games (status='preparation')
       |      |
       |      v
       |   [LOBBY]  game.php  <-- polling check_game_status.php (2s)
       |      |  (capitaine + equipage, invitations, bouton lancer)
       |      |
       +-- [REJOINDRE]  list_games.php (sonar) -> join_game.php -> game.php
              |
              v  (createur clique "Lancer" -> start_game.php)
       [PLACEMENT]  place_ships_view.php  <-- polling check_ready.php (2s)
              |  (grille interactive, timer 60s, regles FR/BE)
              |  -> POST place_ships.php (validation serveur)
              |
              v  (placement valide)
       [ATTENTE]  wait_for_players.php  <-- polling check_ready.php (2s)
              |  (en attente des autres joueurs)
              |
              v  (tous les joueurs ont valide -> status='in_progress')
       [COMBAT]  play.php  <-- polling get_shots.php (3s)
              |  (grilles 3D, tir, animations, stress, minimap)
              |  -> POST shoot.php (tir pending)
              |  -> POST resolve_turn.php (toutes les 7s)
              |
              |  [ABANDON] quit_game.php -> player_status='dead'
              |
              v  (dernier survivant -> status='finished')
       [FIN DE PARTIE]  Modal victoire/defaite dans play.php
              |  (XP gagne, Gold gagne, level-up ?)
              |  -> grantXp() distribue les recompenses
              |
              v
       [RETOUR]  -> index.php
```

### Transitions de statut de la partie (games.status)

```
preparation  ->  placement  ->  in_progress  ->  finished
     |               |               |
   (lobby)     (placement       (combat en
                des navires)      cours)
```

| Transition | Declencheur | Fichier |
|------------|-------------|---------|
| preparation -> placement | Createur clique "Lancer" | start_game.php |
| placement -> in_progress | Tous les joueurs ont valide | place_ships.php ou check_ready.php |
| in_progress -> finished | Un seul joueur/equipe survit | resolve_turn.php ou quit_game.php |

---

## 11. Systeme XP / Gold / Niveaux

### Recompenses par partie

| Resultat | XP | Gold | Bonus |
|----------|-----|------|-------|
| Victoire | 50 | 100 | - |
| Defaite | 25 | 25 | - |
| Level-up | - | +200 | Par niveau gagne |

### Formule de progression

```
XP_requis(niveau) = floor(100 * 1.02^(niveau - 1))
```

Progression lente mais constante (+2% par niveau).

### Fonction grantXp() - Processus

```
1. Lire niveau, xp, Gold du joueur
2. xp += xpGain, gold += goldGain
3. Tant que (xp >= xpRequiredForLevel(niveau)):
     xp -= xpRequiredForLevel(niveau)
     niveau++
     gold += 200  (bonus level-up)
4. UPDATE users (niveau, xp, Gold)
5. INSERT/UPDATE ratio (Win ou Defeat +1, Game_Played +1)
6. Retourner {niveau, xp, gold, leveled_up}
```

---

## 12. Polling temps reel

Le jeu n'utilise **pas de WebSocket**. Tout le temps reel est gere par AJAX polling.

| Page | Endpoint | Intervalle | Objectif |
|------|----------|-----------|----------|
| index.php | get_friends.php | 5s | Mise a jour liste amis |
| index.php | get_game_invites.php | 5s | Invitations de jeu |
| game.php (lobby) | check_game_status.php | 2s | Detecter changement de statut + MAJ joueurs |
| place_ships_view.php | check_ready.php | 2s | Detecter quand tous sont prets |
| wait_for_players.php | check_ready.php | 2s | Attendre que tous aient place, puis rediriger vers play.php |
| play.php (combat) | get_shots.php | 3s | Etat complet du jeu (tirs, status, sunk) |
| list_games.php (sonar) | list_games.php?ajax=1 | 5s | Rafraichir la liste des parties |

### Systeme de tours (combat)

```
Duree d'un tour : 7 secondes (ROUND_DURATION, definie dans config/constants.php)

1. Le joueur tire (POST shoot.php) -> tir enregistre state='pending'
2. Apres 7s, resolve_turn.php est appele
3. Tous les tirs pending sont resolus (hit/miss/sunk)
4. last_turn_timestamp est mis a jour
5. Le cycle recommence
```

---

## 13. Session & Securite

### Variables de session

| Variable | Type | Description |
|----------|------|-------------|
| `$_SESSION['uid']` | int | ID utilisateur connecte |
| `$_SESSION['flash']` | array | Messages flash par type (success, error, info) |
| `$_SESSION['kicked']` | string | Message si expulse d'une partie |

### Mesures de securite

| Mesure | Implementation |
|--------|---------------|
| **Hash des mots de passe** | BCRYPT (inscription) / ARGON2ID (modification) via password_hash() |
| **Verification MDP** | password_verify() |
| **Prepared statements** | Toutes les requetes SQL utilisent PDO prepare/execute (pas d'injection SQL) |
| **Regeneration session** | session_regenerate_id() a la connexion (anti fixation de session) |
| **Destruction session** | Vidage des cookies + session_destroy() a la deconnexion |
| **Validation inputs** | filter_var() pour emails, cast (int) pour les IDs, regles MDP strictes |
| **Controle d'acces** | AuthMiddleware::requireAuth() (pages) / requireAuthJson() (API) en debut de chaque endpoint |
| **Suivi d'activite** | last_activity mis a jour toutes les 30s via AuthMiddleware::touchActivity() |
| **Autorisations** | Verification createur pour kick/start/invite |

### Flotte standard (5 navires)

| Navire | Taille | ID interne |
|--------|--------|------------|
| Porte-avions | 5 cases | 5 |
| Croiseur | 4 cases | 4 |
| Sous-marin A | 3 cases | 3.1 |
| Sous-marin B | 3 cases | 3.2 |
| Torpilleur | 2 cases | 2 |

### Regles de placement

| Regle | Francaise (version=1) | Belge (version=2) |
|-------|----------------------|-------------------|
| Chevauchement | Interdit | Interdit |
| Adjacence (cote a cote) | Interdit | Autorise |
| Diagonale | Interdit | Interdit |
| Hors grille | Interdit | Interdit |

---

## 14. Musique de fond

| Page | Fichier son | Volume |
|------|-------------|--------|
| login.php / register.php | connexion_register_sound.mp3 | Depuis BDD (option.Volume) |
| index.php | index_sound.mp3 | Depuis BDD |
| list_games.php | list_game_sound.mp3 | Depuis BDD |
| game.php | lobby_sound.mp3 | Depuis BDD |
| place_ships_view.php | place_view_ship_sound.mp3 | Depuis BDD |
| play.php | play_sound.mp3 | Depuis BDD |
| shop.php | boutique_sound.mp3 | Depuis BDD |

- Volume initial lu depuis la table `option` (0-100)
- Bouton mute/unmute en bas a droite (persiste via localStorage)
- Sur index.php, le slider des options met a jour le volume en temps reel
- Autoplay tente au chargement + relance au premier clic utilisateur

---

*Document mis a jour le 13/05/2026 - Projet Bataille Navale by Quentin*
