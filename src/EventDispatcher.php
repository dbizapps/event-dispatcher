<?php

/*
 * This file is part of the EventDispatcher package.
 * It's an extended version of Symfony EventDispatcher to support wildcard listeners
 *
 * (c) Mark Fluehmann mark.fluehmann@gmail.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dbizapps\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface;
use dbizapps\EventDispatcher\Contracts\EventDispatcherInterface;
use dbizapps\EventDispatcher\Contracts\EventSubscriberInterface;
use dbizapps\EventDispatcher\Event;

class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array
     */
    private $listeners = [];

    /**
     * @var array
     */
    private $wildcardListeners = [];

    /**
     * @var array
     */
    private $sorted = [];


    /**
     * Adds an event listener that listens on the specified events.
     *
     * @param string  $event
     * @param mixed  $listener
     * @param int  $priority The higher this value, the earlier an event
     *                       listener will be triggered in the chain (defaults to 0)
     */
    public function addListener( string $event, $listener, int $priority = 0 )
    {
        // register non-wildcard listener
        if ( strpos($event, '.*') == false )
            $this->registerListener($event, $listener, $priority);

        // register wildcard listener
        if ( strpos($event, '.*') != false )
            $this->registerWildcardListener($event, $listener, $priority);

        unset($this->sorted[$event]);
    }


    /**
     * Register listener for event
     *
     * @param string  $event
     * @param mixed  $listener
     * @param int  $priority
     */
    private function registerListener( string $event, $listener, int $priority )
    {
        if (! isset($this->listeners[$event][$priority]) )
            $this->listeners[$event][$priority] = [];

        $this->listeners[$event][$priority][] = $listener;
    }


    /**
     * Register wildcard listener for event
     *
     * @param string  $event
     * @param mixed  $listener
     * @param int  $priority
     */
    private function registerWildcardListener( string $event, $listener, int $priority )
    {
        if (! isset($this->wildcardListeners[$event][$priority]) )
            $this->wildcardListeners[$event][$priority] = [];

        $this->wildcardListeners[$event][$priority][] = $listener;
    }


    /**
     * Removes an event listener from the specified events.
     * 
     * @param string  $event
     * @param mixed  $listener
     */
    public function removeListener( string $event, mixed $listener )
    {
        if ( empty($this->listeners[$event]) && empty($this->wildcardListeners[$event]) )
            return;

        if ( is_array($listener) && isset($listener[0]) && $listener[0] instanceof \Closure && count($listener) <= 2 ) {
            $listener[0] = $listener[0]();
            $listener[1] = $listener[1] ?? '__invoke';
        }

        if ( strpos($event, '*') == false )
            $this->unregisterListener( $event, $listener );

        if ( strpos($event, '*') != false )
            $this->unregisterWildcardListener( $event, $listener );
    }   


    /**
     * Adds an event subscriber.
     *
     * The subscriber is asked for all the events it is
     * interested in and added as a listener for these events.
     * 
     * @param EventSubscriberInterface  $subscriber
     */
    public function addSubscriber( EventSubscriberInterface $subscriber )
    {
        foreach ($subscriber->getSubscribedEvents() as $event => $params) {

            if ( is_string($params) ) {
                $this->addListener( $event, [$subscriber, $params] );

            } elseif ( is_string($params[0]) ) {
                $this->addListener( $event, [$subscriber, $params[0]], $params[1] ?? 0 );

            } else {
                foreach ($params as $listener) {
                    $this->addListener( $event, [$subscriber, $listener[0]], $listener[1] ?? 0 );
                }
            }
        }
    }


    /**
     * Removes an event subscriber.
     * 
     * @param EventSubscriberInterface  $subscriber
     */
    public function removeSubscriber( EventSubscriberInterface $subscriber )
    {
        foreach ($subscriber->getSubscribedEvents() as $event => $params) {
            if ( is_array($params) && is_array($params[0]) ) {
                foreach ($params as $listener) {
                    $this->removeListener($event, [$subscriber, $listener[0]] );
                }

            } else {
                $this->removeListener( $event, [$subscriber, is_string($params) ? $params : $params[0]] );
            }
        }
    }


    /**
     * Dispatch event 
     * 
     * @param Event  $event
     * @param string  $eventName
     */
    public function dispatch( Event $event, string $eventName = null )
    {
        $eventName = $eventName ?? get_class($event);

        $listeners = $this->getListeners( $eventName );

        if ( $listeners )
            $this->callListeners( $listeners, $eventName, $event );

        return $event;
    }


    /**
     * Gets the listeners of a specific event or all listeners sorted by descending priority.
     *
     * @param string  $event
     * @return array  The event listeners for the specified event, or all event listeners by event name
     */
    public function getListeners( string $event = null )
    {
        if (! is_null($event) ) {

            $wildcardEvent = $this->buildWildcardEvent($event);

            if ( empty($this->listeners[$event]) && empty($this->wildcardListeners[$wildcardEvent]) )
                return [];

            if (! isset($this->sorted[$event]) )
                $this->sortListeners($event);

            return $this->sorted[$event];
        }

        $listeners = array_replace($this->listeners ?? [], $this->wildcardListeners ?? []);

        foreach ($listeners as $event => $listener) {

            if (! isset($this->sorted[$event]) )
                $this->sortListeners($event);
        }

        return array_filter($this->sorted);
    }


    /**
     * Gets the listener priority for a specific event.
     *
     * Returns null if the event or the listener does not exist.
     *
     * @param string  $event
     * @param callable  $listener
     * @return int|null  event listener priority
     */
    public function getListenerPriority( string $event, $listener )
    {
        if ( empty($this->listeners[$event]) && empty($this->wildcardListeners[$event]) )
            return null;

        if ( is_array($listener) && isset($listener[0]) && $listener[0] instanceof \Closure && count($listener) <= 2 ) {
            $listener[0] = $listener[0]();
            $listener[1] = $listener[1] ?? '__invoke';
        }

        $wildcardEvent = $this->buildWildcardEvent($event);

        $collection = array_replace($this->listeners[$event] ?? [], $this->wildcardListeners[$wildcardEvent] ?? []);

        foreach ($collection as $priority => &$listeners) {

            foreach ($listeners as &$fn) {
                
                if ($fn !== $listener && is_array($fn) && isset($fn[0]) && $fn[0] instanceof \Closure && count($fn) <= 2) {
                    $fn[0] = $fn[0]();
                    $fn[1] = $fn[1] ?? '__invoke';
                }

                if ( $fn === $listener )
                    return $priority;
            }
        }

        return null;
    }


    /**
     * Checks whether an event has any registered listeners.
     *
     * @param string  $event
     * @return bool  true if the specified event has any listeners, false otherwise
     */
    public function hasListeners( string $event = null )
    {
        if (! is_null($event) )
            return (!empty($this->listeners[$event]) || !empty($this->wildcardListeners[$event]));

        $collection = array_replace($this->listeners ?? [], $this->wildcardListeners ?? []);

        foreach ($collection as $listener) {
            if ( !empty($listener) ) 
                return true;
        }

        return false;
    }


    /**
     * Triggers the listeners of an event.
     *
     * This method can be overridden to add functionality that is executed
     * for each listener.
     *
     * @param callable[]  $listeners The event listeners
     * @param string  $eventName The name of the event to dispatch
     * @param object  $event     The event object to pass to the event handlers/listeners
     */
    private function callListeners( iterable $listeners, string $eventName, object $event )
    {
        $stoppable = $event instanceof StoppableEventInterface;

        foreach ($listeners as $listener) {

            if ( $stoppable && $event->isPropagationStopped() )
                break;

            $listener($event, $eventName, $this);
        }
    }


    /**
     * Sorts the internal list of listeners for the given event by priority.
     * 
     * @param string  $event
     */
    private function sortListeners( string $event )
    {
        if (! empty($this->listeners[$event]) )
            krsort($this->listeners[$event]);

        $wildcardEvent = $this->buildWildcardEvent($event);

        if (! empty($this->wildcardListeners[$wildcardEvent]) )
            krsort($this->wildcardListeners[$wildcardEvent]);

        $this->sorted[$event] = [];

        $collection = array_replace($this->listeners[$event] ?? [], $this->wildcardListeners[$wildcardEvent] ?? []);

        foreach ($collection as &$listeners) {

            foreach ($listeners as $k => &$listener) {
                
                if ( is_array($listener) && isset($listener[0]) && $listener[0] instanceof \Closure && count($listener) <= 2 ) {
                    $listener[0] = $listener[0]();
                    $listener[1] = $listener[1] ?? '__invoke';
                }

                $this->sorted[$event][] = $listener;
            }
        }
    }


    /**
     * Remove listener for event
     *
     * @param string  $event
     * @param mixed  $listener
     */
    private function unregisterListener( string $event, $listener )
    {
        foreach ($this->listeners[$event] as $priority => &$listeners) {

            foreach ($listeners as $key => &$fn) {
                if ( $fn !== $listener && is_array($fn) && isset($fn[0]) && $fn[0] instanceof \Closure && count($fn) <= 2 ) {
                    $fn[0] = $fn[0]();
                    $fn[1] = $fn[1] ?? '__invoke';
                }

                if ( $fn === $listener )
                    unset($listeners[$key], $this->sorted[$event]);
            }

            if (! $listeners )
                unset($this->listeners[$event][$priority], $this->listeners[$event]);
        }
    }


    /**
     * Remove wildcard listener for event
     *
     * @param string  $event
     * @param mixed  $listener
     */
    private function unregisterWildcardListener( string $event, $listener )
    {
        foreach ($this->wildcardListeners[$event] as $priority => &$listeners) {

            foreach ($listeners as $key => &$fn) {
                if ( $fn !== $listener && is_array($fn) && isset($fn[0]) && $fn[0] instanceof \Closure && count($fn) <= 2 ) {
                    $fn[0] = $fn[0]();
                    $fn[1] = $fn[1] ?? '__invoke';
                }

                if ( $fn === $listener )
                    unset($listeners[$key], $this->sorted[$event]);
            }

            if (! $listeners )
                unset($this->wildcardListeners[$event][$priority]);
        }
    }


    /**
     * Build wildcard event
     * 
     * @param string  $event
     */
    private function buildWildcardEvent( string $event )
    {
        if ( strpos($event, '.') == false )
            return $event;

        if ( strpos($event, '.*') != false )
            return $event;

        return substr($event, 0, strpos($event, '.')) . '.*';
    }
}