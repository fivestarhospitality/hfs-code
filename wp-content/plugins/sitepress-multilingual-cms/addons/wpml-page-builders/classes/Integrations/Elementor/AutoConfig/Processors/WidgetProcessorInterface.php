<?php

namespace WPML\PB\Elementor\AutoConfig\Processors;

use Elementor\Widget_Base;

interface WidgetProcessorInterface {

	/**
	 * @param Widget_Base $widget
	 *
	 * @return bool
	 */
	public function canProcess( $widget );

	/**
	 * @param Widget_Base $widget
	 *
	 * @return array
	 */
	public function process( $widget );
}
