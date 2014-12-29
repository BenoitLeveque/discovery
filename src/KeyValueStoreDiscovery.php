<?php

/*
 * This file is part of the puli/discovery package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Discovery;

use Assert\Assertion;
use InvalidArgumentException;
use Puli\Discovery\Api\BindingType;
use Puli\Discovery\Api\NoSuchTypeException;
use Puli\Discovery\Api\ResourceBinding;
use Puli\Discovery\Binding\LazyBinding;
use Puli\Repository\Api\ResourceRepository;
use RuntimeException;
use Webmozart\KeyValueStore\Api\KeyValueStore;

/**
 * A resource discovery that stores the bindings in a key-value store.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class KeyValueStoreDiscovery extends AbstractEditableDiscovery
{
    /**
     * @var KeyValueStore
     */
    private $store;

    /**
     * @var \Puli\Discovery\Api\ResourceBinding[]
     */
    private $bindings = array();

    /**
     * @var \Puli\Discovery\Api\BindingType[]
     */
    private $types;

    /**
     * Creates a new resource discovery.
     *
     * @param ResourceRepository $repo  The repository to fetch resources from.
     * @param KeyValueStore      $store The key-value store used to store the
     *                                  bindings and the binding types.
     */
    public function __construct(ResourceRepository $repo, KeyValueStore $store)
    {
        parent::__construct($repo);

        $this->store = $store;
        $this->types = array_flip($store->get('//types', array()));
        $this->queryIndex = $store->get('//queryIndex', array());
        $this->typeIndex = $store->get('//typeIndex', array());
    }

    /**
     * {@inheritdoc}
     */
    public function define($type)
    {
        if (is_string($type)) {
            $type = new BindingType($type);
        }

        if (!$type instanceof BindingType) {
            throw new InvalidArgumentException(sprintf(
                'Expected argument of type string or BindingType. Got: %s',
                is_object($type) ? get_class($type) : gettype($type)
            ));
        }

        $this->types[$type->getName()] = $type;

        $this->store->set('//types', array_keys($this->types));
        $this->store->set($type->getName(), $type);
    }

    /**
     * {@inheritdoc}
     */
    public function undefine($typeName)
    {
        Assertion::string($typeName);

        unset($this->types[$typeName]);

        $this->store->set('//types', array_keys($this->types));
        $this->store->remove($typeName);
    }

    /**
     * {@inheritdoc}
     */
    public function getType($typeName)
    {
        if (!isset($this->types[$typeName]) || !$this->types[$typeName] instanceof BindingType) {
            $this->loadType($typeName);
        }

        return $this->types[$typeName];
    }

    /**
     * {@inheritdoc}
     */
    public function isDefined($typeName)
    {
        return array_key_exists($typeName, $this->types);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes()
    {
        foreach ($this->types as $typeName => $type) {
            if (is_int($type)) {
                $this->loadType($typeName);
            }
        }

        return $this->types;
    }

    /**
     * {@inheritdoc}
     */
    protected function getBinding($id)
    {
        if (!isset($this->bindings[$id])) {
            $this->loadBinding($id);
        }

        return $this->bindings[$id];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllBindings()
    {
        $nextId = $this->store->get('//nextId');

        for ($id = 1; $id < $nextId; ++$id) {
            if ($this->store->has($id)) {
                $this->loadBinding($id);
            }
        }

        return array_values($this->bindings);
    }

    /**
     * {@inheritdoc}
     */
    protected function insertBinding(ResourceBinding $binding)
    {
        $id = $this->store->get('//nextId', 1);

        $this->bindings[$id] = $binding;

        $this->updateIndicesForId($id, $binding);

        $this->store->set($id, array(
            $binding->getQuery(),
            $binding->getLanguage(),
            $binding->getType()->getName(),
            $binding->getParameters()
        ));

        $this->store->set('//nextId', $id + 1);
        $this->store->set('//queryIndex', $this->queryIndex);
        $this->store->set('//typeIndex', $this->typeIndex);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeBinding($id)
    {
        unset($this->bindings[$id]);

        $this->store->remove($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeBindingsByQuery($query, $parameters = null)
    {
        parent::removeBindingsByQuery($query, $parameters);

        $this->store->set('//typeIndex', $this->typeIndex);
        $this->store->set('//queryIndex', $this->queryIndex);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeBindingsByQueryAndType($query, $typeName, $parameters = null)
    {
        parent::removeBindingsByQueryAndType($query, $typeName, $parameters);

        $this->store->set('//typeIndex', $this->typeIndex);
        $this->store->set('//queryIndex', $this->queryIndex);
    }

    private function loadBinding($id)
    {
        if (!($data = $this->store->get($id))) {
            throw new RuntimeException(sprintf(
                'Could not fetch data for binding with ID %s.',
                $id
            ));
        }

        $this->bindings[$id] = new LazyBinding(
            $data[0], // query
            $data[1], // language
            $this->repo,
            $this->getType($data[2]), // type name
            $data[3] // parameters
        );
    }

    private function loadType($typeName)
    {
        if (!($type = $this->store->get($typeName))) {
            throw new NoSuchTypeException(sprintf(
                'The binding type "%s" has not been defined.',
                $typeName
            ));
        }

        $this->types[$typeName] = $type;
    }
}
