<?php

define("PHPTO_TAILLE_MAX", 5*1024*1024); 

$TYPE_PHOTO = ["image/jpeg" => 'jpg', "image/png" => 'png'];

function uplode($fichier){

    global $TYPE_PHOTO,$erreurphoto;
    $erreurphoto='';

    if (!isset($fichier) || $fichier['error'] != 0) {
        $erreurphoto= "Erreur lors de l'upload";
        return false;
    }

    if ($fichier['size'] > PHPTO_TAILLE_MAX) {
        $erreurphoto= "Fichier trop volumineux";
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mine = finfo_file($finfo, $fichier['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mine, $TYPE_PHOTO)) {
        $erreurphoto= "Type de fichier non autorisé";
        return false;
    }

    $extension = $TYPE_PHOTO[$mine];
    $nomfichier = uniqid(random_bytes(12)) . '.' . $extension;
    $dosier = __DIR__ . '/../uploads/';

    if(!is_dir($dosier)){
        mkdir($dosier,0755,true);
    }

    if(!move_uploaded_file($fichier['tmp_name'],$dosier .$nomfichier)){
        $erreurphoto= "Erreur lors de l'upload";
        return false;
    }
    return 'uploads/' . $nomfichier;


}

?>
