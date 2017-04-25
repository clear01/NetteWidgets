<?php

namespace Clear01\Widgets\Nette\DI;

use Clear01\Widgets\IWidgetDeclarationFactory;
use Clear01\Widgets\Nette\IWidgetComponentFactory;
use Clear01\Widgets\WidgetDeclaration;
use Nette\DI\Container;

class ContainerWidgetDeclarationFactory implements IWidgetDeclarationFactory {

	/** @var string */
	protected $widgetTypeId;

	/** @var string */
	protected $serviceName;

	/** @var  bool */
	protected $unique;

	/** @var Container */
	protected $container;


	/**
	 * ContainerWidgetFactory constructor.
	 * @param string $widgetTypeId
	 * @param string $serviceName
	 * @param bool $unique
	 * @param Container $container
	 */
	public function __construct($widgetTypeId, $serviceName, $unique, Container $container)
	{
		$this->widgetTypeId = $widgetTypeId;
		$this->serviceName = $serviceName;
		$this->container = $container;
		$this->unique = $unique;
	}


	/** @return WidgetDeclaration|WidgetDeclaration[] */
	public function create()
	{
		return new WidgetDeclaration(
			$this->widgetTypeId, $this->unique, function() {
				$service = $this->container->getService($this->serviceName);
				if(!$service) {
					throw new \RuntimeException(sprintf('Service %s not found in DI container.', $this->serviceName));
				}
				if($service instanceof IWidgetComponentFactory) {
					return $service->create();
				} else {
					return $service;
				}
			}
		);
	}
}