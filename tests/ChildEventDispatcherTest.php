<?php

namespace Tests;

use dbizapps\EventDispatcher\EventDispatcher;

class ChildEventDispatcherTest extends EventDispatcherTest
{
    protected function createEventDispatcher()
    {
        return new ChildEventDispatcher();
    }
}

class ChildEventDispatcher extends EventDispatcher
{
}