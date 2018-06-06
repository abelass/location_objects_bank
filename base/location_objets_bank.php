<?php
/**
 * Déclarations relatives à la base de données
 *
 * @plugin     Réservations Bank
 * @copyright  2015
 * @author     Rainer
 * @licence    GNU/GPL
 * @package    SPIP\Reservations_credits\Pipelines
 */

if (!defined('_ECRIRE_INC_VERSION'))
	return;

/**
 * Déclaration des objets éditoriaux
 *
 * @pipeline declarer_tables_objets_sql
 * @param array $tables
 *     Description des tables
 * @return array
 *     Description complétée des tables
 */

	function location_objets_bank_declarer_tables_objets_sql($tables) {
	//Ajouter un champ id_reservation et paiement_detail à la table transaction
	$tables['spip_transactions']['field']['id_objets_location'] = "bigint(21) NOT NULL DEFAULT 0";

	//Ajouter un champ montant_paye à la table spip_reservations_details
	$tables['spip_objets_locations']['field']['montant_paye'] = "decimal(15,2) NOT NULL DEFAULT '0.00'";
	$tables['spip_objets_locations']['champs_editables'][] = "montant_paye";

	return $tables;
}

