clear01/nette-widgets documentation
==================================

<a name="Caution"></a>!!! CAUTION - READ BEFORE PRODUCTION USE !!!
----------------------------------------------
Before start using this extension in production, please pay attention to these few words. Otherwise, you can come across some **serious, high-WTF-factor errors**.

- **Never change the widget type ids**. Class name / namespace **refactoring** of widget components will result in such behaviour when [Direct component definition](#DirectComponentDefinition) or [Component Factory](#ComponentFactory) approach is used without explicit type id definition.

- **Never remove** available widget types. Rather than that, replace the widget content with a message saying that the widget was abandoned.

- If you can't meet these rules, it's necessary to **reflect all changes in the storage used by persistence layer**. For example, when you decide
to change some widget type's ID, manual database update of user widget table content is absolutely necessary. Otherwise, rendering user dashboard will raise an error.

TOC
---
- [Adding available widgets](#AddingWidgets)
- [Rich widgets](#MultipleDashboards)
- [Multiple dashboards](#MultipleDashboards)
- [Extras](#Extras)



<a name="AddingWidgets"></a>Adding available widgets
------------------------

The first great message is that **any component** can act like a widget.The only requirement is that the component **_must_ render only single root HTML element** (use some sort of wrapping component to achieve this requirement with more complex components).

Widgets are managed by WidgetManager (how surprising!), which keeps track of all registered
widget types.

Your widgets can be registered to WidgetManager using following ways:

1. Direct component definition

2. Component factory

3. Widget declaration factory

Let's see the example config below:
```yml
widgets:
    available:
        admin:

            # case 1.1
            - \App\Widgets\Clock
 
            # case 1.2
            anotherClock: \App\Widgets\Clock('blue')
 
            # case 1.3
            timezoneClock:
                class: \App\Widgets\TimeZoneClock('Europe/Prague')
                unique: false
 
            # case 2.1
            - \App\Widgets\IUpcomingEventsComponentFactory
 
            # case 3.1
            - \Clear01\Widgets\Nette\TemplateWidgetDeclarationFactory(%appDir%/WidgetTemplates/Greeting.latte)
 
            # case 3.2
            - \App\Widgets\EmergencyButtonsFactory

services:

    # case 3.3
    -
        class: \App\Widgets\ElevatorButtonsFactory
        tags: [clear01.widgetFactory]
```

#### <a name="DirectComponentDefinition"></a>Direct component definition (cases 1.*)
This is the simplest way to add your widgets.
Just specify the widget component as you would do in the 'service' section.

You can also specify the widget type ID (case 1.2). If no ID is specified (case 1.1), it will be generated
automatically using **only the widget class name**. That's the reason why **widget type ids _must_ be specified when multiple widgets of same class are present**.
Otherwise, ```\RuntimeException``` saying that widget with that typeId was already registered using another factory.

In case of generic components, consider using the *Widget declaration factory* approach instead (see case 3.1).
For example, two clock components are ok to be registered this way, but it would be really boring to specify ids for all DummyTemplateComponent($templateFile) widgets (consider 20+), wouldn't be?

If you wish to let user to add multiple instances of the widget, it's necessary to use a slightly more verbose syntax (see case 1.3).
In this example, ```\App\Widgets\TimeZoneClock``` represents a [Rich widget](#RichWidgets), that can be configured to display time using particular time zone.
End user can add multiple instances of this widget, each for different time zone.

#### <a name="ComponentFactory"></a>Component factory (case 2.1)
Quite similar to [Direct component definition](#DirectComponentDefinition) approach. Complex instance creation process is usually maintained by some sort of factory classes. Let them implement ```\Clear01\Widgets\Nette\IRichWidgetComponent``` and use them as shown above.

#### <a name="WidgetDeclarationFactory"></a>Widget declaration factory (cases 3.*)
The most low-level widget registration method. See the main advantages:
- Widget type id generation responsibility. For example, ids can be composed using constructor arguments. (see ```\Clear01\Widgets\Nette\TemplateWidgetDeclarationFactory```)
- It's possible to create non-unique widgets. That means a widget can be added multiple times to single dashboard.
- Multiple widgets can be specified by one factory.

See the ```\Clear01\Widgets\Nette\TemplateWidgetDeclarationFactory``` class, which brings simple widget that renders a template.

Can be listed in extension parameters (case 3.1, 3.2) or it can be registered as service **with widget factory tag** (case 3.3). If only single dashboard is present,
use ```clear01.widgetFactory``` tag. If [Multiple dashboards](#MultipleDashboards) are present, dashboard must be specified: ```clear01.widgetFactory.DASHBOARD``` tag (eg. ```clear01.widgetFactory.front```)

Your widget declaration factories should return available widgets **independently of context**. For widget filtering (permission purposes, ..), use ```Clear01\Widgets\WidgetManager::addWidgetDeclarationFilterCallback```. (see the Clear01\Widgets package docs)

You should never reduce declarations returned by your factories, only new declarations should be added (see [caution](#Caution)). It's also **necessary to clear cache** after declarations are added to your factory classes.


<a name="RichWidgets"></a>Rich widgets
--------------------------------------
This feature is great for widgets that **needs to be configured**. Just implement the ```Clear01\Widgets\Nette\IRichWidgetComponent``` interface.
Now, your widget can render placeholder content to be DnDropped, different "settings" content for widget edit mode and "presentable" content for dashboard.
See the attached example of simple component that generates and persists random numbers (```docs/examples/rich-widget```).

<a name="MultipleDashboards"></a>Multiple dashboards
----------------------------------------------------
Want to have multiple dashboards available? No problem! See the example config below how to achieve this feature.

```yml
widgets:
    available:
        admin:
            - \App\Widgets\IUpcomingEventsComponentFactory
            - \Clear01\Widgets\Nette\TemplateWidgetDeclarationFactory(%appDir%/WidgetTemplates/Greeting.latte)
        front:
            - \App\Widgets\Clock
            - \App\Widgets\EmergencyButtonsFactory
```
... hmm, but the app suddenly stopped working! how is that possi... Wait! Note that for each dashboard, there is individual \Clear01\Widgets\IWidgetManager instance. When there is **only single** dashboard present, it's possible to use **autowiring** to resolve the ```\Clear01\Widgets\IWidgetManager``` dependency.
But in case of **multiple dashboards**, **autowiring cannot be used**, because DI container can't decide which widget manager should be injected. It's possible to specify the widget manager directly:

```yml
services:
    - \App\Admin\Presenters\ApplePresenter(@widgets.manager.admin)
    -
        class: \App\Front\Presenters\PearPresenter
        setup:
            - setWidgetManager(@widgets.manager.front)
```
... or, there is ```\Clear01\Widgets\Nette\DI\WidgetManagerAccessor``` class for special cases. It **can be autowired**, but it uses service locator approach, which is considered to be an **anti-pattern**. Use with care.
```php
class BananaPresenter {

    /** @var \Clear01\Widgets\Nette\DI\WidgetManagerAccessor */
    protected $widgetManager;

    public function injectWidgetManagerAccessor(\Clear01\Widgets\Nette\DI\WidgetManagerAccessor $widgetManagerAccessor) {
        $this->widgetManager = $widgetManagerAccessor->get('front');
    }

}
```

<a name="Extras"></a>Extras
---------------------------
#### Lazyness
Widget declarations are cached by default during the container compilation. The lazy service map provides performance boost. If you don't want to use cache, feel free to use following config directive:
```yml
widgets:
    optimize: false
```
Cache must be deleted when new declarations are returned by [widget declaration factories](#WidgetDeclarationFactory)

#### !!! This is only documentation of the *nette* widget implementation. See also the [```clear01/widgets-core```](http://github.com/Clear01/WidgetsCore) documentation.
