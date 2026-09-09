<?php

namespace WPML\Core\Component\PostHog\Application\Service\Event;

use WPML\Core\Component\PostHog\Domain\Event\Custom\Event as CustomEvent;
use WPML\Core\Component\PostHog\Domain\Event\Custom\TEAEvent as CustomTEAEvent;
use WPML\Core\Component\PostHog\Domain\Event\OpenTranslationEditor\Event as OpenTranslationEditorEvent;
use WPML\Core\Component\PostHog\Domain\Event\SetupWizard\WizardCompleted\Event as WizardCompletedEvent;
use WPML\Core\Component\PostHog\Domain\Event\SetupWizard\WizardFirstStepCompleted\Event as WizardFirstStepCompletedEvent;
use WPML\Core\Component\PostHog\Domain\Event\SetupWizard\WizardStarted\Event as WizardStartedEvent;
use WPML\Core\Component\PostHog\Domain\Event\SetupWizard\WizardStepCompleted\Event as WizardStepCompletedEvent;
use WPML\Core\Component\PostHog\Domain\Event\TaxonomyTranslation\TaxonomyHierarchySyncCompleted\Event as TaxonomyHierarchySyncCompletedEvent;
use WPML\Core\Component\PostHog\Domain\Event\TaxonomyTranslation\TaxonomyHierarchySyncNoticeDisplayed\Event as TaxonomyHierarchySyncNoticeDisplayedEvent;
use WPML\Core\Component\PostHog\Domain\Event\TaxonomyTranslation\TaxonomyHierarchySyncNoticeLinkClicked\Event as TaxonomyHierarchySyncNoticeLinkClickedEvent;
use WPML\Core\Component\PostHog\Domain\Event\TaxonomyTranslation\TaxonomyTermTranslationSaved\Event as TaxonomyTermTranslationSavedEvent;
use WPML\Core\Component\PostHog\Domain\Event\WPMLLanguages\EditLanguagesFormSubmitted\Event as EditLanguagesFormSubmittedEvent;
use WPML\Core\Component\PostHog\Domain\Event\WPMLLanguages\FooterLanguageSwitcherToggled\Event as FooterLanguageSwitcherToggledEvent;
use WPML\Core\Component\PostHog\Domain\Event\WPMLLanguages\RootPageSaved\Event as RootPageSavedEvent;
use WPML\Core\Component\PostHog\Domain\Event\WPMLLanguages\SetLanguageUrlFormat\Event as SetLanguageUrlFormatEvent;
use WPML\Core\Component\PostHog\Domain\Event\WPMLLanguages\SetLanguageUrlFormatFailed\Event as SetLanguageUrlFormatFailedEvent;
use WPML\Core\Component\PostHog\Domain\Event\WPMLSettings\ATEForOldTranslationsEnabled\Event as ATEForOldTranslationsEnabledEvent;
use WPML\Core\Component\PostHog\Domain\Event\WPMLSettings\AutomaticTranslationSettingsSaved\Event as AutomaticTranslationSettingsSavedEvent;
use WPML\Core\Component\PostHog\Domain\Event\WPMLSettings\PostTypeUnlocked\Event as PostTypeUnlockedEvent;
use WPML\Core\Component\PostHog\Domain\Event\WPMLSettings\TaxonomyUnlocked\Event as TaxonomyUnlockedEvent;
use WPML\Core\Component\PostHog\Domain\Event\WPMLSettings\TranslationEditorSwitched\Event as TranslationEditorSwitchedEvent;

class EventInstanceService {


    /**
     * @param string $name
     * @param array<string,mixed> $props
     *
     * @return CustomEvent
     */
  public function getCustomEvent( string $name, array $props ): CustomEvent {
      return new CustomEvent( $name, $props );
  }


