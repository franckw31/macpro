<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Règlement Officiel du Poker de Tournoi</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            max-width: 100%;
            margin: 0 auto;
            padding: 20px 10px;
            background: url('https://images.unsplash.com/photo-1579546929518-9e396f3cc809') no-repeat center center fixed;
            background-size: cover;
            color: #333;
            padding-top: 80px; /* Réduit le padding-top initial de 100px */
            padding-bottom: 70px;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
            margin-top: 25px;
        }
        .article {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 30px;
            margin: 20px 0;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .article-header {
            font-weight: bold;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        /* Styles pour la recherche */
        .search-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        #searchInput {
            width: 50%; /* Réduit la largeur de l'input */
            padding: 12px;
            font-size: 16px;
            border: 2px solid #2c3e50;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        #searchInput:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 2px 10px rgba(52,152,219,0.3);
        }
        
        .highlight {
            background-color: rgba(255, 255, 0, 0.5);
            padding: 2px 4px;
            border-radius: 3px;
            transition: background-color 0.3s ease;
        }
        
        #searchResults {
            margin: 5px 0; /* Réduit les marges */
            padding: 10px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 5px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        /* Styles pour la navigation des résultats */
        .search-navigation {
            margin-top: 5px; /* Réduit la marge supérieure */
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: center; /* Centre les éléments */
        }
        
        .nav-button {
            padding: 5px 12px; /* Réduit le padding des boutons */
            background: #34495e;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .nav-button:hover:not(:disabled) {
            background: #2c3e50;
            transform: translateY(-1px);
        }
        
        .nav-button:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .current-match {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            padding: 5px 15px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .sub-article {
            background-color: rgba(255, 255, 255, 0.8);
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .sub-article:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        /* Style pour le footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(44, 62, 80, 0.95);
            color: white;
            text-align: center;
            padding: 15px;
            font-size: 14px;
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 1000;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-links a {
            color: white;
            text-decoration: none;
            margin: 0 15px;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: #3498db;
        }

        /* Style pour le header fixe */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            padding: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            z-index: 1000;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .search-container {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            max-width: 800px;
        }

        #searchInput {
            flex: 1;
            padding: 8px;
            font-size: 14px;
            border: 2px solid #2c3e50;
            border-radius: 4px;
            margin: 0;
        }

        #searchResults {
            min-width: 100px;
            margin: 0;
            padding: 5px 10px;
            text-align: center;
        }

        .search-navigation {
            margin: 0;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .nav-button {
            padding: 4px 10px;
            font-size: 12px;
        }

        .current-match {
            min-width: 50px;
            text-align: center;
        }

        @media screen and (max-width: 768px) {
            body {
                padding: 10px 5px;
                padding-top: 70px;
                padding-bottom: 60px;
            }

            h1 {
                font-size: 1.5em;
                padding: 15px 10px;
            }

            h2 {
                font-size: 1.2em;
            }

            .article {
                padding: 15px;
                margin: 15px 0;
            }

            .sub-article {
                padding: 15px;
                margin: 10px 0;
            }

            .search-container {
                padding: 0 5px;
            }

            #searchInput {
                width: 100%;
                padding: 8px;
                font-size: 14px;
            }

            .nav-button {
                padding: 4px 8px;
                font-size: 12px;
            }

            .current-match {
                min-width: 40px;
                font-size: 12px;
            }

            .footer {
                padding: 10px;
            }

            .footer-content {
                flex-direction: column;
                gap: 10px;
            }

            .footer-links {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }

            .footer-links a {
                margin: 0 5px;
            }

            .gains-table {
                font-size: 12px;
            }

            .gains-table th, 
            .gains-table td {
                padding: 4px;
            }
        }

        @media screen and (max-width: 480px) {
            .header-content {
                flex-direction: column;
            }

            .search-navigation {
                flex-wrap: wrap;
                justify-content: center;
                gap: 5px;
            }

            .nav-button {
                flex: 1;
                min-width: 80px;
            }

            .gains-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Rechercher dans le règlement...">
                <div id="searchResults"></div>
                <div class="search-navigation">
                    <button id="prevMatch" class="nav-button" disabled>Précédent</button>
                    <span class="current-match">0/0</span>
                    <button id="nextMatch" class="nav-button" disabled>Suivant</button>
                </div>
            </div>
        </div>
    </header>

    <h1>REGLEMENT OFFICIEL DU POKER WPT 2025</h1>
    <p class="subtitle">No Limit Hold'Em – Sans Croupier Version 2025-02</p>

    <div class="article">
        <div class="article-header">ARTICLE 1 : CONCEPTS GENERAUX</div>
        
        <div class="sub-article">
            <h2>ARTICLE 1.1 : REGLE DE BASE – MISE A DISPOSITION DU REGLEMENT ET ESPRIT DU JEU</h2>
            <p><?php echo htmlspecialchars("L'organisateur du tournoi a obligation de tenir le ROPTA à disposition des participants, ainsi que le règlement du tournoi avec ses règles spécifiques s'il y en a."); ?></p>
            <p><?php echo htmlspecialchars("Les responsables de tournois (floors, directeurs de tournois) se doivent de considérer l'intérêt et l'équité du jeu comme la première priorité dans leur processus de prise de décision. Des circonstances inhabituelles peuvent mener à des décisions dans lesquelles l'équité du jeu prime sur les règles techniques. Les responsables peuvent donc être amenés à prendre une décision contraire au règlement. La décision du responsable est définitive."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 1.2 : RESPONSABILITE DES JOUEURS – REGLE GENERALE</h2>
            <p><?php echo htmlspecialchars("La participation à un tournoi vaut pleine et entière acceptation de son règlement, dont le joueur doit prendre connaissance. Tout participant se doit de respecter l'organisation, les autres joueurs, la presse et le public. Les joueurs doivent être clairement identifiables à tout moment."); ?></p>
            <p><?php echo htmlspecialchars("Tout participant à un tournoi a des responsabilités dans le bon déroulement de celui–ci : vérifier son inscription et son placement à la table, rester à la table et protéger sa main lorsqu’il est en jeu, suivre l’action et protéger son droit d’agir, vérifier le nombre de cartes qu’il a reçues avant toute action substantielle à la table, agir à son tour en utilisant des termes et des gestes corrects, jouer en temps voulu, garder ses cartes visibles et ses jetons correctement rangés (voir §3.3), montrer ses cartes correctement lorsqu’il participe à l’abattage, intervenir s’il voit une erreur, changer de table rapidement en cas de déplacement, respecter le principe « une main un joueur », connaître le règlement et l’appliquer, informer les responsables s’il subit ou constate un comportement discriminatoire ou blessant et contribuer au bon déroulement de l’événement où tous les joueurs se sentent bien accueillis."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 1.3 : MATERIEL A LA TABLE</h2>
            <p><?php echo htmlspecialchars("Il est interdit de communiquer par téléphone à la table de poker et les sonneries, musiques, … ne doivent pas être audibles des autres joueurs. Les règles maison sont appliquées concernant les autres utilisations d’appareils électroniques qui ne doivent en aucun cas créer de dérangement, retarder le jeu ou apporter un avantage au joueur."); ?></p>
            <p><?php echo htmlspecialchars("L’utilisation d’un « Card Guard » est recommandée pour protéger les cartes, toutefois un « Card Guard » doit être de taille raisonnable, ne pas être de nature à pouvoir choquer un autre participant (signes religieux, objets à connotation irrespectueuse…) et ne pas gêner la bonne visibilité des cartes et des jetons du joueur."); ?></p>
            <p><?php echo htmlspecialchars("Il ne devrait y avoir aucune affaire personnelle sur la table en–dehors du « Card Guard », des jetons et des cartes."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 1.4 : INTERDICTION AUX MINEURS</h2>
            <p><?php echo htmlspecialchars("Seuls les joueurs majeurs peuvent participer au tournoi. Si un joueur triche sur son identité et/ou son âge et que le Directeur de Tournoi s’en aperçoit, il sera immédiatement exclu du tournoi."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 1.5 : LANGUE OFFICIELLE</h2>
            <p><?php echo htmlspecialchars("La langue officielle est le français. Une tolérance existe pour l’anglais en ce qui concerne les mots suivants : bet, raise, call, fold, check et all–in (voir §1.6)."); ?></p>
            <p><?php echo htmlspecialchars("Si un joueur non francophone souhaite participer, c’est possible à condition que l’ensemble de ses propos à la table puissent être compris de l’ensemble des autres joueurs (directement ou via une traduction)."); ?></p>
            <p><?php echo htmlspecialchars("Les conditions sont les mêmes pour l’éventuelle participation d’une personne communicant avec le langage des signes."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 1.6 : TERMINOLOGIE ET GESTES</h2>
            <p><?php echo htmlspecialchars("Les termes officiels sont simples, il est impossible de se méprendre sur leur utilisation, et ils sont consacrés par l’usage : mise/ouverture (bet), relance (raise), payé/suivi (call), passe (fold), parole (check), tapis (all in)."); ?></p>
            <p><?php echo htmlspecialchars("L’utilisation de termes qui n’entrent pas dans cette nomenclature est un risque pour le joueur car cela peut aboutir à des décisions différentes de l’action prévue par celui–ci. Il est donc de la responsabilité du joueur d’être extrêmement clair dans ses annonces. Il est convenu que taper sur la table signifie « parole » ou « check »."); ?></p>
            <p><?php echo htmlspecialchars("Miser ses jetons de façon non habituelle pourra entraîner des incompréhensions et être jugé par les responsables du tournoi d’une façon différente du choix initial envisagé par le joueur."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 1.7 : RECLAMATIONS</h2>
            <p><?php echo htmlspecialchars("Le Directeur de Tournoi et les Floors sont seuls responsables des décisions prises. Leur décision est irrévocable et aucune réclamation ne sera acceptée. De même, aucune réclamation ne peut être faite auprès d’un quelconque tiers (personne ou organisme extérieur)."); ?></p>
            <p><?php echo htmlspecialchars("Exception : dans de rares cas et sur les seules compétitions officielles de la FFPA, une réclamation peut être faite directement à la commission compétition par mail à competitions@ffpoker.org pour une prise en compte ultérieure."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 1.8 : RETARDS</h2>
            <p><?php echo htmlspecialchars("Un délai de retard, exprimé en niveaux de jeu (rounds) peut être spécifié aux joueurs dans un règlement. Si aucune disposition en ce sens n’est prise, la règle est d’autoriser les retardataires à entrer en jeu jusqu’à la fin du deuxième niveau."); ?></p>
            <p><?php echo htmlspecialchars("Durant son retard, les cartes du joueur lui sont distribuées et ses blindes sont prélevées. Les préinscriptions sont fortement conseillées. Les enregistrements tardifs avec un stack complet sont à éviter."); ?></p>
            <p><?php echo htmlspecialchars("Sauf si les places sont gérées informatiquement, un joueur qui entre tardivement dans le tournoi est positionné de façon à payer la grosse blinde le plus vite possible sur sa table."); ?></p>
            <p><?php echo htmlspecialchars("Dans un tournoi en plusieurs journées, il est admis qu’un joueur qualifié à l’issue du premier jour ne sera pas éliminé avant la disparition de ses jetons en cas d’absence, sauf s’il a prévenu ou si le règlement du tournoi prévoit qu’il doit se présenter dans un temps défini pour ne pas être retiré du tournoi."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 1.9 : JOUEUR SORTANT</h2>
            <p><?php echo htmlspecialchars("Un joueur est déclaré hors du tournoi lorsqu’il n’a plus de jetons ou lorsqu’il annonce clairement son abandon au Directeur de Tournoi."); ?></p>
            <p><?php echo htmlspecialchars("Un joueur qui a des jetons et quitte le tournoi sans intention de revenir est encouragé à prévenir le responsable du tournoi avant de quitter afin que ses jetons soient retirés du tournoi."); ?></p>
            <p><?php echo htmlspecialchars("Un joueur peut toutefois quitter le tournoi sans annoncer son abandon, auquel cas ses jetons sont maintenus en jeu (les blindes tournent) et il ne sera éliminé que lorsqu’il n’en aura plus. Il est recommandé aux joueurs qui s’absentent longtemps de prévenir le Directeur de Tournoi. Même s’il est à tapis, un joueur absent ne peut en aucun cas remporter un coup."); ?></p>
            <p><?php echo htmlspecialchars("Si un joueur annonce un départ définitif, il faut lui demander d’aller le confirmer au responsable du tournoi. Dans ce cas, ses jetons sont retirés du tournoi et il est classé comme sortant au moment de son annonce."); ?></p>
            <p><?php echo htmlspecialchars("Un Directeur de Tournoi peut considérer qu’un joueur abandonne même si ce dernier ne l’a pas clairement annoncé s’il dispose d’un faisceau d’indices suffisant (ex : le dernier jour d’un tournoi le joueur quitte la salle en annonçant qu’il a un avion à prendre)."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 1.10 : SORTANTS SIMULTANES</h2>
            <p><?php echo htmlspecialchars("Si deux joueurs (ou plus) sont éliminés en même temps à la même table ou dans le cas de la bulle, celui qui possédait le plus gros tapis au début du coup sera le mieux classé. Si les deux joueurs possédaient le même tapis ou s’ils étaient assis à deux tables différentes et qu’il ne s’agit pas de la bulle, ils sont définitivement ex–æquo. S’il s’agit de la bulle, voir le §2.7."); ?></p>
            <p><?php echo htmlspecialchars("Par exemple, le joueur peut entrer à n’importe quelle position mais :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("S’il entre au bouton, il peut jouer à cette position si cela ne modifie pas l’avancement de la grosse blinde. Sinon, il passe le bouton à sa gauche mais peut jouer le coup. Un joueur arrivant à une place précédemment occupée par le joueur de petite blinde (et qui vient juste d’être éliminé) peut jouer immédiatement au bouton puisque les deux règles d’avance des blindes (à sa gauche) et de droit au bouton (le joueur à sa droite a bien été au bouton le coup précédant) sont respectées."); ?></li>
                <li><?php echo htmlspecialchars("S’il entre en petite blinde, il ne peut jouer que s’il occupe la place de l’ancienne grosse blinde (celui–ci ayant été éliminé au coup précédent) et il assumera la petite blinde, même s’il était déjà de blinde le coup précédent surson ancienne table puisque les deux règles d’avance de la grosse blinde (à sa gauche) et de droit au bouton (à sa droite) sont respectées."); ?></li>
                <li><?php echo htmlspecialchars("S’il rentre en grosse blinde, il assumera la grosse blinde ainsi que l’ante Big Blinde si c’est le format du tournoi, même s’il était déjà de grosse blinde le coup précédent sur son ancienne table."); ?></li>
                <li><?php echo htmlspecialchars("Cas (très) spécial : le bouton avance bien mais la BB recule ! Cette situation peut arriver même en respectant la règle primordiale, exemple : place 1 = donneur / place 2 = SB / places 3 et 4 = vides / place 5 = BB … et BB saute ! … Plus trois nouveaux joueurs sont assis aux places 3,4 et 5 … Cela donne ensuite : place 2 = donneur / place 3 = SB / place 4 = BB"); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Dans tous les cas où il peut jouer, un nouveau joueur à la table reçoit des cartes dans la simple mesure ou la donne n’a pas commencé et cela même si le mélange et les mises initiales (blindes et ante) sont terminés. Il devra bien sûr s’acquitter de ses mises, si nécessaire en faisant modifier les mises des autres joueurs."); ?></p>
            <p><?php echo htmlspecialchars("Note : en cas de gestion par un logiciel de tournoi, les règles de cassage et d’équilibrage du logiciel prévalent. Le logiciel doit toutefois être paramétré (autant que possible) pour réaliser les équilibrages."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 2.4: FERMETURE DE TABLES</h2>
            <p><?php echo htmlspecialchars("L’ordre de fermeture des tables sera prédéterminé et l’information sera disponible pour les participants, idéalement en l’affichant sur l’écran du tournoi. Le Directeur de Tournoi peut toutefois le modifier en cas de nécessité, en prenant soin d’informer les joueurs."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 2.5: LE DROIT AU BOUTON</h2>
            <p><?php echo htmlspecialchars("Si un mouvement incorrect du bouton est découvert avant qu’une Action Substantielle (AS, voir§6.7) ne se produise, l’erreur sera corrigée. Cependant, si une AS a eu lieu, le jeu continuera. Par exemple : si le bouton est déplacé deux fois et qu’une AS a lieu, l’erreur sera maintenue et le bouton ne sera pas rétabli pour la main suivante. Tous les joueurs ont la responsabilité de surveiller le placement du bouton et de signaler s’ils voient une erreur (voir§1.2)."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 2.6 : NOMBRE DE JOUEURS A LA TABLE FINALE</h2>
            <p><?php echo htmlspecialchars("Une table finale sera constituée au maximum de 9 joueurs et sera de préférence constituée d’un nombre impair de joueurs afin que les deux dernières tables se terminent avec le même nombre de joueurs. Dans le cas où le tournoi est joué avec un nombre pair par table, la table finale sera jouée avec un joueur de plus que le format du tournoi (9 joueurs en tournoi à 8 maximum par table, 7 joueurs en tournoi à 6 maximum par table, …)."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 2.7 : JEU EN MAIN PAR MAIN</h2>
            <p><?php echo htmlspecialchars("Le jeu en « main par main » est conseillé lorsqu’il reste un joueur à éliminer avant la table finale d’un tournoi ou avant une « bulle » qui peut avoir une forte incidence sur le jeu : début des places payées, palier important dans les places payées, …"); ?></p>
            <p><?php echo htmlspecialchars("Dans une telle situation, un coup joué de trop peut s’avérer fatal à un petit tapis. La recherche de la neutralité implique donc que tous les participants du tournoi doivent jouer le même nombre de coups dans le même laps de temps."); ?></p>
            <p><?php echo htmlspecialchars("Usuellement, le Directeur de Tournoi gère le « main par main » avec l’aide des floors, selon le nombre de tables concernées, avec les consignes suivantes :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Le début du « main par main » est annoncé : « à la fin du coup en cours, le jeu se déroulera en main par main »."); ?></li>
                <li><?php echo htmlspecialchars("Toutes les tables doivent attendre le signal de fin du coup sur l’ensemble des tables pour débuter un nouveau coup."); ?></li>
                <li><?php echo htmlspecialchars("Lorsqu’un joueur est payé à tapis au cours d’un « main par main », les cartes ne sont dévoilées que lorsque le jeu est terminé sur l’ensemble des autres tables et il est demandé aux joueurs de ne donner aucune indication sur leurs cartes afin de ne pas influencer le jeu en cours sur les autres tables. Une fois le jeu terminé sur l’ensemble des tables, les cartes sont dévoilées et jouées normalement."); ?></li>
                <li><?php echo htmlspecialchars("Lorsque deux joueurs sont éliminés à la même table au cours de la même main d’un « main par main », ils sont départagés en fonction de leur stack avant le coup qui les élimine. S’ils sont sur 2 tables différentes, ils seront classés à égalité (avec partage du gain du dernier payé le cas échéant)."); ?></li>
                <li><?php echo htmlspecialchars("Il est conseillé d’arrêter le chronomètre au début de la première main en « main par main » et de le faire avancer d’exactement 2 minutes à la fin de chaque main jouée pendant cette procédure. Le chronomètre est ensuite relancé normalement à la fin du « main par main »."); ?></li>
            </ul>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 3 : PRATIQUES GENERALES</h2>
            <p><?php echo htmlspecialchars("ARTICLE 3.1 : NOUVELLE MAIN, NOUVEAU NIVEAU"); ?></p>
            <p><?php echo htmlspecialchars("Une main commence dès que le pot précédent est attribué. L’attribution d’un pot est le fait de déterminer le vainqueur d’un coup. Quand le temps est écoulé dans un niveau de blindes pendant qu’une main est jouée (et cela même avant la distribution des cartes), le nouveau niveau de blindes s’applique à la main suivante. Si une main débute par erreur sur les blindes du niveau précédent, celle-ci doit continuer ainsi du moment qu’une action substantielle est intervenue (voir §6.7)"); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 3.2 : COLOR UP ET CHIP RACE</h2>
            <p><?php echo htmlspecialchars("À tout moment, le directeur de tournoi peut proposer ou imposer aux joueurs un échange de jetons (ou color up)."); ?></p>
            <p><?php echo htmlspecialchars("L’échange doit nécessairement se faire pour des montants exacts."); ?></p>
            <p><?php echo htmlspecialchars("Cet échange peut être total (implique la disparition totale des jetons de petite valeur qui ne sont plus utiles du fait de l’augmentation des blindes) ou partiel (suppression de quelques jetons, dans le cas où un joueur a trop de jetons de petites valeurs). L’échange, qu’il soit total ou partiel, doit toujours être fait par un floor et de préférence sous la surveillance des joueurs à la table."); ?></p>
            <p><?php echo htmlspecialchars("La procédure de color up et de chip race est la suivante :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Le floor annonce au cours d’un niveau le chip race d’une valeur en cours du niveau ou lors de la pause suivante. Les joueurs ne doivent plus utiliser les jetons de la valeur annoncée, sauf dans le cas où ils sont à tapis."); ?></li>
                <li><?php echo htmlspecialchars("Les joueurs procèdent aux échanges de cette valeur (color up) entre eux à la table afin qu’un seul joueur regroupe quasiment l’ensemble des jetons de cette valeur sans porter préjudice au rythme de la partie. Il est conseillé que ce soit le plus gros tapis de la table qui regroupe ainsi les jetons en procédant à des échanges à valeur égale et il peut utiliser un rack pour ranger efficacement les jetons regroupés."); ?></li>
                <li><?php echo htmlspecialchars("Lorsque le color up est terminé à la table, les joueurs indiquent au floor que le chip race peut avoir lieu."); ?></li>
                <li><?php echo htmlspecialchars("Le floor vient alors procéder au chip race pour les jetons supplémentaires (ceux ne permettant pas un échange à valeur exacte) :"); ?></li>
                <ul>
                    <li><?php echo htmlspecialchars("les joueurs avancent distinctement les jetons restants de la valeur à supprimer,"); ?></li>
                    <li><?php echo htmlspecialchars("en commençant par la place 1, chaque joueur reçoit une carte en échange de chaque jeton supplémentaire restant,"); ?></li>
                    <li><?php echo htmlspecialchars("la plus grosse (ou les plus grosses s’il y a plusieurs nouveaux jetons en jeu) gagnant un jeton de valeur supérieure (un joueur ne peut gagner qu’un seul jeton par ce procédé)."); ?></li>
                    <li><?php echo htmlspecialchars("Le nombre total des jetons supérieurs distribués est calculé sur l’ensemble des jetons inférieurs restant sur la table, arrondi au-dessus dès 50% du jeton supérieur atteint, sinon arrondi en-dessous (par exemple si on enlève les jetons de 100, on donnera 1 seul jeton de 500 s’il reste sept jetons de 100 et on donnera deux jetons de 500 s’il en reste huit)."); ?></li>
                    <li><?php echo htmlspecialchars("En cas d’égalité de cartes (exemple : deux as distribués et un seul jeton à donner) la valeur des couleurs des cartes sera utilisée dans l’ordre suivant du plus fort au moins fort : pique, cœur, carreau et trèfle."); ?></li>
                    <li><?php echo htmlspecialchars("Le floor prend les jetons nécessaires au chip race auprès du joueur qui les a regroupés et lui donne en échange les jetons récupérés lors de cette procédure."); ?></li>
                </ul>
                <li><?php echo htmlspecialchars("Ensuite, il procède à un échange total des jetons de la valeur à retirer avec le joueur qui les a regroupés (avec le même arrondi que lors du chip race). Il est conseillé d’utiliser pour cet échange un ou plusieurs jetons de la plus grosse valeur disponible pour éviter de réinjecter des jetons qui seront prochainement retirés du tournoi."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Un joueur ne peut être éliminé du tournoi par le chip race : un joueur perdant son dernier jeton lors d’un chip race se verra remettre un jeton de la plus petite valeur encore en jeu. Ce jeton sera un jeton en plus de ceux accordés aux joueurs par le chip race."); ?></p>
            <p><?php echo htmlspecialchars("Si des jetons de l’ancienne plus petite valeur (qui ont déjà été retirés) sont retrouvés après le chip race, ils seront échangés uniquement pour des montants strictement identiques. Les jetons insuffisants ou supplémentaires seront retirés du tournoi."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 3.3 : LES JETONS DES JOUEURS (STACKS) DOIVENT ETRE VISIBLES</h2>
            <p><?php echo htmlspecialchars("Tout joueur doit pouvoir estimer les tapis de ses adversaires. En conséquence il est obligatoire de ranger les jetons par valeur et par piles de tailles équivalentes. Il est fortement recommandé de faire des piles de 10 ou 20 jetons selon le nombre total de jetons dans le tapis. Les responsables du tournoi peuvent obliger un joueur à respecter cette règle."); ?></p>
            <p><?php echo htmlspecialchars("Les jetons de la plus forte valeur doivent rester bien visibles. La seule annonce qui est obligatoire si elle demandée est l’annonce exacte du montant d’une mise ou d’une relance. Dans le dernier cas, le décompte doit être fait sur la totalité de la mise."); ?></p>
            <p><?php echo htmlspecialchars("Le fait d’annoncer tapis étant une mise, le joueur qui a fait tapis doit bien annoncer le montant du tapis misé sur la demande d’un adversaire encore en lice (voir §9.15)"); ?></p>
            <p><?php echo htmlspecialchars("Un joueur peut demander le nombre de jetons par pile d’un adversaire ou la taille totale du tapis MAIS l’adversaire n’a aucune obligation de répondre."); ?></p>
            <p><?php echo htmlspecialchars("Les supports de jetons (racks) ne peuvent être utilisés en dehors du déplacement des jetons d’une table à une autre ou le regroupement de jetons lors d’un color up."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 3.4 : CHANGEMENT DE JEU – CARTES SUR LA TABLE</h2>
            <p><?php echo htmlspecialchars("Les changements de jeux de cartes ne peuvent être demandés par les joueurs que si une carte du jeu utilisé est marquée."); ?></p>
            <p><?php echo htmlspecialchars("Le Directeur de Tournoi est seul juge de la nécessité du changement et peut imposer un changement de jeu à tout moment."); ?></p>
            <p><?php echo htmlspecialchars("Lors d’un coup de poker, l’ensemble des cartes doit rester sur la table, visible de tous. De même lorsqu’un joueur mélange les cartes, il ne peut les prendre et les mélanger ailleurs que sur la table."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 3.5 : DEMANDE DU TIME</h2>
            <p><?php echo htmlspecialchars("Quand un délai raisonnable s’est écoulé (selon l’usage, ce délai dépend de l’importance du coup à jouer et n’est jamais inférieur à une minute), le « Time » peut être demandé. Cette demande peut être faite par tout joueur en jeu dans le tournoi assis à sa table ou un responsable du tournoi. Le « Time » doit être validé par l’arrivée du floor et peut être refusé ou retardé par ce dernier s’il estime que la demande est faite trop rapidement."); ?></p>
            <p><?php echo htmlspecialchars("Le joueur qui hésite se verra donner 30 secondes pour prendre une décision, les 5 dernières secondes étant décomptées à haute voix. Cette durée peut être modifiée si le floor le juge nécessaire. Si le joueur n’a toujours pas agi à la fin du décompte, sa main sera brûlée. S’il agit au moment de la fin du décompte, sa décision sera prise en considération."); ?></p>
            <p><?php echo htmlspecialchars("Le « Time » sera décompté par un floor ou par un joueur désigné par celui-ci et non engagé dans le coup."); ?></p>
            <p><?php echo htmlspecialchars("L’abus de demande de « Time » peut être sanctionné, de même que l’abus de lenteur."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 3.6 : RABBIT HUNTING</h2>
            <p><?php echo htmlspecialchars("Le « rabbit hunting » n’est pas autorisé. Le « rabbit hunting » est le fait de révéler les cartes qui “seraient venues” si la main n’avait pas pris fin."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 3.7 : TRANSPORT DE JETONS</h2>
            <p><?php echo htmlspecialchars("Sauf indication inverse du Directeur de Tournoi, les joueurs ne peuvent porter ou transporter les jetons de tournoi d’une manière où ils ne sont pas visibles. Un joueur qui agit de la sorte annule les jetons et risque l’élimination. Les jetons annulés seront retirés du jeu. Ceci est valable même pour une pause de courte durée. Si possible, l’organisation mettra à disposition des joueurs devant changer de table des racks pour faire ce déplacement."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 4 : AVANT LE JEU</h2>
            <p><?php echo htmlspecialchars("ARTICLE 4.1 : PRESENCE DES JOUEURS A LA TABLE"); ?></p>
            <p><?php echo htmlspecialchars("Un joueur doit être assis à sa place ou, si ponctuel et justifié, debout à portée de son jeu lorsque la première carte de la donne est distribuée pour pouvoir jouer la main. Dans tout autre cas la main est déclarée morte. Le joueur dont la main est déclarée morte ne pourra pas voir ses cartes et elles seront jetées au rebut à la fin de la donne. Les éventuelles blindes et antes lui seront prélevées."); ?></p>
            <p><?php echo htmlspecialchars("Note : cette règle fait partie de celles qui diffèrent régulièrement, n’hésitez pas à vous renseigner avant de disputer un tournoi."); ?></p>
            <p><?php echo htmlspecialchars("Un joueur doit être à portée de sa place pour conserver sa main vivante, pour demander le « Time », … Cela signifie qu’il doit pouvoir toucher son siège sans se déplacer et avoir son attention portée sur la table."); ?></p>
            <p><?php echo htmlspecialchars("Un joueur qui s’éloigne de la table alors qu’il est toujours en jeu verra sa main jetée automatiquement, sauf s’il est à tapis auquel cas sa main reste vivante mais s’il n’est pas éliminé du tournoi, il devra être sanctionné pour cet éloignement."); ?></p>
            <p><?php echo htmlspecialchars("Un joueur qui utilise son téléphone ou un autre objet électronique de façon active alors qu’il est en jeu dans le coup sera averti. Il verra sa main jetée automatiquement en cas de récidive, et cela même s’il est à tapis. De plus, le joueur pourra être sanctionné."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 4.2 : FORMAT DES ANTE</h2>
            <p><?php echo htmlspecialchars("Le format « Ante big blinde (BB) » devient la norme, le ROPTA conseille son utilisation dans les tournois, toutefois le format des ante peut être défini par les organisateurs."); ?></p>
            <p><?php echo htmlspecialchars("FORMAT ANTE (BB) BIG BLINDE"); ?></p>
            <p><?php echo htmlspecialchars("Le joueur à la big blinde paie une ante qui est versée au pot. La valeur de l’ante est définie par la structure. Un joueur à tapis peut prétendre à l’intégralité de l’ante payée par la big blinde quelle que soit la taille de son tapis."); ?></p>
            <p><?php echo htmlspecialchars("Dans ce format, la big blinde est prioritaire sur l’ante et donc un joueur disposant de moins de 2 BB et se retrouvant en big blinde paiera d’abord sa big blinde puis l’ante pour le reste de ses jetons s’il en a. Si le joueur n’a pas de quoi payer plus que sa BB, il n’y a exceptionnellement pas d’ante en jeu et le joueur à tapis jouera pour le montant qu’il a posé en BB multiplié par le nombre de fois où la BB aura été payée. Cela ne modifie pas les montants des relances des autres joueurs, le call minimum restant d’une BB."); ?></p>
            <p><?php echo htmlspecialchars("EXEMPLES :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Cas A : Blindes 500/1 000 (1 000), le joueur A est en BB avec 800. Il est à tapis pour 800 de BB. Le coup se déroule sans ante et le joueur A ne pourra gagner que 800 par joueur dans le coup."); ?></li>
                <li><?php echo htmlspecialchars("Cas B : Blindes 500/1 000 (1 000), le joueur A est en BB avec 1 400. Il est à tapis avec 1 000 de BB et 400 d’ante partielle, il pourra gagner l’ante de 400 et 1 000 par joueur dans le coup."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("FORMAT ANTE (BTN) BOUTON"); ?></p>
            <p><?php echo htmlspecialchars("Le joueur au bouton paie une ante qui est versée au pot. La valeur de l’ante est définie par la structure. Un joueur à tapis peut prétendre à l’intégralité de l’ante quelle que soit la taille de son tapis. Si c’est le joueur au bouton qui est à tapis et ne peut pas payer son ante intégralement, il ne peut gagner que le montant de son ante."); ?></p>
            <p><?php echo htmlspecialchars("FORMAT ANTE CLASSIQUE"); ?></p>
            <p><?php echo htmlspecialchars("Tous les joueurs de la table paient une ante qui est versée au pot. La valeur de l’ante est définie par la structure. Un joueur qui n’a pas le montant de l’ante pose son tapis et ne peut gagner que le montant de son tapis multiplié par le nombre de joueurs ayant joué le coup. Un joueur de blinde qui n’a pas de quoi payer celle-ci et l’ante paiera d’abord son ante (et une part de la blinde s’il lui reste des jetons) et ne pourra pas gagner de la part de chaque adversaire plus que ce qu’il a engagé."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 4.3 : BOUTON MORT</h2>
            <p><?php echo htmlspecialchars("Le jeu en tournoi utilise la règle du bouton mort : si le joueur qui devait donner le coup est éliminé, les cartes sont distribuées par celui immédiatement à sa droite tel que le bouton l’aurait fait (le bouton ne peut passer au joueur suivant car il sauterait la petite blinde). De ce fait, il peut ne pas y avoir de petite blinde dans un coup."); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("La petite blinde est éliminée : il y a bouton mort, l’ancienne grosse blinde devient petite blinde, l’ancien UTG devient grosse blinde et l’ancien bouton donne les cartes."); ?></li>
                <li><?php echo htmlspecialchars("La grosse blinde est éliminée : il n’y a pas bouton mort, l’ancienne petite blinde devient bouton et l’ancien UTG devient grosse blinde toute seule. Il y aura un bouton mort au coup suivant si aucun joueur n’arrive."); ?></li>
                <li><?php echo htmlspecialchars("Les deux blindes sont éliminées ensemble : il y a bouton mort, l’ancien UTG devient grosse blinde toute seule et l’ancien bouton donne les cartes."); ?></li>
                <li><?php echo htmlspecialchars("Ante bouton : lorsque le bouton est mort, il n’y a pas d’ante dans le format ante bouton."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Pratique : si le bouton n’est plus dans le coup, c’est le joueur précédent (qui usuellement vient de distribuer le coup précédent) qui distribue pour le « bouton mort » et aucunement un joueur en blinde. Le joueur précédent mélange alors à sa place."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 4.4 : BOUTON EN HEADS -UP</h2>
            <p><?php echo htmlspecialchars("En Heads-up, la petite blinde est au bouton et parle en premier avant le flop, en dernier après le flop. Lorsque le Heads-up commence, le bouton doit être placé devant celui des deux joueurs qui a payé la grosse blinde en dernier, en vertu de la règle qui veut qu’un joueur ne doit pas payer deux fois de suite la grosse blinde. Les cartes sont données en commençant par la grosse blinde."); ?></p>
            <p><?php echo htmlspecialchars("Note : l’ante est due même en Heads Up."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 4.5 : ERREUR DANS LA POSE DES BLINDES</h2>
            <p><?php echo htmlspecialchars("Si le joueur qui doit jouer UTG pose par mégarde une blinde et éventuellement une ante, l’arbitrage doit dépendre des actions :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Si le joueur pose les jetons après avoir reçu ses cartes, on ne peut pas préjuger du fait qu’il les a vues ou pas. Sa pose de jetons sera considérée comme une action de jeu (call ou raise selon les jetons posés et l’éventuel ‘string bet’)."); ?></li>
                <li><?php echo htmlspecialchars("Si les joueurs suivants ont réalisé une action substantielle (voir §6.7) après la pose du joueur fautif, les jetons sont engagés (et l’action devra être corrigée si elle ne correspondait pas à un call ou un raise)."); ?></li>
                <li><?php echo htmlspecialchars("Dans les autres cas (pose avant la distribution des cartes et remarque faite avant la moindre action substantielle), le joueur sera averti mais pourra reprendre ses jetons et jouer normalement le coup."); ?></li>
            </ul>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 5 : LA REGULARITE DE LA DONNE ET LES ANNONCES PAR LE DONNEUR</h2>
            <p><?php echo htmlspecialchars("Afin de sécuriser le jeu et d’éviter des litiges, le donneur doit :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Réunir le muck sans le ranger (comme une défausse)."); ?></li>
                <li><?php echo htmlspecialchars("Réunir le pot au centre de la table ou près de lui sans le trier ni ranger les jetons par valeur."); ?></li>
                <li><?php echo htmlspecialchars("Mettre les cartes brulées de côté, indépendamment des cartes jetées par les joueurs : par exemple sous le pot constitué."); ?></li>
                <li><?php echo htmlspecialchars("Toujours maintenir le paquet de cartes à l’écart du reste du jeu soit en le tenant en permanence dans une main (ce n’est pas forcément facile en self-dealing) soit en le protégeant du reste des cartes par exemple en mettant le bouton dealer systématiquement dessus. Il doit bien évidemment rester visible de l’ensemble des joueurs de la table."); ?></li>
            </ul>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 5.1 : ASSISTANCE A LA DONNE</h2>
            <p><?php echo htmlspecialchars("Dans un tournoi multi tables, il doit y avoir uniformité de traitement de l’ensemble des tables du tournoi. En particulier, le nombre de jeux de cartes doit être le même sur toutes les tables, et si des personnes non en jeu aident à la distribution, ils doivent le faire sur toutes les tables simultanément, sauf cas particulier prévu dans le règlement du tournoi (table TV, …)."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 5.2 : MELANGE DES CARTES</h2>
            <p><?php echo htmlspecialchars("Le jeu se déroule de préférence avec deux paquets de cartes (de dos de couleurs distinctes) à chaque table."); ?></p>
            <p><?php echo htmlspecialchars("Si le jeu se déroule avec un seul paquet, le joueur qui a distribué passe le jeu au suivant qui le mélange et lui fait couper avant de distribuer le coup suivant."); ?></p>
            <p><?php echo htmlspecialchars("Dans tous les cas, il n’est pas admissible qu’un seul joueur quel qu’il soit mélange et coupe les cartes entre deux coups."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 5.3 : PRECISIONS LIEES A LA PRESENCE DE DEUX JEUX DE CARTES</h2>
            <p><?php echo htmlspecialchars("MELANGE ET COUPE DES JEUX"); ?></p>
            <p><?php echo htmlspecialchars("La seule règle impérative reste que ce ne soit pas la même personne qui mélange et qui coupe chaque jeu. La procédure suivante est recommandée, elle peut être différente localement."); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Avant de distribuer un coup, le dealer coupe le jeu qu’il a reçu, peu importe s’il y a déjà eu une coupe avant."); ?></li>
                <li><?php echo htmlspecialchars("Lorsque le coup est fini, il récupère toutes les cartes et mélange le jeu."); ?></li>
                <li><?php echo htmlspecialchars("Une fois qu’il a fini de mélanger le jeu, il le passe à celui qui le distribuera la prochaine fois."); ?></li>
                <li><?php echo htmlspecialchars("Le jeu peut se dérouler avec 1 ou 2 cartes de coupe par table, selon les habitudes et en respectant ce qui suit."); ?></li>
                <li><?php echo htmlspecialchars("S’il y a 2 cartes de coupe, le jeu est présenté avec la carte de coupe AU-DESSUS du paquet, pour faire penser au dealer suivant de couper le jeu."); ?></li>
                <li><?php echo htmlspecialchars("S’il n’y a qu’une carte de coupe, elle est donnée par le dealer d’un coup au dealer du coup suivant pour faire penser au dealer suivant de couper le jeu."); ?></li>
                <li><?php echo htmlspecialchars("Dans les rares cas où un joueur doit distribuer avec le jeu qu’il a mélangé (sortie des 2 joueurs à sa gauche entre le coup où il donnait et le coup suivant), il fera couper le jeu par un autre joueur de la table avant de distribuer."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("ERREUR DANS LE JEU DU FAIT DE DEUX JEUX DIFFERENTS"); ?></p>
            <p><?php echo htmlspecialchars("Si la procédure précédente est appliquée, le nombre d’erreurs devrait être réduit. Toutefois, cela arrive qu’à la fin d’un coup, on se rende compte que les deux jeux ont été utilisés dans le même coup, ce qui est bien évidemment une erreur."); ?></p>
            <p><?php echo htmlspecialchars("SI L’UTILISATION DU 2E JEU EST DECOUVERTE AU FLOP"); ?></p>
            <p><?php echo htmlspecialchars("Si l’utilisation du 2e jeu est découverte pendant le tour de mises du flop, les cartes du 2e jeu sont écartées et les mises sont rendues aux joueurs, puis un flop est distribué avec le 1er jeu et le tour de mises du flop redémarre normalement."); ?></p>
            <p><?php echo htmlspecialchars("Le 2e jeu est mélangé à nouveau avant la donne suivante."); ?></p>
            <p><?php echo htmlspecialchars("SI L’UTILISATION DU 2E JEU DECOUVERTE A LA TURN OU A LA RIVER"); ?></p>
            <p><?php echo htmlspecialchars("Si l’utilisation du 2e jeu est découverte pendant le tour de mise de la turn ou de la river, le coup est annulé et les mises sont rendues aux joueurs. Le coup est intégralement rejoué avec le même dealer. Cela doit être considéré comme une fausse donne (voir §6.1) donc un joueur absent lors de la donne initiale mais présent lors de la nouvelle donne recevra des cartes et pourra jouer normalement."); ?></p>
            <p><?php echo htmlspecialchars("SI L’UTILISATION DU 2E JEU DECOUVERTE APRES LA FIN DU COUP"); ?></p>
            <p><?php echo htmlspecialchars("Si les jetons du pot sont dans le stack du gagnant avant la découverte de l’erreur, le coup est validé et ne peut pas être rejoué. Par exception, si le coup s’est joué à tapis pre-flop et que les montants des tapis peuvent être précisément reconstitués, le coup doit être rejoué avec le bon jeu tant que le coup suivant n’est pas démarré. Cette décision est de la responsabilité du Directeur de Tournoi."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 5.4 : FEUILLE MORTE</h2>
            <p><?php echo htmlspecialchars("Toute carte apparaissant directement face visible dans le paquet (“boxed card”) doit être clairement montrée à tous les joueurs puis être mise de côté pour le reste du coup. Bien que pouvant influencer tactiquement la fin du coup, elle ne doit en rien modifier la donne et doit être considérée « comme une feuille morte » découverte au milieu du paquet. Deux cartes à l’envers ou plus créent une fausse donne automatique si cela arrive pendant la donne initiale. Si cela arrive après la donne initiale, voir le §7.6."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 5.5 : PAQUET DE CARTES IRREGULIER</h2>
            <p><?php echo htmlspecialchars("S’il apparaît en cours de donne que le paquet de cartes n’est pas régulier (présence d’une carte à dos différent, d’une carte en double, d’un joker ou toute autre carte différente …), la donne est annulée et les jetons restitués aux joueurs, même si ça arrive à la turn ou à la river : blinds et mises sont rendues à leurs propriétaires avant de redistribuer le coup."); ?></p>
            <p><?php echo htmlspecialchars("Cela ne remet nullement en cause les coups précédents terminés, et donc validés (voir le §10.9 « Réclamer le Pot »)."); ?></p>
            <p><?php echo htmlspecialchars("Si deux paquets sont utilisés et qu’il apparaît en cours de donne que les deux paquets ont été utilisés dans la même donne, le coup est annulé uniquement si le coup n’est pas récupérable (des actions ont été faites avec les cartes des deux paquets) et les jetons sont restitués aux joueurs et ce même si ça arrive à la turn ou à la river : on redonne les blindes et les mises à leurs propriétaires avant de redistribuer le coup. Si le coup est récupérable (cas d’un joueur à tapis pré flop pour lequel le tableau aura été distribué avec le mauvais paquet de cartes par exemple), il sera récupéré (et le tableau sera redistribué avec le bon paquet, dans cet exemple)."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 5.6 : ANNONCES PAR LE DONNEUR</h2>
            <p><?php echo htmlspecialchars("Le donneur devrait annoncer toutes les mises et relances (silencieuses ou annoncées par les joueurs, et en-dehors du montant d’un tapis) avec leur montant. Le donneur n’a pas à annoncer les autres événements : payer (call), parole (check), couché (fold). Les mises à tapis ne doivent être comptées que sur demande d’un joueur à son tour de parole."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 6 : ERREUR LORS DE LA DONNE INITIALE</h2>
            <p><?php echo htmlspecialchars("ARTICLE 6.1 : LA FAUSSE DONNE"); ?></p>
            <p><?php echo htmlspecialchars("Il y a automatiquement fausse donne dans les cas suivants :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("L’une des deux premières cartes distribuées est retournée,"); ?></li>
                <li><?php echo htmlspecialchars("Deux cartes ou plus ont été retournées,"); ?></li>
                <li><?php echo htmlspecialchars("Au moins une carte a été distribuée à un siège qui ne devait pas en recevoir,"); ?></li>
                <li><?php echo htmlspecialchars("Un joueur a été oublié par le donneur,"); ?></li>
                <li><?php echo htmlspecialchars("Le nombre de cartes distribuées est incorrect (3 cartes ou une seule à un joueur) et le joueur prévient avant une action substantielle (voir §6.7)"); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Le joueur au bouton peut recevoir deux cartes consécutives (voir §6.3)"); ?></p>
            <p><?php echo htmlspecialchars("Aucune fausse donne ne peut être annoncée sur un acte volontaire d’un joueur : ce joueur seul sera hors du coup et sera sanctionné."); ?></p>
            <p><?php echo htmlspecialchars("Le fait de faire une fausse donne implique de recommencer intégralement le coup dans les mêmes conditions. Toutefois, un joueur qui aurait été absent en début de donne mais présent une fois la fausse donne constatée pourra jouer le coup issu de la nouvelle donne."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 6.2 : LA REPETITION DES FAUSSES DONNES</h2>
            <p><?php echo htmlspecialchars("Toute répétition exagérée de fausses donnes, d’erreurs ou de cartes retournées par un même donneur ou par la faute d’un même joueur sera sanctionnée."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 6.3 : LES CARTES RETOURNEES PENDANT LA DONNE</h2>
            <p><?php echo htmlspecialchars("Une carte retournée est déclarée morte et le joueur concerné par une carte déclarée morte ne peut pas choisir de la garder. La donne continue normalement jusqu’à la fin et une ultime carte (celle qui devait être la première carte brulée) est donnée au joueur concerné en échange de sa carte retournée. Cette dernière est placée (toujours visible) sur le paquet et sera la première carte brulée avant le flop. Une fois brulée, cette carte est à nouveau jetée face cachée sur la table pour éviter toute confusion avec le tableau. Il est donc tout à fait possible dans certains cas que le dernier joueur servi (donneur ou bouton) reçoive ainsi deux cartes à la suite."); ?></p>
            <p><?php echo htmlspecialchars("Si deux cartes sont déclarées mortes dans le même tour de donne pré flop, il y a fausse donne. Si c’est à partir du flop, il faut appliquer le §7.6 en fonction de la situation."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 6.4 : PAQUET OU CARTES TOMBES DE LA TABLE</h2>
            <p><?php echo htmlspecialchars("Une carte tombée de la table du fait du donneur est toujours considérée retournée et morte (même si elle est face cachée au sol et que personne ne l’a vue) et sera traitée comme une carte retournée. Si elle est tombée du fait du joueur après la donne, elle doit être jouée."); ?></p>
            <p><?php echo htmlspecialchars("Si le paquet est tombé de la table ou sur la table, il faut essayer de le reconstituer dans son ordre original. Sinon, le dealer ramasse les cartes, crée un nouveau paquet et le fait couper par le joueur à sa droite."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 6.5 : LA CARTE MORTE MELEE A LA MAIN</h2>
            <p><?php echo htmlspecialchars("Si un joueur mêle une carte morte (retournée par le donneur ou tombée par terre par exemple) à son autre carte de telle manière que la carte vivante ne peut plus être clairement identifiée, l’ensemble de sa main est alors considéré comme morte mais les autres joueurs continuent le coup."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 6.6 : PAS DE CARTE FLASHEE</h2>
            <p><?php echo htmlspecialchars("Il n’y a pas de cartes flashées : soit la carte a été vue par une majorité des joueurs présents à la table et elle est brûlée, soit elle n’a été vue que par une partie des joueurs (carte volante qui ne se retourne pas) et le jeu continue, les joueurs pouvant simplement prévenir qu’ils pensent avoir vu une carte (sans préciser ce qu’ils ont vu)."); ?></p>
            <p><?php echo htmlspecialchars("Note : le principe de la carte flashée si vue par une ou plusieurs personnes a été supprimé car, la plupart du temps, les joueurs qui annoncent avoir vu une carte se trompent !"); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 6.7 : LES ACTIONS QUI ANNULENT TOUTE FAUSSE DONNE : L’ACTION SUBSTANTIELLE</h2>
            <p><?php echo htmlspecialchars("La main doit obligatoirement se poursuivre même en cas de fausse donne avérée si une Action Substantielle a eu lieu."); ?></p>
            <p><?php echo htmlspecialchars("Une Action Substantielle (AS) est définie par :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Deux actions consécutives dont l’une au moins implique la mise de jetons (call + fold, check + bet…)."); ?></li>
                <li><?php echo htmlspecialchars("Toute combinaison de 3 actions (check, bet, raise, call, fold)."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Le fait de poser les blindes n’est pas compté comme une action."); ?></p>
            <p><?php echo htmlspecialchars("Dans ce cas, seuls les joueurs disposant d’un jeu conforme (deux cartes) peuvent jouer le coup, les jeux des autres joueurs étant brûlés (voir §1.2)."); ?></p>
            <p><?php echo htmlspecialchars("Même si un joueur avait trop ou trop peu de carte(s), cela ne modifie pas l’ordre d’apparition des cartes suivantes. Il n’y a pas « d’ordre des cartes » à préserver."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 6.8 : LE BOUTON PEUT RECEVOIR SA DEUXIEME CARTE TANT QU’IL N’A PAS AGI</h2>
            <p><?php echo htmlspecialchars("Si le dealer oublie de se donner une deuxième carte et s’en rend compte avant son tour de jeu, il peut compléter sa main avant d’agir. S’il joue avec une seule carte, sa main sera déclarée morte."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 7 : ERREUR DE DONNE A PARTIR DU FLOP</h2>
            <p><?php echo htmlspecialchars("Une carte DOIT être brûlée avant chaque élément du tableau (Flop, Turn & River). Cela doit être fait dans tous les cas au moment de la distribution, cette carte brûlée étant là pour protéger le reste du paquet."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 7.1 : UNE ERREUR N’ANNULE PAS LE COUP</h2>
            <p><?php echo htmlspecialchars("Toute erreur constatée n’annule pas les mises qui précédent le tour d’enchères de la faute (ex : les mises pré flop sont conservées en cas d’erreur du flop, les mise à la turn sont conservées en cas d’erreur à la river)."); ?></p>
            <p><?php echo htmlspecialchars("Si une Action Substantielle (voir §6.7) a été faite avant que l’erreur ne soit vue, le jeu continue tel quel. Dans la mesure du possible, priorité sera donnée à faire apparaître par la suite les cartes qui devaient réellement arriver."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 7.2 : LES ERREURS AU FLOP</h2>
            <p><?php echo htmlspecialchars("Plusieurs erreurs peuvent se produire au flop. Attention, dans ces cas les cartes jetées par les joueurs sont mortes et jamais remélangées au paquet :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Flop sans carte brûlée : Si la première carte est identifiable, elle est désignée brulée et le donneur tire une quatrième carte. Si elle n’est pas identifiable, les 3 cartes du flop sorti sont mélangées pour déterminer la carte brûlée et la carte suivante est placée comme troisième carte du flop. Si leur nombre le permet, un floor effectuera le mélange et le tirage."); ?></li>
                <li><?php echo htmlspecialchars("Flop avec deux cartes brûlées : Si les cartes sont identifiables, la deuxième brûlée devient première du flop et la dernière du flop devient carte brûlée pour le turn. Si elle n’est pas identifiable, les cartes du flop sorti (y compris les deux cartes brûlées) sont mélangées pour brûler une carte avant le flop, tirer un nouveau flop et brûler une carte avant le turn. Si leur nombre le permet, un floor effectuera le mélange et le tirage."); ?></li>
                <li><?php echo htmlspecialchars("Flop à 4 cartes (après la carte brulée) : Si les cartes sont identifiables, la dernière du flop devient carte brûlée pour le turn. Si seule la carte brulée est identifiable, les 4 cartes sorties sont mélangées pour tirer un nouveau flop et la carte brûlée de la turn. Si leur nombre le permet, un floor effectuera le mélange et le tirage."); ?></li>
                <li><?php echo htmlspecialchars("Flop prématuré : si la carte brulée est identifiable, les autres cartes du flop sorti et les cartes restant à distribuer sont mélangées pour tirer un nouveau flop sans carte brûlée. Si la carte brulée n’est pas identifiable, toutes les cartes sorties et les cartes restant à distribuer sont mélangées pour tirer un nouveau flop avec carte brûlée. Si leur nombre le permet, un floor effectuera le mélange et le tirage."); ?></li>
            </ul>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 7.3 : TURN OU RIVER PREMATUREE</h2>
            <p><?php echo htmlspecialchars("Lorsque la turn ou la river est donnée de façon prématurée, la carte brûlée est maintenue brûlée et la carte sortie est remélangée avec le reste du paquet afin de distribuer une carte le moment venu."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 7.4 : LE TABLEAU ENLEVE PREMATUREMENT</h2>
            <p><?php echo htmlspecialchars("Si le dealer retire le tableau prématurément et qu’il n’est plus clairement discernable sans avoir à retourner les cartes, le pot est partagé entre les joueurs encore en jeu. Si le donneur retire le tableau prématurément mais qu’il est discernable sans avoir à retourner les cartes, le coup continue."); ?></p>
            <p><?php echo htmlspecialchars("Le donneur pourra être pénalisé s’il a dévoilé ou fait tomber le paquet, surtout s’il est impliqué dans le coup."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 7.5 : PAQUET TOMBE OU MELANGE AU MUCK ET/OU AUX CARTES BRULEES</h2>
            <p><?php echo htmlspecialchars("Si le paquet est tombé de la table ou sur la table, il faut essayer de le reconstituer dans son ordre original. Si ce n’est pas possible, créez un nouveau paquet en utilisant uniquement les cartes du talon (sans le muck ni les cartes brûlées). Si le paquet est tombé sur le muck et/ou les cartes brulées sans distinction possible, mélangez le paquet avec le muck et/ou les cartes brûlées."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 7.6 : PAQUET DEVOILE SANS MISE POSSIBLE</h2>
            <p><?php echo htmlspecialchars("Si le paquet est dévoilé (à partir de deux cartes) alors que plus aucune mise n’est possible, toutes les cartes de ce paquet et uniquement celles-ci sont à nouveau mélangées par le dealer et coupées par le joueur à sa droite pour continuer la distribution du coup."); ?></p>
            <p><?php echo htmlspecialchars("Le dealer procède ensuite au tirage du flop, turn ou river en se basant sur le principe du paragraphe 7.1 qui stipule que toute erreur constatée n’annule pas les mises qui précédent le tour d’enchère de la faute."); ?></p>
            <p><?php echo htmlspecialchars("Note : Les erreurs signalées aux §7.1 à §7.6 peuvent se cumuler si le donneur multiplie les erreurs, et ce sans remettre en cause la validité du coup. Dans ce cas, le donneur devra être averti."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 7.7 : LES CAS QUI ANNULENT LE COUP</h2>
            <p><?php echo htmlspecialchars("En plus des cas évoqués dans le §6.1, un coup peut être annulé dans les cas suivants :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Deux cartes au moins sont à l’envers (boxed cards) après la première donne et des mises sont encore possibles."); ?></li>
                <li><?php echo htmlspecialchars("Il y a une irrégularité flagrante dans le paquet (joker, 2 fois la même carte…)."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Les cas d’annulation obligent à rejouer le coup : restitution des mises et blindes à tous les joueurs sans exception."); ?></p>
            <p><?php echo htmlspecialchars("Attention, si l’incident n’a pas influencé les abandons des joueurs précédents (ex : irrégularité du paquet reconnue après les abandons), le pot est partagé entre les joueurs restant à égalité de mises."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 8 : LES CARTES MORTES</h2>
            <p><?php echo htmlspecialchars("ARTICLE 8.1 : LES JOUEURS SONT RESPONSABLES DE LEURS CARTES"); ?></p>
            <p><?php echo htmlspecialchars("Tout joueur est seul et unique responsable de ses cartes. À lui de les protéger afin qu’aucune mésaventure ne puisse lui arriver, par exemple en les plaçant devant lui avec une protection dessus (des jetons, un bibelot…) qui permettra de les identifier. Les cartes des joueurs toujours en jeu doivent toujours rester sur la table et être visibles."); ?></p>
            <p><?php echo htmlspecialchars("Dans le cas où un responsable de tournoi est obligé d’intervenir pour rendre sa main (si les cartes sont identifiables) à un joueur qui ne l’a pas protégée, ce dernier recevra un avertissement."); ?></p>
            <p><?php echo htmlspecialchars("A l’abattage, le joueur gagnant le coup doit attendre que le pot lui soit clairement attribué pour jeter sa main (voir §10.5)."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 8.2 : MAINS NON-PROTEGEES</h2>
            <p><?php echo htmlspecialchars("Si le donneur brûle une main non-protégée ou si un autre joueur la jette, le joueur dont la main est brulée n’a aucun recours, perd le coup et se verra remboursé ses mises du tour d’action en cours comme suit :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Si le joueur ayant sa main brûlée n’a pas misé comme dernière action, il ne récupère rien,"); ?></li>
                <li><?php echo htmlspecialchars("Si le joueur ayant sa main brûlée a misé, il ne récupère sa mise que s’il n’a pas été payé,"); ?></li>
                <li><?php echo htmlspecialchars("Si le joueur ayant sa main brûlée a relancé, il récupère la différence entre sa relance et la dernière mise s’il n’a pas été payé."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("L’ensemble de ses autres mises dans le coup est perdu"); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 8.3 : CARTES JETEES INDISCERNABLES</h2>
            <p><?php echo htmlspecialchars("Toute main jetée faces cachées vers ou dans le muck et jugée indiscernable est définitivement déclarée morte."); ?></p>
            <p><?php echo htmlspecialchars("Si cette main a été jetée à cause de la faute avérée d’un joueur adverse qu’il soit ou non dans le coup, ce dernier recevra a minima un avertissement. S ‘il s’avère que cette faute est volontaire ou que le fautif est récidiviste, la sanction laissée à l’initiative du directeur de tournoi devra logiquement être bien plus lourde."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 8.4 : CARTES JETEES DISCERNABLES</h2>
            <p><?php echo htmlspecialchars("Toute main jetée faces cachées ou visibles vers ou dans le muck, même jugée discernable, est déclarée morte. Dans de rares cas (une faute adverse avérée) et sur le seul jugement du directeur de tournoi, le joueur pourra récupérer sa main."); ?></p>
            <p><?php echo htmlspecialchars("Dans ce cas particulier, le joueur fautif recevra a minima un avertissement. S’il s’avère que cette faute est volontaire ou que le fautif est récidiviste, la sanction laissée à l’initiative du directeur de tournoi devra logiquement être plus lourde."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 8.5 : CARTES JETEES SUR UN JEU NON PROTEGE</h2>
            <p><?php echo htmlspecialchars("Si un joueur jette ses cartes sur le jeu non protégé d’un adversaire, le responsable du tournoi doit faire ce qu’il peut pour les identifier. Si c’est possible, le jeu du joueur qui n’a pas jeté reste en jeu mais reçoit une sanction pour ne pas avoir protégé ses cartes (voir §8.1 « les joueurs sont responsables de leurs cartes »). S’il n’est pas possible d’identifier les cartes, ou si les cartes ont été mélangées au rebus, le jeu du joueur est considéré comme mort. Que la faute soit intentionnelle ou non, le joueur qui a jeté ses cartes devra être sanctionné."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 8.6 : EXPOSER SA MAIN</h2>
            <p><?php echo htmlspecialchars("Si le joueur dont c’est le tour de jouer expose sa main devant lui sans aucun commentaire, il sera sanctionné après le coup pour avoir dévoilé son jeu prématurément mais son acte ne recevra aucune interprétation."); ?></p>
            <p><?php echo htmlspecialchars("Le joueur devra préciser ses intentions, qui pourront être librement exprimées (passer, payer ou relancer). De manière générale et pour lutter contre ce type d’attitudes ambigües, le directeur de tournoi appliquera après le coup un niveau de sanction proportionnel à la confusion créée à la table."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9 : MISES ET RELANCES</h2>
            <p><?php echo htmlspecialchars("ARTICLE 9.1 : LE MONTANT TOTAL"); ?></p>
            <p><?php echo htmlspecialchars("Toute annonce d’une relance sera automatiquement traduite par la somme totale (« Plus 4 000 » ou « Relance de 4 000 » seront par exemple traduits en « Relance pour un montant total de 4 000 »). Relancer en deux temps n’est pas autorisé,"); ?></p>
            <p><?php echo htmlspecialchars("à savoir par exemple sur une mise de 2 000 annoncer « je relance pour tes 2 000 et 5 000 de plus » sera interprété comme une relance minimale du fait de la phrase comprise « je relance 2000 » qui est une annonce relance insuffisante, ce sera donc une relance à 4 000."); ?></p>
            <p><?php echo htmlspecialchars("Une annonce d’un montant abrégé sera interprétée selon la valeur du pot. Si un joueur annonce « 5 » alors que le pot représente moins de 5 000, cela sera interprété comme 500. La même annonce alors que le pot représente 5000 ou plus sera interprétée comme 5 000 (sauf dans le cas de présence de jetons de valeur 5 où une annonce « 5 » vaudra 5 !)."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.2 : MISER SES JETONS</h2>
            <p><?php echo htmlspecialchars("Les mises doivent être faites clairement vers l’avant, à une distance accessible et facilitant le bon déroulement du jeu mais sans atteindre ni approcher le pot central. Un joueur misant volontairement de manière gênante pour le bon déroulement du jeu sera sanctionné."); ?></p>
            <p><?php echo htmlspecialchars("Un joueur désirant reprendre de la monnaie à la suite d’une mise doit avoir annoncé clairement le montant de sa mise."); ?></p>
            <p><?php echo htmlspecialchars("Sans annonce, la mise est considérée comme totale et les différentes règles (50%, jetons multiples, jeton unique…) s’appliquent."); ?></p>
            <p><?php echo htmlspecialchars("EXEMPLE :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Sur 50/100, le joueur A mise 325 et le joueur B, sans rien annoncer, pose ensemble un jeton de 500 et un jeton de 25 (pour aider à la monnaie). La mise totale étant de 525 et composant une relance valable (plus de 50% de la relance minimale qui est ici de 225), on considèrera que le joueur B a relancé à 525 (relance valable mais insuffisante) et devra donc mettre 550 (voir §9.6 le montant minimal de la relance)."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("CLARTE DES INTENTIONS"); ?></p>
            <p><?php echo htmlspecialchars("Le joueur se doit de faire connaître le plus clairement possible ses intentions de mise. Toute action interprétée par les autres joueurs et validée par le Directeur de Tournoi engagera le joueur qui aura commis l’erreur."); ?></p>
            <p><?php echo htmlspecialchars("MISE CORRECTE SANS ANNONCE VERBALE"); ?></p>
            <p><?php echo htmlspecialchars("Les mises et les relances (string bet/string raise) en plusieurs fois ne sont pas autorisées."); ?></p>
            <p><?php echo htmlspecialchars("A partir du moment où un joueur prend des jetons et commence à les avancer pour miser, l’intégralité de la pile de jetons est prise en compte dans la relance et aucun jeton ne peut être ajouté ou retiré (sauf si le montant de la mise ou relance a été annoncé)."); ?></p>
            <p><?php echo htmlspecialchars("Note : cette définition n’est pas celle admise par le TDA ou les casinos français qui parlent de « mouvement fluide » mais la détermination d’un mouvement fluide ne peut pas être prise en compte en l’absence de croupier à la table."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.3 : JOUER A SON TOUR, AVANT SON TOUR, APRES SON TOUR</h2>
            <p><?php echo htmlspecialchars("JOUER A SON TOUR"); ?></p>
            <p><?php echo htmlspecialchars("La parole engage un joueur de façon inconditionnelle lorsque c’est son tour de jeu."); ?></p>
            <p><?php echo htmlspecialchars("JOUER AVANT SON TOUR"); ?></p>
            <p><?php echo htmlspecialchars("Une annonce faite avant son tour prévaudra si l’action n’a pas changé quand la parole revient au joueur. Un check, un call ou un fold ne sont pas considérés comme un changement d’action. En cas de mise ou de relance, il récupère toute liberté de parole ainsi que sa mise. Un fold annoncé a valeur d’engagement. Le joueur ayant fait une action avant son tour pourra être sanctionné."); ?></p>
            <p><?php echo htmlspecialchars("Tout joueur foldant ou se levant de table avant son tour (« out of turn »), ainsi que tout joueur foldant quand il peut checker sera sanctionné. Tout joueur agissant hors de son tour de manière accidentelle mais répétitive se verra sanctionné."); ?></p>
            <p><?php echo htmlspecialchars("Note : le fait de jouer avant que le joueur précédent ait complété son action est sujet à sanction, en particulier en cas de répétition. Si le joueur précédent annonce « Relance », il faut attendre de connaître le montant de la relance avant de jouer (que ce soit pour payer bien sûr, mais également pour abandonner le coup)."); ?></p>
            <p><?php echo htmlspecialchars("NE PAS JOUER A SON TOUR"); ?></p>
            <p><?php echo htmlspecialchars("Si un joueur a été oublié, il ne récupère son droit à disputer le coup normalement que s’il réagit avant qu’une action substantielle (voir §6.7 « les actions qui annulent toute fausse donne : l’action substantielle ») aient été faites. Sinon, il ne pourra plus que passer ou suivre la dernière mise."); ?></p>
            <p><?php echo htmlspecialchars("Note : une tolérance pourra être accordée à un joueur qui n’a pas réellement tardé à parler, par exemple s’il était en train de mélanger le jeu précédent ou si ses adversaires ont parlé très rapidement après lui."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.4 : L’ANNONCE VERBALE PREVAUT</h2>
            <p><?php echo htmlspecialchars("Si elles sont faites avant ou en même temps que le mouvement des jetons, les annonces verbales prévalent : un call annoncé sera toujours considéré comme un call même si un joueur envoie plus du double de la mise minimum au milieu."); ?></p>
            <p><?php echo htmlspecialchars("Inversement, même s’il met moins de la mise et annonce une relance, le joueur sera obligé de mettre le minimum de relance."); ?></p>
            <p><?php echo htmlspecialchars("Si un joueur annonce un montant, il sera obligé de le mettre sous réserve qu’il respecte les autres règles à suivre. Si un joueur annonce « relance » sans donner de montant mais met plus que le nécessaire à la relance minimale au milieu, les jetons seront considérés comme faisant tous partie de la relance."); ?></p>
            <p><?php echo htmlspecialchars("Si une mise insuffisante est faite par erreur mais avec une annonce verbale officielle (par exemple « je suis » ou « je relance »), l’action annoncée sera imposée au joueur."); ?></p>
            <p><?php echo htmlspecialchars("Si des jetons sont avancés sans aucune annonce verbale, ils doivent être interprétés sans prendre en compte toute annonce postérieure."); ?></p>
            <p><?php echo htmlspecialchars("Si un joueur annonce « call » alors qu’aucune mise n’a été faite, ce sera considéré comme un check."); ?></p>
            <p><?php echo htmlspecialchars("Si un joueur annonce « check » alors qu’une mise a été faite, il devra reprendre son action mais ne pourra pas relancer."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.5 : METHODES DE RELANCE</h2>
            <p><?php echo htmlspecialchars("Une relance peut être faite de trois manières :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("En plaçant le montant total de la relance en un seul geste (précédé ou non de l’annonce de relance), de manière distincte (jetons suffisamment éloignés du tapis du joueur et suffisamment éloignés du pot central pour éviter toute confusion), entre les jetons du joueur et le pot."); ?></li>
                <li><?php echo htmlspecialchars("En annonçant le montant total avant le geste de mise des jetons entre les jetons du joueur et le pot."); ?></li>
                <li><?php echo htmlspecialchars("En annonçant « Relance » (ou « Raise ») puis en posant ses jetons en une seule fois. Si les jetons posés ne correspondent pas à un montant supérieur à la relance minimale, ces jetons seront interprétés comme une relance minimale (du fait de l’annonce) et le joueur devra compléter au niveau de cette relance minimale."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Si un joueur met ou annonce un montant inférieur au minimum de relance sans annoncer son intention de relancer, tenir compte de la règle des 50 % (voir §9.6) et des règles de jeton unique ou multiples (voir §9.11 et §9.13)."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.6 : MONTANT DE LA RELANCE</h2>
            <p><?php echo htmlspecialchars("Une relance doit ajouter au moins le même montant que la grosse blinde, ou que la mise précédente, ou que la relance de l’actuel tour d’enchères. La petite blinde n’est jamais considérée comme une mise."); ?></p>
            <p><?php echo htmlspecialchars("EXEMPLES :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Cas A : Blindes 25 / 50 – seules les blindes ont été placées, relance minimum = 100"); ?></li>
                <li><?php echo htmlspecialchars("Cas B : Blindes 25 / 50 – une relance à 150 a été faite, la relance minimum est de 250 : la mise précédente de 150 plus la relance précédente de 150-50 = 100. Si une telle relance est faite alors la relance minimum suivante est de 350 : la mise précédente de 250 plus la relance précédente de 250-150 = 100."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Attention, à chaque nouveau tour d’enchères, on repart de zéro : la mise ne peut être inférieure à la grosse blinde, la première relance doit ajouter au moins le montant de la mise précédente (la première mise) et la sur relance a les mêmes règles."); ?></p>
            <p><?php echo htmlspecialchars("EXEMPLE :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Blindes 25/50 : le tour se finit avec deux joueurs à 350. Le premier joueur à parler mise 250, la relance minimum doit ajouter la mise donc à 250 + 250 soit 500. Dans cet exemple et en cas de relance à 500, la sur relance minimum est 500 + 250 donc 750 et ainsi de suite."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("La règle des 50% s’applique tout le temps : si un joueur place une relance de 50% ou plus de la mise précédente mais moins que la mise minimale, la relance est validée au montant minimum et il doit compléter. Si un joueur met moins de 50% de la relance minimale, il ne peut compléter pour atteindre ce montant minimal que s’il avait annoncé une relance. Sinon, son action sera considérée comme un call. Tenir également compte des règles de jetons unique ou multiples (voir §9.11 et §9.13)."); ?></p>
            <p><?php echo htmlspecialchars("EXEMPLES :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Cas A : Post flop, le joueur A a misé 100. Le joueur B n’annonce rien et engage 150. Comme il a engagé 50% de la mise en plus, il doit compléter à 200 pour valider sa relance, même s’il a voulu juste suivre."); ?></li>
                <li><?php echo htmlspecialchars("Cas B : Post flop, le joueur C a misé 100. Le joueur D n’annonce rien et mise 125. Comme il a engagé moins de 50% de la mise en plus, il doit reprendre 25 pour réduire son engagement à 100 et juste suivre, même s’il a voulu relancer."); ?></li>
                <li><?php echo htmlspecialchars("Cas C : Post flop, le joueur E a misé 100. Le joueur F annonce qu’il relance et mise 125. Il doit compléter en ajoutant 75 pour atteindre la relance minimum, qui est de 200, même s’il a voulu relancer au-delà (cela illustre aussi le §9.4)."); ?></li>
            </ul>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.7 : RELANCE A TAPIS PRE -FLOP INFERIEURE A LA GROSSE BLINDE</h2>
            <p><?php echo htmlspecialchars("Si un joueur n’a pas assez pour ouvrir à hauteur de la grosse blinde avant le flop, il est à tapis mais les autres joueurs sont tenus d’ouvrir du montant minimum égal à la grosse blinde (qui est considérée comme une mise) ou de relancer au moins du double de la grosse blinde."); ?></p>
            <p><?php echo htmlspecialchars("EXEMPLE :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Grosse blinde à 100, tapis à 30, les autres joueurs pourront alors suivre à 100 ou relancer au minimum à 200."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("En conséquence, si le joueur qui mise la grosse blinde a un tapis inférieur à celle-ci, le premier joueur qui paie ou relance crée un pot extérieur."); ?></p>
            <p><?php echo htmlspecialchars("De même, si le joueur qui mise la petite blinde a un tapis inférieur à celle-ci, le joueur qui mise la grosse blinde crée un pot extérieur."); ?></p>
            <p><?php echo htmlspecialchars("Rappel : dans le format ante big blind, la big blinde est prioritaire sur l’ante (§4.2)."); ?></p>
            <p><?php echo htmlspecialchars("EXEMPLES :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Cas A : Blindes 5 000/10 000 sans ante. Le joueur de petite blinde a un tapis de 3 000. Il mise son tapis. Le joueur de grosse blinde check. On se retrouve avec un pot principal de 6 000 (2 × 3 000) et un pot extérieur de 7 000 qui est immédiatement rendu au joueur de grosse blinde s’il n’y a pas d’autre mise dans le coup."); ?></li>
                <li><?php echo htmlspecialchars("Cas B : Blindes 5 000/10 000 sans ante. Le bouton ouvre pour son tapis de 3 000. Le joueur de petite blinde doit au moins compléter à hauteur de la grosse blinde (10 000) pour disputer le pot et donc le tapis du bouton. Si le joueur de petite blinde complète à 10 000 et le joueur de grosse blinde check, on se retrouve avec un pot principal de 9 000 (3 × 3 000) et un pot extérieur de 14 000 (2 × 7 000) qui sera disputé entre la petite blinde et la grosse blinde."); ?></li>
                <li><?php echo htmlspecialchars("Cas C : Blindes 5 000/10 000 ante big blinde 10 000. Le joueur big blinde est à tapis à 3 000, tout le monde passe sauf le bouton qui paye 10 000 et petite blinde qui suit également à 10 000. Le pot principal est de 9 000 (tapis de la big blinde payé 2 fois) et le pot extérieur est de 14 000 (2 × (10 000 – 3 000))."); ?></li>
                <li><?php echo htmlspecialchars("Cas D : Blindes 5 000/10 000 ante big blinde 10 000. Le joueur big blinde est à tapis et avait 13 000 avant le coup, donc 3 000 d’ante et 10 000 de BB, tout le monde folde sauf le bouton et la petite blinde qui suivent à 10 000. Le pot est de 33 000 (ante big blinde 3 000 + 3 × 10 000) et il n’y a pas de pot extérieur."); ?></li>
            </ul>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.8 : MISE A TAPIS PRE-FLOP INFERIEURE A LA RELANCE MINIMALE</h2>
            <p><?php echo htmlspecialchars("Toute mise à tapis d’un joueur inférieure à la relance minimale ne peut être considérée comme une relance même si le montant est supérieur à la précédente mise."); ?></p>
            <p><?php echo htmlspecialchars("Les joueurs parlant après dans ce tour d’enchères peuvent soit suivre du montant de son tapis soit le relancer au moins du montant de la dernière mise valable. Voir le §9.21 « Relance bloquée » pour les cas particuliers."); ?></p>
            <p><?php echo htmlspecialchars("EXEMPLES :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("La grosse blinde est à 100, un joueur fait tapis à 30, les autres joueurs pourront alors suivre à 100 ou relancer normalement au montant minimum à 200."); ?></li>
                <li><?php echo htmlspecialchars("La grosse blinde est à 100, un joueur fait tapis à 180, les autres joueurs pourront alors suivre à 180 ou relancer au montant minimum à 280 (soit 180 du tapis + la mise précédente qui est la grosse blind)."); ?></li>
            </ul>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.9 : OUVERTURE POST-FLOP A TAPIS INFERIEURE A LA GROSSE BLINDE</h2>
            <p><?php echo htmlspecialchars("Après le flop, si un joueur ouvre sans avoir assez pour ouvrir à hauteur de la grosse blinde, il est à tapis et les autres peuvent le suivre du montant de son tapis ou le relancer au moins du montant de la grosse blinde (puisque la valeur ajoutée est inférieure à la grosse blinde)."); ?></p>
            <p><?php echo htmlspecialchars("EXEMPLE :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("La grosse blinde est à 100, post flop un joueur fait tapis à 30, les autres joueurs pourront alors suivre à 30 ou miser au minimum à 130."); ?></li>
            </ul>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.10 : MISES INSUFFISANTES AVEC ET SANS ANNONCE VERBALE</h2>
            <p><?php echo htmlspecialchars("Si une mise insuffisante est faite par erreur mais avec une annonce verbale officielle (par exemple « je suis » ou « je relance »), l’action annoncée sera imposée au joueur (selon le §9.4 « L’annonce verbale prévaut »)."); ?></p>
            <p><?php echo htmlspecialchars("Une relance annoncée mais insuffisante obligera le joueur à compléter à hauteur de la relance minimum."); ?></p>
            <p><?php echo htmlspecialchars("Si une mise insuffisante est faite par erreur mais sans annonce verbale officielle (par exemple des jetons posés en silence), les conséquences dépendront du type de mise :"); ?></p>
            <p><?php echo htmlspecialchars("MISE D’OUVERTURE INSUFFISANTE (« UNDERBET »)"); ?></p>
            <p><?php echo htmlspecialchars("S’il s’agit d’une ouverture insuffisante, le montant doit être aligné sur la grosse blinde (que nul n’est censé ignorer). Ce montant devra être corrigé tant que l’on est dans le même tour de mise (avec ou sans action substantielle ultérieure) mais pas si on est passé au tour de mise suivant."); ?></p>
            <p><?php echo htmlspecialchars("EXEMPLE :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Blindes 600/1 200, le joueur A ouvre à 1 000 et le joueur B relance à 2 300. Après cette relance, un joueur signale que A aurait dû ouvrir à 1 200, le montant de l’ouverture est corrigé à 1200 comme celui de la relance qui doit être de 2 400. Si B avait relancé à 2 500, le montant de sa relance ne serait pas corrigé car il resterait valable avec la correction de l’ouverture."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("MISE POUR SUIVRE INSUFFISANTE (« UNDERCALL »)"); ?></p>
            <p><?php echo htmlspecialchars("Si seulement deux joueurs restent en jeu dans la main en cours, la mise devra obligatoirement être complétée."); ?></p>
            <p><?php echo htmlspecialchars("Si la mise fait suite à la première mise dans un pot à plusieurs joueurs, la mise devra obligatoirement être complétée."); ?></p>
            <p><?php echo htmlspecialchars("Dans les autres cas, le directeur de tournoi doit statuer sur l’action à imposer au fautif entre compléter ou abandonner sa mise, en particulier dans le cas où cette erreur a des conséquences graves (comme de faire exposer une main adverse)."); ?></p>
            <p><?php echo htmlspecialchars("Si plusieurs « undercalls » consécutifs ont lieu, le premier doit corriger son action (comme prévu ci-dessus) et le directeur de tournoi doit statuer sur l’action à imposer ou non au(x) joueur(s) suivant(s)."); ?></p>
            <p><?php echo htmlspecialchars("EXEMPLES :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Cas A : sur les blindes 100/200, 4 joueurs sont en jeu après le flop. A ouvre à 800 et B avance 200 sans annonce. Comme A a ouvert les enchères, B devra payer les 800."); ?></li>
                <li><?php echo htmlspecialchars("Cas B : sur les blindes 100/200, 4 joueurs sont en jeu au flop. A ouvre à 200 et B relance à 800. C avance 200 sans annonce. B n’ayant pas ouvert les enchères, l’action de C sera soumise à décision du directeur de tournoi."); ?></li>
                <li><?php echo htmlspecialchars("Cas C : sur les blindes 100/200, 4 joueurs sont en jeu après le flop. A ouvre à 200 et B relance à 800. C annonce « Call » (éventuellement en avançant 200). Comme C a fait une annonce verbale, il doit payer 800"); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("RELANCE INSUFFISANTE (« UNDERRAISE »)"); ?></p>
            <p><?php echo htmlspecialchars("Le complément sera imposé au joueur s’il avait atteint 50% du montant de la relance minimum. Dans le cas contraire, il sera contraint de simplement suivre (call). Tenir compte avant cela des règles du jeton unique et des jetons multiples (voir le §9.11 « Jeton unique de valeur supérieure » & le §9.13 « Jetons multiples »)."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.11 : JETON UNIQUE DE VALEUR SUPERIEURE</h2>
            <p><?php echo htmlspecialchars("Après une relance (ou une blinde), le fait de placer un seul jeton de valeur supérieure correspond à un call si la relance n’est pas verbalement annoncée et ce même si le joueur a déjà d’autres jetons misés dans le coup. Pour relancer avec un seul jeton de valeur supérieure, une annonce doit être faite avant que le jeton ne touche la surface de la table. Si la relance est déclarée (mais pas le montant), la relance sera du montant du jeton."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.12 : JETON UNIQUE DE VALEUR INFERIEURE</h2>
            <p><?php echo htmlspecialchars("Dans un coup où il ne reste plus que deux joueurs, le fait de placer un seul jeton de valeur inférieure après une mise d’ouverture ou une relance correspond à un call."); ?></p>
            <p><?php echo htmlspecialchars("Dans un coup à trois joueurs ou plus, le fait de placer un seul jeton de valeur inférieure n’est pas une mise valide. Le jeton sera engagé et le joueur devra préciser s’il call ou fold mais il ne pourra plus relancer."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.13 : JETONS MULTIPLES</h2>
            <p><?php echo htmlspecialchars("Miser plusieurs jetons sans aucune annonce après une mise ou après les blindes est un call si, en retirant le jeton de plus petite valeur, le total deviendrait inférieur à une mise pour suivre (call)."); ?></p>
            <p><?php echo htmlspecialchars("EXEMPLE :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("à la suite d’une mise d’ouverture de 1 200, une mise sans annonce de deux jetons de 1 000 est un call et non une relance de montant insuffisant (qui aurait sinon été complété à 2 400 suivant la règle des 50% déjà vue plus haut dans le cas de relance insuffisante)."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Dans tous les autres cas, une mise de plusieurs jetons est une mise à hauteur de la valeur totale des jetons."); ?></p>
            <p><?php echo htmlspecialchars("La règle des jetons multiples doit être appliquée même si le joueur en question a déjà misé des jetons pendant ce même tour d’enchères (ses blindes ou ses mises antérieures sont déjà sur la table)."); ?></p>
            <p><?php echo htmlspecialchars("EXEMPLE :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("sur des blindes 100/200, si un joueur ouvre à 1 300 et que le joueur en grosse blinde pose 2 jetons de 1 000 sans aucune annonce, cette mise sera considérée comme un call et non une relance insuffisante puisqu’en retirant un des 2 jetons, la mise de 1 200 est insuffisante pour un call."); ?></li>
            </ul>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.14 : NOMBRE DE RELANCES</h2>
            <p><?php echo htmlspecialchars("Il n’y a pas de limite du nombre de relances dans les parties de no-limit"); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.15 : COMPTER LES JETONS</h2>
            <p><?php echo htmlspecialchars("Le seul cas où une demande de compter les jetons doit être accordée est celui d’une mise ou d’une relance : le joueur doit compter et annoncer le montant total des jetons misés sur la demande d’un adversaire encore en lice."); ?></p>
            <p><?php echo htmlspecialchars("Note : le fait d’annoncer « Tapis » étant une mise, le joueur qui a fait tapis doit bien annoncer le montant du tapis misé sur la demande d’un adversaire encore en lice."); ?></p>
            <p><?php echo htmlspecialchars("Dans tous les autres cas (jetons restants devant un joueur, différence à rajouter pour suivre, montant total du pot) aucun joueur n’est sensé compter quoi que ce soit. Un joueur peut en revanche demander que le pot soit bien étalé sur la table pour en faciliter le compte et que les jetons restants des adversaires en jeu soient correctement rangés (piles de jetons homogènes, de préférence de 10 ou 20 jetons) pour faciliter leur décompte. – voir §3.3."); ?></p>
            <p><?php echo htmlspecialchars("Miser « le pot » n’est pas une annonce valable en no-limit puisque le pot n’est pas compté."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.16 : JOUER ‘IN THE DARK’</h2>
            <p><?php echo htmlspecialchars("Miser avant l’arrivée des cartes du tableau (« in the dark ») est autorisé sans aucune limite quant au nombre de joueurs s’y risquant et au nombre de tours d’enchères ainsi déclarés. En cas de désaccord sur ce qui a été annoncé : seules seront prises en compte les annonces confirmées par le dealer. En cas de désaccord, le floor tiendra compte selon son libre jugement des témoignages pour officialiser un nombre choisi d’annonces avant l’arrivée du tableau."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.17 : LE POT COMPLET</h2>
            <p><?php echo htmlspecialchars("Après la réunion des jetons à la fin du tour d’enchères, le pot est définitivement considéré comme complet. En cas de désaccord sur l’origine d’une anomalie concernant le montant total des mises des joueurs, ces dernières doivent donc être vérifiées avant que les jetons ne soient réunis."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.18 : ACTIONS SIMULTANEES</h2>
            <p><?php echo htmlspecialchars("Si deux joueurs (ou plus !) agissent simultanément, ils sont considérés comme ayant joué dans l’ordre et on vérifie simplement la cohérence des actions."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.19 : DECLARATIONS CONDITIONNELLES</h2>
            <p><?php echo htmlspecialchars("Les déclarations conditionnelles (ex : « si tu mises je me couche » ou « si tu checkes je mise » ou autres) sont interdites."); ?></p>
            <p><?php echo htmlspecialchars("Le joueur en ayant fait s’expose à une sanction si l’attention du Directeur de Tournoi est attirée par un joueur adverse."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.20 : LES ACTIONS MAL COMPRISES</h2>
            <p><?php echo htmlspecialchars("Toute action mal interprétée par un donneur n’engage pas son auteur. Toute erreur sur un comptage de la part du donneur ou d’un joueur initial n’enlève en rien les responsabilités des joueurs suivants. C’est aux joueurs suivants de vérifier l’exactitude des informations données en amont et ils restent responsables de leurs actions."); ?></p>
            <p><?php echo htmlspecialchars("Le directeur de tournoi pourra dans quelques cas rares impliquant des différences conséquentes et sur son seul jugement, utiliser ici le §1.1 « L’équité du jeu avant tout » et uniquement à l’avantage du joueur trompé par les informations. Toute action mal interprétée sera maintenue telle que comprise si elle est suivie d’une action substantielle (voir §6.7) avant que le malentendu ne soit constaté et si les règles de mise et de relance sont respectées"); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.21 : RELANCE BLOQUEE</h2>
            <p><?php echo htmlspecialchars("Lorsqu’un joueur annonce un tapis pour un montant supérieur à la dernière relance mais insuffisant pour être une relance, son tapis est engagé sans que ce soit une relance."); ?></p>
            <p><?php echo htmlspecialchars("Ainsi :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Tout joueur qui souhaite payer le tapis doit normalement mettre le montant du tapis."); ?></li>
                <li><?php echo htmlspecialchars("Tout joueur qui souhaite relancer doit relancer au moins du montant de la dernière relance."); ?></li>
                <li><?php echo htmlspecialchars("Si aucun autre joueur ne relance, le joueur qui a fait la dernière relance ainsi que ceux ayant éventuellement suivi sa relance seront ‘bloqués’."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("EXEMPLES :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Cas A : Blindes 50/100, le joueur A relance à 225 et le joueur B fait tapis pour 300. La relance minimum étant de 350 [225+125], le tapis de B ne peut être considéré comme une relance (même insuffisante). Si un joueur veut sur relancer, il doit miser au moins 425 [300 du tapis + 125 de la relance de A]. Si un ou plusieurs joueurs paient les 300, le joueur A ne peut pas relancer le coup puisque c’est lui le dernier joueur à avoir effectué une relance. Si un autre joueur effectue une relance (à 425 au minimum), le joueur A est libéré et peut réagir comme il le souhaite (fold, call ou relance)."); ?></li>
                <li><?php echo htmlspecialchars("Cas B : Blindes 50/100, le joueur A relance à 225 et le joueur B fait tapis pour 300. La relance minimum étant de 350 [225+125], le tapis de B n’est pas une relance. Le joueur C fait tapis pour 400. Bien que le tapis de C ne soit pas une relance par rapport au tapis de B, si un joueur call à 400, le joueur A peut à nouveau relancer le coup puisque lorsque son tour revient, sa relance à 225 fait face à une mise de 400 qui est considérée comme une sur relance."); ?></li>
            </ul>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.22 : INTERDICTION DE JETER QUAND AUCUNE MISE N’EST ATTENDUE</h2>
            <p><?php echo htmlspecialchars("Il est interdit de jeter ses cartes pendant un tour de mise quand aucune mise n’est attendue (« open muck »)."); ?></p>
            <p><?php echo htmlspecialchars("La main jetée par inadvertance d’un joueur n’ayant rien misé mais qui pouvait checker est déclarée morte et le joueur sera pénalisé."); ?></p>
            <p><?php echo htmlspecialchars("S’il s’agissait du joueur ayant posé la grosse blinde, le montant est perdu sans autre pénalité."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.23 : JETONS OUBLIES A TAPIS</h2>
            <p><?php echo htmlspecialchars("Si des jetons ont été oubliés par un joueur qui mise son tapis et ne sont montrés qu’une fois le tapis payé, le TD sera seul juge de leur intégration au tapis. Le TD peut valider le fait que les jetons oubliés ne seront pas payés par le joueur qui a payé si le joueur à tapis gagne le coup mais seront perdus si je joueur à tapis perd le coup."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 9.24 : SUR-MISER POUR OBTENIR DU CHANGE</h2>
            <p><?php echo htmlspecialchars("Les mises faites par les joueurs ne peuvent pas être comprises comme impliquant une demande de change. Face à une ?ouverture de 325, le fait de vouloir payer en posant silencieusement et en une seule fois un jeton de 500 et un jeton de 25 (pour obtenir un retour de 200) sera considéré comme une relance incomplète et le joueur devra relancer à 650."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 10 : FIN D’UN COUP</h2>
            <p><?php echo htmlspecialchars("ARTICLE 10.1 : MONTRER SES CARTES SUR UN COUP FINI SANS ABATTAGE"); ?></p>
            <p><?php echo htmlspecialchars("Un joueur peut, s’il gagne le coup sans que sa dernière mise ne soit suivie, ne rien montrer ou ne montrer qu’une seule carte. Qu’il n’en montre qu’une ou les deux, s’il les montre à un joueur (ou même à un spectateur) il doit les montrer à tous les joueurs de la table."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 10.2 : TUER LA MAIN GAGNANTE</h2>
            <p><?php echo htmlspecialchars("Le donneur ne peut faire perdre la main gagnante si celle-ci a été correctement exposée et était de toute évidence la main gagnante. Les joueurs de la table sont invités à apporter leur aide au donneur s’il apparaît qu’une erreur va être commise."); ?></p>
            <p><?php echo htmlspecialchars("Si un joueur expose une seule carte qui rend sa main gagnante, le donneur doit lui demander d’exposer sa deuxième carte. S’il ne le fait pas, un responsable du tournoi doit être appelé (voir §10.5) pour que le joueur expose sa deuxième carte et soit éventuellement pénalisé."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 10.3 : LES CARTES ONT RAISON</h2>
            <p><?php echo htmlspecialchars("À l’abattage, les cartes parlent. Les annonces verbales concernant la main d’un joueur n’ont aucune valeur ; cependant, un joueur annonçant délibérément une fausse main pourra être pénalisé."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 10.4 : FACE VISIBLE EN CAS DE TAPIS</h2>
            <p><?php echo htmlspecialchars("Toutes les cartes de tous les joueurs en jeu seront retournées face visible si au moins un joueur est à tapis et que plus aucune enchère n’est possible, quel que soit le moment où le tapis est intervenu. Les jeux seront donc vus pour l’attribution d’un éventuel pot secondaire entre joueur non à tapis ET ensuite pour l’attribution du ou des pots impliquant un ou plusieurs joueurs à tapis."); ?></p>
            <p><?php echo htmlspecialchars("Si un joueur jette ses cartes faces cachées alors qu’un adversaire au moins est à tapis et qu’il ne fait face à aucune relance, il devra être sanctionné et son jeu récupéré dans la mesure du possible. La sanction sera plus importante si le jeu ne peut pas être récupéré. Si le jeu est récupéré et que le joueur gagne le coup, il gagnera le pot mais la sanction pourra être aggravée en fonction du désordre causé à la table (suspicion de collusion, …)"); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 10.5 : ABATTAGE</h2>
            <p><?php echo htmlspecialchars("À la fin du dernier tour d’enchères, le joueur qui a effectué la dernière action agressive (mise ou relance) dans ce tour doit montrer sa main le premier, puis les autres doivent le faire dans l’ordre de jeu."); ?></p>
            <p><?php echo htmlspecialchars("S’il n’y a eu aucune mise, le premier joueur à gauche du bouton est le premier à montrer sa main et ainsi de suite dans le sens des aiguilles d’une montre. Par main on entend obligatoirement les deux cartes privatives."); ?></p>
            <p><?php echo htmlspecialchars("Sauf en cas de joueur à tapis (voir ci-dessus §10.4), tout joueur peut refuser son droit à disputer le pot en jetant ses cartes faces cachées, mais doit le faire de façon qu’aucun doute sur son intention ne soit possible. Il est interdit de jeter dans le muck au cas où un autre joueur qui en a le droit demande à voir les cartes (voir ci-dessous)."); ?></p>
            <p><?php echo htmlspecialchars("Si tous les joueurs sauf un jettent leurs cartes face cachée, ce dernier remporte le coup sans avoir à dévoiler sa main"); ?></p>
            <p><?php echo htmlspecialchars("Seuls les joueurs ayant suivi une mise ou une relance à la river peuvent demander à voir le jeu du dernier adversaire ayant misé ou relancé et seulement le sien. S’ils en font la demande (et ce même après avoir montré leurs mains mais avant que le pot ne soit attribué), le jeu doit leur être montré sous peine de sanction pour le relanceur qui aura jeté ses cartes directement dans le muck."); ?></p>
            <p><?php echo htmlspecialchars("En demandant à voir les cartes d’un adversaire, le demandant les remet en jeu : même si elles avaient été jetées, si elles sont gagnantes le pot sera attribué au joueur qui les détenait."); ?></p>
            <p><?php echo htmlspecialchars("Un joueur qui ne retourne qu’une carte à l’abattage et ensuite jette ses cartes sans attendre que le pot lui soit attribué le fait à ses propres risques : si le TD estime que ses cartes ne sont plus identifiables et que le pot n’a pas été clairement attribué, il n’aura plus aucun droit au pot."); ?></p>
            <p><?php echo htmlspecialchars("Même pour prétendre partager le pot, un joueur doit montrer ses deux cartes privatives."); ?></p>
            <p><?php echo htmlspecialchars("Les cartes ne sont considérées comme abandonnées que lorsqu’elles sont envoyées distinctement vers le muck. Des cartes posées face cachée devant le joueur peuvent être reprises pour disputer le pot."); ?></p>
            <p><?php echo htmlspecialchars("Si un joueur checke en dernier de parole alors qu’il a le seul meilleur jeu possible à la river, il ne sera pas automatiquement pénalisé : c’est au Directeur de Tournoi d’apprécier la situation avant de le pénaliser ou pas, une pénalité ne pouvant provenir que d’un soupçon de collusion."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 10.6 : DEMANDE DE CONTROLER LES CARTES A L’ABATTAGE</h2>
            <p><?php echo htmlspecialchars("Tout joueur à table peut demander à un responsable de vérifier le ou les jeux non dévoilés dans le but de lutter contre la collusion. Un joueur qui demande au responsable du tournoi de regarder les cartes d’un autre joueur doit exprimer clairement que c’est pour un soupçon de triche, sans quoi le responsable ne peut valider la demande. Tout usage abusif de cette demande devra être sanctionné."); ?></p>
            <p><?php echo htmlspecialchars("Tout floor peut également demander à vérifier le ou les jeux non dévoilés dans le but de lutter contre la collusion, il doit indiquer que c’est dans ce but. Dans ce cadre, il vérifie le jeu sans le montrer aux autres joueurs. Une fois la vérification faite, le TD peut choisir de dévoiler ou non le jeu à l’ensemble de la table."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 10.7 : POTS EXTERIEURS</h2>
            <p><?php echo htmlspecialchars("Chaque pot extérieur sera divisé séparément avant tirage de la ou des cartes suivantes (flop, turn ou river). À la fin du coup, les pots seront attribués en commençant par celui qui concerne le moins de joueurs (le pot le plus extérieur)."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 10.8 : JETON INDIVISIBLE</h2>
            <p><?php echo htmlspecialchars("Lors du partage d’un pot, le jeton indivisible va au joueur en jeu le plus près à gauche du bouton."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 10.9 : ATTRIBUER LE POT ET RECLAMATION</h2>
            <p><?php echo htmlspecialchars("Le pot est attribué par le dealer au joueur ayant remporté le coup. Toute personne présente autour du coup doit aider à ce que l’attribution se fasse à la bonne personne : joueur dans le coup ou pas, spectateur, TD, …"); ?></p>
            <p><?php echo htmlspecialchars("Si, lors d’un coup, les jetons sont attribués au mauvais joueur du fait d’une mauvaise lecture du jeu, l’attribution peut être modifiée en cas d’intervention immédiate d’un tiers (avant que les cartes ne soient ramassées) par exception au §3.1 (qui indique que le coup suivant commence lors de l’attribution du pot). Une fois que les cartes sont ramassées et même si tous les joueurs sont d’accord à propos de l’erreur, l’attribution du pot ne peut plus être modifiée"); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 11 : ÉTHIQUE & SANCTIONS</h2>
            <p><?php echo htmlspecialchars("Les fautes de jeu peuvent influencer une décision voir même changer intégralement un coup, pour cette raison il faut réduire au maximum la possibilité des joueurs de commettre des fautes de jeu."); ?></p>
            <p><?php echo htmlspecialchars("La première étape est d’éduquer les joueurs, leur apprendre les fautes de jeu et leurs sanctions."); ?></p>
            <p><?php echo htmlspecialchars("La deuxième étape sera de donner une progression dans les sanctions par rapport au degré d’importance de la faute et aussi en fonction du niveau dans le tournoi."); ?></p>
            <p><?php echo htmlspecialchars("La dernière étape sera de rendre la sanction systématique, peu importe le degré de la faute, afin de la rendre punitive."); ?></p>
            <p><?php echo htmlspecialchars("Avec ces directives, les fautes de jeu seront moins présentes et le jeu en sera plus agréable pour les joueurs. Trois degrés de sanction sont à choisir pour les organisateurs de tournois, qu’ils devront indiquer dans leur règlement de tournoi :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Sanction légère (éducatif) : principalement des avertissements suivis d’explication. C’est conseillé pour des « tournois open » ou des « tournois réguliers »."); ?></li>
                <li><?php echo htmlspecialchars("Sanction normale : Avertissement et sanction en fonction de l’importance de la faute et du niveau du tournoi. C’est conseillé pour des « tournois réguliers » ou des « tournois sur plusieurs jours »."); ?></li>
                <li><?php echo htmlspecialchars("Sanction lourde (compétition) : Sanction systématique sans avertissement. C’est conseillé pour des « tournois en compétition »."); ?></li>
            </ul>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 11.1 : SANCTIONS ET DISQUALIFICATION</h2>
            <p><?php echo htmlspecialchars("Une sanction PEUT être appliquée si un joueur"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("S’éloigne de la table ou utilise un appareil électronique en ayant encore des cartes (voir §4.1)"); ?></li>
                <li><?php echo htmlspecialchars("Annonce une fausse donne volontairement ou répétition de fausse donne (voir §6.1 et §6.2)"); ?></li>
                <li><?php echo htmlspecialchars("Joue avant son tour (voir §9.3)"); ?></li>
                <li><?php echo htmlspecialchars("Fait une déclaration conditionnelle (voir §9.19)"); ?></li>
                <li><?php echo htmlspecialchars("Open muck (voir §9.22)"); ?></li>
                <li><?php echo htmlspecialchars("Jette ses cartes intentionnellement dans le muck à l’abattage (voir §10.5)"); ?></li>
                <li><?php echo htmlspecialchars("Viole la règle du « un jeu = un joueur » (voir §11.3)"); ?></li>
                <li><?php echo htmlspecialchars("Montre ses cartes alors qu’un coup est engagé (voir §11.4)"); ?></li>
                <li><?php echo htmlspecialchars("Ou si un incident similaire prend place"); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Des sanctions SERONT appliquées en cas de"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Collusion et soft play"); ?></li>
                <li><?php echo htmlspecialchars("Abus,"); ?></li>
                <li><?php echo htmlspecialchars("Injure et comportement dérangeant,"); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Les sanctions possibles incluent les avertissements verbaux, les mains et les tours de pénalités."); ?></p>
            <p><?php echo htmlspecialchars("Les tours de pénalités seront infligés comme suivant : le joueur sanctionné manquera une main par joueur à la table lorsque la sanction est donnée, y compris lui-même, multiplié par le nombre de tours spécifiés dans la sanction. Pour la durée de la sanction, le joueur sanctionné devra rester à l’écart de la table mais recevra tout de même un jeu sans que quiconque ne puisse le voir."); ?></p>
            <p><?php echo htmlspecialchars("Seul le Directeur de Tournoi peut prononcer une disqualification"); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 11.2 : LA HIERARCHIE DES SANCTIONS</h2>
            <p><?php echo htmlspecialchars("Si la même faute se répète avec le même joueur, les sanctions suivantes pourront être prises dans l’ordre chronologique :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Avertissement (warning). C’est sans conséquence sur le jeu, mais en cas d’autre sanction, elle devra être plus importante."); ?></li>
                <li><?php echo htmlspecialchars("Suspension momentanée du tournoi pendant moins d’un tour de table (le plus souvent, 1 ou 2 mains) : le joueur peut rester à la table et les blindes lui sont prélevées. Les cartes lui sont distribuées et sont automatiquement déclarées mortes sans que personne ne puisse les voir."); ?></li>
                <li><?php echo htmlspecialchars("Suspension momentanée du tournoi pendant 1, 2, 3 ou 4 tours de table. Le joueur doit s’éloigner de la table et les blindes lui sont prélevées. Les cartes lui sont distribuées et sont automatiquement déclarées mortes sans que personne ne puisse les voir."); ?></li>
                <li><?php echo htmlspecialchars("Disqualification définitive du tournoi. Les jetons du joueur sont alors retirés du tournoi."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Le directeur du tournoi est seul habilité à juger du caractère volontaire ou non de la répétition des fautes. En fonction de la gravité de la faute, le Directeur de Tournoi peut directement suspendre un joueur ou le disqualifier."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 11.3 : PROTECTION DES AUTRES JOUEURS</h2>
            <p><?php echo htmlspecialchars("Les joueurs se doivent de protéger les autres joueurs dans le tournoi en tout temps. De ce fait, qu’ils soient dans un coup ou non, par exemple ils ne peuvent :"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("Révéler le contenu de mains en jeu ou couchées sur le coup en cours,"); ?></li>
                <li><?php echo htmlspecialchars("Conseiller ou critiquer une action quoi qu’il arrive,"); ?></li>
                <li><?php echo htmlspecialchars("Spéculer sur le contenu d’une main qui n’a pas été tablée."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Ainsi,"); ?></p>
            <ul>
                <li><?php echo htmlspecialchars("les joueurs qui ne sont plus dans le coup ne peuvent aucunement parler du coup en cours (même pour des commentaires “évidents” à propos du tableau)."); ?></li>
                <li><?php echo htmlspecialchars("les joueurs qui sont dans le coup ne peuvent pas en parler tant qu’ils sont plus de 2 (sauf annonces officielles)."); ?></li>
                <li><?php echo htmlspecialchars("Si les joueurs dans le coup ne sont que deux, ils peuvent alors spéculer sur la fin du coup en respectant l’adversaire et le jeu et sans faire d’annonce conditionnelle."); ?></li>
            </ul>
            <p><?php echo htmlspecialchars("Par exemple, une remarque anodine « il y a 4 piques sur le tableau », faite par quiconque à la table ou autour, aurait pu ‘sauver’ Phil Ivey d’un muck de main gagnante lors des WSOP 2009 (cherchez la video !)."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 11.4 : CARTES REVELEES</h2>
            <p><?php echo htmlspecialchars("Si un joueur encore en jeu montre intentionnellement sa main ou même une carte à quiconque (à un spectateur, un joueur ayant couché son jeu, son capitaine, …) sans la retourner, sa main sera montrée à l’ensemble de la table en fin de coup. De plus, le joueur devra être sanctionné."); ?></p>
            <p><?php echo htmlspecialchars("Si la main est montrée à un joueur encore en jeu, elle doit être immédiatement montrée à toute la table afin que les joueurs en jeu aient la même information. Le joueur ayant montré sa main devra être sanctionné plus sévèrement dans ce cas."); ?></p>
            <p><?php echo htmlspecialchars("Exception : s’il ne reste qu’un joueur en jeu (le joueur abandonne le coup alors qu’il ne restait que 2 joueurs), la main peut être montrée mais doit l’être à l’ensemble de la table en les retournant face visible sur la table (« Show One, Show All »)"); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 11.5 : CARTES RETOURNEES PAR UN JOUEUR</h2>
            <p><?php echo htmlspecialchars("Un joueur qui retourne ses cartes face visible devant lui alors que l’action n’est pas finie recevra une sanction, mais sa main ne sera pas brûlée (voir §8.6). La sanction débutera à la fin de la main."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 11.6 : ÉTHIQUE</h2>
            <p><?php echo htmlspecialchars("Le poker est un jeu individuel. La collusion sera sanctionnée, ce qui peut inclure une annulation des jetons et/ou disqualification. Le chip dumping (donner volontairement des jetons à un autre joueur en foldant une mise) et/ou toute autre forme de collusion sera passible de disqualification."); ?></p>
        </div>

        <div class="sub-article">
            <h2>ARTICLE 11.7 : MANQUEMENTS ETHIQUES ET COMPORTEMENTS IRRESPECTUEUX</h2>
            <p><?php echo htmlspecialchars("Les manquements éthiques et comportements irrespectueux envers qui que ce soit (responsable, joueur, presse, public…) répétés seront sanctionnés. Des exemples peuvent inclure le fait de toucher les cartes ou les jetons d’un autre joueur alors que ce n’est pas nécessaire, retarder le jeu, parler avant son tour avec répétition ou parler excessivement. Cette liste n’est pas exhaustive et reste à la discrétion du TD"); ?></p>
        </div>
    </div>

    <div class="article">
        <div class="article-header"><h2>RÉPARTITION DES GAINS EN MTT HOLD'EM ET OMAHA</h2></div>
        <div class="sub-article">
            <style>
                .gains-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                    background-color: rgba(255, 255, 255, 0.95);
                }
                
                .gains-table th, .gains-table td {
                    border: 1px solid #2c3e50;
                    padding: 8px;
                    text-align: center;
                }
                
                .gains-table th {
                    background-color: #34495e;
                    color: white;
                }
                
                .gains-table tr:nth-child(even) {
                    background-color: rgba(255, 255, 255, 0.7);
                }
                
                .gains-table tr:hover {
                    background-color: rgba(52, 152, 219, 0.1);
                }
            </style>
            <table class="gains-table">
                <tr>
                    <th>Participants</th>
                    <th>6 à 9</th>
                    <th>10 à 16</th>
                    <th>17 à 22</th>
                    <th>23 à 30</th>
                    <th>31 à 36</th>
                    <th>37 à 40</th>
                    <th>41 à 45</th>
                    <th>46 à 50</th>
                </tr>
                <tr>
                    <th>Classement</th>
                    <th>2 premiers</th>
                    <th>3 premiers</th>
                    <th>4 premiers</th>
                    <th>5 premiers</th>
                    <th>6 premiers</th>
                    <th>7 premiers</th>
                    <th>8 premiers</th>
                    <th>9 premiers</th>
                </tr>
                <tr>
                    <td>1</td>
                    <td>60 %</td>
                    <td>50 %</td>
                    <td>45 %</td>
                    <td>45 %</td>
                    <td>40 %</td>
                    <td>35 %</td>
                    <td>30 %</td>
                    <td>25 %</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>40 %</td>
                    <td>30 %</td>
                    <td>30 %</td>
                    <td>25 %</td>
                    <td>20 %</td>
                    <td>20 %</td>
                    <td>15 %</td>
                    <td>15 %</td>
                    
                </tr>
                <tr>
                    <td>3</td>
                    <td>-</td>
                    <td>20 %</td>
                    <td>15 %</td>
                    <td>15 %</td>
                    <td>15 %</td>
                    <td>15 %</td>
                    <td>15 %</td>
                    <td>15 %</td>
                    
                </tr>
                <tr>
                    <td>4</td>
                    <td>-</td>
                    <td>-</td>
                    <td>10 %</td>
                    <td>10 %</td>
                    <td>10 %</td>
                    <td>10 %</td>
                    <td>10 %</td>
                    <td>10 %</td>
                    
                </tr>
                <tr>
                    <td>5</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>5 %</td>
                    <td>10 %</td>
                    <td>10 %</td>
                    <td>10 %</td>
                    <td>10 %</td>
                    
                </tr>
                <tr>
                    <td>6</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>5 %</td>
                    <td>5 %</td>
                    <td>10 %</td>
                    <td>10 %</td>
                    
                </tr>
                <tr>
                    <td>7</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>5 %</td>
                    <td>5 %</td>
                    <td>5 %</td>
                    
                </tr>
                <tr>
                    <td>8</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>5 %</td>
                    <td>5 %</td>
                </tr>
                <tr>
                    <td>9</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>5 %</td>
                </tr>
            </table>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="/index.html">Accueil</a>
                <a href="dashboard.php">Menus</a>
                <!-- <a href="#">Mentions légales</a> -->
            </div>
            <div class="footer-copyright">
                © <?php echo date('Y'); ?> Franck.W.
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchResults = document.getElementById('searchResults');
            const content = document.querySelector('.article');
            const originalContent = content.innerHTML;
            const prevButton = document.getElementById('prevMatch');
            const nextButton = document.getElementById('nextMatch');
            const currentMatch = document.querySelector('.current-match');
            
            let currentHighlight = 0;
            let totalHighlights = 0;

            function updateNavigation() {
                const highlights = document.querySelectorAll('.highlight');
                totalHighlights = highlights.length;
                
                if (totalHighlights > 0) {
                    // Mettre à jour le compteur
                    currentMatch.textContent = `${currentHighlight + 1}/${totalHighlights}`;
                    
                    // Activer/désactiver les boutons
                    prevButton.disabled = currentHighlight === 0;
                    nextButton.disabled = currentHighlight === totalHighlights - 1;
                    
                    // Réinitialiser tous les surlignages en jaune
                    highlights.forEach(h => h.style.backgroundColor = 'yellow');
                    
                    // Mettre en évidence le résultat actuel en orange et défiler jusqu'à lui
                    if (highlights[currentHighlight]) {
                        highlights[currentHighlight].style.backgroundColor = 'orange';
                        
                        // Calculer la position de défilement
                        const element = highlights[currentHighlight];
                        const elementRect = element.getBoundingClientRect();
                        const absoluteElementTop = elementRect.top + window.pageYOffset;
                        const middle = absoluteElementTop - (window.innerHeight / 2);
                        
                        window.scrollTo({
                            top: middle,
                            behavior: 'smooth'
                        });

                        // Ajouter une petite animation flash pour mieux repérer le résultat
                        element.style.transition = 'background-color 0.3s';
                        element.style.backgroundColor = '#ff9632';
                        setTimeout(() => {
                            element.style.backgroundColor = 'orange';
                        }, 300);
                    }
                } else {
                    currentMatch.textContent = '0/0';
                    prevButton.disabled = true;
                    nextButton.disabled = true;
                }
            }

            // Ajouter la gestion des touches clavier pour la navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (currentHighlight < totalHighlights - 1) {
                        currentHighlight++;
                        updateNavigation();
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (currentHighlight > 0) {
                        currentHighlight--;
                        updateNavigation();
                    }
                }
            });

            prevButton.addEventListener('click', () => {
                if (currentHighlight > 0) {
                    currentHighlight--;
                    updateNavigation();
                }
            });

            nextButton.addEventListener('click', () => {
                if (currentHighlight < totalHighlights - 1) {
                    currentHighlight++;
                    updateNavigation();
                }
            });

            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                currentHighlight = 0;
                
                if (searchTerm.length < 3) {
                    content.innerHTML = originalContent;
                    searchResults.textContent = '';
                    currentMatch.textContent = '0/0';
                    prevButton.disabled = true;
                    nextButton.disabled = true;
                    return;
                }

                // Restaurer le contenu original avant chaque nouvelle recherche
                content.innerHTML = originalContent;

                // Rechercher et surligner
                let matches = 0;
                
                // Fonction récursive pour parcourir tous les nœuds de texte
                function highlightText(element) {
                    if (element.nodeType === Node.TEXT_NODE) {
                        const text = element.nodeValue;
                        if (text.toLowerCase().includes(searchTerm)) {
                            const newText = text.replace(new RegExp(searchTerm, 'gi'), (match) => {
                                matches++;
                                return `<span class="highlight">${match}</span>`;
                            });
                            const span = document.createElement('span');
                            span.innerHTML = newText;
                            element.parentNode.replaceChild(span, element);
                        }
                    } else {
                        // Parcourir récursivement tous les enfants
                        Array.from(element.childNodes).forEach(child => {
                            highlightText(child);
                        });
                    }
                }

                // Démarrer la recherche récursive depuis l'élément content
                highlightText(content);

                // Afficher les résultats
                if (matches > 0) {
                    searchResults.textContent = `${matches} résultat(s) trouvé(s)`;
                    updateNavigation();
                } else {
                    searchResults.textContent = 'Aucun résultat trouvé';
                    currentMatch.textContent = '0/0';
                    prevButton.disabled = true;
                    nextButton.disabled = true;
                }
            });
        });
    </script>
</body>
</html>

