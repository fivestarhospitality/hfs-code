<?php

namespace WPML\TM\TranslationDashboard\EncodedFieldsValidation;

use WPML\Core\Component\Base64Detection\Application\Service\Base64DetectionService;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\Infrastructure\Dic;
use WPML\LIB\WP\Post;
use WPML\TM\TranslationDashboard\SentContentMessages;
use function WPML\FP\spreadArgs;

class Validator {
	/** @var Base64DetectionService */
	private $base64Detector;
	/** @var \WPML_Element_Translation_Package */
	private $package_helper;
	/** @var SentContentMessages */
	private $sentContentMessages;
	/** @var FieldTitle */
	private $fieldTitle;
	/** @var \WPML_PB_Factory */
	private $pbFactory;

	public function __construct(
		\WPML_Element_Translation_Package $package_helper,
		SentContentMessages $sentContentMessages,
		FieldTitle $fieldTitle,
		\WPML_PB_Factory $pbFactory
	) {
		$this->base64Detector      = $this->getBase64DetectionService();
		$this->package_helper      = $package_helper;
		$this->sentContentMessages = $sentContentMessages;
		$this->fieldTitle          = $fieldTitle;
		$this->pbFactory           = $pbFactory;
	}


	private function getBase64DetectionService() {
		/** @var Dic $wpml_dic */
		global $wpml_dic;

		return $wpml_dic->make( Base64DetectionService::class );
	}

	/**
	 * $data may contain two keys: 'post' and 'package'. Each of them has the same shape:
	 * [
	 *   idOfElement1 => [
	 *      checked: 1,
	 *      type: 'post',
	 *   ],
	 *   idOfElement2 => [
	 *      type: 'post',
	 *   ],
	 * ] and so on.
	 *
	 * If element has "checked" field, it means it has been selected for translation.
	 * Therefore, if we want to filter it out from translation, we have to remove that field.
	 *
	 * The "validateTMDashboardInput" performs similar check for both "post" and "package" lists,
	 * checks if their elements contains encoded fields and removes them from the list.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function validateTMDashboardInput( $data ) {
		$postInvalidElements = [];
		if ( isset( $data['post'] ) ) {
			$postInvalidElements = $this->findPostsWithEncodedFields( $this->getCheckedIds( 'post', $data ) );
			$data                = $this->excludeInvalidElements( 'post', $data, Lst::pluck( 'elementId', $postInvalidElements ) );
		}

		$packageInvalidElements = [];
		if ( isset( $data['package'] ) ) {
			$packageInvalidElements = $this->findPackagesWithEncodedFields( $this->getCheckedIds( 'package', $data ) );
			$data                   = $this->excludeInvalidElements( 'package', $data, Lst::pluck( 'elementId', $packageInvalidElements ) );
		}

		$invalidElements = array_merge( $postInvalidElements, $packageInvalidElements );
		if ( count( $invalidElements ) ) {
			// Display the error message only if there are invalid elements.
			$this->sentContentMessages->postsWithEncodedFieldsHasBeenSkipped( $invalidElements );
		}

		return $data;
	}

	/**
	 * @param int[] $postIds
	 * @param int[] $packageIds
	 *
	 * @return int[][]
	 */
	public function getInvalidPostAndPackageIds( $postIds, $packageIds ) {
		$invalidPostIds    = [];
		$invalidPackageIds = [];
		$postIds           = array_unique( $postIds );
		$packageIds        = array_unique( $packageIds );

		if ( is_array( $postIds ) && ! empty( $postIds ) ) {
			$invalidPostIds = $this->findElementsIdsWithEncodedFields( 'post', $postIds );
		}

		if ( is_array( $packageIds ) && ! empty( $packageIds ) ) {
			$invalidPackageIds = $this->findElementsIdsWithEncodedFields( 'package', $packageIds );
		}

		return [ $invalidPostIds, $invalidPackageIds ];
	}

	/**
	 * @param string $type
	 * @param int[]  $elementsIds
	 *
	 * @return int[]
	 */
	private function findElementsIdsWithEncodedFields( $type, $elementsIds ) {
		$elementsIdsWithEncodedFields = [];

		$extractElementId = function ( ErrorEntry $errorEntry ) {
			return $errorEntry->elementId;
		};

		if ( 'post' === $type ) {
			$elementsIdsWithEncodedFields = array_map(
				$extractElementId,
				$this->findPostsWithEncodedFields( $elementsIds )
			);
		} elseif ( 'package' === $type ) {
			$elementsIdsWithEncodedFields = array_map(
				$extractElementId,
				$this->findPackagesWithEncodedFields( $elementsIds )
			);
		}

		return $elementsIdsWithEncodedFields;
	}

