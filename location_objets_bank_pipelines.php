<?php
/**
 * Utilisations de pipelines par Location d’objets - paiements
 *
 * @plugin     Location d’objets - paiements
 * @copyright  2018
 * @author     Rainer Müller
 * @licence    GNU/GPL v3
 * @package    SPIP\Location_objets_bank\Pipelines
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Enregistrer le bon reglement d'une commande liee a une transaction du plugin bank
 *
 * @pipeline bank_traiter_reglement
 *
 * @param array $flux
 * @return array mixed
 */
function location_objets_bank_bank_traiter_reglement($flux) {
	// Si on est dans le bon cas d'un paiement de reservation et qu'il y a un id_reservation et que la reservation existe toujours

	if ($id_transaction = $flux['args']['id_transaction'] and
		$transaction = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction)) and
		$id_reservation = $transaction['id_reservation'] and
		$reservation = sql_fetsel('statut, reference', 'spip_reservations', 'id_reservation=' . intval($id_reservation))) {

			$paiement_detail = array();
			if (!_request('gratuit')) {
				if (!$montant_reservations_detail_total = _request('montant_reservations_detail_total')) {
					include_spip('inc/reservation_bank');
					$montant_reservations_detail_total = montant_reservations_detail_total($id_reservation);
				}

				foreach (array_keys($montant_reservations_detail_total) as $id_reservation_detail) {
					$paiement_detail[$id_reservation_detail] = _request('montant_reservations_detail_' . $id_reservation_detail);
				}

				if (!$montant_regle = array_sum($paiement_detail)) {
					$montant_regle = $transaction['montant_regle'];
				}
				elseif (is_array($montant_regle)) {
					$montant_regle = array_sum($montant_regle);
				}



				set_request('montant_regle', $montant_regle);

				$set = array(
					'montant_regle' => $montant_regle,
					'paiement_detail' => serialize($paiement_detail)
				);

				sql_updateq('spip_transactions', $set, 'id_transaction=' . $id_transaction);
			}

			include_spip('action/editer_objet');
			objet_instituer('reservation', $id_reservation, array(
				'statut' => 'accepte',
				'date_paiement' => $transaction['date_transaction']
			));

			// un message gentil pour l'utilisateur qui vient de payer, on lui rappelle son numero de commande
			$flux['data'] .= "<br />" . _T('reservation_bank:merci_de_votre_reservation_paiement', array(
				'reference' => $reservation['reference']
			));

		}

		return $flux;
}

/**
 * Changer de statut si transaction en attente
 *
 * @pipeline trig_bank_reglement_en_attente
 *
 * @param array $flux
 * @return array
 */
function location_objets_bank_trig_bank_reglement_en_attente($flux) {
	if ($id_reservation = sql_getfetsel('id_reservation', 'spip_transactions', 'id_transaction=' . $flux['args']['id_transaction'])) {
		include_spip('action/editer_objet');
		objet_instituer('reservation', $id_reservation, array(
			'statut' => 'attente_paiement'
		));
	}

	return $flux;
}

