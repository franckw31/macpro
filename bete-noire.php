<?php
// bete-noire.php
// Liste des joueurs et leurs pires cauchemars (bêtes noires) via Tailwind CSS.

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Get everyone's bêtes noires
    // A bête noire is the person who eliminated them the most.
    $mode = $_GET['mode'] ?? 'normal';

    if ($mode === 'inverse') {
        // Mode inverse : On affiche qui le joueur a éliminé le plus
        $query = "
            SELECT 
                e.nom_membre AS main_person, 
                p.`nom-membre` AS opponent, 
                COUNT(*) as elim_count 
            FROM eliminations e
            JOIN participation p ON e.id_participation = p.`id-participation`
            WHERE e.nom_membre IS NOT NULL AND e.nom_membre != '' 
              AND p.`nom-membre` IS NOT NULL AND p.`nom-membre` != ''
              AND e.nom_membre != p.`nom-membre` 
            GROUP BY e.nom_membre, p.`nom-membre`
            ORDER BY e.nom_membre ASC, elim_count DESC
        ";
    } else {
        // Mode normal : On affiche par qui le joueur a été éliminé le plus
        $query = "
            SELECT 
                p.`nom-membre` AS main_person, 
                e.nom_membre AS opponent, 
                COUNT(*) as elim_count 
            FROM eliminations e
            JOIN participation p ON e.id_participation = p.`id-participation`
            WHERE e.nom_membre IS NOT NULL AND e.nom_membre != '' 
              AND p.`nom-membre` IS NOT NULL AND p.`nom-membre` != ''
              AND e.nom_membre != p.`nom-membre` 
            GROUP BY p.`nom-membre`, e.nom_membre
            ORDER BY p.`nom-membre` ASC, elim_count DESC
        ";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll();

    $data = [];
    foreach ($results as $row) {
        $main_person = ucfirst(strtolower($row['main_person']));
        $opponent = ucfirst(strtolower($row['opponent']));
        $count = (int)$row['elim_count'];

        if (!isset($data[$main_person])) {
            $data[$main_person] = [];
        }
        $data[$main_person][] = [
            'opponent' => $opponent,
            'count' => $count
        ];
    }

    // Sort by most total eliminations received or just alphabetically?
    // Alphabetically is easier to find yourself, let's keep it sorted by name
    ksort($data);

} catch (Exception $e) {
    die("Erreur de connexion a la base de donnees. " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bêtes Noires</title>
    <!-- Tailwind CSS (via CDN pour un rendu très visuel immédiatement) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .victim-card {
            transition: all 0.3s ease;
        }
        .victim-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
        }
        .text-gradient {
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-4 md:p-8">

    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-12 py-8">
            <h1 class="text-4xl md:text-5xl font-extrabold mb-4">
                <span class="bg-gradient-to-r from-red-500 to-orange-500 text-gradient">
                    <i class="fa-solid fa-skull-crossbones mr-3 text-red-500"></i><?= $mode === 'inverse' ? 'Bourreaux & Victimes' : 'Bêtes Noires' ?>
                </span>
            </h1>
            <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                <?= $mode === 'inverse' 
                    ? 'Tableau de chasse : Qui est le pire bourreau de qui ? Découvrez les victimes favorites de chaque joueur.' 
                    : 'Tableau des pires cauchemars : qui a éliminé qui le plus grand nombre de fois ? Survivez à votre bête noire lors du prochain tournoi !' ?>
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($data as $main_person => $opponents): 
                // The first one is the "bête noire" or "favorite victim" since query is ordered by count desc
                $beteNoire = $opponents[0];
            ?>
            <div class="victim-card bg-slate-800 rounded-2xl overflow-hidden border border-slate-700 relative flex flex-col h-full">
                <!-- Top Header for Main Person -->
                <div class="bg-slate-800 p-5 border-b border-slate-700 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full border-2 border-slate-500 flex items-center justify-center bg-slate-700 text-xl font-bold">
                        <?= strtoupper(substr($main_person, 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-xl font-bold truncate" title="<?= htmlspecialchars($main_person) ?>">
                            <?= htmlspecialchars($main_person) ?>
                        </h2>
                        <?php if ($mode === 'inverse'): ?>
                            <a href="?mode=normal" class="text-xs text-red-400 uppercase tracking-widest font-semibold mt-1 hover:text-red-300 transition-colors inline-flex items-center gap-1 cursor-pointer" title="Voir ses bêtes noires">
                                BOURREAU <i class="fa-solid fa-right-left text-[10px]"></i>
                            </a>
                        <?php else: ?>
                            <a href="?mode=inverse" class="text-xs text-blue-400 uppercase tracking-widest font-semibold mt-1 hover:text-blue-300 transition-colors inline-flex items-center gap-1 cursor-pointer" title="Voir ses victimes favorites">
                                VICTIME DE <i class="fa-solid fa-right-left text-[10px]"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Main Opponent -->
                <div class="p-5 bg-gradient-to-br from-slate-800 to-slate-900 relative overflow-hidden flex-1">
                    <div class="absolute right-[-20px] top-[10px] text-red-500/10 text-8xl transform -rotate-12 pointer-events-none">
                        <i class="fa-solid fa-ghost"></i>
                    </div>
                    <div class="relative z-10 h-full flex flex-col justify-center">
                        <p class="text-[11px] font-bold <?= $mode === 'inverse' ? 'text-orange-400' : 'text-red-400' ?> mb-2 uppercase tracking-wide flex items-center gap-1.5">
                            <i class="fa-solid fa-crosshairs"></i> <?= $mode === 'inverse' ? 'Sa Victime Favorite' : 'Son Pire Cauchemar' ?>
                        </p>
                        <div class="flex items-end justify-between gap-3">
                            <h3 class="text-2xl font-bold text-white truncate" title="<?= htmlspecialchars($beteNoire['opponent']) ?>">
                                <?= htmlspecialchars($beteNoire['opponent']) ?>
                            </h3>
                            <div class="flex flex-col items-center shrink-0 <?= $mode === 'inverse' ? 'bg-orange-500/10 border-orange-500/20 text-orange-500' : 'bg-red-500/10 border-red-500/20 text-red-500' ?> px-3 py-1.5 rounded-lg border">
                                <span class="text-3xl font-black leading-none">
                                    <?= $beteNoire['count'] ?>
                                </span>
                                <span class="text-[9px] <?= $mode === 'inverse' ? 'text-orange-300' : 'text-red-300' ?> font-bold uppercase mt-1">Fois</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Other Opponents (if any) -->
                <?php if (count($opponents) > 1): ?>
                <div class="px-5 py-4 bg-slate-800/80 flex flex-col gap-2.5 border-t border-slate-700">
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"><?= $mode === 'inverse' ? 'A aussi éliminé :' : 'Aussi éliminé(e) par :' ?></p>
                    <div class="flex flex-wrap gap-2">
                        <?php 
                        $others = array_slice($opponents, 1, 3); // Take up to 3 others
                        foreach ($others as $other): 
                        ?>
                        <span class="inline-flex items-center gap-1.5 pl-2 pr-1 py-1 rounded-md text-[11px] font-medium bg-slate-700/50 text-slate-300 border border-slate-600/50 hover:bg-slate-700 transition-colors">
                            <?= htmlspecialchars($other['opponent']) ?>
                            <span class="bg-indigo-500/20 text-indigo-300 min-w-[1.25rem] h-4 px-1 rounded block text-center flex items-center justify-center text-[10px] font-bold">
                                <?= $other['count'] ?>
                            </span>
                        </span>
                        <?php endforeach; ?>
                        
                        <?php 
                        $rest = array_slice($opponents, 4);
                        if (!empty($rest)): 
                            $uid = md5($main_person . $mode);
                        ?>
                            <button type="button" onclick="document.getElementById('m-<?= $uid ?>').style.display='contents'; this.style.display='none';" class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-medium text-indigo-400 hover:text-indigo-300 hover:bg-indigo-500/10 italic cursor-pointer transition-all">
                                +<?= count($rest) ?> autres <i class="fa-solid fa-chevron-down ml-1 text-[9px]"></i>
                            </button>
                            <span id="m-<?= $uid ?>" style="display: none;">
                                <?php foreach ($rest as $other): ?>
                                <span class="inline-flex items-center gap-1.5 pl-2 pr-1 py-1 rounded-md text-[11px] font-medium bg-slate-700/50 text-slate-300 border border-slate-600/50 hover:bg-slate-700 transition-colors">
                                    <?= htmlspecialchars($other['opponent']) ?>
                                    <span class="bg-indigo-500/20 text-indigo-300 min-w-[1.25rem] h-4 px-1 rounded block text-center flex items-center justify-center text-[10px] font-bold">
                                        <?= $other['count'] ?>
                                    </span>
                                </span>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="px-5 p-4 flex items-center justify-start <?= $mode === 'inverse' ? 'text-orange-500/70' : 'text-emerald-500/70' ?> text-[11px] font-medium border-t border-slate-700/50 bg-slate-800/30 gap-2">
                    <i class="fa-solid <?= $mode === 'inverse' ? 'fa-ghost' : 'fa-shield-halved' ?>"></i> <?= $mode === 'inverse' ? 'Aucune autre victime.' : 'Aucun autre éliminateur.' ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($data)): ?>
            <div class="col-span-full py-16 flex flex-col items-center justify-center text-slate-500 bg-slate-800/30 rounded-2xl border border-slate-700/50 border-dashed">
                <div class="relative mb-4">
                    <i class="fa-solid fa-ghost text-6xl opacity-20 transform -scale-x-100"></i>
                    <i class="fa-solid fa-ban absolute text-red-500/50 text-4xl top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"></i>
                </div>
                <p class="text-lg font-medium">Aucune donnée d'élimination disponible pour le moment.</p>
                <p class="text-sm mt-1 opacity-70">Les statistiques s'afficheront ici après les premiers tournois joués.</p>
            </div>
            <?php endif; ?>

        </div>
        
        <div class="mt-12 text-center pb-8">
            <a href="index.php" class="inline-flex items-center justify-center gap-2 px-6 py-3 border border-slate-600 rounded-xl text-sm font-medium text-slate-300 bg-slate-800 hover:bg-slate-700 hover:text-white transition duration-200">
                <i class="fa-solid fa-arrow-left"></i> Retour à l'accueil
            </a>
        </div>
    </div>
</body>
</html>