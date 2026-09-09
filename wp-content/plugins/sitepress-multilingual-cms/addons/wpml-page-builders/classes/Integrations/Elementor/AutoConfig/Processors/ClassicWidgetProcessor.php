<?php

namespace WPML\PB\Elementor\AutoConfig\Processors;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class ClassicWidgetProcessor implements WidgetProcessorInterface {

	const EDITOR_TYPE_MAP = [
		Controls_Manager::TEXT     => 'LINE',
		Controls_Manager::TEXTAREA => 'AREA',
		Controls_Manager::WYSIWYG  => 'VISUAL',
		Controls_Manager::URL      => 'LINK',
	];

	/**
	 * @param Widget_Base $widget
	 *
	 * @return bool
	 */
	public function canProcess( $widget ) {
		return ! method_exists( $widget, 'get_atomic_controls' );
	}

	/**
	 * @param Widget_Base $widget
	 *
	 * @return array
	 */
	public function process( $widget ) {
		$title    = $widget->get_title();
		$controls = $widget->get_controls();
		$config   = [];

		foreach ( $controls as $key => $control ) {
			if ( $this->isRegistrable( $key, $control ) ) {
				if ( $this->isRepeater( $control['type'] ) ) {
					$config = $this->processRepeaterControl( $config, $control, $title );
				} else {
					$config = $this->processControl( $config, $control, $title );
				}
			}
		}

		return $config;
	}

	/**
	 * @param array  $config
	 * @param array  $control
	 * @param string $title
	 * @param bool   $isTopLevel
	 *
	 * @return array
	 */
	private function processControl( $config, $control, $title, $isTopLevel = true ) {
		$type   = $control['type'];
		$name   = $control['name'];
		$label  = $control['label'] ?? $name;
		$editor = $this->getEditorType( $type );

		if ( ! $editor ) {
			return $config;
		}

		$fieldPath = Controls_Manager::URL === $type ? $name . '>url' : $name;

		$field = [
			'field'       => $fieldPath,
			'type'        => $title . ': ' . $label,
			'editor_type' => $editor,
		];

		if ( $isTopLevel ) {
			if ( ! isset( $config['fields'] ) ) {
				$config['fields'] = [];
			}
			$config['fields'][] = $field;
		} else {
			$config[] = $field;
		}

		return $config;
	}

	/**
	 * @param array  $config
	 * @param array  $control
	 * @param string $title
	 *
	 * @return array
	 */
	private function processRepeaterControl( $config, $control, $title ) {
		if ( empty( $control['fields'] ) ) {
			return $config;
		}

		$name = $control['name'];

		if ( ! isset( $config['fields_in_item'] ) ) {
			$config['fields_in_item'] = [];
		}

		if ( ! isset( $config['fields_in_item'][ $name ] ) ) {
			$config['fields_in_item'][ $name ] = [];
		}

		foreach ( $control['fields'] as $control ) {
			$config['fields_in_item'][ $name ] = $this->processControl(
				$config['fields_in_item'][ $name ],
				$control,
				$title,
				false
			);
		}

		return $config;
	}

	/**
	 * @param string $key
	 * @param array  $control
	 *
	 * @return bool
	 */
	private function isRegistrable( $key, $control ) {
		if ( $this->isPrivate( $key ) ) {
			return false;
		}

		if ( ControlNameFilter::shouldExclude( $key ) ) {
			return false;
		}

		return $this->isTranslatable( $control['type'] );
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	private function isPrivate( $key ) {
		return 0 === strpos( $key, '_' );
	}

	/**
	 * @param string $type
	 *
	 * @return bool
	 */
	private function isTranslatable( $type ) {
		return $this->isRepeater( $type ) || null !== $this->getEditorType( $type );
	}

	/**
	 * @param string $type
	 *
	 * @return bool
	 */
	private function isRepeater( $type ) {
		return Controls_Manager::REPEATER === $type;
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
