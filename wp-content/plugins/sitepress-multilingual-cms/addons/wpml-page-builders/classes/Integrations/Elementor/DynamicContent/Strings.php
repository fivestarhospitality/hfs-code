<?php

namespace WPML\PB\Elementor\DynamicContent;

use WPML\Collect\Support\Collection;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML_Elementor_Translatable_Nodes;
use WPML_PB_String;

class Strings {

	const KEY_SETTINGS = WPML_Elementor_Translatable_Nodes::SETTINGS_FIELD;
	const KEY_NODE_ID  = 'id';
	const KEY_ITEM_ID  = '_id';

	const KEY_DYNAMIC_V3 = '__dynamic__';
	const KEY_DYNAMIC_V4 = '$$type';

	const SETTINGS_REGEX        = '/settings="(.*?(?="]))/';
	const NAME_PREFIX           = 'dynamic';
	const DELIMITER             = '-';
	const TRANSLATABLE_SETTINGS = [
		'before',
		'after',
		'fallback',
		'video_url',
		'shortcode',
	];

	/**
	 * Remove the strings overwritten with dynamic content
	 * and add the extra strings "before", "after" and "fallback".
	 *
	 * @param WPML_PB_String[] $strings
	 * @param string           $nodeId
	 * @param array            $element
	 *
	 * @return WPML_PB_String[]
	 */
	public static function filter( array $strings, $nodeId, array $element ) {

		$dynamicFields = self::getDynamicFields( $element );

		$updateFromDynamicFields = function ( WPML_PB_String $pbString ) use ( &$dynamicFields ) {
			$matchingField = $dynamicFields->first(
				function ( Field $field ) use ( $pbString ) {
					return $field->isMatchingStaticString( $pbString );
				}
			);

			if ( $matchingField ) {
				return self::addBeforeAfterAndFallback( wpml_collect( [ $dynamicFields->pull( $dynamicFields->search( $matchingField ) ) ] ), $pbString->get_title() );
			}

			return $pbString;
		};

		$stringsForNonTranslatableFields = function ( $dynamicFields ) {
			return self::addBeforeAfterAndFallback( $dynamicFields );
		};

		return wpml_collect( $strings )
			->map( $updateFromDynamicFields )
			->merge( $stringsForNonTranslatableFields( $dynamicFields ) )
			->flatten()
			->toArray();
	}

