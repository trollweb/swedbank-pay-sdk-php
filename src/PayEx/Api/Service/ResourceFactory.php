<?php

namespace PayEx\Api\Service;

use PayEx\Api\Service\Data\ResourceInterface;
use PayEx\Api\Service\Resource\Data\ResponseInterface;

use PayEx\Framework\DataObjectCollection as Collection;
use PayEx\Framework\Data\DataObjectCollectionInterface as CollectionInterface;

use PayEx\Framework\DataObjectCollectionItem as CollectionItem;
use PayEx\Framework\Data\DataObjectCollectionItemInterface as CollectionItemInterface;

use PayEx\Framework\DataObjectHelper;

use PayEx\Api\Service\Resource\Request as RequestResource;
use PayEx\Api\Service\Resource\Data\RequestInterface as RequestResourceInterface;

use PayEx\Api\Service\Resource\Response as ResponseResource;
use PayEx\Api\Service\Resource\Data\ResponseInterface as ResponseResourceInterface;

class ResourceFactory
{
    protected $helper;

    public function __construct()
    {
        $this->helper = new DataObjectHelper();

        return $this;
    }

    /**
     * @param string $service
     * @param string $resource
     * @param array|object|string $data
     * @param string $type
     * @return ResourceInterface|RequestResourceInterface|ResponseResourceInterface
     */
    public function create($service = '', $resource = '', $data = [], $type = 'request')
    {
        if ($type == 'response') {
            return $this->newResponseResource($service, $resource, $data);
        }

        if ($type == 'request') {
            return $this->newRequestResource($service, $resource, $data);
        }

        return $this->newServiceResource($service, $resource, $data);
    }

    /**
     * @param string $service
     * @param string $resource
     * @param array|object|string $data
     * @return ResourceInterface
     */
    public function newServiceResource($service = '', $resource = '', $data = [])
    {
        $resourceFqcn = $this->getResourceFqcn($resource, $service);

        /** @var ResourceInterface $resourceObj */
        if ($this->findFileByNamespace($resourceFqcn)) {
            $resourceObj = new $resourceFqcn($data);
        }

        if (!isset($resourceObj)) {
            $resourceObj = new Resource($data);
        }

        return $this->createSubResources($service, $resourceObj);
    }

    /**
     * @param string $service
     * @param string $resource
     * @param array|object|string $data
     * @return RequestResourceInterface
     */
    public function newRequestResource($service = '', $resource = '', $data = [])
    {
        $resourceFqcn = $this->getResourceFqcn($resource, $service, 'request');

        /** @var RequestResourceInterface $resourceObj */
        if ($this->findFileByNamespace($resourceFqcn)) {
            $resourceObj = new $resourceFqcn($data);
        }

        if (!isset($resourceObj)) {
            $resourceObj = new RequestResource($data);
        }

        return $this->createSubResources($service, $resourceObj);
    }

    /**
     * @param string $service
     * @param string $resource
     * @param array|object|string $data
     * @return ResponseResourceInterface
     */
    public function newResponseResource($service = '', $resource = '', $data = [])
    {
        $resourceFqcn = $this->getResourceFqcn($resource, $service, 'response');

        /** @var ResponseResourceInterface $resourceObj */
        if ($this->findFileByNamespace($resourceFqcn)) {
            $resourceObj = new $resourceFqcn($data);
        }

        if (!isset($resourceObj)) {
            $resourceObj = new ResponseResource($data);
        }

        return $this->createSubResources($service, $resourceObj);
    }

    /**
     * @param string $service
     * @param string $resource
     * @param array $items
     * @return CollectionInterface
     */
    public function newCollectionResource($service = '', $resource = '', $items = [])
    {
        $resourceFqcn = $this->getResourceFqcn($resource, $service, 'collection');

        foreach ((array)$items as $key => $item) {
            $items[$key] = $this->newCollectionResourceItem($service, $resource, $item);
        }

        /** @var ResponseInterface $resourceObj */
        if ($this->findFileByNamespace($resourceFqcn)) {
            $resourceObj = new $resourceFqcn($items);
        }

        if (!isset($resourceObj)) {
            $resourceObj = new Collection($items);
        }

        return $resourceObj;
    }

    /**
     * @param string $service
     * @param string $resource
     * @param array $item
     * @return CollectionItemInterface
     */
    public function newCollectionResourceItem($service = '', $resource = '', $item = [])
    {
        $resourceFqcn = $this->getResourceFqcn($resource, $service, 'item');

        /** @var ResponseInterface $resourceObj */
        if ($this->findFileByNamespace($resourceFqcn)) {
            $resourceObj = new $resourceFqcn($item);
        }

        if (!isset($resourceObj)) {
            $resourceObj = new CollectionItem($item);
        }

        return $this->createSubResources($service, $resourceObj);
    }