	/**
	 * Get list of ids of selected posts/packages
	 *
	 * @param 'post'|'package' $type
	 * @param array            $data
	 *
	 * @return []
	 */
	private function getCheckedIds( $type, $data ) {
		return \wpml_collect( Obj::propOr( [], $type, $data ) )
			->filter( Obj::prop( 'checked' ) )
			->keys()
			->toArray();
	}

	/**
	 * It removes "checked" property from the elements that have "encoded" fields. It means they will not be sent to translation.
	 *
	 * @param 'post'|'package' $type
	 * @param array            $data
	 * @param int[]            $ids
	 *
	 * @return array
	 */
	private function excludeInvalidElements( $type, $data, $invalidElementIds ) {
		return (array) Obj::over( Obj::lensProp( $type ), function ( $elements ) use ( $invalidElementIds ) {
			return \wpml_collect( $elements )
				->map( function ( $element, $elementId ) use ( $invalidElementIds ) {
					if ( Lst::includes( $elementId, $invalidElementIds ) ) {
						return Obj::removeProp( 'checked', $element );
					}

					return $element;
				} )
				->toArray();
		}, $data );
	}

	/**
	 * @param int[] $postIds
	 *
	 * @return ErrorEntry[]
	 */
	private function findPostsWithEncodedFields( $postIds ) {
		$appendPackage = function ( \WP_Post $post ) {
			$package = $this->package_helper->create_translation_package( $post->ID, true );

			return [ $post, $package ];
		};

		$isFieldEncoded = function ( $field, $slug ) {
			$decodedFieldData = base64_decode( $field['data'] );

			return array_key_exists( 'format', $field )
			        // HotFix - wpmldev-6436: Exclude title and body fields. Title can contain visible base64 encoded data we should allow.
			        // Body is not translatable for page builder, we should exclude.
			        // In case of classic editor containing base64 encoded data in body, we should allow it for now.
					&& ! in_array( $slug, [ 'title', 'body' ], true )
					&& 'base64' === $field['format']
					&& (
						$this->base64Detector->isBase64EncodedText( $decodedFieldData ) ||
						$this->base64Detector->containsBase64EncodedText( $decodedFieldData )
					);
		};

		$getInvalidFieldData = function ( $field, $slug ) {
			return [
				'title'   => $this->fieldTitle->get( $slug ),
				'content' => base64_decode( $field['data'] ),
			];
		};

		$tryToGetError = function ( \WP_Post $post, $package ) use ( $isFieldEncoded, $getInvalidFieldData ) {
			$invalidFields = \wpml_collect( $package['contents'] )
				->filter( $isFieldEncoded )
				->map( $getInvalidFieldData )
				->values()
				->toArray();

			if ( $invalidFields ) {
				return new ErrorEntry( $post->ID, $package['title'], $invalidFields );
			}

			return null;
		};

		return \wpml_collect( $postIds )
			->map( Post::get() )
			->filter()
			->map( $appendPackage )
			->map( spreadArgs( $tryToGetError ) )
			->filter()
			->toArray();

	}

	/**
	 * @param int[] $packageIds
	 *
	 * @return ErrorEntry[]
	 */
	private function findPackagesWithEncodedFields( $packageIds ) {
		$getInvalidFieldData = function ( $field, $slug ) {
			return [
				'title'   => $this->fieldTitle->get( $slug ),
				'content' => $field,
			];
		};

		$isEncodedContent = function ( $content ) {
			return $this->base64Detector->isBase64EncodedText( $content ) ||
					$this->base64Detector->containsBase64EncodedText( $content );
		};

		/**
		 * @param \WPML_Package $package
		 *
		 * @return ErrorEntry|null
		 */
		$tryToGetError = function ( $package ) use ( $getInvalidFieldData, $isEncodedContent ) {
			$invalidFields = \wpml_collect( Obj::propOr( [], 'string_data', $package ) )
				->filter( $isEncodedContent )
				->map( $getInvalidFieldData )
				->values()
				->toArray();

			if ( $invalidFields ) {
				return new ErrorEntry( $package->ID, $package->title, $invalidFields );
			}

			return null;
		};

		return \wpml_collect( $packageIds )
			->map( function ( $packageId ) {
				return $this->pbFactory->get_wpml_package( $packageId );
			} )
			->filter( Obj::prop( 'ID' ) )
			->map( $tryToGetError )
			->filter()
			->toArray();

	}
}
