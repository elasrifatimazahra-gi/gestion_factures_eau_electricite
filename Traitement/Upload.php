<?php

define("PHOTO_TAILLE_MAX", 5 * 1024 * 1024); 

$TYPE_PHOTO = ["image/jpeg" => 'jpg', "image/png" => 'png'];

$erreurUpload = '';

function uploadPhoto($fichier)
{
    global $TYPE_PHOTO, $erreurUpload;
    $erreurUpload = '';

    if (!isset($fichier) || !is_array($fichier) || $fichier['error'] !== UPLOAD_ERR_OK) {
        $erreurUpload = "Erreur lors du téléversement du fichier.";
        return false;
    }

    if ($fichier['size'] > PHOTO_TAILLE_MAX) {
        $erreurUpload = "Le fichier est trop volumineux (5 Mo maximum).";
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $fichier['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mime, $TYPE_PHOTO)) {
        $erreurUpload = "Type de fichier non autorisé. Seuls JPG et PNG sont acceptés.";
        return false;
    }

    $extension  = $TYPE_PHOTO[$mime];
    $nomFichier = bin2hex(random_bytes(12)) . '_' . time() . '.' . $extension;
    $dossier    = __DIR__ . '/../uploads/';

    if (!is_dir($dossier)) {
        mkdir($dossier, 0755, true);
    }

    if (!move_uploaded_file($fichier['tmp_name'], $dossier . $nomFichier)) {
        $erreurUpload = "Impossible de déplacer le fichier vers le dossier uploads/.";
        return false;
    }

    return 'uploads/' . $nomFichier;
}
