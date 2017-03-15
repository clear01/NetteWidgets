Clear01/NetteWidgets
====================

Briefing
--------
This package brings support of widgets to your Nette projects.
It's build upon the package [```clear01/widgets-core```](http://github.com/Clear01/WidgetsCore), the widgets model base.

It provides Nette specific implementation of abstract entities and introduces
an extension for easy widget registration.

This documentation describes only the Nette-related implementation, the common widget model
is described in [```clear01/widgets-core```](http://github.com/Clear01/WidgetsCore) package as long as it can be used within a different framework (Symfony,..) or as it is.

Requirements
------------

- PHP >= 5.5

- Nette >= 2.1

- clear01/widgets-core package

<a name="FurtherPackages"></a>Further packages
----------------
Because this package does not implement all the abstract entities of [```clear01/widgets-core```](http://github.com/Clear01/WidgetsCore),
further packages can be used to reach the out-of-the-box solution (or to get a bit of inspiration for your own implementation).

##### Persistence layer implementations
- [```clear01/doctrine-widget-adapter```](http://github.com/Clear01/DoctrineWidgetAdapter) Doctrine implementation of persistence layer

##### Dashboard management components
- [```clear01/bootstrap-nette-widget-control```](http://github.com/Clear01/BootstrapNetteWidgetControl) Dashboard component for Twitter Bootstrap and jQuery UI (draggable)

Example usage
-------------
config.neon
```yml
widgets:
    available:
        - \App\Widgets\IUpcomingEventsComponentFactory
        - \Clear01\Widgets\Nette\TemplateWidgetDeclarationFactory(%appDir%/WidgetTemplates/Greeting.latte)
        - \App\Widgets\Clock
        - \App\Widgets\EmergencyButtonsFactory
```

YourOwnSuperCoolDashboardManagementComponent.php
```phpX
class DashboardComponent {

    /** @var \Clear01\Widgets\IWidgetManager **/
    protected $widgetManager;

    ...
}
```

Installation
------------

!!! This package is not a complete out-of-box solution (see the section ["Further packages"](#FurtherPackages))

This package is available on packagist. Run the following command to add your dependency.

```sh
$ composer require clear01/nette-widgets
```

Documentation
-------------
Learn more in the [documentation](./docs/en/index.md).

