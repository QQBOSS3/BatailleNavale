# Cahier des Charges — Bataille Navale

**Version** : 2.0  
**Date** : 16 avril 2026  
**Projet** : Bataille Navale — Jeu multijoueur en ligne  
**Stack** : PHP 8.0 / MariaDB 10.5 / JavaScript Vanilla  

---

## 1. Présentation du projet

### 1.1 Description générale

Bataille Navale est un jeu multijoueur en ligne reprenant le concept classique de la bataille navale, enrichi de fonctionnalités sociales (amis, invitations), de modes de jeu variés (1v1, équipes, Battle Royale) et d'un système de progression (XP, niveaux, or).

Le jeu est accessible via navigateur web, sans installation, et fonctionne en temps réel grâce à un système de polling côté client.

### 1.2 Objectifs

- Offrir une expérience de jeu en ligne fluide et responsive
- Supporter plusieurs modes de jeu et variantes de règles
- Permettre une progression motivante via un système d'XP et de récompenses
- Fournir des fonctionnalités sociales (amis, invitations de parties)
- Garantir la sécurité des données utilisateurs (RGPD, hashage des mots de passe)

### 1.3 Public cible

Joueurs occasionnels et passionnés de jeux de stratégie, tous âges, sur navigateur desktop ou mobile.

---

## 2. Architecture technique

### 2.1 Stack technologique

| Composant     | Technologie                        |
|---------------|------------------------------------|
| Backend       | PHP 8.0                            |
| Base de données | MariaDB 10.5 (PDO, connexions persistantes) |
| Frontend      | HTML5, CSS3, JavaScript Vanilla    |
| Authentification | Sessions PHP, BCRYPT              |
| Autoload      | Composer (PSR-4)                   |
| Police        | PixelFont (Pixelbasel.ttf)         |
| Thème visuel  | Naval / Steampunk cuivré           |

### 2.2 Architecture applicative

```
BN/
├── config/
│   ├── db.php                  # Connexion PDO (persistante, utf8mb4)
│   └── xp.php                  # Système de progression (XP, Gold, niveaux)
├── src/
│   ├── Entity/
│   │   ├── User.php            # Entité utilisateur
│   │   └── Avatar.php          # Entité avatar
│   ├── Repository/
│   │   ├── UserRepository.php  # Accès données utilisateur
│   │   └── AvatarRepository.php# Accès données avatar
│   └── Service/
│       ├── AuthService.php     # Authentification (login, session)
│       └── FlashService.php    # Messages flash (notifications)
├── public/
│   ├── [38 endpoints PHP]      # Points d'entrée de l'application
│   └── assets/
│       ├── css/style.css       # Feuille de style principale
│       ├── js/app.js           # Logique côté client
│       ├── fonts/              # Police pixel
│       └── img/                # 30+ images (UI, boutons, fonds)
├── vendor/                     # Dépendances Composer
└── index.html                  # Redirection vers login.php
```

### 2.3 Modèle de données (BDD)

#### Tables principales

| Table            | Description                                      |
|------------------|--------------------------------------------------|
| `users`          | Comptes joueurs (email, pseudo, mot de passe hashé, niveau, XP, Gold, avatar, statut en ligne) |
| `games`          | Sessions de jeu (mode, type, règles, statut, créateur, taille grille, timer) |
| `game_players`   | Participation joueur/partie (équipe, statut : in_game, dead, left) |
| `player_boards`  | Grilles de placement (board_json : tableau 2D avec IDs uniques par bateau) |
| `shots`          | Tirs effectués (position, cible, résultat : pending → hit/miss/sunk) |
| `rounds`         | Gestion des tours (numéro, timestamps)           |
| `game_invites`   | Invitations à rejoindre une partie                |
| `friends`        | Relations d'amitié (Pending, Accepted, Rejected)  |
| `avatar`         | Avatars disponibles (image BLOB, nom, MIME type)  |
| `mode`           | Modes de jeu (Public, Privé)                      |
| `type`           | Types de jeu (Solo, Team, BattleRoyal)            |
| `team_mode`      | Configurations d'équipe (taille : 1, 2, 3, 4)    |
| `version`        | Variantes de règles (Française, Belge)            |
| `ratio`          | Statistiques joueur (victoires, défaites, parties jouées) |
| `option`         | Préférences utilisateur (volume, langue, daltonisme, thème) |
| `traductions`    | Traductions i18n (clé, langue, valeur)            |
| `update`         | Historique des versions                           |
| `credit`         | Crédits du jeu                                    |

