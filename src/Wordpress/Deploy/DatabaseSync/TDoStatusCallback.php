<?php

namespace Wordpress\Deploy\DatabaseSync;

trait TDoStatusCallback {
    /**
     * @param Status $status
     * @param \Closure|null $statusCallback
     */
    private function doStatusCallback(Status $status, $statusCallback) {
        if(!$statusCallback) return;
        else $statusCallback($status);
    }
}