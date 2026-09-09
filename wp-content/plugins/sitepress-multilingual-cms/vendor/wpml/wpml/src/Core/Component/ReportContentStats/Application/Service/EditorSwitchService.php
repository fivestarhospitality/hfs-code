<?php

namespace WPML\Core\Component\ReportContentStats\Application\Service;

class EditorSwitchService {

    /** @var ResendTriggerService */
    private $resendTriggerService;


  public function __construct( ResendTriggerService $resendTriggerService ) {
      $this->resendTriggerService = $resendTriggerService;
  }


    /**
     * @return void
     */
  public function handleEditorSwitch() {
      $this->resendTriggerService->trigger( EventReasonService::REASON_EDITOR_SWITCH );
  }


}
