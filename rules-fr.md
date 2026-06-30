# Hack N Slash

## C'est quoi ce jeu ?

Hack N Slash est un dungeon crawler tactique cooperatif pour 1 ou 2 joueurs. Vous incarnez des heros qui traversent un donjon salle par salle, deviennent plus forts, puis affrontent un boss final. Vous gagnez ou perdez ensemble.

---

## Mise en Place

### Choisir un Scenario

- **Donjon** : Traversez les salles et ameliorez vos pouvoirs avant le boss.
- **Combat de Boss** : Commencez avec des pouvoirs avances et affrontez le boss directement.

### Choisir une Difficulte

| Difficulte | PV de depart |
| ---------- | :----------: |
| Facile     |      20      |
| Normal     |      10      |
| Difficile  |      5       |
| Hardcore   |      1       |

### Nombre de Joueurs

| Joueurs | Actions par round | Salle de depart |
| :-----: | :---------------: | :-------------: |
|    1    |     2 actions     |     Salle 1     |
|    2    |  1 action chacun  |     Salle 3     |

Chaque heros commence avec 2 cartes de pouvoir basiques : **Attaque** et **Frappe**.

---

## Le Donjon

Le donjon comporte **7 salles** suivies d'une **salle de boss** (salle 8).

Chaque salle est une grille remplie de monstres. Eliminez tous les monstres d'une salle pour avancer. Les premieres salles (1-3) sont plus petites (5x5) ; les suivantes sont plus grandes (7x7).

### Terrain

| Terrain | Praticable ? | Bloque la vue ? | Effet                                      |
| ------- | :----------: | :-------------: | ------------------------------------------ |
| Sol     |     Oui      |       Non       | —                                          |
| Pics    |     Oui      |       Non       | 1 degat par round a tout ce qui s'y trouve |
| Mur     |     Non      |       Oui       | —                                          |
| Pilier  |     Non      |       Oui       | —                                          |
| Trou    |     Non      |       Non       | —                                          |
| Entree  |     Non      |       Non       | —                                          |
| Sortie  |     Non      |       Non       | —                                          |

---

## Structure d'un Round

Chaque round suit ces etapes dans l'ordre :

1. **Deplacement gratuit** — Chaque heros peut se deplacer de 1 case (orthogonal uniquement). Optionnel.
2. **Actions des heros** — Les heros jouent des cartes de pouvoir en depensant des points d'action.
3. **Cooldown** — Tous les cooldowns diminuent de 1.
4. **Pieges** — Les pics infligent 1 degat a toute entite situee dessus.
5. **Petits monstres** — S'activent du plus proche au plus eloigne d'un heros.
6. **Gros monstres** — S'activent du plus proche au plus eloigne.
7. **Boss** — S'active en dernier.