---

## 3. Parcours utilisateur

### 3.1 Inscription

1. L'utilisateur accède à `register.php`
2. Il renseigne : email, pseudo, mot de passe, date de naissance
3. Il sélectionne un avatar parmi la galerie disponible
4. **Validations serveur** :
   - Email : format valide, non déjà utilisé
   - Pseudo : minimum 3 caractères
   - Mot de passe (conformité RGPD/CNIL) : 12 caractères minimum, 1 majuscule, 1 minuscule, 1 chiffre, 1 caractère spécial
   - Date de naissance : obligatoire
   - Avatar : obligatoire
5. Le mot de passe est hashé en BCRYPT avant stockage
6. Compte créé → redirection vers l'accueil avec message de bienvenue

### 3.2 Connexion

1. L'utilisateur saisit son email et son mot de passe sur `login.php`
2. Vérification via `password_verify()` + régénération de session
3. Statut `Online = 1` mis à jour en BDD
4. Redirection vers `index.php` (tableau de bord)
5. Toggle de visibilité du mot de passe (icône oeil)

### 3.3 Tableau de bord (index.php)

L'écran principal affiche :
- **Carte joueur** : avatar, pseudo, niveau, or
- **Actions principales** :
  - Créer une partie (modal de sélection du mode)
  - Rejoindre une partie (liste des parties publiques)
  - Amis (gestion des relations)
  - Invitations (parties en attente)
  - Options (avatar, paramètres)
  - Règles du jeu
  - Crédits
  - Déconnexion
  - Suppression de compte (avec modal de confirmation)

### 3.4 Création de partie

Le joueur configure sa partie via un modal avec les options suivantes :

| Paramètre      | Options disponibles               |
|-----------------|-----------------------------------|
| Type de jeu     | Solo (1v1), Team (2v2, 3v3, 4v4), Battle Royale |
| Mode            | Public (visible de tous) ou Privé (sur invitation) |
| Règles          | Françaises (bateaux espacés) ou Belges (bateaux adjacents autorisés) |
| Taille de grille| 5×5 à 25×25 (défaut : 10×10)     |

La partie est créée avec le statut `preparation` et le joueur est redirigé vers le lobby.

### 3.5 Lobby (game.php)

- Affichage des joueurs présents avec pseudo et avatar (cartes 240px, transparence 0.65)
- **Hôte** (créateur) peut :
  - Inviter des amis via la sidebar
  - Exclure un joueur (kick)
  - Lancer la partie quand le nombre minimum est atteint
- **Joueur** peut :
  - Voir les autres participants
  - Quitter le lobby (modal de confirmation naval)
- Polling toutes les 2 secondes via `check_game_status.php`

### 3.6 Placement des bateaux (place_ships_view.php)

#### Interface
- Layout horizontal : flotte à gauche, grille au centre, actions à droite
- Cellules de 40px, responsive (empile verticalement sous 900px)
- Timer de 60 secondes (placement auto si expiré)
- Rotation des bateaux : clic bouton ou touche **R**
- Placement aléatoire disponible
- Reset de la grille

#### Flottes selon les règles

**Règles Françaises** :
| Bateau        | Taille | Quantité |
|---------------|--------|----------|
| Porte-avions  | 5      | 1        |
| Croiseur      | 4      | 1        |
| Sous-marin    | 3      | 2        |
| Torpilleur    | 2      | 1        |

- Bateaux ne peuvent pas se toucher (y compris en diagonale)

**Règles Belges** :
| Bateau    | Taille | Quantité |
|-----------|--------|----------|
| Cuirassé  | 4      | 1        |
| Croiseur  | 3      | 2        |
| Torpilleur| 2      | 3        |
| Vedette   | 1      | 4        |

- Bateaux peuvent être adjacents

#### Stockage
- Chaque bateau reçoit un **ID unique** incrémental dans `board_json` (1, 2, 3, 4, 5...)
- Validation serveur des règles (diagonales FR, structure des bateaux)
- Grille stockée en JSON 2D dans `player_boards`

### 3.7 Phase de combat (play.php)

