<?php

namespace Clear01\Widgets\Nette\DI;

use Clear01\Widgets\IWidgetManager;
use Nette\DI\Container;

/**
 * This class is useful when multiple widget namespaces are in use. Beware, it's based on service-locator-like pattern,
 * but it may be useful in some cases.
 */
class WidgetManagerAccessor
{

	/** @var string[] Associative array of registered managers. Format: [namespace => widgetManagerServiceName] */
	protected $serviceMap;

	/** @var  Container */
	protected $container;

	/**
	 * WidgetManagerAccessor constructor.
	 * @param \string[] $serviceMap Associative array of registered managers. Format: [namespace => widgetManagerServiceName]
	 * @param Container $container
	 */
	public function __construct(array $serviceMap, Container $container)
	{
		$this->serviceMap = $serviceMap;
		$this->container = $container;
	}

	/**
	 * @param $namespace string Namespace that should be the manager retrieved for.
	 * @return IWidgetManager
	 */
	public function get($namespace) {
		if(!isset($this->serviceMap[$namespace])) {
			throw new \InvalidArgumentException(sprintf('Namespace "%s" was not recognized.', $namespace));
		}
		return $this->container->getService($this->serviceMap[$namespace]);
	}

}