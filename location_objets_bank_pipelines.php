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
 * permet de modifier le tableau de valeurs envoyé par la fonction charger d’un formulaire CVT
 *
 * @pipeline formulaire_charger
 *
 * @param array $flux
 *        	Données du pipeline
 * @return array Données du pipeline
 */
function location_objets_bank_formulaire_charger($flux) {
	$form = $flux['args']['form'];
	if ($form == 'editer_objets_location') {
		if ($flux['data']['checkout'] = _request('checkout')) {
			$flux['data']['editable'] = FALSE;
			$flux['data']['message_ok'] .= recuperer_fond('inclure/paiement_location', array(
				'id_objets_location' => session_get('id_objets_location'),
			));
		}
	}

	return $flux;
}

/**
 * Intervient au traitement d'un formulaire CVT
 *
 * @pipeline formulaire_traiter
 *
 * @param array $flux
 *        	Données du pipeline
 * @return array Données du pipeline
 */
function location_objets_bank_formulaire_traiter($flux) {
	$form = $flux['args']['form'];

	// Affiche le formulaire de paiment au retour du formulaire réservation
	if ($form == 'editer_objets_location') {
		$id_objets_location = $flux['data']['id_objets_location'];
		lob_inserer_transaction($id_objets_location);
		if (
			!_request('epsace_prive') and
			!_request('gratuit')) {

			$message_ok = preg_replace('/<p[^>]*>.*?<\/p>/i', '', $flux['data']['message_ok']);
			$flux['data']['message_ok'] = '<div class="intro">' . recuperer_fond(
				'inclure/paiement_location',
				array(
					'id_objets_location' => $id_objets_location
				)
			) . '</div>' . $message_ok;

		}
	}

	return $flux;
}

/**
 * Permet de compléter ou modifier le résultat de la compilation d’un squelette donné.
 *
 * @pipeline recuperer_fond
 *
 * @param array $flux
 *        	Données du pipeline
 * @return array Données du pipeline
 */
function location_objets_bank_recuperer_fond($flux) {
	$fond = $flux['args']['fond'];
	$contexte = $flux['data']['contexte'];

	// Ajoute le message de paiement à la notification de réservation.
	if ($fond == 'inclure/location' and
		$id_objets_location = $flux['data']['contexte']['id_objets_location'] and
		$statut = sql_getfetsel('statut', 'spip_objets_locations', 'id_objets_location=' . $id_objets_location) and
		(in_array($statut, array('attente', 'paye')))) {
			$qui = $flux['data']['contexte']['qui'];
			$transaction = sql_fetsel(
				'mode, id_transaction, transaction_hash, message, tracking_id',
				'spip_transactions',
				'id_objets_location=' . $id_objets_location,
				'',
				'date_transaction DESC');
			$mode = $transaction['mode'];
			$id_transaction = $transaction['id_transaction'];
			if ($qui == 'client') {
				if ($statut == 'attente') {
					$pattern = array(
						'|<p class="titre h4">|',
						'|</p>|'
					);
					$replace = array(
						'<h3>',
						'</h3>'
					);
					$texte = preg_replace(
							$pattern,
							$replace,
							bank_afficher_attente_reglement($mode, $id_transaction, $transaction['transaction_hash'], '')
						);
				}
				else {
					$texte = '<p>' . $transaction['message'] . '</p>';
				}
			}
			elseif ($qui == 'vendeur') {
				$url = generer_url_ecrire('transaction', 'id_transaction=' . $id_transaction);
				$texte = '<h2>' . _T('location_objets_bank:titre_paiement_vendeur') . '</h2>';
				$texte .= '<p>' . _T('location_objets_bank:message_paiement_vendeur', array(
					'mode' => $mode,
					'url' => $url
				)) . '</p>';
			}

			$flux['data']['texte'] .= $texte;
		}

		// Ajouter le message pour la référence su paiement par virement.
		if ($fond == 'presta/virement/payer/attente' and
			$tracking_id = sql_getfetsel(
				'tracking_id',
				'spip_transactions',
				'id_transaction=' . $contexte['id_transaction']) and
			$id_objets_location = sql_getfetsel('id_objets_location', 'spip_objets_locations', 'reference LIKE ' . sql_quote($tracking_id))) {

			$texte = '<strong>' . _T('location_objets_bank:location_paiement_reference', array(
				'reference' => $tracking_id
			)) . '</strong>';
			$flux['data']['texte'] = str_replace('</div>', $texte . '</div>', $flux['data']['texte']);
		}

	return $flux;
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
	// Si on est dans le bon cas d'un paiement de location et qu'il y a un id_objets_location et que la location existe toujours

	if ($id_transaction = $flux['args']['id_transaction'] and
		$transaction = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction)) and
		$id_objets_location = $transaction['id_objets_location'] and
		$location = sql_fetsel('statut, reference', 'spip_objets_locations', 'id_objets_location=' . intval($id_objets_location))) {

		include_spip('action/editer_objet');
		objet_instituer('objets_location', $id_objets_location, array(
			'statut' => 'paye',
			'date_paiement' => $transaction['date_transaction']
		));

		// un message gentil pour l'utilisateur qui vient de payer, on lui rappelle son numero de commande
		$flux['data'] .= "<br />" . _T('location_objets_bank:merci_de_votre_location_paiement', array(
			'reference' => $location['reference']
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
	if ($id_objets_location = sql_getfetsel(
			'id_objets_location',
			'spip_transactions',
			'id_transaction=' . $flux['args']['id_transaction'])) {
		include_spip('action/editer_objet');

		objet_instituer('objets_location', $id_objets_location, array(
			'statut' => 'attente'
		));
	}

	return $flux;
}

