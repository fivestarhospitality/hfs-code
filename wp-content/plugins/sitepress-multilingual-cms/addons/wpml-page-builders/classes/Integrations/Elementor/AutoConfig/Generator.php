<?php

namespace WPML\PB\Elementor\AutoConfig;

use Elementor\Widget_Base;
use WPML\PB\Elementor\AutoConfig\Processors\WidgetProcessorInterface;

class Generator {

	/**
	 * @var WidgetProcessorInterface[]
	 */
	private $processors;

	/**
	 * @param WidgetProcessorInterface[] $processors
	 */
	public function __construct( array $processors ) {
		$this->processors = $processors;
	}

	/**
	 * @param array $existingWidgets
	 * @param array $widgetInstances
	 *
	 * @return array
	 */
	public function generate( array $existingWidgets, array $widgetInstances ) {
		$config = [];

		foreach ( $widgetInstances as $widgetType => $widgetInstance ) {
			if ( array_key_exists( $widgetType, $existingWidgets ) ) {
				continue;
			}

			$widgetConfig = $this->generateWidgetConfig( $widgetInstance );

			if ( ! empty( $widgetConfig ) ) {
				$widgetConfig['conditions'] = [ 'widgetType' => $widgetType ];
				$config[ $widgetType ]      = $widgetConfig;
			}
		}

		return $config;
	}

	/**
	 * @param Widget_Base $widgetInstance
	 *
	 * @return array
	 */
	private function generateWidgetConfig( $widgetInstance ) {
		foreach ( $this->processors as $processor ) {
			if ( $processor->canProcess( $widgetInstance ) ) {
				return $processor->process( $widgetInstance );
			}
		}

		return [];
	}
}
