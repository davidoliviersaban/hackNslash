Hack N Slash

## Pitch

Hack N Slash est un dungeon crawler tactique physique en pixel art.
Le jeu combine un plateau central tres lisible, des salles generees aleatoirement, des monstres geres via cartes laterales, et une forte pression temporelle.

L'intention est de proposer une experience tactique rapide a lire, facile a manipuler physiquement, et suffisamment dense pour recompenser l'optimisation de chaque tour.

## Direction Artistique

- Style visuel : pixel art en vue 2.5D top-down
- Lecture des decors : sol vu de dessus, elements avec volume lus de face pour renforcer la profondeur
- Tileset : 32x32 px
- Elements de decor : murs, piliers, pics, trous, entree, sortie, salle de boss
- Personnages : style adventure fantasy, avec sprites de face et de dos adaptes a une manipulation sur support physique

## Objectif De Jeu

- Le heros traverse un donjon compose de 7 salles avant une salle de boss
- La partie se joue contre-la-montre
- Le joueur doit sortir du donjon en moins de 10 minutes
- Le score final correspond au temps restant sur le chronometre

Le temps de reflexion fait donc partie du cout tactique : chaque hesitation se traduit directement en perte de score.

## Structure Du Donjon

- Le donjon contient 7 salles aleatoires avant le boss
- Chaque salle propose une configuration de monstres specifique
- Le boss occupe une 8eme salle dediee
- Le plateau central ne contient que les heros, les obstacles et les marqueurs de monstres
- Les emplacements monstres sont numerotes de 1 a 7
- Les emplacements joueurs sont identifies par A et B
- Chaque salle represente une entree et une sortie

## Interface Physique

Le dispositif de jeu repose sur une separation forte entre lecture spatiale et lecture systemique.

- Le board central reste volontairement epure
- Les monstres y sont representes par des jetons numerotes de 1 a 7
- Les cartes monstres sont placees sur un dashboard lateral avec des emplacements numerotes correspondants
- Les informations lourdes ne figurent pas sur le plateau mais sur les cartes laterales

### Cartes Monstres

- Format : 63 mm x 44 mm
- Orientation : horizontale
- Contenu : nom, artwork, moteur d'IA, capacites, informations importantes de lecture

### Cartes Pouvoir Joueur

- Orientation : verticale
- Le joueur dispose de 3 cartes de pouvoir fixes
- Exemples actuels : Dash, Vortex, Attack

### Jetons Et Suivi D'etat

- Les PV des monstres sont suivis sur leurs cartes
- Le cooldown des pouvoirs joueur est note soit avec des jetons, soit en pivotant les cartes
- Pour le moment, les memes jetons servent a noter a la fois les PV des monstres et le cooldown des pouvoirs
- Ce point n'est pas une decision definitive : c'est un choix de simplification pour le prototype

### Boss

- Le boss dispose d'une salle dediee
- Sa carte est au format poker pour marquer sa place particuliere dans l'experience

## Systeme De Combat

- Le joueur ne pioche plus dans un deck infini
- Il gere un petit set de pouvoirs fixes
- La tension tactique repose sur l'ordre d'utilisation, le positionnement et la gestion du rythme plutot que sur la pioche

## Structure D'un Tour

L'ordre des actions est le suivant :

1. Deplacement gratuit optionnel des joueurs
2. Action des joueurs
3. Cooldown
4. Activation des pieges
5. Activation des petits monstres
6. Activation des gros monstres
7. Activation des bosses

Regles associees :

- Pour compter comme deplacement gratuit, le mouvement doit etre exactement de 1 case
- Si un seul joueur est en jeu, il peut jouer 2 cartes
- Si 2 joueurs sont en jeu, chaque joueur joue 1 carte

## Priorite D'activation Des Monstres

- Les monstres s'activent du plus proche au plus loin des joueurs
- En cas d'egalite, le joueur choisit l'ordre ou l'action qu'il prefere resoudre

Cette priorite donne au joueur une petite marge de controle tactique sans casser la lisibilite du systeme.

## Cooldown Et Progression Des Pouvoirs

- Chaque carte utilisee declenche un cooldown
- La strategie repose sur la gestion du rythme plutot que sur la chance de pioche
- Les cartes montent en niveau selon une progression arithmetique

### Progression Des Cartes

- Niveau 0 : 1 degat a distance 1 ou 2 degats a distance 0
- Niveau 1 : +2 points d'amelioration
- Niveau 2 : +1 ou +2 points supplementaires
- Niveau 3 : +2 ou +3 points supplementaires

Les points d'amelioration servent a augmenter la portee, ajouter des effets ou reduire le cooldown.

## Bestiaire Et Occupation De L'espace

Le bestiaire est pense aussi comme un outil de rythme et de lecture spatiale.

- Les monstres alternent entre Petit et Gros pour mieux controler l'occupation du plateau
- Regle de placement :

1. Petit sur les emplacements impairs 1, 3, 5, 7
2. Gros sur les emplacements pairs 2, 4, 6

### Types De Creatures

- Petits : Goblins, Evil Eye
- Gros : Orc Warrior, Pig Rider, Wolf Rider

### Capacites Cles

- `dash` : un deplacement doit etre effectue pour declencher les degats
- `repousse` : fait reculer un adversaire ; si le recul est empeche, 1 degat est inflige
- `saut` : permet de franchir certaines contraintes de terrain
- `vol` : ne declenche pas les pieges au sol et passe au-dessus des trous
- `gros` : declenche les pieges au sol et s'installe sur un gros emplacement
- `petit` : ne declenche pas les pieges au sol et s'installe sur les petits emplacements
- `prison` : empeche tout mouvement tant que la creature est vivante

## Principes De Design

- Le plateau doit rester lisible en un coup d'oeil
- La numerotation des monstres sert a relier instantanement le plateau central et le dashboard lateral
- La surcharge d'information doit etre deportee vers les cartes, pas vers la grille de jeu
- Le systeme doit rester manipulable rapidement avec un minimum de composants temporaires

---

## Game Log

| Player   | Date       | Initial deck                    | Final deck                                                    | Damages taken |
| -------- | ---------- | ------------------------------- | ------------------------------------------------------------- | ------------- | --- |
| DOS      | 2021/08/10 | 6 Attack, Rapid Fire, Vortex    | 2 Attack, 3 Rapid Fire, Vortex II, 3 Fireball                 | 3             |
| Jonas    | 2021/08/10 | 6 Attack, Dash, Jump            | Vortex I, 2 Spin Attack, Heal I, Jump I, Fireball I, 5 Attack | 14            |
| Ambroise | 2021/08/10 | 6 Attack, Spin Attack, Fireball |                                                               |               |     |
