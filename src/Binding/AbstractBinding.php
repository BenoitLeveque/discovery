<?php

/*
 * This file is part of the puli/discovery package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Discovery\Binding;

use Puli\Discovery\Api\BindingType;
use Puli\Discovery\Api\MissingParameterException;
use Puli\Discovery\Api\NoSuchParameterException;
use Puli\Discovery\Api\ResourceBinding;

/**
 * Base class for resource bindings.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractBinding implements ResourceBinding
{
    /**
     * @var string
     */
    private $query;

    /**
     * @var string
     */
    private $language;

    /**
     * @var BindingType
     */
    private $type;

    /**
     * @var array
     */
    private $parameters = array();

    /**
     * Creates a new binding.
     *
     * A binding has a query that is used to retrieve the resources matched
     * by the binding.
     *
     * You can pass parameters that have been defined for the type. If you pass
     * unknown parameters, or if a required parameter is missing, an exception
     * is thrown.
     *
     * All parameters that you do not set here will receive the default values
     * set for the parameter.
     *
     * @param string      $query      The resource query.
     * @param BindingType $type       The type to bind against.
     * @param array       $parameters Additional parameters.
     * @param string      $language   The language of the resource query.
     *
     * @throws NoSuchParameterException If an invalid parameter was passed.
     * @throws MissingParameterException If a required parameter was not passed.
     */
    public function __construct($query, BindingType $type, array $parameters = array(), $language = 'glob')
    {
        $this->validateParameters($type, $parameters);

        $this->query = $query;
        $this->language = $language;
        $this->type = $type;
        $this->parameters = $this->normalizeParameters($type, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameter($parameter)
    {
        if (!array_key_exists($parameter, $this->parameters)) {
            throw new NoSuchParameterException(sprintf(
                'The parameter "%s" does not exist on type "%s".',
                $parameter,
                $this->type->getName()
            ));
        }

        return $this->parameters[$parameter];
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameter($parameter)
    {
        return array_key_exists($parameter, $this->parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function equals(ResourceBinding $binding)
    {
        if (get_class($binding) !== get_class($this)) {
            return false;
        }

        if ($this->query !== $binding->getQuery()) {
            return false;
        }

        if ($this->type !== $binding->getType()) {
            return false;
        }

        if ($this->language !== $binding->getLanguage()) {
            return false;
        }

        // The local parameters are sorted by key
        $comparedParameters = $binding->getParameters();
        ksort($comparedParameters);

        if ($this->parameters !== $comparedParameters) {
            return false;
        }

        return true;
    }

    private function validateParameters(BindingType $type, array $parameters)
    {
        foreach ($parameters as $name => $value) {
            if (!$type->hasParameter($name)) {
                throw new NoSuchParameterException(sprintf(
                    'The parameter "%s" does not exist on type "%s".',
                    $name,
                    $type->getName()
                ));
            }
        }

        foreach ($type->getParameters() as $parameter) {
            if (!isset($parameters[$parameter->getName()])) {
                if ($parameter->isRequired()) {
                    throw new MissingParameterException(sprintf(
                        'The required binding parameter "%s" is missing.',
                        $parameter->getName()
                    ));
                }
            }
        }
    }

    private function normalizeParameters(BindingType $type, array $parameters)
    {
        foreach ($type->getParameters() as $parameter) {
            $parameterName = $parameter->getName();

            if (!isset($parameters[$parameterName])) {
                $parameters[$parameterName] = $parameter->getDefaultValue();
            }
        }

        ksort($parameters);

        return $parameters;
    }
}
