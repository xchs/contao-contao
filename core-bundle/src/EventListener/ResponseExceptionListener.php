<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\EventListener;

use Contao\CoreBundle\Exception\ResponseException;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

class ResponseExceptionListener
{
    /**
     * Sets the response from the exception.
     */
    public function onKernelException(GetResponseForExceptionEvent $event): void
    {
        $exception = $event->getException();

        if (!$exception instanceof ResponseException) {
            return;
        }

        $event->allowCustomResponseCode();
        $event->setResponse($exception->getResponse());
    }
}
