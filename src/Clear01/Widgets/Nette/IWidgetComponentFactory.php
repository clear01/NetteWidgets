<?php

namespace Clear01\Widgets\Nette;

use Nette\Application\UI\Control;

interface IWidgetComponentFactory
{
	/** @return Control */
	public function create();
}
?>