<?php

/*
 * This file is part of the EventDispatcher package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dbizapps\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface;

class Event implements StoppableEventInterface, \ArrayAccess, \IteratorAggregate
{
    /**
     * @var bool
     */
    private $propagationStopped = false;

    /**
     * @var object|callable
     */
    private $subject;

    /**
     * @var array
     */
    private $arguments;


    /**
     * Class constructor
     *
     * @param mixed $subject   The subject of the event, usually an object or a callable
     * @param array $arguments Arguments to store in the event
     */
    public function __construct( $subject = null, array $arguments = [] )
    {
        $this->subject = $subject;

        $this->arguments = $arguments;
    }


    /**
     * Evaluates if progagation has been stopped
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }


    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     */
    public function stopPropagation()
    {
        $this->propagationStopped = true;
    }


    /**
     * Getter for subject property.
     *
     * @return mixed The observer subject
     */
    public function getSubject()
    {
        return $this->subject;
    }


    /**
     * Set argument property.
     *
     * @return $this
     */
    public function setArguments( array $arguments = [] )
    {
        $this->arguments = $arguments;

        return $this;
    }
    

    /**
     * Getter for all arguments.
     *
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }


    /**
     * Add argument to event.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setArgument(string $key, $value)
    {
        $this->arguments[$key] = $value;

        return $this;
    }


    /**
     * Get argument by key.
     *
     * @param string $key
     * @return mixed contents of array key
     * @throws InvalidArgumentException if key is not found
     */
    public function getArgument( string $key )
    {
        if ( $this->hasArgument($key) )
            return $this->arguments[$key];

        throw new \InvalidArgumentException('Argument not found: ' . $key);
    }


    /**
     * Has argument.
     *
     * @param string $key
     * @return bool
     */
    public function hasArgument( string $key )
    {
        return array_key_exists($key, $this->arguments);
    }


    /**
     * ArrayAccess for argument getter.
     *
     * @param string $key Array key
     * @return mixed
     * @throws \InvalidArgumentException if key does not exist in $this->args
     */
    public function offsetGet($key)
    {
        return $this->getArgument($key);
    }


    /**
     * ArrayAccess for argument setter.
     *
     * @param string $key   Array key to set
     * @param mixed  $value Value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->setArgument($key, $value);
    }

    /**
     * ArrayAccess for unset argument.
     *
     * @param string $key Array key
     * @return void
     */
    public function offsetUnset($key)
    {
        if ($this->hasArgument($key)) {
            unset($this->arguments[$key]);
        }
    }


    /**
     * ArrayAccess has argument.
     *
     * @param string $key Array key
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->hasArgument($key);
    }


    /**
     * IteratorAggregate for iterating over the object like an array.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->arguments);
    }

}