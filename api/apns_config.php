<?php
// ============================================================
//  Configuration APNs — Push Notifications Apple
//  Générez votre clé sur :
//  https://developer.apple.com/account/resources/authkeys/list
// ============================================================

// Votre Team ID (Apple Developer > Membership)
define('APNS_TEAM_ID', 'XXXXXXXXXX');

// Key ID de votre clé APNs (Certificates, Identifiers & Profiles > Keys)
define('APNS_KEY_ID', 'XXXXXXXXXX');

// Bundle ID de votre application iOS (ex: com.viendez.pokertimer)
define('APNS_BUNDLE_ID', 'com.example.cardevent');

// Chemin absolu vers le fichier .p8 téléchargé depuis Apple Developer
define('APNS_KEY_PATH', __DIR__ . '/AuthKey_XXXXXXXXXX.p8');

// false = sandbox (Simulator / Debug), true = production (TestFlight / App Store)
define('APNS_PRODUCTION', true);
