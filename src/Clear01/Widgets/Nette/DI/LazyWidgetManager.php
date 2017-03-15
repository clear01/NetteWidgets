<?php

namespace Clear01\Widgets\Nette\DI;

use Clear01\Widgets\IComponentStateSerializer;
use Clear01\Widgets\IUserIdentityAccessor;
use Clear01\Widgets\IWidgetDeclarationFactory;
use Clear01\Widgets\IWidgetPersister;
use Clear01\Widgets\WidgetDeclaration;
use Clear01\Widgets\WidgetManager;
use Nette\Caching\Cache;
use Nette\DI\Container;

class LazyWidgetManager extends WidgetManager {

	const CACHE_KEY_SERVICE_LIST_CHECKSUM = 'serviceListChecksum';
	const CACHE_KEY_MAP = 'map';

	/**
	 * @var string[] List of services from DI container to be scanned. Format: [serviceName1, serviceName2]
	 */
	protected $serviceList;

	/**
	 * @var Container
	 */
	protected $container;

	/**
	 * @var Cache
	 */
	protected $cache;

	/**
	 * @var string[] Map used for lazy loading services from DI container. Format: [widgetTypeId => serviceName]. It's cached
	 */
	protected $map;

	/** @var WidgetDeclaration[][] Loaded widget declarations */
	protected $loadedDeclarationsByFactoryServiceName = [];

	public function addWidgetDeclaration(WidgetDeclaration $declaration) {
		throw new \RuntimeException("Cannot call add methods on lazy manager. Use service map instead.");
	}

	public function addWidgetFactory(IWidgetDeclarationFactory $widgetFactory) {
		throw new \RuntimeException("Cannot call add methods on lazy manager. Use service map instead.");
	}

	public function __construct($serviceList, Cache $cache, Container $container, IUserIdentityAccessor $userIdentityAccessor, IWidgetPersister $widgetPersister, IComponentStateSerializer $componentStateSerializer)
	{
		parent::__construct($userIdentityAccessor, $widgetPersister, $componentStateSerializer);
		$this->container = $container;
		$this->cache = $cache;
		$this->serviceList = $serviceList;
	}

	protected function getServiceListChecksum() {
		return md5(serialize($this->serviceList));
	}

	protected function loadMapFromCache() {
		if($this->cache->load(self::CACHE_KEY_SERVICE_LIST_CHECKSUM) == $this->getServiceListChecksum()) {
			return $this->cache->load(self::CACHE_KEY_MAP);
		}
		return null;
	}

	protected function writeMapToCache($map) {
		$this->cache->save(self::CACHE_KEY_MAP, $map);
		$this->cache->save(self::CACHE_KEY_SERVICE_LIST_CHECKSUM, $this->getServiceListChecksum());
	}

	protected function invokeWidgetFactories()
	{
		if($cachedMap = $this->loadMapFromCache()) {
			$this->map = $cachedMap;
		} else {

			// load declarations
			$map = [];
			foreach ($this->serviceList as $serviceName) {
				$declarationFactoryInstance = $this->container->getService($serviceName);
				if (!($declarationFactoryInstance instanceof IWidgetDeclarationFactory)) {
					throw new \RuntimeException(sprintf('The service \'%s\' does not implement %s.', $serviceName, IWidgetDeclarationFactory::class));
				}
				$createdDeclarations = $declarationFactoryInstance->create();
				if (!is_array($createdDeclarations)) {
					$createdDeclarations = [$createdDeclarations];
				}
				foreach ($createdDeclarations as $createdDeclaration) {
					if ($map[$createdDeclaration->getWidgetTypeId()]) {
						throw new \RuntimeException("Widget factory " . $serviceName . " returned widget with typeId '" . $createdDeclaration->getWidgetTypeId() . "'. Widget with that typeId was already registered using the factory " . $map[$createdDeclaration->getWidgetTypeId()]);
					}
					$map[$createdDeclaration->getWidgetTypeId()] = $serviceName;
				}
			}

			$this->writeMapToCache($map);

			$this->map = $map;
		}
	}

	/**
	 * @param $widgetTypeId
	 * @return WidgetDeclaration
	 * @throws \InvalidArgumentException
	 */
	protected function getWidgetDeclarationByTypeId($widgetTypeId)
	{
		if(!isset($this->map[$widgetTypeId])) {
			throw new \InvalidArgumentException("Widget with typeId $widgetTypeId not found in lazy service map!");
		}
		$factoryServiceName = $this->map[$widgetTypeId];
		$this->loadFactory($factoryServiceName);
		if(!isset($this->loadedDeclarationsByFactoryServiceName[$factoryServiceName][$widgetTypeId])) {
			throw new \RuntimeException('Factory ' . $factoryServiceName . ' did not return the declaration of widget with type id ' . $widgetTypeId . '. Widget declarations returned by widget factories should not depend on any context.');
		}
		return $this->loadedDeclarationsByFactoryServiceName[$factoryServiceName][$widgetTypeId];
	}

	protected function loadFactory($factoryServiceName)
	{
		if(!isset($this->loadedDeclarationsByFactoryServiceName[$factoryServiceName])) {
			$this->loadedDeclarationsByFactoryServiceName[$factoryServiceName] = [];
			/** @var IWidgetDeclarationFactory $factoryService */
			$factoryService = $this->container->getService($factoryServiceName);
			$declarations = $factoryService->create();
			if(!is_array($declarations)) {
				$declarations = [$declarations];
			}
			foreach($declarations as $declaration) {
				$this->loadedDeclarationsByFactoryServiceName[$factoryServiceName][$declaration->getWidgetTypeId()] = $declaration;
			}
		}
		return $this->loadedDeclarationsByFactoryServiceName[$factoryServiceName];
	}

	public function getAvailableWidgets()
	{
		$this->lockDeclarations();

		$instances = [];

		$userWidgetTypeIds = $this->getUserWidgetTypeIds();

		foreach(array_unique(array_values($this->map)) as $factoryServiceName) {
			$this->loadFactory($factoryServiceName);
		}

		$allDeclarations = [];
		foreach($this->loadedDeclarationsByFactoryServiceName as $declarations) {
			foreach($declarations as $declaration) {
				$allDeclarations[] = $declaration;
			}
		}

		$allDeclarations = $this->filterDeclarations($allDeclarations);

		foreach($allDeclarations as $widgetDeclaration) {
			if($widgetDeclaration->isUnique() && in_array($widgetDeclaration->getWidgetTypeId(), $userWidgetTypeIds)) {
				// skip already used unique widget
				continue;
			}
			$instances[$widgetDeclaration->getWidgetTypeId()] = $widgetDeclaration->createInstance();
		}

		return $instances;
	}

}