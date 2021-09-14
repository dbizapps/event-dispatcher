<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dbizapps\EventDispatcher\Contracts;

use dbizapps\EventDispatcher\Event;

interface EventDispatcherInterface
{
    /**
     * Dispatch event 
     * 
     * @param object  $event
     * @param string  $eventName
     */
    public function dispatch( Event $event, string $eventName = null);

}