	/**
	 * @param array $element
	 *
	 * @return Collection
	 */
	private static function getDynamicFields( array $element ) {
		if ( self::isModuleWithItems( $element ) ) {
			return self::getDynamicFieldsForModuleWithItems( $element );
		} elseif ( isset( $element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ] ) ) {
			return self::getFields(
				$element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ],
				$element[ self::KEY_NODE_ID ]
			);
		} elseif ( self::hasV4DynamicFields( $element ) ) {
			return self::getV4DynamicFields( $element );
		}

		return wpml_collect();
	}

	/**
	 * @param array $element
	 *
	 * @return Collection
	 */
	private static function getDynamicFieldsForModuleWithItems( array $element ) {
		$isDynamic = function ( $item ) {
			return isset( $item[ self::KEY_DYNAMIC_V3 ] );
		};

		$getFields = function ( array $item ) use ( $element ) {
			return self::getFields(
				$item[ self::KEY_DYNAMIC_V3 ],
				$element[ self::KEY_NODE_ID ],
				$item[ self::KEY_ITEM_ID ]
			);
		};

		$collection = wpml_collect( self::getFieldsFromModuleWithItems( $element[ self::KEY_SETTINGS ] ) )
			->filter( $isDynamic )
			->map( $getFields );

		if ( isset( $element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ] ) ) {
			$collection = $collection->merge( self::getFields( $element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ], $element[ self::KEY_NODE_ID ] ) );
		}

		return $collection->flatten();
	}

	/**
	 * @param array $module
	 *
	 * @return array|null
	 */
	public static function getFieldsFromModuleWithItems( $module ) {
		$hasFields = function ( $item ) {
			return is_array( $item );
		};

		return wpml_collect( $module )
			->first( $hasFields );
	}

	/**
	 * @param array $module
	 *
	 * @return string
	 */
	public static function getKeyFromModuleWithItems( $module ) {
		$hasFields = function ( $item ) {
			return is_array( $item );
		};

		return wpml_collect( $module )
			->filter( $hasFields )
			->keys()
			->first();
	}

	/**
	 * @param array  $data
	 * @param string $nodeId
	 * @param string $itemId
	 *
	 * @return Collection
	 */
	private static function getFields( array $data, $nodeId, $itemId = '' ) {
		$buildField = function ( $tagValue, $tagKey ) use ( $nodeId, $itemId ) {
			return new Field( $tagValue, $tagKey, $nodeId, $itemId );
		};

		return wpml_collect( $data )->map( $buildField );
	}

	/**
	 * @param array $element
	 *
	 * @return bool
	 */
	private static function isModuleWithItems( array $element ) {
		if ( isset( $element[ self::KEY_SETTINGS ] ) ) {
			$firstSettingElement = self::getFieldsFromModuleWithItems( $element[ self::KEY_SETTINGS ] );
			return is_array( $firstSettingElement ) && 0 === key( $firstSettingElement );
		}

		return false;
	}

	/**
	 * @param Collection  $dynamicFields
	 * @param string|null $stringTitle
	 *
	 * @return Collection
	 */
	private static function addBeforeAfterAndFallback( Collection $dynamicFields, $stringTitle = null ) {
		$dynamicFieldToSettingStrings = function ( Field $field ) use ( $stringTitle ) {
			preg_match( self::SETTINGS_REGEX, $field->tagValue, $matches );

			$isTranslatableSetting = function ( $value, $settingField ) {
				return $value && is_string( $value ) && in_array( $settingField, self::TRANSLATABLE_SETTINGS, true );
			};

			$buildStringFromSetting = function ( $value, $settingField ) use ( $field, $stringTitle ) {
				// Get a title for dynamic strings attached to a field that isn't marked for translation.
				$stringTitle = ( null === $stringTitle ) ? $field->tagKey : $stringTitle;

				return new WPML_PB_String(
					$value,
					self::getStringName( $field->nodeId, $field->itemId, $field->tagKey, $settingField ),
					$stringTitle . ' (' . $settingField . ')',
					'LINE'
				);
			};

			return wpml_collect( isset( $matches[1] ) ? self::decodeSettings( $matches[1] ) : [] )
				->filter( $isTranslatableSetting )
				->map( $buildStringFromSetting );
		};

		return $dynamicFields->map( $dynamicFieldToSettingStrings );
	}

	/**
	 * @param array          $element
	 * @param WPML_PB_String $pbString
	 *
	 * @return array
	 */
	public static function updateNode( array $element, WPML_PB_String $pbString ) {
		$stringNameParts = explode( self::DELIMITER, $pbString->get_name() );

		if ( count( $stringNameParts ) !== 5 || self::NAME_PREFIX !== $stringNameParts[0] ) {
			return $element;
		}

		list( , , $itemId, $dynamicField, $settingField ) = $stringNameParts;

		if ( $itemId && self::isModuleWithItems( $element ) ) {
			$element = self::updateNodeWithItems( $element, $pbString, $stringNameParts );
		} elseif ( isset( $element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ][ $dynamicField ] ) ) {
			$element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ][ $dynamicField ] = self::replaceSettingString(
				$element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ][ $dynamicField ],
				$pbString,
				$settingField
			);
		} elseif ( self::isV4DynamicField( $element, $dynamicField ) ) {
			$element = self::updateV4DynamicField( $element, $dynamicField, $settingField, $pbString );
		}

		return $element;
	}

	/**
	 * @param string         $encodedSettings
	 * @param WPML_PB_String $pbString
	 * @param string         $settingField
	 *
	 * @return string|null
	 */
	private static function replaceSettingString( $encodedSettings, WPML_PB_String $pbString, $settingField ) {
		$replace = function ( array $matches ) use ( $pbString, $settingField ) {
			$settings                  = self::decodeSettings( $matches[1] );
			$settings[ $settingField ] = $pbString->get_value();
			$replace                   = self::encodeSettings( $settings );

			return str_replace( $matches[1], $replace, $matches[0] );
		};

		return preg_replace_callback( self::SETTINGS_REGEX, $replace, $encodedSettings );
	}

	/**
	 * @param array          $element
	 * @param WPML_PB_String $pbString
	 * @param array          $stringNameParts
	 *
	 * @return array
	 */
	private static function updateNodeWithItems( array $element, WPML_PB_String $pbString, array $stringNameParts ) {
		list( , , $itemId, $dynamicField, $settingField ) = $stringNameParts;

		$items   = wpml_collect( self::getFieldsFromModuleWithItems( $element[ self::KEY_SETTINGS ] ) );
		$mainKey = self::getKeyFromModuleWithItems( $element[ self::KEY_SETTINGS ] );

		$replaceStringInItem = function ( array $item ) use ( $itemId, $pbString, $dynamicField, $settingField ) {
			if (
				isset( $item[ self::KEY_DYNAMIC_V3 ][ $dynamicField ], $item[ self::KEY_ITEM_ID ] )
				&& $item[ self::KEY_ITEM_ID ] === $itemId
			) {
				$item[ self::KEY_DYNAMIC_V3 ][ $dynamicField ] = self::replaceSettingString( $item[ self::KEY_DYNAMIC_V3 ][ $dynamicField ], $pbString, $settingField );
			}

			return $item;
		};

		$element[ self::KEY_SETTINGS ][ $mainKey ] = $items->map( $replaceStringInItem )->toArray();
		if ( isset( $element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ][ $dynamicField ] ) ) {
			$element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ][ $dynamicField ] = self::replaceSettingString(
				$element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ][ $dynamicField ],
				$pbString,
				$settingField
			);
		}

		return $element;
	}

	/**
	 * @param string $settingsString
	 *
	 * @return array
	 */
	private static function decodeSettings( $settingsString ) {
		return json_decode( urldecode( $settingsString ), true );
	}

	/**
	 * @param array $settings
	 *
	 * @return string
	 */
	private static function encodeSettings( array $settings ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode, WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
		return urlencode( json_encode( $settings ) );
	}

	/**
	 * @param string $nodeId
	 * @param string $itemId
	 * @param string $tagKey
	 * @param string $settingField
	 *
	 * @return string
	 */
	public static function getStringName( $nodeId, $itemId, $tagKey, $settingField ) {
		return self::NAME_PREFIX . self::DELIMITER
			. $nodeId . self::DELIMITER
			. $itemId . self::DELIMITER
			. $tagKey . self::DELIMITER
			. $settingField;
	}

	/**
	 * @param array $element
	 *
	 * @return bool
	 */
	private static function hasV4DynamicFields( array $element ) {
		if ( ! isset( $element[ self::KEY_SETTINGS ] ) ) {
			return false;
		}

		$isDynamicField = function ( $value ) {
			return is_array( $value ) && Relation::propEq( self::KEY_DYNAMIC_V4, 'dynamic', $value );
		};

		return wpml_collect( $element[ self::KEY_SETTINGS ] )->contains( $isDynamicField );
	}

	/**
	 * @param array $element
	 *
	 * @return Collection
	 */
	private static function getV4DynamicFields( array $element ) {
		$toField = function ( $value, $tagKey ) use ( $element ) {
			$tagValue = self::convertV4DynamicToV3Format( $value );

			return new Field( $tagValue, $tagKey, $element[ self::KEY_NODE_ID ] );
		};

		return wpml_collect( $element[ self::KEY_SETTINGS ] )
			->filter( Relation::propEq( self::KEY_DYNAMIC_V4, 'dynamic' ) )
			->map( $toField );
	}

	/**
	 * @param array $v4Dynamic
	 *
	 * @return string
	 */
	private static function convertV4DynamicToV3Format( array $v4Dynamic ) {
		$value    = Obj::propOr( [], 'value', $v4Dynamic );
		$name     = Obj::propOr( '', 'name', $value );
		$settings = Obj::propOr( [], 'settings', $value );

		$isTypedSetting = function ( $setting ) {
			return is_array( $setting ) && isset( $setting[ self::KEY_DYNAMIC_V4 ], $setting['value'] );
		};

		$convertedSettings = wpml_collect( $settings )
			->filter( $isTypedSetting )
			->map( Obj::prop( 'value' ) )
			->toArray();

		$encodedSettings = self::encodeSettings( $convertedSettings );

		return sprintf( '[elementor-tag id="%s" name="%s" settings="%s"]', uniqid(), $name, $encodedSettings );
	}

	/**
	 * @param array  $element
	 * @param string $dynamicField
	 *
	 * @return bool
	 */
	private static function isV4DynamicField( array $element, $dynamicField ) {
		return isset( $element[ self::KEY_SETTINGS ][ $dynamicField ] )
			&& Relation::propEq( self::KEY_DYNAMIC_V4, 'dynamic', $element[ self::KEY_SETTINGS ][ $dynamicField ] );
	}

	/**
	 * @param array          $element
	 * @param string         $dynamicField
	 * @param string         $settingField
	 * @param WPML_PB_String $pbString
	 *
	 * @return array
	 */
	private static function updateV4DynamicField( array $element, $dynamicField, $settingField, WPML_PB_String $pbString ) {
		$path = [ self::KEY_SETTINGS, $dynamicField, 'value', 'settings', $settingField ];

		if ( ! Obj::hasPath( $path, $element ) ) {
			return $element;
		}

		return Obj::assocPath(
			$path,
			[
				self::KEY_DYNAMIC_V4 => 'string',
				'value'              => $pbString->get_value(),
			],
			$element
		);
	}
}