    /**
     * @param $service
     * @param ResourceInterface|RequestResourceInterface|ResponseResourceInterface|CollectionItemInterface $resourceObj
     * @return ResourceInterface|RequestResourceInterface|ResponseResourceInterface|CollectionItemInterface
     */
    private function createSubResources($service, $resourceObj)
    {
        foreach ($resourceObj->__toArray() as $key => $value) {
            if (is_array($value)) {
                if ($this->helper->isAssocArray($value)) {
                    $subResourceObj = $this->newServiceResource($service, $key, $value);
                    if ($subResourceObj) {
                        $resourceObj->offsetSet($key, $subResourceObj);
                        continue;
                    }
                }
                $subResourceObj = $this->newCollectionResource($service, $key, $value);
                $resourceObj->offsetSet($key, $subResourceObj);
                continue;
            }
        }

        return $resourceObj;
    }

    /**
     * @param $service
     * @param $resource
     * @param string $type
     * @return false|string
     */
    private function getResourceFqcn($resource, $service = '', $type = '')
    {
        if ($resource == '') {
            return false;
        }

        if ($type == 'item') {
            $collResourceFqcn = $this->getResourceFqcn($resource, $service, 'collection');
            if ($collResourceFqcn) {
                $resourceFqcn = constant("{$collResourceFqcn}::" . strtoupper($resource) . "_ITEM_FQCN");
                if ($resourceFqcn != '' && $this->findFileByNamespace($resourceFqcn)) {
                    return '\\' . preg_replace("/[\\\\]+/", '\\', $resourceFqcn);
                }
            }
        }

        $resourceNamespaces = $this->getResourceNsLookups($resource, $service, $type);

        foreach ($resourceNamespaces as $namespace) {
            if ($this->findFileByNamespace($namespace)) {
                return '\\' . preg_replace("/[\\\\]+/", '\\', $namespace);
            }
        }

        return false;
    }

    private function getResourceNsLookups($resource, $service = '', $type = '')
    {
        $resource = $this->camelCaseStr($resource);
        $service = ($service) ? $this->camelCaseStr($service) . '\\' : '';
        $type = ($type) ? $this->camelCaseStr($type) . '\\' : '';

        $resourceNsLookUps = [];

        switch ($type) {
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'Collection\\':
                $resourceNsLookUps[] = __NAMESPACE__ . "\\{$service}Resource\\Collection\\{$resource}Collection";
                $resourceNsLookUps[] = __NAMESPACE__ . "\\Resource\\Collection\\{$resource}Collection";
                break;
            default:
                if ($type) {
                    $resourceNsLookUps[] = __NAMESPACE__ . "\\{$service}Resource\\{$type}{$resource}";
                    $resourceNsLookUps[] = __NAMESPACE__ . "\\Resource\\{$type}{$resource}";
                }
                $resourceNsLookUps[] = __NAMESPACE__ . "\\{$service}Resource\\{$resource}";
                $resourceNsLookUps[] = __NAMESPACE__ . "\\Resource\\{$resource}";
                break;
        }

        return $resourceNsLookUps;
    }

    private function findFileByNamespace($resourceFqcn)
    {
        $basePath = str_replace(str_replace('\\', '/', __NAMESPACE__), '', __DIR__);
        return file_exists($basePath . str_replace('\\', '/', $resourceFqcn) . '.php');
    }

    /**
     * @param string $string
     * @return string
     */
    private function camelCaseStr($string = '')
    {
        return implode('', array_map('ucfirst', preg_split('/[^a-z0-9]+/i', $this->unCamelCaseStr($string))));
    }

    /**
     * @param string $string
     * @return null|string
     */
    private function unCamelCaseStr($string = '')
    {
        $splitParts = preg_split('/([A-Z])/', $string, false, PREG_SPLIT_DELIM_CAPTURE);

        if (empty($splitParts)) {
            return $string;
        }

        $formattedParts = array();
        array_shift($splitParts);
        foreach ($splitParts as $i => $splitPart) {
            if ($i % 2) {
                $formattedParts[] = strtolower($splitParts[$i - 1] . $splitPart);
            }
        }

        if (empty($formattedParts)) {
            return $string;
        }

        return preg_replace('/[_]+/', '_', implode('_', $formattedParts));
    }
}
