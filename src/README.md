# Event Dispatcher

The Event Dispatcher allows to dispatch event classes for application component communication.
This component is a slightly extended version of Symfony EventDispatcher by allowing event wildcards 


## Installation
composer require dbisapps/event-dispatcher


## Documentation
Please refer to the Symfony EventDispatcher documentation
https://symfony.com/doc/current/components/event_dispatcher.html



## Usage
As extension to the Symfony EventDispatcher you can add listeners based on wildcard events:

	use Symfony\Component\EventDispatcher\EventDispatcher;
	use Symfony\Contracts\EventDispatcher\Event;

	$dispatcher = new EventDispatcher();

	$dispatcher->addListener('acme.foo.*', function (Event $event) {
	    // will be executed when the acme.foo.action event is dispatched
	});

The above listener will listen to any events matching the pattern acme.foo.* like
- acme.foo.action
- acme.foo.dosomething
- acme.foo.whatever

