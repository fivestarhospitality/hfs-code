<?php

/**
 *  PostHog should record
 *   - [arrayKey]                  Id of the script.
 *      - src                      Script source path. Start with 'public/js/...'.
 *      - usedOn (optional)        Default: 'admin'
 *                                 'admin' | 'front' | 'both'
 *                                 ('front' | 'both' are not implemented yet - as not needed).
 *      - onlyRegister (optional)  Default: false.
 *                                 If true, the script will only be registered.
 *                                 (And is only loaded if it is a dependency of another script).
 *      - dependencies (optional)  Array of script dependencies.
 *      - prerequisites (optional) Classname of script prerequisites
 *                                 (must implement ScriptPrerequisitesInterface).
 *      - dataProvider (optional)  Classname of data provider
 *
 */

use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\PostHog\Controller as PostHogShouldRecordController;

return [
  'check-posthog-should-record' => [
    'src'           => 'public/js/check-posthog-should-record.js',
    'prerequisites' => PostHogShouldRecordController::class,
    'dataProvider'  => PostHogShouldRecordController::class,
    'dependencies'  => [ 'wpml-node-modules' ],
  ],
];
