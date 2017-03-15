<?php

namespace Clear01\Widgets\Nette;

use Clear01\Widgets\IComponentStateSerializer;
use Clear01\Widgets\IWidgetComponent;

class NetteComponentStateSerializer implements IComponentStateSerializer
{

	/**
	 * This method saves widget's state as string.
	 * @param $widget \Nette\Application\UI\Control|IWidgetComponent
	 * @return string serialized widget state
	 */
	public function serializeWidgetState($widget)
	{
		$this->validateComponent($widget);

		$state = [];
		$widget->saveState($state);

		$serialized = serialize($state);
		if($serialized === false) {
			throw new \InvalidArgumentException('State of the widget could not be serialized. Please, check the state value.');
		}
		return $serialized;
	}

	/**
	 * This method restores widget state form serialized data.
	 * @param $widget \Nette\Application\UI\Control|IWidgetComponent
	 * @param $state string
	 * @return void
	 */
	public function restoreSerializedWidgetState($widget, $state)
	{
		$this->validateComponent($widget);
		$state = unserialize($state);
		if($state === false) {
			throw new \InvalidArgumentException('State of the widget could not be deserialized. Please, check the serialized value.');
		}
		$widget->loadState($state);
	}

	protected function validateComponent($component) {
		if(!is_a($component, \Nette\Application\UI\Control::class)) {
			throw new \InvalidArgumentException('Given widget is not of type ' . \Nette\Application\UI\Control::class);
		}
	}
}