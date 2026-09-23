<?php
/**
 * Vorlage. Kopieren, Passwort eintragen, als mail-config.php speichern und
 * per FTP nach /123vorteilswelt.at/ laden. mail-config.php gehört NICHT ins Repo.
 */
return [
    'host'     => 'w0214e71.kasserver.com',   // ggf. an den KAS-Server anpassen
    'port'     => 465,
    'secure'   => 'ssl',
    'username' => 'website@123vorteilswelt.at',
    'password' => 'HIER_DAS_PASSWORT_EINTRAGEN',
    'from'     => 'website@123vorteilswelt.at',
    'fromName' => '123Vorteilswelt Website',
    'empfaenger' => [
        'anfrage'   => 'anfrage@123vorteilswelt.at',
        'franchise' => 'franchise@123vorteilswelt.at',
    ],
    'antwortAn' => 'office@123vorteilswelt.at',
];
