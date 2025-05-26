# Résolveur de labyrinthe en C — Documentation technique

## Objectif

Ce programme C permet de résoudre un labyrinthe donné sous forme de matrice (provenant d’un input PHP) en générant **tous les chemins possibles** entre le départ et l’arrivée , en renvoyant une **liste triée** avec le **chemin le plus court en premier**.  
Il prend en compte deux types de labyrinthes :
- Sans portes/boutons.
- Avec **boutons (4)** qui ouvrent/ferment des **portes (5 = fermée, 6 = ouverte)**.

---

## Entrées attendues

-argument 1: `val_map` : chaîne de caractères représentant une matrice carrée de valeurs (format PHP).
-argument 2: `taille` : dimension entière du labyrinthe.
-argument 3: `diff_map` : paire = labyrinthe avec boutons/portes, impaire = sans. Correspond à la difficulté du labyrinthe entre 1 et 10

# Sortie
- Retour sous forme de tableau de chaînes : `0,0;0,1;1,1;2,1;2,2;3,2;3,3_0,0;0,1;0,2;1,2;2,2;3,2;3,3` (chemin1 = plus court). les chemins sont séparés par le caractère "_" et il y a max 10 solutions
---

## Fonctions principales

|             Fonction             |                                 Rôle                                   |
|----------------------------------|------------------------------------------------------------------------|
| `strToMatrix()`                  | Convertit la chaîne `val_map` en matrice `int**`.                      |
| `matrixToStr()`                  | Reconvertit une matrice `int**` en chaîne pour affichage.              |
| `transformetat()`                | Inverse toutes les portes (5 ↔ 6) dans une matrice.                    |
| `ajouterSolution()`              | Ajoute un chemin à la liste triée des solutions par taille croissante. |
| `rechercherChemins()`            | Explore tous les chemins pour un labyrinthe **sans portes**.           |
| `rechercherCheminsAvecPortes()`  | Explore tous les chemins pour un labyrinthe **avec portes/boutons**.   |
| `resoudreLabyrintheSansPortes()` | Fonction principale sans gestion de portes.                            |
| `resoudreLabyrintheAvecPortes()` | Fonction principale avec gestion dynamique de l’état des portes.       |
| `afficherSolutions()`            | Affiche les chemins trouvés pour debug.                                |
| `libererSolutions()`             | Libère toute la mémoire utilisée par la liste des chemins.             |

---

## Logique de résolution

- **Algorithme utilisé** : exploration en **profondeur (DFS)** sans backtracking, et copie de l’état pour chaque branche.
- **État des portes** regarde l'etat pour savoir s'il peut passer ou non
- Tous les chemins sont sauvegardés et triés par longueur.


---


