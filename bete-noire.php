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
    $query = "
        SELECT 
            p.`nom-membre` AS victim, 
            e.nom_membre AS eliminator, 
            COUNT(*) as elim_count 
        FROM eliminations e
        JOIN participation p ON e.id_participation = p.`id-participation`
        WHERE e.nom_membre IS NOT NULL AND e.nom_membre != '' 
          AND p.`nom-membre` IS NOT NULL AND p.`nom-membre` != ''
          AND e.nom_membre != p.`nom-membre` 
        GROUP BY p.`nom-membre`, e.nom_membre
        ORDER BY p.`nom-membre` ASC, elim_count DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll();

    $data = [];
    foreach ($results as $row) {
        $victim = ucfirst(strtolower($row['victim']));
        $eliminator = ucfirst(strtolower($row['eliminator']));
        $count = (int)$row['elim_count'];

        if (!isset($data[$victim])) {
            $data[$victim] = [];
        }
        $data[$victim][] = [
            'eliminator' => $eliminator,
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
                    <i class="fa-solid fa-skull-crossbones mr-3 text-red-500"></i>Bêtes Noires
                </span>
            </h1>
            <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                Tableau des pires cauchemars : qui a éliminé qui le plus grand nombre de fois ? 
                Survivez à votre bête noire lors du prochain tournoi !
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($data as $victim => $eliminators): 
                // The first one is the "bête noire" since query is ordered by count desc
                $beteNoire = $eliminators[0];
            ?>
            <div class="victim-card bg-slate-800 rounded-2xl overflow-hidden border border-slate-700 relative flex flex-col h-full">
                <!-- Top Header for Victim -->
                <div class="bg-slate-800 p-5 border-b border-slate-700 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full border-2 border-slate-500 flex items-center justify-center bg-slate-700 text-xl font-bold">
                        <?= strtoupper(substr($victim, 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-xl font-bold truncate" title="<?= htmlspecialchars($victim) ?>">
                            <?= htmlspecialchars($victim) ?>
                        </h2>
                        <p class="text-xs text-slate-400 uppercase tracking-widest font-semibold mt-1">victime</p>
                    </div>
                </div>

                <!-- Main Bête Noire -->
                <div class="p-5 bg-gradient-to-br from-slate-800 to-slate-900 relative overflow-hidden flex-1">
                    <div class="absolute right-[-20px] top-[10px] text-red-500/10 text-8xl transform -rotate-12 pointer-events-none">
                        <i class="fa-solid fa-ghost"></i>
                    </div>
                    <div class="relative z-10 h-full flex flex-col justify-center">
                        <p class="text-[11px] font-bold text-red-400 mb-2 uppercase tracking-wide flex items-center gap-1.5">
                            <i class="fa-solid fa-crosshairs"></i> Son Pire Cauchemar
                        </p>
                        <div class="flex items-end justify-between gap-3">
                            <h3 class="text-2xl font-bold text-white truncate" title="<?= htmlspecialchars($beteNoire['eliminator']) ?>">
                                <?= htmlspecialchars($beteNoire['eliminator']) ?>
                            </h3>
                            <div class="flex flex-col items-center shrink-0 bg-red-500/10 px-3 py-1.5 rounded-lg border border-red-500/20">
                                <span class="text-3xl font-black text-red-500 leading-none">
                                    <?= $beteNoire['count'] ?>
                                </span>
                                <span class="text-[9px] text-red-300 font-bold uppercase mt-1">Fois</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Other Eliminators (if any) -->
                <?php if (count($eliminators) > 1): ?>
                <div class="px-5 py-4 bg-slate-800/80 flex flex-col gap-2.5 border-t border-slate-700">
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Aussi éliminé(e) par :</p>
                    <div class="flex flex-wrap gap-2">
                        <?php 
                        $others = array_slice($eliminators, 1, 3); // Take up to 3 others
                        foreach ($others as $other): 
                        ?>
                        <span class="inline-flex items-center gap-1.5 pl-2 pr-1 py-1 rounded-md text-[11px] font-medium bg-slate-700/50 text-slate-300 border border-slate-600/50 hover:bg-slate-700 transition-colors">
                            <?= htmlspecialchars($other['eliminator']) ?>
                            <span class="bg-indigo-500/20 text-indigo-300 min-w-[1.25rem] h-4 px-1 rounded block text-center flex items-center justify-center text-[10px] font-bold">
                                <?= $other['count'] ?>
                            </span>
                        </span>
                        <?php endforeach; ?>
                        
                        <?php 
                        $rest = array_slice($eliminators, 4);
                        if (!empty($rest)): 
                            $uid = md5($victim);
                        ?>
                            <button type="button" onclick="document.getElementById('m-<?= $uid ?>').style.display='contents'; this.style.display='none';" class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-medium text-indigo-400 hover:text-indigo-300 hover:bg-indigo-500/10 italic cursor-pointer transition-all">
                                +<?= count($rest) ?> autres <i class="fa-solid fa-chevron-down ml-1 text-[9px]"></i>
                            </button>
                            <span id="m-<?= $uid ?>" style="display: none;">
                                <?php foreach ($rest as $other): ?>
                                <span class="inline-flex items-center gap-1.5 pl-2 pr-1 py-1 rounded-md text-[11px] font-medium bg-slate-700/50 text-slate-300 border border-slate-600/50 hover:bg-slate-700 transition-colors">
                                    <?= htmlspecialchars($other['eliminator']) ?>
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
                <div class="px-5 p-4 flex items-center justify-start text-emerald-500/70 text-[11px] font-medium border-t border-slate-700/50 bg-slate-800/30 gap-2">
                    <i class="fa-solid fa-shield-halved"></i> Aucun autre éliminateur.
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