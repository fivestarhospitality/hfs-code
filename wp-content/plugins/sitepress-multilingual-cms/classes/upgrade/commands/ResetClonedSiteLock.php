<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\TM\ATE\ClonedSites\Lock;

/**
 * Removes a stale cloned-site lock left over from the pre-4.9.4 migration wizard
 * so the new auto-migration flow can re-evaluate the site on the next ATE call.
 *
 * Without this reset, ApiCommunication short-circuits outgoing AMS calls while
 * the lock is held, so no fresh 426 ever arrives and the auto-migration handler
 * never runs — leaving the user with no recovery UI.
 */
class ResetClonedSiteLock implements \IWPML_Upgrade_Command {

	/** @var bool $result */
	private $result = false;

	public function run_admin() {
		delete_option( Lock::CLONED_SITE_OPTION );
		$this->result = true;

		return $this->result;
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->result;
	}
}
