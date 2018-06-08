<?php
/**
 * Fonctions utiles au plugin Location d’objets
 *
 * @plugin     Location d’objets - paiements
 * @copyright  2018
 * @author     Rainer Müller
 * @licence    GNU/GPL v3
 * @package    SPIP\Reservation_bank\Fonctions
 */
if (!defined('_ECRIRE_INC_VERSION'))
	return;

/**
 * Crée une transaction
 *
 * @param integer $id_objets_location
 *        	identifian de la location
 * @return $id_transaction Id de la transaction crée
 */
	function lob_inserer_transaction($id_objets_location) {

	// Voir si on peut récupérer une transaction, sino on crée une.
	if (!$id_transaction = sql_getfetsel(
		'id_transaction', 'spip_transactions', 'id_objets_location=' . $id_objets_location . ' AND statut LIKE ("commande")')) {
		$inserer_transaction = charger_fonction("inserer_transaction", "bank");
		$donnees = unserialize(recuperer_fond('inclure/paiement_location', array(
			'id_objets_location' => $id_objets_location,
			'tableau' => TRUE
		)));
		$id_transaction = $inserer_transaction($donnees['montant'], $donnees['options']);
	}

	return $id_transaction;
}