#### Interface de jeu
- **Minimap** (haut gauche, fixe) :
  - Grille personnelle avec état des bateaux (intact, touché, coulé)
  - Cellules de 24px (normal) / 38px (zoom)
  - Bouton **👁** : masquer/afficher la minimap
  - Bouton **🔍** : zoom sur la grille
  - Barre de vie (HP restants)
  - Animation de pulsation en cas d'attaque reçue
- **Barre d'infos** : timer du tour + statut
- **Grilles ennemies** : taille responsive `clamp(28px, 4.5vw, 52px)`
  - Affichage du pseudo, statut (allié/ennemi), mode BR
  - Marqueurs de tir : touché (rouge/flamme), coulé (bordeaux/skull), raté (X gris)
  - Animation de ripple à l'impact
- **Bouton quitter** : modal de confirmation naval → écran de défaite

#### Mécanique des tours
1. Le joueur a **7 secondes** pour tirer sur une cible
2. Il clique sur une cellule ennemie → tir enregistré en `state = 'pending'`
3. À la fin du timer, `resolve_turn.php` est appelé :
   - Les tirs `pending` sont résolus en `hit` ou `miss`
   - Détection des bateaux coulés par **flood-fill** (composantes connexes)
   - Les joueurs ayant perdu tous leurs bateaux passent en `dead`
   - Vérification de la condition de victoire
4. Seul le **créateur** de la partie avance le `last_turn_timestamp` (horloge maître)
5. Guard anti-doublon : `WHERE last_turn_timestamp = ?` dans l'UPDATE

#### Détection des bateaux coulés
- Algorithme de **flood-fill orthogonal** sur `board_json`
- Chaque composante connexe (cellules adjacentes avec valeur > 0) = un bateau
- Si toutes les cellules d'un bateau sont dans la liste des `hit` → marqué `sunk`
- Calcul fait côté serveur dans `get_shots.php` comme **source de vérité** (`sunk_cells`)
- Le client applique l'état `sunk` depuis les données serveur (`applySunkOverride`)

#### Condition de victoire
- **Mode Solo/BR** : dernier joueur vivant (tous les autres `dead`)
- **Mode Team** : dernière équipe avec au moins un joueur vivant

#### Mode spectateur
- Si le joueur est éliminé (`dead`), il passe en mode spectateur
- Grilles ennemies visibles en lecture seule
- Polling toutes les 3 secondes pour suivre la partie
- Timer affiché comme 💀, statut "Mode spectateur"

### 3.8 Fin de partie

#### Écran de résultat animé
Séquence d'animations en cascade :

