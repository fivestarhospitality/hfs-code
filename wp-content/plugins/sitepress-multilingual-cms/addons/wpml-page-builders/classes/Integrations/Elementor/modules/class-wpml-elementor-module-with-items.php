<?php

use WPML\FP\Obj;

/**
 * Class WPML_Elementor_Module_With_Items
 */
abstract class WPML_Elementor_Module_With_Items implements IWPML_Page_Builders_Module {

	/**
	 * @param string $field
	 *
	 * @return string
	 */
	abstract protected function get_title( $field );

	/** @return array */
	abstract protected function get_fields();

	/**
	 * @param string $field
	 *
	 * @return string mixed
	 */
	abstract protected function get_editor_type( $field );

	/**
	 * @return string
	 */
	abstract public function get_items_field();

	/**
	 * @param string|int       $node_id
	 * @param array            $element
	 * @param WPML_PB_String[] $strings
	 *
	 * @return WPML_PB_String[]
	 */
	public function get( $node_id, $element, $strings ) {
		$activeRepeater = $this->get_active_repeater( $element );

		foreach ( $this->get_items( $element ) as $index => $item ) {
			if ( null === $activeRepeater ) {
				continue;
			}

			$activeItem = false === $activeRepeater ? null : ( $activeRepeater[ $index ] ?? null );

			foreach ( $this->get_fields() as $key => $field ) {
				if ( ! is_array( $field ) ) {
					if ( $this->is_repeater_field_inactive( $activeItem, $field ) ) {
						continue;
					}

					$pathInFlatField = explode( self::FIELD_SEPARATOR, $field );
					$stringValue     = Obj::path( $pathInFlatField, $item );
					if ( $stringValue ) {
						$strings[] = new WPML_PB_String(
							$stringValue,
							$this->get_string_name( $node_id, $item, $element, $field ),
							$this->get_title( $field ),
							$this->get_editor_type( $field )
						);
					}
				} else {
					if ( $this->is_repeater_field_inactive( $activeItem, $key ) ) {
						continue;
					}

					foreach ( $field as $inner_field ) {
						if ( isset( $item[ $key ][ $inner_field ] ) ) {
							$strings[] = new WPML_PB_String(
								$item[ $key ][ $inner_field ],
								$this->get_string_name( $node_id, $item, $element, $inner_field, $key ),
								$this->get_title( $inner_field ),
								$this->get_editor_type( $inner_field )
							);
						}
					}
				}
			}
		}
		return $strings;
	}

	/**
	 * @param int|string     $node_id
	 * @param mixed          $element
	 * @param WPML_PB_String $pbString
	 *
	 * @return mixed
	 */
	public function update( $node_id, $element, WPML_PB_String $pbString ) {
		$activeRepeater = $this->get_active_repeater( $element );

		foreach ( $this->get_items( $element ) as $key => $item ) {
			if ( null === $activeRepeater ) {
				continue;
			}

			$activeItem = false === $activeRepeater ? null : ( $activeRepeater[ $key ] ?? null );

			foreach ( $this->get_fields() as $field_key => $field ) {
				if ( ! is_array( $field ) ) {
					if ( $this->is_repeater_field_inactive( $activeItem, $field ) ) {
						continue;
					}

					if ( $this->get_string_name( $node_id, $item, $element, $field ) === $pbString->get_name() ) {
						$pathInFlatField = explode( self::FIELD_SEPARATOR, $field );
						$stringValue     = Obj::path( $pathInFlatField, $item );
						if ( is_string( $stringValue ) ) {
							$item = Obj::assocPath( $pathInFlatField, $pbString->get_value(), $item );
						}

						return [ $key, $item ];
					}
				} else {
					if ( $this->is_repeater_field_inactive( $activeItem, $field_key ) ) {
						continue;
					}

					foreach ( $field as $inner_field ) {
						if (
							isset( $item[ $field_key ][ $inner_field ] ) &&
							$this->get_string_name( $node_id, $item, $element, $inner_field, $field_key ) === $pbString->get_name()
						) {
							$item[ $field_key ][ $inner_field ] = $pbString->get_value();

							return [ $key, $item ];
						}
					}
				}
			}
		}

		return [ null, null ];
	}

	/**
	 * @param string $nodeId
	 * @param array  $item
	 * @param array  $element
	 * @param string $field
	 * @param string $key
	 *
	 * @return string
	 */
	private function get_string_name( $nodeId, $item, $element, $field = '', $key = '' ) {
		$widgetType = Obj::prop( 'widgetType', $element );
		$itemId     = Obj::prop( '_id', $item );
		$name       = $widgetType . '-' . $field . '-' . $nodeId . '-' . $itemId;

		/**
		 * Filter a package string name.
		 *
		 * Could be used for repeater or nested fields with the same key.
		 *
		 * @since 2.0.5
		 *
		 * @param string $name
		 * @param array  $args {
		 *     @type string $nodeId  Elementor node id.
		 *     @type array  $item    The item that is being registered.
		 *     @type array  $element The element that is being processed and registered.
		 *     @type string $field   Optional. The item field that is being registered.
		 *     @type string $key     Optional. The item field sub-key that is being registered.
		 * }
		 */
		return apply_filters(
			'wpml_pb_elementor_register_string_name_' . $widgetType,
			$name,
			[
				'nodeId'  => $nodeId,
				'item'    => $item,
				'element' => $element,
				'field'   => $field,
				'key'     => $key,
			]
		);
	}

	/**
	 * @param array $element
	 *
	 * @return mixed
	 */
	public function get_items( $element ) {
		return $element[ WPML_Elementor_Translatable_Nodes::SETTINGS_FIELD ][ $this->get_items_field() ];
	}

	/**
	 * Elementor-evaluated repeater items, aligned by index with the raw items
	 * (Elementor preserves order and nulls inactive inner controls per item).
	 *
	 * @param array $element
	 *
	 * @return array|false|null false: do not gate (fail open); null: repeater
	 *                          control is inactive (gate every item); array:
	 *                          per-item active settings.
	 */
	private function get_active_repeater( $element ) {
		$itemsField = $this->get_items_field();

		// Nested repeaters are not gated to avoid mis-aligning sub-items.
		if ( false !== strpos( $itemsField, self::FIELD_SEPARATOR ) ) {
			return false;
		}

		$active = WPML_Elementor_Translatable_Nodes::get_active_element_settings( $element );

		if ( ! array_key_exists( $itemsField, $active ) ) {
			return false;
		}

		$items = $active[ $itemsField ];

		return is_array( $items ) || null === $items ? $items : false;
	}

	/**
	 * @param array|null $activeItem
	 * @param string     $field
	 *
	 * @return bool
	 */
	private function is_repeater_field_inactive( $activeItem, $field ) {
		return is_array( $activeItem )
			&& array_key_exists( $field, $activeItem )
			&& null === $activeItem[ $field ];
	}

	/**
	 * @param string $key
	 *
	 * @return array
	 */
	public function get_field_path( $key ) {
		$path = $this->get_items_field();
		if ( strpos( $path, self::FIELD_SEPARATOR ) ) {
			list( $parent, $path ) = explode( self::FIELD_SEPARATOR, $path, 2 );
			list( $x, $y )         = explode( self::FIELD_SEPARATOR, $key, 2 );
			return [ $parent, $x, $path, $y ];
		} else {
			return [ $path, $key ];
		}
	}
}
