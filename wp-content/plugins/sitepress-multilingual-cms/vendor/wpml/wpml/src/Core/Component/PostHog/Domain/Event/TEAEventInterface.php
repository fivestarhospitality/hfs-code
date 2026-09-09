<?php

namespace WPML\Core\Component\PostHog\Domain\Event;

/**
 * Marker interface for events that belong to the Translation Editor Advanced (TEA) context.
 *
 * Events implementing this interface are allowed to be captured when the tracking
 * mode is set to `tea_only`, in addition to the `all` mode.
 */
interface TEAEventInterface {

}
