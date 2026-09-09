<?php

namespace WPML\PB\Elementor\AutoConfig\Processors;

use Elementor\Widget_Base;

class AtomicWidgetProcessor implements WidgetProcessorInterface {

	const EDITOR_TYPE_MAP = [
		'text'     => 'LINE',
		'textarea' => 'AREA',
		'link'     => 'LINK',
	];

	/**
	 * @param Widget_Base $widget
	 *
	 * @return bool
	 */
	public function canProcess( $widget ) {
		return method_exists( $widget, 'get_atomic_controls' );
	}

	/**
	 * @param object $widget
	 *
	 * @return array
	 */
	public function process( $widget ) {
		if ( ! method_exists( $widget, 'get_title' ) || ! method_exists( $widget, 'get_atomic_controls' ) ) {
			return [];
		}

		$title    = $widget->get_title();
		$controls = $widget->get_atomic_controls();
		$config   = [];

		foreach ( $controls as $control ) {
			$config = $this->processControl( $config, $control, $title );
		}

		return $config;
	}

	/**
	 * @param array  $config
	 * @param object $section
	 * @param string $title
	 *
	 * @return array
	 */
	private function processControl( $config, $section, $title ) {
		$items = $section->get_items();

		foreach ( $items as $control ) {
			$type = $control->get_type();
			$name = $control->get_bind();

			if ( ! $this->isRegistrable( $name, $type ) ) {
				continue;
			}

			$editor = $this->getEditorType( $type );

			if ( ! $editor ) {
				continue;
			}

			$fieldPath = 'link' === $type ? $name . '>value>destination>value' : $name . '>value';

			$field = [
				'field'       => $fieldPath,
				'type'        => $title . ': ' . $name,
				'editor_type' => $editor,
			];

			if ( ! isset( $config['fields'] ) ) {
				$config['fields'] = [];
			}

			$config['fields'][] = $field;
		}

		return $config;
	}

	/**
	 * @param string $name
	 * @param string $type
	 *
	 * @return bool
	 */
	private function isRegistrable( $name, $type ) {
		if ( $this->isPrivate( $name ) ) {
			return false;
		}

		if ( ControlNameFilter::shouldExclude( $name ) ) {
			return false;
		}

		return $this->isTranslatable( $type );
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	private function isPrivate( $name ) {
		return 0 === strpos( $name, '_' );
	}

	/**
	 * @param string $type
	 *
	 * @return bool
	 */
	private function isTranslatable( $type ) {
		return null !== $this->getEditorType( $type );
	}

	/**
	 * @param string $type
	 *
	 * @return string|null
	 */
	private function getEditorType( $type ) {
		return self::EDITOR_TYPE_MAP[ $type ] ?? null;
	}
}