Le tour d'un heros se termine quand il a utilise son deplacement gratuit, depense tous ses points d'action et n'a plus d'action gratuite disponible (ou qu'il choisit de passer).

---

## Deplacement

**Deplacement gratuit** : Exactement 1 case en orthogonal (haut/bas/gauche/droite). Impossible si le heros est Englue.

**Ligne de vue** : Necessaire pour toutes les attaques a distance. Les murs et piliers la bloquent.

---

## Pouvoirs

Les pouvoirs sont vos outils principaux. Chaque carte de pouvoir possede :

- Un **cooldown** (generalement 2 rounds apres utilisation)
- Une **portee** (distance minimale et maximale)
- Un **type de portee** : orthogonale (directions cardinales uniquement) ou diagonale (inclut les diagonales)

### Pouvoirs Basiques

|                                                             |             |                                                |
| ----------------------------------------------------------- | ----------- | ---------------------------------------------- |
| ![Attaque](src/resources/images/cards/powers/attack-0.webp) | **Attaque** | 1 degat, portee 0-1 (diagonale), cooldown 2    |
| ![Frappe](src/resources/images/cards/powers/strike-0.webp)  | **Frappe**  | 2 degats, portee 0-1 (orthogonale), cooldown 2 |

### Cartes de Pouvoir

Chaque pouvoir existe en 3 rangs. Les rangs superieurs ont une meilleure portee, plus de degats, une zone elargie ou un cooldown reduit.

| Pouvoir                | Rang 1                                                     | Rang 2                                                     | Rang 3                                                     |
| ---------------------- | ---------------------------------------------------------- | ---------------------------------------------------------- | ---------------------------------------------------------- |
| **Dash**               | ![](src/resources/images/cards/powers/dash-1.webp)         | ![](src/resources/images/cards/powers/dash-2.webp)         | ![](src/resources/images/cards/powers/dash-3.webp)         |
| **Dash Attaque**       | ![](src/resources/images/cards/powers/dash-attack-1.webp)  | ![](src/resources/images/cards/powers/dash-attack-2.webp)  | ![](src/resources/images/cards/powers/dash-attack-3.webp)  |
| **Boule de Feu**       | ![](src/resources/images/cards/powers/fireball-1.webp)     | ![](src/resources/images/cards/powers/fireball-2.webp)     | ![](src/resources/images/cards/powers/fireball-3.webp)     |
| **Tourbillon**         | ![](src/resources/images/cards/powers/whirlwind-1.webp)    | ![](src/resources/images/cards/powers/whirlwind-2.webp)    | ![](src/resources/images/cards/powers/whirlwind-3.webp)    |
| **Vortex**             | ![](src/resources/images/cards/powers/vortex-1.webp)       | ![](src/resources/images/cards/powers/vortex-2.webp)       | ![](src/resources/images/cards/powers/vortex-3.webp)       |
| **Saut**               | ![](src/resources/images/cards/powers/jump-1.webp)         | ![](src/resources/images/cards/powers/jump-2.webp)         | ![](src/resources/images/cards/powers/jump-3.webp)         |
| **Soin**               | ![](src/resources/images/cards/powers/heal-1.webp)         | ![](src/resources/images/cards/powers/heal-2.webp)         | ![](src/resources/images/cards/powers/heal-3.webp)         |
| **Projection**         | ![](src/resources/images/cards/powers/grab-1.webp)         | ![](src/resources/images/cards/powers/grab-2.webp)         | ![](src/resources/images/cards/powers/grab-3.webp)         |
| **Frappe Puissante**   | ![](src/resources/images/cards/powers/power-strike-1.webp) | ![](src/resources/images/cards/powers/power-strike-2.webp) | ![](src/resources/images/cards/powers/power-strike-3.webp) |
| **Frappe Rapide**      | ![](src/resources/images/cards/powers/quick-strike-1.webp) | ![](src/resources/images/cards/powers/quick-strike-2.webp) | ![](src/resources/images/cards/powers/quick-strike-3.webp) |
| **Tir Rapide**         | ![](src/resources/images/cards/powers/quick-shot-1.webp)   | ![](src/resources/images/cards/powers/quick-shot-2.webp)   | ![](src/resources/images/cards/powers/quick-shot-3.webp)   |
| **Tir a Bout Portant** | ![](src/resources/images/cards/powers/point-blank-1.webp)  | ![](src/resources/images/cards/powers/point-blank-2.webp)  | ![](src/resources/images/cards/powers/point-blank-3.webp)  |
| **Leech**              | ![](src/resources/images/cards/powers/leech-1.webp)        | ![](src/resources/images/cards/powers/leech-2.webp)        | ![](src/resources/images/cards/powers/leech-3.webp)        |
| **Renforcement**       | ![](src/resources/images/cards/powers/reinforce-1.webp)    | ![](src/resources/images/cards/powers/reinforce-2.webp)    | ![](src/resources/images/cards/powers/reinforce-3.webp)    |

### Description des Pouvoirs

| Pouvoir                | Ce qu'il fait                                                                                   |
| ---------------------- | ----------------------------------------------------------------------------------------------- |
| **Dash**               | Deplacez votre heros en ligne droite. Supprime le statut Englue.                                |
| **Dash Attaque**       | Foncez vers une case, puis frappez un ennemi adjacent a votre destination.                      |
| **Boule de Feu**       | Touchez tous les ennemis dans une zone autour d'une case cible. Ignore les boucliers.           |
| **Tourbillon**         | Faites un pas optionnel, puis frappez tous les ennemis autour de vous.                          |
| **Vortex**             | Attirez les ennemis d'une zone vers un point choisi.                                            |
| **Saut**               | Bondissez vers une case eloignee. Repoussez ou blessez les monstres sur la case d'atterrissage. |
| **Soin**               | Restaurez des PV a vous-meme ou a un allie proche (sans depasser le max de depart).             |
| **Projection**         | Repoussez un ennemi adjacent loin de vous.                                                      |
| **Frappe Puissante**   | Coup de melee lourd, optionnellement avec recul.                                                |
| **Frappe Rapide**      | Touchez plusieurs ennemis au corps a corps.                                                     |
| **Tir Rapide**         | Touchez plusieurs ennemis a distance.                                                           |
| **Tir a Bout Portant** | Tir a distance qui repousse la cible.                                                           |
| **Leech**              | Attaque de melee qui vous soigne de 1 PV par touche. Ignore les boucliers.                      |
| **Renforcement**       | Renforcez un heros : sa prochaine carte ce tour est gratuite (distance de Dash doublee).        |

### Actions Gratuites

Certains pouvoirs peuvent etre declenches **gratuitement** quand un evenement specifique survient plus tot dans le meme tour :

| Declencheur              | Quand il se declenche                                                 |
| ------------------------ | --------------------------------------------------------------------- |
| Apres Deplacement        | Vous venez d'utiliser votre deplacement gratuit                       |
| Apres Dash               | Vous venez de faire un dash ou un saut                                |
| Apres Carte Jouee        | Vous venez de jouer une carte quelconque                              |
| Apres Kill               | Vous venez de tuer un monstre                                         |
| Apres Poussee/Attraction | Vous venez de pousser ou d'attirer un ennemi                          |
| Apres Dash Attaque       | Vous venez de resoudre un Dash Attaque (pour les chaines multi-coups) |
| Bouclier Brise           | Vous venez de briser le bouclier d'un monstre                         |

**Important** : Chaque pouvoir gratuit ne peut se declencher qu'une seule fois par chaine de tour. Les actions gratuites ne coutent pas de points d'action.

**Pouvoirs multi-jeu** (Dash Attaque rang 2+) : Un seul point d'action vous permet d'enchainer plusieurs executions a la suite.

---

## Monstres

### Bestiaire

| Monstre       | Carte                                                         | Taille | PV  | Special                                                   |
| ------------- | ------------------------------------------------------------- | :----: | :-: | --------------------------------------------------------- |
| Gobelins      | ![](src/resources/images/cards/monsters/gobelins.webp)        | Petit  |  1  | Apparaissent toujours par paire                           |
| Slime         | ![](src/resources/images/cards/monsters/slime.webp)           | Petit  |  2  | Immobilisent les heros au lieu d'infliger des degats      |
| Evil Eye      | ![](src/resources/images/cards/monsters/evil-eye.webp)        | Petit  |  1  | Attaque a distance, ne bouge jamais                       |
| Kamikaze      | ![](src/resources/images/cards/monsters/goblin-kamikaze.webp) | Petit  |  1  | Explose en mourant, inflige 2 degats aux heros adjacents  |
| Magicien      | ![](src/resources/images/cards/monsters/goblin-wizard.webp)   | Petit  |  2  | Longue portee (2-4), se deplace pour garder ses distances |
| Bombardier    | ![](src/resources/images/cards/monsters/goblin-bomber.webp)   | Petit  |  3  | Portee moyenne (2-3), resistant                           |
| Orc           | ![](src/resources/images/cards/monsters/orc.webp)             |  Gros  |  6  | Frappe tout ce qui se trouve devant lui (arc de 3 cases)  |
| Cavalier Porc | ![](src/resources/images/cards/monsters/pig-rider.webp)       |  Gros  |  5  | Charge et repousse le heros                               |
| Cavalier Loup | ![](src/resources/images/cards/monsters/wolf-rider.webp)      |  Gros  |  3  | Invoque un Gobelin puis s'enfuit                          |

### Comportement des Monstres

Les monstres agissent automatiquement. Lors de leur activation :

1. Ils identifient le **heros le plus proche** comme cible.
2. S'ils peuvent deja toucher leur cible, ils **attaquent** (pas de deplacement).
3. Sinon, ils **se deplacent** vers leur portee d'attaque.

Les monstres ne peuvent jamais attaquer et se deplacer dans la meme activation — sauf le Cavalier Loup qui invoque puis fuit en un tour.

### Capacites Speciales

**Charge** (Cavalier Porc) : Se deplace de toute sa distance, puis percute le heros et le repousse de 1 case. Si le heros ne peut pas etre repousse (mur derriere), les deux subissent 1 degat supplementaire.

**Arc Frontal** (Orc) : Frappe tous les heros dans un cone de 3 cases devant lui (la case en face + les deux diagonales dans cette direction).

**Explosion** (Kamikaze) : Quand cette creature meurt, elle inflige ses degats a tous les heros qui lui sont adjacents (en orthogonal).

**Invocation et Fuite** (Cavalier Loup) : Place un Gobelin sur une case adjacente, puis s'eloigne du heros le plus proche.

**Englue** (Slime) : Au lieu d'infliger des degats, applique le statut **Englue**. Un heros Englue ne peut plus utiliser son deplacement gratuit ni aucun pouvoir de mouvement — sauf le Dash, qui libere. Le statut persiste tant que le heros reste adjacent a un Slime vivant.

### Empilement

Les Gobelins peuvent partager une case : jusqu'a 2 Gobelins peuvent occuper le meme espace. Les Gobelins empiles se deplacent et attaquent ensemble en tant que groupe.

---

## Enchantements de Niveau

Certaines salles appliquent un modificateur a **tous les monstres** de cette salle :

|                                                        | Enchantement | Effet                                                                                                                                                                                                                                       |
| ------------------------------------------------------ | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ![](src/resources/images/cards/monsters/shielded.webp) | **Shielded** | Chaque monstre gagne un bouclier qui absorbe entierement le premier coup, quelle que soit la quantite de degats. Boule de Feu et Leech ignorent les boucliers. Briser un bouclier declenche l'evenement d'action gratuite "Bouclier Brise". |
| ![](src/resources/images/cards/monsters/spiky.webp)    | **Spiky**    | Chaque fois qu'un heros frappe un monstre au corps a corps (adjacent, orthogonal), le heros recoit 1 degat en retour.                                                                                                                       |

---

## Boss

Apres avoir complete 7 salles, vous atteignez le boss. Les boss ont **3 phases**, chacune avec sa propre reserve de points de vie.

### Slasher

![](src/resources/images/cards/monsters/slasher-1.webp) ![](src/resources/images/cards/monsters/slasher-2.webp) ![](src/resources/images/cards/monsters/slasher-3.webp)

Un cogneur rapide en melee. Se deplace de 2 cases par tour et frappe tout ce qui est adjacent.

| Phase | PV  | Comportement                                                                                                 |
| :---: | :-: | ------------------------------------------------------------------------------------------------------------ |
|   1   |  8  | Se deplace de 2, attaque les adjacents (2 degats)                                                            |
|   2   |  8  | **Pre-action** : invoque 2 sbires. Puis se deplace et attaque.                                               |
|   3   |  8  | **Pre-actions** : accorde un bouclier a toutes les creatures + invoque 2 sbires. Puis se deplace et attaque. |

### Striker

![](src/resources/images/cards/monsters/striker-1.webp) ![](src/resources/images/cards/monsters/striker-2.webp) ![](src/resources/images/cards/monsters/striker-3.webp)

Un devastateur de zone. Avance d'un pas, puis frappe le sol autour de lui.

| Phase | PV  | Comportement                                                                                                                                 |
| :---: | :-: | -------------------------------------------------------------------------------------------------------------------------------------------- |
|   1   | 10  | Se deplace de 1 (diagonales OK), puis inflige 2 degats a tout le monde dans un rayon de 1 case (les 8 cases environnantes + sa propre case). |
|   2   | 10  | **Pre-action** : charge le heros le plus proche (1 degat, poussee). Puis attaque de zone.                                                    |
|   3   | 10  | **Pre-actions** : accorde un bouclier + charge. Puis attaque de zone.                                                                        |

### Regles du Boss

- **Transition de phase** : Quand une phase de boss est vaincue, toutes les actions des heros s'arretent immediatement. Le round saute a la phase ennemie (cooldown → pieges → activation des monstres) avec le boss entrant dans sa nouvelle phase a PV pleins.
- **Les pre-actions** se deroulent au debut de l'activation du boss (etape 7 du round). Elles peuvent accorder des boucliers, invoquer des sbires ou charger.
- **Les sbires invoques n'agissent pas au tour ou ils apparaissent.** Puisque les petits monstres s'activent avant les boss (etape 5), les sbires invoques pendant les pre-actions du boss ne bougeront qu'au round suivant.
- **Victoire** : Vaincre la troisieme phase du boss pour gagner la partie.

---

## Recompenses

Apres avoir complete une salle (pas la salle du boss), chaque joueur choisit **une** recompense :

**Option A — Nouveau Pouvoir** : Choisissez un pouvoir de rang 1 parmi une offre partagee de 3 cartes. Il remplace l'un de vos pouvoirs actuels. Les familles de pouvoirs que vous possedez deja sont exclues de l'offre.

**Option B — Amelioration** : Ameliorez l'un de vos pouvoirs actuels vers son rang suivant.

En partie a 2 joueurs, les deux voient la meme offre de nouveaux pouvoirs. Si un joueur prend une carte, elle disparait de l'offre de l'autre. Les offres d'amelioration sont personnelles.

---

## Collisions

Quand une creature poussee ou attiree est bloquee (mur, case occupee, bord du plateau) :

- La creature bloquee recoit **1 degat**.
- Si la case bloquante contient un monstre, ce monstre recoit aussi **1 degat**.

---

## Victoire et Defaite

| Issue        | Condition                                       |
| ------------ | ----------------------------------------------- |
| **Victoire** | Vaincre la troisieme phase du boss.             |
| **Defaite**  | Un heros tombe a 0 PV. Fin de partie immediate. |