1. **Titre** (200ms) : "VICTOIRE !" (vert) ou "DÉFAITE..." (rouge) avec effet scale+rotation
2. **Panneau récapitulatif** (800ms) : slide vers le haut, style naval (bande cuivrée)
3. **Lignes de stats** (une par une, 250ms d'intervalle) :
   - Résultat (Victoire/Défaite)
   - ⚡ Expérience gagnée (compteur animé)
   - 💰 Or gagné (compteur animé)
   - 🏆 Bonus niveau (affiché uniquement si level-up)
4. **Barre d'XP** animée :
   - Sans level-up : progression de l'ancienne valeur vers la nouvelle
   - Avec level-up : remplissage à 100% (flash doré), reset, puis nouveau pourcentage
5. **Bannière "NIVEAU SUPÉRIEUR !"** (si level-up, effet pop doré)
6. **Bouton "Retour au QG"**

#### Abandon de partie (quit_game.php)
- Modal de confirmation naval ("Abandonner la mission ?")
- Le joueur est marqué `dead` et reçoit l'XP/Gold de défaite
- Si dernier adversaire → partie terminée, victoire pour les survivants
- L'écran de défaite animé s'affiche (pas de redirection directe)

---

## 4. Système de progression

### 4.1 Expérience (XP)

| Événement    | XP gagnée |
|-------------|-----------|
| Victoire     | 50 XP     |
| Défaite      | 25 XP     |

**Formule de niveau** : XP requis pour passer du niveau N au N+1 :
```
XP_requis = floor(100 × 1.02^(N-1))
```

| Niveau | XP requis |
|--------|-----------|
| 1 → 2 | 100 XP    |
| 2 → 3 | 102 XP    |
| 5 → 6 | 108 XP    |
| 10 → 11| 120 XP   |
| 50 → 51| 264 XP   |

Progression exponentielle douce (+2% par niveau).

### 4.2 Or (Gold)

| Événement          | Or gagné  |
|--------------------|-----------|
| Victoire            | 100 Gold  |
| Défaite             | 25 Gold   |
| Passage de niveau   | 200 Gold (bonus) |

### 4.3 Statistiques (ratio)

- Nombre de victoires
- Nombre de défaites
- Nombre de parties jouées
- Stockées dans la table `ratio` par joueur

### 4.4 Attribution des récompenses

- L'XP et le Gold sont attribués **uniquement au joueur connecté** (pas aux adversaires)
- Chaque client est responsable de déclencher l'attribution pour son propre utilisateur
- Garde anti-doublon : le premier appel à `resolve_turn.php` met `status = 'finished'`, les suivants sont bloqués

---

## 5. Fonctionnalités sociales

### 5.1 Système d'amis

| Action                  | Endpoint                  |
|-------------------------|---------------------------|
| Rechercher un joueur     | `search_friend.php`       |
| Envoyer une demande      | `send_friend_request.php` |
| Accepter/Refuser         | `validate_friend.php`     |
| Lister ses amis          | `get_friends.php`         |

- Indicateur de statut en ligne (point vert/rouge)
- Vérification des doublons et auto-demandes

### 5.2 Invitations de partie

| Action                  | Endpoint                   |
|-------------------------|----------------------------|
| Inviter un ami           | `invite_to_game.php`       |
| Voir ses invitations     | `get_game_invites.php`     |
| Accepter/Refuser         | `respond_game_invite.php`  |

- Seul l'hôte peut inviter
- Polling des invitations toutes les 5 secondes sur l'accueil

---

## 6. Interface utilisateur

### 6.1 Thème visuel

- **Palette** : bleu océan profond, cuivre/laiton, or naval
- **Bandes cuivrées** : décoration en haut des panneaux (gradient repeating-linear)
- **Fond** : background sombre avec radial-gradient de vignettage
- **Police** : PixelFont (style rétro/pixel art)
- **Animations** : sonar (ping radial), ripple d'impact, pulsation des cellules

### 6.2 Composant modal naval (réutilisable)

Remplacement de tous les `alert()` et `confirm()` système par un modal custom :
- Classes CSS : `.nv-overlay`, `.nv-box`, `.nv-brass`, `.nv-body`, `.nv-title`, `.nv-text`, `.nv-buttons`
- Fonctions JS : `navalAlert(titre, texte)` et `navalConfirm(titre, texte, label, callback, style)`
- 4 styles de boutons : cancel (discret), danger (rouge), primary (cyan), ok (doré)
- Animation d'entrée : scale + slide (cubic-bezier)

### 6.3 Responsive

- Grilles de jeu : `clamp()` pour adaptation automatique
- Modal gamemode : hauteur en `clamp(260px, 50vh, 420px)`
- Placement des bateaux : empile verticalement sous 900px
- Minimap : taille dynamique selon la taille de grille

---

## 7. Sécurité

### 7.1 Authentification
- Hashage des mots de passe : **BCRYPT** (`password_hash` / `password_verify`)
- Régénération de session à la connexion (`session_regenerate_id`)
- Validation des mots de passe conforme RGPD/CNIL (12 chars, majuscule, minuscule, chiffre, spécial)

### 7.2 Protection des données
- Requêtes préparées PDO (protection injection SQL)
- Échappement HTML : `htmlspecialchars()` sur toutes les sorties utilisateur
- Sessions côté serveur (`$_SESSION['uid']`)

### 7.3 Intégrité du jeu
- Validation serveur des placements de bateaux
- Guard anti-doublon sur l'avancement des tours (`WHERE last_turn_timestamp = ?`)
- Calcul des bateaux coulés côté serveur (source de vérité)
- Seul le créateur peut avancer l'horloge du tour

---

## 8. Synchronisation et performance

### 8.1 Gestion du timer

- `last_turn_timestamp` en BDD = **horloge maître** (timestamp UNIX)
- Durée d'un tour : **7 secondes** (constante `ROUND_DURATION`)
- Le client calcule le countdown localement depuis `last_turn_timestamp`
- Seul le créateur met à jour le timestamp en BDD (pattern "master clock")

### 8.2 Polling

| Page              | Intervalle | Endpoint               |
|-------------------|------------|------------------------|
| Lobby (game.php)  | 2s         | `check_game_status.php`|
| Combat (play.php) | 3s         | `get_shots.php`        |
| Liste parties     | 5s         | `list_games.php?ajax=1`|
| Invitations       | 5s         | `get_game_invites.php` |

### 8.3 Optimisations
- Connexions PDO **persistantes** (`PDO::ATTR_PERSISTENT = true`)
- Requêtes consolidées dans `get_shots.php` : 4 requêtes au lieu de 9
- Filtrage côté PHP au lieu de requêtes multiples
- Cache des plateaux dans `resolve_turn.php` pour éviter les requêtes redondantes

---

## 9. Points d'API (résumé)

### Authentification & Compte

| Endpoint           | Méthode | Description                     |
|--------------------|---------|---------------------------------|
| `login.php`        | POST    | Connexion (email + mot de passe)|
| `register.php`     | POST    | Inscription                     |
| `logout.php`       | GET     | Déconnexion                     |
| `update_info.php`  | POST    | Modifier profil                 |
| `delete_account.php`| POST   | Supprimer le compte             |

### Gestion de partie

| Endpoint              | Méthode | Description                          |
|-----------------------|---------|--------------------------------------|
| `create_game.php`     | GET     | Créer une partie                     |
| `list_games.php`      | GET     | Lister les parties publiques         |
| `join_game.php`       | GET     | Rejoindre une partie                 |
| `start_game.php`      | POST    | Lancer la partie (hôte)             |
| `kick_player.php`     | POST    | Exclure un joueur (hôte)            |
| `leave_game.php`      | POST    | Quitter le lobby                     |
| `quit_game.php`       | POST    | Abandonner en cours de jeu           |

### Gameplay

| Endpoint              | Méthode | Description                          |
|-----------------------|---------|--------------------------------------|
| `place_ships.php`     | POST    | Enregistrer le placement             |
| `shoot.php`           | POST    | Tirer sur une cellule                |
| `resolve_turn.php`    | POST    | Résoudre le tour (hit/miss/sunk)     |
| `get_shots.php`       | GET     | Récupérer tous les tirs + état       |
| `check_ready.php`     | GET     | Vérifier si tous ont placé           |
| `check_game_status.php`| GET    | État de la partie (polling)          |

### Social

| Endpoint                | Méthode | Description                        |
|-------------------------|---------|------------------------------------|
| `search_friend.php`     | POST    | Rechercher un joueur par pseudo    |
| `send_friend_request.php`| POST   | Envoyer une demande d'ami         |
| `validate_friend.php`   | POST    | Accepter/refuser une demande      |
| `get_friends.php`       | GET     | Lister les amis                    |
| `invite_to_game.php`    | POST    | Inviter un ami à une partie       |
| `get_game_invites.php`  | GET     | Lister les invitations reçues     |
| `respond_game_invite.php`| POST   | Accepter/refuser une invitation   |

### Personnalisation

| Endpoint            | Méthode | Description                       |
|---------------------|---------|------------------------------------|
| `avatars.php`       | GET     | Galerie d'avatars                  |
| `get_avatar.php`    | GET     | Récupérer l'image d'un avatar     |
| `update_avatar.php` | POST    | Changer son avatar                 |
| `update_options.php`| POST    | Modifier les préférences           |

---

## 10. Interopérabilité

### 10.1 Base de données partagée

Le jeu est conçu pour fonctionner avec des clients développés dans **d'autres technologies** (ex : Vue.js / Express.js) partageant la même base de données. 

Chaque client doit implémenter :
1. L'insertion des tirs en `state = 'pending'` dans la table `shots`
2. La résolution des tirs (`pending` → `hit`/`miss`) en lisant `board_json`
3. La détection des bateaux coulés par flood-fill
4. Le marquage des joueurs morts (`player_status = 'dead'`)
5. La vérification de la condition de victoire
6. La gestion de l'XP/Gold pour ses propres utilisateurs uniquement

### 10.2 Règles de synchronisation

- `last_turn_timestamp` est l'unique source de vérité pour le timing
- Un seul client (le créateur) doit avancer le timestamp
- La durée de tour (7s) doit être identique entre tous les clients
- Le guard `AND last_turn_timestamp = ?` empêche les doublons
