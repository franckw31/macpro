<?php
// Connexion à la base de données
$servername = "localhost";
$username = "root";
$password = "Kookies7*";
$dbname = "dbs9616600";

// Créer la connexion
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Vérifier la connexion
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Fonction pour échapper les entrées utilisateur
function escape($conn, $value) {
    return mysqli_real_escape_string($conn, $value);
}

// Traitement du formulaire pour ajouter une adresse
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["address"])) {
    $address = escape($conn, $_POST["address"]);
    $latitude = floatval($_POST["latitude"]);
    $longitude = floatval($_POST["longitude"]);
    $identifier = intval($_POST["identifier"]);
    
    // Formatter les coordonnées avec 6 décimales pour l'affichage
    $formatted_latitude = number_format($latitude, 6);
    $formatted_longitude = number_format($longitude, 6);
    
    // Insérer dans la base de données
    $sql = "INSERT INTO adresse (address, latitude, longitude, identifier) VALUES ('$address', $latitude, $longitude, $identifier)";
    
    if (mysqli_query($conn, $sql)) {
        $success_message = "Adresse enregistrée avec succès!!";
    } else {
        $error_message = "Erreur: " . mysqli_error($conn);
    }
}

// Récupérer l'historique des adresses
$wait_message = "En attente : ";
$sql = "SELECT id, address, latitude, longitude, identifier, date_ajout FROM adresse ORDER BY date_ajout DESC";
$result = mysqli_query($conn, $sql);
$addresses = [];

if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $addresses[] = $row;
    }
}

