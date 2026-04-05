<?php
/**
 * Configuration SMTP Réelle
 */
return [
    'host' => 'smtp.free.fr',         // Serveur SMTP (ex: smtp.gmail.com, smtp.free.fr)
    'auth' => true,
    'username' => 'contact.poker31@free.fr',
    'password' => 'Kookies7*fb',      // Utilisez un mot de passe d'application si possible
    'secure' => 'ssl',                // 'ssl' ou 'tls'
    'port' => 465,                    // 465 pour SSL, 587 pour TLS
    'from_email' => 'contact.poker31@free.fr',
    'from_name' => 'Poker31 Admin',
];
