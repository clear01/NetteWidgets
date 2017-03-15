<?php

namespace Clear01\Widgets\Nette;
use Clear01\Widgets\IWidgetComponent;

/**
 * This interface can be used for rich widgets. Common components can be used as widgets as well,
 * but by implementing this interface, all advanced widget features can be used.
 */
interface IRichWidgetComponent extends IWidgetComponent
{
	
	public function render();

	public function renderPlaceholder();

	public function renderEditable();

}