// Convertir les adresses en JSON pour JavaScript
$addresses_json = json_encode($addresses);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Géolocalisation avec Mapbox</title>
    
    <!-- Mapbox CSS -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.css" rel="stylesheet">
    <link href="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.css" rel="stylesheet">
    
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        
        #map {
            width: 100%;
            height: 500px;
            margin-bottom: 20px;
        }
        
        .form-container {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 10px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
        }
        
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        
        button {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        
        button:hover {
            background-color: #45a049;
        }
        
        .coordinates-display {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f8f8;
            border: 1px solid #ddd;
        }
        
        .mapboxgl-popup {
            max-width: 300px;
        }
        
        .mapboxgl-popup-content {
            padding: 15px;
        }
        
        .success-message {
            color: green;
            padding: 10px;
            background-color: #f0fff0;
            border: 1px solid #d0e9d0;
            margin-bottom: 15px;
        }
        
        .error-message {
            color: red;
            padding: 10px;
            background-color: #fff0f0;
            border: 1px solid #e9d0d0;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <h1>Déplacer le marqueur ROUGE pour une nouvelle adresse</h1>
    
    
    <div id="map"></div>
    <div class="form-container">
        <form id="locationForm" method="POST">
            <div class="form-group">
                <label for="address">Adresse:</label>
                <input type="text" id="address" name="address" required>
            </div>
            <div class="form-group">
                <label for="identifier">Identifiant:</label>
                <input type="number" id="identifier" name="identifier" required>
            </div>
            <input type="hidden" id="latitude" name="latitude">
            <input type="hidden" id="longitude" name="longitude">
            <button type="submit">Enregistrer</button>
        </form>
    </div>
    <?php if(isset($success_message)): ?>
        <div class="success-message"><?php echo $succes_message; ?></div>
    <?php endif; ?>
    <?php if(!isset($success_message)): ?>
        <div class="success-message"><?php echo $wait_message; ?></div>
    <?php endif; ?>
    
    <?php if(isset($error_message)): ?>
        <div class="error-message"><?php echo $error_message; ?></div>
    <?php endif; ?>
    <!--
    <div class="coordinates-display" id="coordinates">
        <p><strong>Adresse sélectionnée:</strong> <span id="selectedAddress">-</span></p>
        <p><strong>Latitude:</strong> <span id="latitudeDisplay">-</span></p>
        <p><strong>Longitude:</strong> <span id="longitudeDisplay">-</span></p>
    </div>
    -->
        
    <!-- Mapbox JS -->
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.js"></script> 
    <script src="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js"></script>
    
    <script>
        // Configuration Mapbox
        mapboxgl.accessToken = 'pk.eyJ1IjoiZnJhbmNrdzMxIiwiYSI6ImNsbmJqemU5cjA0MDYya3RkczNrMHdqb2wifQ.6NLEMz-lShL80j9QuGW9cA';
        
        // Initialiser la carte
        const map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [1.43, 43.60],
            zoom: 10.5
        });
        
        // Créer un marqueur déplaçable
        const marker = new mapboxgl.Marker({
            color: '#FF0000',
            draggable: true
        }).setLngLat([1.453635, 43.591568]).addTo(map);
        
        // Récupérer les éléments DOM
        const addressInput = document.getElementById('address');
        const latitudeInput = document.getElementById('latitude');
        const longitudeInput = document.getElementById('longitude');
        const latitudeDisplay = document.getElementById('latitudeDisplay');
        const longitudeDisplay = document.getElementById('longitudeDisplay');
        const selectedAddressDisplay = document.getElementById('selectedAddress');
        
        // Ajouter le geocoder
        const geocoder = new MapboxGeocoder({
            accessToken: mapboxgl.accessToken,
            mapboxgl: mapboxgl,
            // color: '#666666',
            marker: false,
            placeholder: '     Rechercher une adresse',
            zoom: 10
        });
        
        map.addControl(geocoder);
        // Ajouter les contrôles de navigation
        map.addControl(new mapboxgl.NavigationControl());
        // Quand une adresse est sélectionnée via le geocoder
        geocoder.on('result', function(e) {
            const coordinates = e.result.center;
            const address = e.result.place_name;
            
            // Mettre à jour le marqueur
            marker.setLngLat(coordinates);
            
            // Mettre à jour les champs du formulaire
            updateFormFields(coordinates[0], coordinates[1], address);
        });
        
        // Quand le marqueur est déplacé
        marker.on('dragend', function() {
            const lngLat = marker.getLngLat();
            
            // Reverse geocoding pour obtenir l'adresse
            fetch(`https://api.mapbox.com/geocoding/v5/mapbox.places/${lngLat.lng},${lngLat.lat}.json?access_token=${mapboxgl.accessToken}`)
                .then(response => response.json())
                .then(data => {
                    const address = data.features[0].place_name;
                    updateFormFields(lngLat.lng, lngLat.lat, address);
                });
        });
        
        // Fonction pour mettre à jour les champs du formulaire
        function updateFormFields(longitude, latitude, address) {
            // Formater avec 6 décimales
            const formattedLongitude = longitude.toFixed(6);
            const formattedLatitude = latitude.toFixed(6);
            
            // Mettre à jour les champs cachés du formulaire
            latitudeInput.value = latitude;
            longitudeInput.value = longitude;
            addressInput.value = address;
            
            // Mettre à jour l'affichage
            latitudeDisplay.textContent = formattedLatitude;
            longitudeDisplay.textContent = formattedLongitude;
            selectedAddressDisplay.textContent = address;
        }
        
        // Charger l'historique des adresses
        const addresses = <?php echo $addresses_json; ?>;
        
        // Afficher les marqueurs de l'historique sur la carte
        addresses.forEach(function(addr) {
            // Créer un popup pour chaque adresse
            const popup = new mapboxgl.Popup({ offset: 25 })
                .setHTML(`
                    <h3>ID: ${addr.identifier}</h3>
                    <p>${addr.address}</p>
                    <p>Lat: ${parseFloat(addr.latitude).toFixed(6)}</p>
                    <p>Lng: ${parseFloat(addr.longitude).toFixed(6)}</p>
                    <p>Date: ${addr.date_ajout}</p>
                `);
            
            // Ajouter un marqueur avec popup
            new mapboxgl.Marker({ color: '#3887be' })
                .setLngLat([parseFloat(addr.longitude), parseFloat(addr.latitude)])
                .setPopup(popup)
                .addTo(map);
        });
        
        // Si l'historique n'est pas vide, fit la carte pour montrer tous les points
        if (addresses.length > 0) {
            // Créer une bounding box pour tous les points
            const bounds = new mapboxgl.LngLatBounds();
            addresses.forEach(function(addr) {
                bounds.extend([parseFloat(addr.longitude), parseFloat(addr.latitude)]);
            });
            
            // Ajuster la carte
            //map.fitBounds(bounds, { padding: 50 });
        }
    </script>
</body>
</html>
<?php
// Fermer la connexion
mysqli_close($conn);
?>