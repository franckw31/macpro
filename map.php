<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://api.tiles.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Open+Sans" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet"
        href="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.css"
        type="text/css">
    <link href="../dist/output.css" rel="stylesheet">
    <style>
        .body {
            margin: 0;
            padding: 10;
        }

        #map {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50;
            right: 5;
            width: 100%;
            height: 100%;
            background-color: #666;
        }

        #marker {
            /* background-image: url('https://docs.mapbox.com/mapbox-gl-js/assets/washington-monument.jpge'); */
            /* background-size: cover; */
            width: 125px;
            height: 125px;
            border-radius: 50%;
            opacity: 0.5;
            background-color: #666;
            background: #666;
            color: #666;
            width: 700px;
            height: 250px;
            left: 55%;
            bottom: 90px;
            top: auto;
            right: auto;
            border-radius: 33%;
            position: fixed;
            transform: none !important;
        }

        /* cursor: pointer; */
        .marker {
            /* background-image: url('mapbox-icon.png'); */
            /* background-size: cover; */
            width: 75px;
            height: 75px;
            border-radius: 50%;
            background: #fff;
            background-color: #fff;
            opacity: 0.5;
            cursor: pointer;
        }

        .mapboxgl-popup1 {
            min-width: 250px;
            background-color: #666;
            background: black;
            border-radius: 50%;
        }

        .mapboxgl-popup-content {
            text-align: center;
            font-size: 14px;
            background: #666;
            background-color: #666;
            font-family: 'Open Sans', sans-serif;
            color: #040404;
            background-color: #f1f0f0;
            border-color: #666;
            max-width: 40%;
            width: 40%;
            min-width: 150px;
            min-height: 100px;
            box-shadow: 5px 5px 4px #8B5D33;
            font-family: 'Oswald';
            left: -47%;
            bottom: 40%;
            /* top: auto;
            right: auto; */
            opacity: 0.85;
            position: fixed;
            transform: none !important;
        }

        #menu {
            background: #fff;
            opacity: 0.8;
            position: absolute;
            z-index: 1;
            top: 10px;
            left: 2%;
            border-radius: 3px;
            width: 125px;
            border: 1px solid rgba(0, 0, 0, 0.4);
            font-family: 'Open Sans', sans-serif;
        }

        #menu a {
            font-size: 13px;
            color: #404040;
            display: block;
            margin: 0;
            padding: 0;
            padding: 5px;
            text-decoration: none;
            border-bottom: 1px solid rgba(0, 0, 0, 0.25);
            text-align: center;
        }

        #menu a:last-child {
            border: none;
        }

        #menu a:hover {
            background-color: #f8f8f8;
            color: #141414;
        }

        #menu a.active {
            background-color: #3887be;
            opacity: 0.7;
            color: #ffffff;
        }

        #menu a.active:hover {
            background: #3074a4;
        }

        .mapboxgl-map .mapboxgl-popup {
            min-width: 35%;
            max-width: 150px;
            min-height: 17%;
            left: 20%;
            bottom: 60px;
            top: auto;
            right: auto;
            opacity: 0.95;
            border-radius: 50%;
            position: fixed;
            transform: none !important;
        }

        .mapboxgl-map .mapboxgl-popup-tip {
            display: none
        }

        #fit {
            display: block;
            position: relative;
            margin: 0px auto;
            width: 10%;
            height: 40px;
            padding: 10px;
            border: none;
            border-radius: 3px;
            font-size: 12px;
            text-align: center;
            color: #fff;
            background: #c9800c;
            opacity: 0.75;
            /* background-image: url("https://poker31.org/panel/assets/images/logo.png") opacity: 0.7; */
            position: absolute;
            z-index: 1;
            top: 155px;
            left: 2%;
            border-radius: 3px;
            width: 125px;
            border: 1px solid rgba(0, 0, 0, 0.25);
            font-family: 'Open Sans', sans-serif;
        }

        #accesmenu {
            display: block;
            position: absolute;
            margin: 0px auto;
            width: 10%;
            height: 40px;
            padding: 10px;
            border: none;
            border-radius: 3px;
            font-size: 12px;
            text-align: center;
            opacity: 0.66;
            color: #fff;
            background: #3887be;
            /* background-image: url("https://poker31.org/panel/assets/images/logo.png") opacity: 0.7; */
            position: absolute;
            z-index: 1;
            top: 155px;
            right: 20px;
            border-radius: 3px;
            width: 125px;
            border: 1px solid rgba(0, 0, 0, 0.25);
            font-family: 'Open Sans', sans-serif;
        }

        #dashb {
            position: absolute;
            z-index: 1;
            top: 10px;
            right: 15px;
            /* right : -250px; */
            display: block;
            /* position: relative; */
            margin: 0px auto;
            width: 12%;
            height: 130px;
            padding: 5px;
            opacity: 0.75;
            background-image: url('/panel/images/menucarre.png');
            background-size: cover;
            width: 135px;
            height: 120px;
            font-size: 2rem;
        }

        .compact-popup .mapboxgl-popup-content {
            padding: 10px;
            width: auto;
            min-width: 200px;
            max-width: 300px;
            min-height: auto;
            font-size: 12px;
            opacity: 0.9;
        }

        .compact-popup table {
            width: 100%;
            border-collapse: collapse;
        }

        .compact-popup th,
        .compact-popup td {
            padding: 4px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
    </style>
</head>

<body>
    <!--    <div class="flex font-sans">   <div class="h-screen w-full" id="map"></div> </div>  -->
    <nav id="menu"></nav>
    <div id="map"></div>
    <button id="fit">Recentrer Carte</button>
    <button id="accesmenu">Menu Général</button>
    <button id="dashb"></button>
    <script src="https://api.tiles.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.js"></script>
    <!--    <link href="https://api.tiles.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.css" rel="stylesheet"/> -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
        integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script
        src="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js"></script>
    <script>
        mapboxgl.accessToken = 'pk.eyJ1IjoiZnJhbmNrdzMxIiwiYSI6ImNsbmJqemU5cjA0MDYya3RkczNrMHdqb2wifQ.6NLEMz-lShL80j9QuGW9cA';
        
        const symbo = `url(https://placekitten.com/g/100/100/)`;

            var map = new mapboxgl.Map({
            container: 'map',
            // style: 'mapbox://styles/mapbox/streets-v12',
            style: 'mapbox://styles/franckw31/clnd1m23b03o501qu3x5ab4xk',
            center: [1.43, 43.65],
            zoom: 9.6,
            attributionControl: false
        });
        // create the popup			
        // create DOM element for the marker

            map.on('load', function () {
            //place object we will add our events to
            map.addSource('Activités', {
                'type': 'geojson',
                'data': {
                    'type': 'FeatureCollection',
                    'features': []
                }
            });
            map.addSource('hommes', {
                'type': 'geojson',
                'data': {
                    'type': 'FeatureCollection',
                    'features_m': []
                }
            });
            map.addSource('femmes', {
                'type': 'geojson',
                'data': {
                    'type': 'FeatureCollection',
                    'features_m': []
                }
            });
            map.addSource('services', {
                'type': 'geojson',
                'data': {
                    'type': 'FeatureCollection',
                    'features_m': []
                }
            });
            //add place object to map
            map.addLayer({
                'id': 'Activités',
                'type': 'symbol',
                'source': 'Activités',
                'layout': {
                    'icon-image': '{icon}',
                    'icon-size': 0.1,
                    'icon-allow-overlap': true,
                    'visibility': 'visible'
                },
                'paint': {
                    'icon-opacity': 0.9
                }
            });
            map.addLayer({
                'id': 'hommes',
                'type': 'symbol',
                'source': 'hommes',
                'layout': {
                    'icon-image': '{icon}',
                    'icon-size': 0.4,
                    'icon-allow-overlap': true,
                    'visibility': 'visible'
                },
                'paint': {
                    'icon-opacity': 0.9
                }
            });
            map.addLayer({
                'id': 'femmes',
                'type': 'symbol',
                'source': 'femmes',
                'layout': {
                    'icon-image': '{icon}',
                    'icon-size': 0.4,
                    'icon-allow-overlap': true,
                    'visibility': 'visible'
                },
                'paint': {
                    'icon-opacity': 0.9
                }
            });
            map.addLayer({
                'id': 'services',
                'type': 'symbol',
                'source': 'services',
                'layout': {
                    'icon-image': '{icon}',
                    'icon-size': 0.10,
                    'icon-allow-overlap': true,
                    'visibility': 'visible'
                },
                'paint': {
                    'icon-opacity': 0.9
                }
            });
            document.getElementById('fit').addEventListener('click', () => {
                map.flyTo({ center: [1.43, 43.65],zoom: 9.6 });
                const popup = document.getElementsByClassName('mapboxgl-popup');
                if (popup.length) {
                    popup[0].remove();
                }
                // map.fitBounds([
                //     [32.958984, -5.353521], // southwestern corner of the bounds
                //     [43.50585, 5.615985] // northeastern corner of the bounds
                // ]);
            });
            document.getElementById('dashb').addEventListener('click', () => {
                document.location.href = "/panel/dashboard.php";
            });
            document.getElementById('accesmenu').addEventListener('click', () => {
                document.location.href = "/panel/dashboard.php";
            });
            //Handle popups		
            map.on('click', 'Activités', (e) => {
                map.flyTo({ center: e.features[0].geometry.coordinates, zoom: 13 });
                const symbo = e.features[0].properties.t2;
                const coordinates = e.features[0].geometry.coordinates.slice();
                const description = e.features[0].properties.description;
                while (Math.abs(e.lngLat.lng - coordinates[0]) > 180) {
                    coordinates[0] += e.lngLat.lng > coordinates[0] ? 360 : -360;
                }
                const tml = '';
                const titi = '<table style border:"1"><tr><th>Date</th><th>Buyin</th><th>Max</th></tr><tr><th>'+e.features[0].properties.date_depart+'</th><th>'+e.features[0].properties.buyin+'</th><th>'+e.features[0].properties.places+'</th></tr></table>';
                const el = document.createElement('div');
                el.id = 'marker';
                el.style.backgroundImage = `${symbo}`;
                el.style.width = `125px`;
                el.style.height = `125px`;
                el.style.opacity = `0.8`;
                el.style.backgroundSize = `100%`;
                new mapboxgl.Popup({ closeOnClick: true, closeButton: false })
                    .setLngLat(coordinates)
                    .setHTML(description + titi)
                    .addTo(map);
                new mapboxgl.Marker(el)
                    .setLngLat(coordinates)
                // .setPopup(new mapboxgl.Popup({ offset: 25, closeOnClick: true }).setLngLat(coordinates).setHTML(description)) 
                <!--        .setPopup(new mapboxgl.Popup({ offset: 25, closeOnClick: true }).setLngLat(coordinates)) -->
                // .addTo(map);
            });
            map.on('click', 'hommes', (e) => {
                const coordinates = e.features[0].geometry.coordinates.slice();
                const memberId = e.features[0].properties.membreid;
                const memberName = e.features[0].properties.description;
                
                // Load only the filtered content
                fetch(`/panel/prochaines-activites-membre.php?uid=${memberId}&compact=true`)
                    .then(response => response.text())
                    .then(content => {
                        // Create and show the popup with reduced size
                        new mapboxgl.Popup({ 
                            closeOnClick: true, 
                            closeButton: false,
                            maxWidth: '300px',
                            className: 'compact-popup'
                        })
                        .setLngLat(coordinates)
                        .setHTML(`<div style="font-weight:bold; margin-bottom:5px;">${memberName}</div>${content}`)
                        .addTo(map);
                    });
            
                map.flyTo({ 
                    center: coordinates, 
                    zoom: 12 
                });
            });
            map.on('click', 'femmes', (e) => {
                map.flyTo({ center: e.features[0].geometry.coordinates, zoom: 14 });
                const symbo = e.features[0].properties.t2;
                const coordinates = e.features[0].geometry.coordinates.slice();
                const description = e.features[0].properties.description;
                while (Math.abs(e.lngLat.lng - coordinates[0]) > 180) {
                    coordinates[0] += e.lngLat.lng > coordinates[0] ? 360 : -360;
                }
                const el = document.createElement('div');
                el.id = 'marker';
                el.style.backgroundImage = `${symbo}`;
                el.style.width = `125px`;
                el.style.height = `125px`;
                el.style.opacity = `0.5`;
                el.style.backgroundSize = `100%`;
                new mapboxgl.Popup({ closeOnClick: true, closeButton: false })
                    .setLngLat(coordinates)
                    .setHTML(description)
                    .addTo(map);
                new mapboxgl.Marker(el)
                    .setLngLat(coordinates)
                // .setPopup(new mapboxgl.Popup({ offset: 25,closeOnClick: true }).setLngLat(coordinates).setHTML(description)) 
                // .setPopup(new mapboxgl.Popup({ offset: 25, closeOnClick: true }).setLngLat(coordinates))
                // .addTo(map);
            });
            map.on('click', 'services', (e) => {
                map.flyTo({ center: e.features[0].geometry.coordinates, zoom: 14 });
                const symbo = e.features[0].properties.t2;
                const coordinates = e.features[0].geometry.coordinates.slice();
                const description = e.features[0].properties.description;
                while (Math.abs(e.lngLat.lng - coordinates[0]) > 180) {
                    coordinates[0] += e.lngLat.lng > coordinates[0] ? 360 : -360;
                }
                const el = document.createElement('div');
                el.id = 'marker';
                el.style.backgroundImage = `${symbo}`;
                el.style.width = `125px`;
                el.style.height = `125px`;
                el.style.opacity = `0.5`;
                el.style.backgroundSize = `100%`;
                new mapboxgl.Popup({ closeOnClick: true, closeButton: false })
                    .setLngLat(coordinates)
                    .setHTML(description)
                    .addTo(map);
                new mapboxgl.Marker(el)
                    .setLngLat(coordinates)
                // .setPopup(new mapboxgl.Popup({ offset: 25,closeOnClick: true }).setLngLat(coordinates).setHTML(description)) 
                // .setPopup(new mapboxgl.Popup({ offset: 25, closeOnClick: true }).setLngLat(coordinates))
                // .addTo(map);
            });
            //Handle pointer styles
            map.on('mouseenter', 'Activités', () => {
                map.getCanvas().style.cursor = 'pointer';
            });
            map.on('mouseleave', 'Activités', () => {
                map.getCanvas().style.cursor = '';
            });
            map.on('mouseenter', 'hommes', () => {
                map.getCanvas().style.cursor = 'pointer';
            });
            map.on('mouseleave', 'hommes', () => {
                map.getCanvas().style.cursor = '';
            });
            map.on('mouseenter', 'femmes', () => {
                map.getCanvas().style.cursor = 'pointer';
            });
            map.on('mouseleave', 'femmes', () => {
                map.getCanvas().style.cursor = '';
            });
            map.on('mouseenter', 'services', () => {
                map.getCanvas().style.cursor = 'pointer';
            });
            map.on('mouseleave', 'services', () => {
                map.getCanvas().style.cursor = '';
            });
            //get our data from php function
            //getAllEvents();
            getAllEvents_m();
            getAllEvents_f();
            getAllEvents_s();
        });
        function getAllEvents() {
            $.ajax({
                url: "sql2map.php",
                contentType: "application/json",
                dataType: "json",
                success: function (eventData) {
                    console.log(eventData)
                    map.getSource('Activités').setData({
                        'type': 'FeatureCollection',
                        'features': eventData
                    });
                },
                error: function (e) {
                    console.log(e);
                }
            });
        }
        function getAllEvents_m() {
            $.ajax({
                url: "sql2map-membres-m.php",
                contentType: "application/json",
                dataType: "json",
                success: function (eventData) {
                    console.log(eventData)
                    map.getSource('hommes').setData({
                        'type': 'FeatureCollection',
                        'features': eventData
                    });
                },
                error: function (e) {
                    console.log(e);
                }
            });
        }
        function getAllEvents_f() {
            $.ajax({
                url: "sql2map-membres-f.php",
                contentType: "application/json",
                dataType: "json",
                success: function (eventData) {
                    console.log(eventData)
                    map.getSource('femmes').setData({
                        'type': 'FeatureCollection',
                        'features': eventData
                    });
                },
                error: function (e) {
                    console.log(e);
                }
            });
        }
        function getAllEvents_s() {
            $.ajax({
                url: "sql2map-membres-s.php",
                contentType: "application/json",
                dataType: "json",
                success: function (eventData) {
                    console.log(eventData)
                    map.getSource('services').setData({
                        'type': 'FeatureCollection',
                        'features': eventData
                    });
                },
                error: function (e) {
                    console.log(e);
                }
            });
        }
        // After the last frame rendered before the map enters an "idle" state.
        // const popup = new mapboxgl.Popup({ offset: 37, anchor: 'bottom' }).setDOMContent('<h5>Hello</h5>').setLngLat('[1.42056, 43.60842]').addTo(map);
        map.on('idle', () => {
            // If these two layers were not added to the map, abort
            if (!map.getLayer('hommes') || !map.getLayer('Activités') || !map.getLayer('femmes')|| !map.getLayer('services')) {
                return;
            }
            // Enumerate ids of the layers.
            const toggleableLayerIds = ['Activités', 'hommes', 'femmes', 'services'];
            // const toggleableLayerIds = ['hommes','test','Activités'];
            // Set up the corresponding toggle button for each layer.
            for (const id of toggleableLayerIds) {
                // Skip layers that already have a button set up.
                if (document.getElementById(id)) {
                    continue;
                }
                // Create a link.
                const link = document.createElement('a');
                link.id = id;
                link.href = '#';
                link.textContent = id;
                link.className = 'active';
                // Show or hide layer when the toggle is clicked.
                link.onclick = function (e) {
                    const clickedLayer = this.textContent;
                    e.preventDefault();
                    e.stopPropagation();
                    const visibility = map.getLayoutProperty(
                        clickedLayer,
                        'visibility'
                    );
                    // Toggle layer visibility by changing the layout object's visibility property.
                    if (visibility === 'visible') {
                        map.setLayoutProperty(clickedLayer, 'visibility', 'none');
                        this.className = '';
                    } else {
                        this.className = 'active';
                        map.setLayoutProperty(
                            clickedLayer,
                            'visibility',
                            'visible'
                        );
                    }
                };
                const layers = document.getElementById('menu');
                layers.appendChild(link);
            }
        });
        map.addControl(new mapboxgl.ScaleControl());
        map.addControl(new mapboxgl.GeolocateControl({
            fitBoundsOptions: {maxZoom: 11.5},
            positionOptions: {
                enableHighAccuracy: true
            },
            // When active the map will receive updates to the device's location as it changes.
            trackUserLocation: true,
            // Draw an arrow next to the location dot to indicate which direction the device is heading.
            showUserHeading: true
        }), 'bottom-right'
        );
        const nav = new mapboxgl.NavigationControl();
        map.addControl(nav, 'bottom-right');
        // const popup = new mapboxgl.Popup({ closeOnClick: true })
        // .setLngLat([0, 0])
        // .setHTML('<h1>Cliquer sur les AS !</h1>')
        // .addTo(map); 
    </script>
</body>

</html>
