Donne moi ce code en entier afin de :
Rendre possible l'option :
Random labyrinthe.
Pour cela utilise :
.exe fonctionnel arg 1 : val taille          arg 2 : difficulte
sortie : taille_map
exemple :
dans cmd: gen.exe
Entrez la taille du labyrinthe entre 4 et 89: 5
Entrez la difficulte: 4
sortie: 6_000200;010110;011010;001010;001110;003000
Avant le _ c'est la taille et après c'est la valeur de la matrice.
quand l'utilisateur choisira l'option random labyrinth :
Il devra choisir sa difficulté entre 1 et 5 et s'il veut des portes/boutons ou pas.
Le php envera à gen.exe : argument 1 taille de la matrice voulue
argument 2 : difficulte entre 1 et 10 (impaire = sans porte et pair avec porte)
Tu transformera donc le entre 1 et 5 avec ou sans porte en un nombre entre 1 et 10
A savoir que l'algo peut retourner une matrice d'une taille différente que celle demandée car la génération de la taille demandé pour la difficultée a échouée.
Le joeuur joue ensuite sur la map et peut sauvegarder sa map comme l'une des siennes à l'aide d'un bouton "save map".