    /**
     * Returns a custom event that also implements TEAEventInterface.
     * Use this when the JS caller sent isTEAEvent: true so that CaptureEventService
     * allows the event in tea_only tracking mode.
     *
     * @param string $name
     * @param array<string,mixed> $props
     *
     * @return CustomTEAEvent
     */
  public function getCustomTEAEvent( string $name, array $props ): CustomTEAEvent {
      return new CustomTEAEvent( $name, $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return AutomaticTranslationSettingsSavedEvent
     */
  public function getAutomaticTranslationSettingsSavedEvent( array $props ): AutomaticTranslationSettingsSavedEvent {
      return new AutomaticTranslationSettingsSavedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return RootPageSavedEvent
     */
  public function getRootPageSavedEvent( array $props ): RootPageSavedEvent {
      return new RootPageSavedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return SetLanguageUrlFormatEvent
     */
  public function getSetLanguageUrlFormatEvent( array $props ): SetLanguageUrlFormatEvent {
      return new SetLanguageUrlFormatEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return SetLanguageUrlFormatFailedEvent
     */
  public function getSetLanguageUrlFormatFailedEvent( array $props ): SetLanguageUrlFormatFailedEvent {
      return new SetLanguageUrlFormatFailedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return EditLanguagesFormSubmittedEvent
     */
  public function getEditLanguagesFormSubmittedEvent( array $props ): EditLanguagesFormSubmittedEvent {
      return new EditLanguagesFormSubmittedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return FooterLanguageSwitcherToggledEvent
     */
  public function getFooterLanguageSwitcherToggledEvent( array $props ): FooterLanguageSwitcherToggledEvent {
      return new FooterLanguageSwitcherToggledEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return TaxonomyHierarchySyncNoticeDisplayedEvent
     */
  public function getTaxonomyHierarchySyncNoticeDisplayedEvent( array $props ):
    TaxonomyHierarchySyncNoticeDisplayedEvent {
      return new TaxonomyHierarchySyncNoticeDisplayedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return TaxonomyHierarchySyncNoticeLinkClickedEvent
     */
  public function getTaxonomyHierarchySyncLinkClickedEvent( array $props ):
    TaxonomyHierarchySyncNoticeLinkClickedEvent {
      return new TaxonomyHierarchySyncNoticeLinkClickedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return TaxonomyHierarchySyncCompletedEvent
     */
  public function getTaxonomyHierarchySyncCompletedEvent( array $props ): TaxonomyHierarchySyncCompletedEvent {
      return new TaxonomyHierarchySyncCompletedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return TaxonomyTermTranslationSavedEvent
     */
  public function getTaxonomyTermTranslationSavedEvent( array $props ): TaxonomyTermTranslationSavedEvent {
      return new TaxonomyTermTranslationSavedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return TranslationEditorSwitchedEvent
     */
  public function getTranslationEditorSwitchedEvent( array $props ): TranslationEditorSwitchedEvent {
      return new TranslationEditorSwitchedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return PostTypeUnlockedEvent
     */
  public function getPostTypeUnlockedEvent( array $props ): PostTypeUnlockedEvent {
      return new PostTypeUnlockedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return TaxonomyUnlockedEvent
     */
  public function getTaxonomyUnlockedEvent( array $props ): TaxonomyUnlockedEvent {
      return new TaxonomyUnlockedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return ATEForOldTranslationsEnabledEvent
     */
  public function getATEForOldTranslationsEnabledEvent( array $props ): ATEForOldTranslationsEnabledEvent {
      return new ATEForOldTranslationsEnabledEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return OpenTranslationEditorEvent
     */
  public function getOpenTranslationEditorEvent( array $props ): OpenTranslationEditorEvent {
      return new OpenTranslationEditorEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return WizardStartedEvent
     */
  public function getWizardStartedEvent( array $props ): WizardStartedEvent {
      return new WizardStartedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return WizardCompletedEvent
     */
  public function getWizardCompletedEvent( array $props ): WizardCompletedEvent {
      return new WizardCompletedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return WizardFirstStepCompletedEvent
     */
  public function getWizardFirstStepCompletedEvent( array $props ): WizardFirstStepCompletedEvent {
      return new WizardFirstStepCompletedEvent( $props );
  }


    /**
     * @param array<string,mixed> $props
     *
     * @return WizardStepCompletedEvent
     */
  public function getWizardStepCompletedEvent( array $props ): WizardStepCompletedEvent {
      return new WizardStepCompletedEvent( $props );
  }


}
