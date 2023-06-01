<?php

header('Content-type: application/json');

// Inclusion de la classe GenericDao
$php_root = dirname(__FILE__);
include($php_root . '/generic_dao.php');

// Récupération d'un formulaire JSON
$json = file_get_contents('php://input');
$data = json_decode($json);

// Mise à jour des données en base
try {
	$noteManager = new GenericDao('Note');
	$list = $noteManager->getList("");
	echo json_encode($list);
} catch (GenericDaoError $error) {
	echo $error->getJson();
	http_response_code(500);
}

?>