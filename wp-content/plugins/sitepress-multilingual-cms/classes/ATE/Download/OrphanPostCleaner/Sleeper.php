<?php

namespace WPML\TM\ATE\Download\OrphanPostCleaner;

class Sleeper {

	/**
	 * @param int $seconds
	 */
	public function sleep( $seconds ) {
		sleep( $seconds );
	}
}
