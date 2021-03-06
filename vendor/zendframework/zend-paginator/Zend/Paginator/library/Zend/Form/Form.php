<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Form
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\Form;

use IteratorAggregate;
use Traversable;
use Zend\Form\Element\Collection;
use Zend\Form\Exception;
use Zend\InputFilter\InputFilter;
use Zend\InputFilter\InputFilterAwareInterface;
use Zend\InputFilter\InputFilterInterface;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\InputFilter\InputProviderInterface;
use Zend\Stdlib\ArrayUtils;
use Zend\Stdlib\Hydrator;

/**
 * @category   Zend
 * @package    Zend_Form
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Form extends Fieldset implements FormInterface
{
    /**
     * Seed attributes
     *
     * @var array
     */
    protected $attributes = array(
        'method' => 'POST',
    );

    /**
     * How to bind values to the attached object
     *
     * @var int
     */
    protected $bindAs = FormInterface::VALUES_NORMALIZED;

    /**
     * Whether or not to bind values to the bound object on successful validation
     *
     * @var int
     */
    protected $bindOnValidate = FormInterface::BIND_ON_VALIDATE;

    /**
     * Base fieldset to use for hydrating (if none specified, directly hydrate elements)
     *
     * @var FieldsetInterface
     */
    protected $baseFieldset;

    /**
     * Data being validated
     *
     * @var null|array|Traversable
     */
    protected $data;

    /**
     * @var null|InputFilterInterface
     */
    protected $filter;

    /**
     * Whether or not to automatically scan for input filter defaults on
     * attached fieldsets and elements
     *
     * @var bool
     */
    protected $useInputFilterDefaults = true;

    /**
     * Whether or not validation has occurred
     *
     * @var bool
     */
    protected $hasValidated = false;

    /**
     * Result of last validation operation
     *
     * @var bool
     */
    protected $isValid = false;

    /**
     * Is the form prepared ?
     *
     * @var bool
     */
    protected $isPrepared = false;

    /**
     * Validation group, if any
     *
     * @var null|array
     */
    protected $validationGroup;

    /**
     * Add an element or fieldset
     *
     * If $elementOrFieldset is an array or Traversable, passes the argument on
     * to the composed factory to create the object before attaching it.
     *
     * $flags could contain metadata such as the alias under which to register
     * the element or fieldset, order in which to prioritize it, etc.
     *
     * @param  array|Traversable|ElementInterface $elementOrFieldset
     * @param  array                              $flags
     * @return \Zend\Form\Fieldset|\Zend\Form\FieldsetInterface|\Zend\Form\FormInterface
     */
    public function add($elementOrFieldset, array $flags = array())
    {
        // TODO: find a better solution than duplicating the factory code, the problem being that if $elementOrFieldset is an array,
        // it is passed by value, and we don't get back the concrete ElementInterface
        if (is_array($elementOrFieldset)
            || ($elementOrFieldset instanceof Traversable && !$elementOrFieldset instanceof ElementInterface)
        ) {
            $factory = $this->getFormFactory();
            $elementOrFieldset = $factory->create($elementOrFieldset);
        }

        parent::add($elementOrFieldset, $flags);

        if ($elementOrFieldset instanceof Fieldset && $elementOrFieldset->useAsBaseFieldset()) {
            $this->baseFieldset = $elementOrFieldset;
        }

        return $this;
    }

    /**
     * Ensures state is ready for use
     *
     * Marshalls the input filter, to ensure validation error messages are
     * available, and prepares any elements and/or fieldsets that require
     * preparation.
     *
     * @return void
     */
    public function prepare()
    {
        if (!$this->isPrepared) {
            $this->getInputFilter();

            foreach ($this->getIterator() as $elementOrFieldset) {
                if ($elementOrFieldset instanceof ElementPrepareAwareInterface) {
                    $elementOrFieldset->prepareElement($this);
                }
            }

            $this->isPrepared = true;
        }
    }

    /**
     * Set data to validate and/or populate elements
     *
     * Typically, also passes data on to the composed input filter.
     *
     * @param  array|\ArrayAccess|\Traversable $data
     * @return Form|FormInterface
     * @throws Exception\InvalidArgumentException
     */
    public function setData($data)
    {
        if ($data instanceof Traversable) {
            $data = ArrayUtils::iteratorToArray($data);
        }
        if (!is_array($data)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable argument; received "%s"',
                __METHOD__,
                (is_object($data) ? get_class($data) : gettype($data))
            ));
        }

        $this->hasValidated = false;
        $this->data         = $data;
        $this->populateValues($data);

        return $this;
    }

    /**
     * Bind an object to the form
     *
     * Ensures the object is populated with validated values.
     *
     * @param  object $object
     * @param  int $flags
     * @return mixed|void
     * @throws Exception\InvalidArgumentException
     */
    public function bind($object, $flags = FormInterface::VALUES_NORMALIZED)
    {
        if (!in_array($flags, array(FormInterface::VALUES_NORMALIZED, FormInterface::VALUES_RAW))) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects the $flags argument to be one of "%s" or "%s"; received "%s"',
                __METHOD__,
                'Zend\Form\FormInterface::VALUES_NORMALIZED',
                'Zend\Form\FormInterface::VALUES_RAW',
                $flags
            ));
        }

        if ($this->baseFieldset !== null) {
            $this->baseFieldset->setObject($object);
        }

        $this->bindAs = $flags;
        $this->setObject($object);
        $this->extract();
    }

    /**
     * Bind values to the bound object
     *
     * @param array $values
     * @return mixed
     */
    public function bindValues(array $values = array())
    {
        if (!is_object($this->object)) {
            return;
        }
        if (!$this->isValid) {
            return;
        }

        $filter = $this->getInputFilter();

        switch ($this->bindAs) {
            case FormInterface::VALUES_RAW:
                $data = $filter->getRawValues();
                break;
            case FormInterface::VALUES_NORMALIZED:
            default:
                $data = $filter->getValues();
                break;
        }

        $this->object = parent::bindValues($data);
    }

    /**
     * Set flag indicating whether or not to bind values on successful validation
     *
     * @param  int $bindOnValidateFlag
     * @return void|Form
     * @throws Exception\InvalidArgumentException
     */
    public function setBindOnValidate($bindOnValidateFlag)
    {
        if (!in_array($bindOnValidateFlag, array(self::BIND_ON_VALIDATE, self::BIND_MANUAL))) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects the flag to be one of %s::%s or %s::%s',
                __METHOD__,
                get_called_class(),
                'BIND_ON_VALIDATE',
                get_called_class(),
                'BIND_MANUAL'
            ));
        }
        $this->bindOnValidate = $bindOnValidateFlag;
        return $this;
    }

    /**
     * Will we bind values to the bound object on successful validation?
     *
     * @return bool
     */
    public function bindOnValidate()
    {
        return (self::BIND_ON_VALIDATE === $this->bindOnValidate);
    }

    /**
     * Set the base fieldset to use when hydrating
     *
     * @param  FieldsetInterface $baseFieldset
     * @return Form
     * @throws Exception\InvalidArgumentException
     */
    public function setBaseFieldset(FieldsetInterface $baseFieldset)
    {
        $this->baseFieldset = $baseFieldset;
        return $this;
    }

    /**
     * Get the base fieldset to use when hydrating
     *
     * @return FieldsetInterface
     */
    public function getBaseFieldset()
    {
        return $this->baseFieldset;
    }

    /**
     * Validate the form
     *
     * Typically, will proxy to the composed input filter.
     *
     * @return bool
     * @throws Exception\DomainException
     */
    public function isValid()
    {
        $this->isValid = false;

        if (!is_array($this->data) && !is_object($this->object)) {
            throw new Exception\DomainException(sprintf(
                '%s is unable to validate as there is no data currently set',
                __METHOD__
            ));
        }

        if (!is_array($this->data)) {
            $hydrator = $this->getHydrator();
            if (!$hydrator instanceof Hydrator\HydratorInterface) {
                throw new Exception\DomainException(sprintf(
                    '%s is unable to validate as there is no data currently set',
                    __METHOD__
                ));
            }
            $data = $hydrator->extract($this->object);
            if (!is_array($data)) {
                throw new Exception\DomainException(sprintf(
                    '%s is unable to validate as there is no data currently set',
                    __METHOD__
                ));
            }
            $this->data = $data;
        }

        $filter = $this->getInputFilter();
        if (!$filter instanceof InputFilterInterface) {
            throw new Exception\DomainException(sprintf(
                '%s is unable to validate as there is no input filter present',
                __METHOD__
            ));
        }

        $filter->setData($this->data);
        $filter->setValidationGroup(InputFilterInterface::VALIDATE_ALL);

        if ($this->validationGroup !== null) {
            $this->prepareValidationGroup($this, $this->data, $this->validationGroup);
            $filter->setValidationGroup($this->validationGroup);
        }

        $this->isValid = $result = $filter->isValid();
        if ($result && $this->bindOnValidate()) {
            $this->bindValues();
        }

        if (!$result) {
            $this->setMessages($filter->getMessages());
        }

        $this->hasValidated = true;
        return $result;
    }

    /**
     * Retrieve the validated data
     *
     * By default, retrieves normalized values; pass one of the
     * FormInterface::VALUES_* constants to shape the behavior.
     *
     * @param  int $flag
     * @return array|object
     * @throws Exception\DomainException
     */
    public function getData($flag = FormInterface::VALUES_NORMALIZED)
    {
        if (!$this->hasValidated) {
            throw new Exception\DomainException(sprintf(
                '%s cannot return data as validation has not yet occurred',
                __METHOD__
            ));
        }

        if (($flag !== FormInterface::VALUES_AS_ARRAY) && is_object($this->object)) {
            return $this->object;
        }

        $filter = $this->getInputFilter();

        if ($flag === FormInterface::VALUES_RAW) {
            return $filter->getRawValues();
        }

        return $filter->getValues();
    }

    /**
     * Set the validation group (set of values to validate)
     *
     * Typically, proxies to the composed input filter
     *
     * @throws Exception\InvalidArgumentException
     * @return Form|FormInterface
     */
    public function setValidationGroup()
    {
        $argc = func_num_args();
        if (0 === $argc) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects at least one argument; none provided',
                __METHOD__
            ));
        }

        $argv = func_get_args();
        $this->hasValidated = false;

        if (1 < $argc) {
            $this->validationGroup = $argv;
            return $this;
        }

        $arg = array_shift($argv);
        if ($arg === FormInterface::VALIDATE_ALL) {
            $this->validationGroup = null;
            return $this;
        }

        if (!is_array($arg)) {
            $arg = (array) $arg;
        }

        $this->validationGroup = $arg;
        return $this;
    }

    /**
     * Prepare the validation group in case Collection elements were used (this function also handle the case where elements
     * could have been dynamically added or removed from a collection using JavaScript)
     *
     * @param FieldsetInterface $formOrFieldset
     * @param array             $data
     * @param array             $validationGroup
     */
    protected function prepareValidationGroup(FieldsetInterface $formOrFieldset, array $data, array &$validationGroup)
    {
        foreach ($validationGroup as $key => &$value) {
            if (!$formOrFieldset->has($key)) {
                continue;
            }

            $fieldset = $formOrFieldset->byName[$key];

            if ($fieldset instanceof Collection) {
                $values = array();
                $count = count($data[$key]);

                for ($i = 0 ; $i != $count ; ++$i) {
                    $values[] = $value;
                }

                $value = $values;
            } else {
                $this->prepareValidationGroup($fieldset, $data[$key], $validationGroup[$key]);
            }
        }
    }

    /**
     * Set the input filter used by this form
     * 
     * @param  InputFilterInterface $inputFilter 
     * @return FormInterface
     */
    public function setInputFilter(InputFilterInterface $inputFilter)
    {
        $this->hasValidated = false;
        $this->filter       = $inputFilter;
        return $this;
    }

    /**
     * Retrieve input filter used by this form
     * 
     * @return null|InputFilterInterface
     */
    public function getInputFilter()
    {
        if ($this->object instanceof InputFilterAwareInterface) {
            $this->filter = $this->object->getInputFilter();
        }

        if ($this->filter instanceof InputFilterInterface && $this->useInputFilterDefaults()) {
            $this->attachInputFilterDefaults($this->filter, $this);
        }

        return $this->filter;
    }

    /**
     * Set flag indicating whether or not to scan elements and fieldsets for defaults
     *
     * @param  bool $useInputFilterDefaults
     * @return Form
     */
    public function setUseInputFilterDefaults($useInputFilterDefaults)
    {
        $this->useInputFilterDefaults = (bool) $useInputFilterDefaults;
        return $this;
    }

    /**
     * Should we use input filter defaults from elements and fieldsets?
     *
     * @return bool
     */
    public function useInputFilterDefaults()
    {
        return $this->useInputFilterDefaults;
    }

    /**
     * Attach defaults provided by the elements to the input filter
     *
     * @param  InputFilterInterface $inputFilter
     * @param  FieldsetInterface $fieldset Fieldset to traverse when looking for default inputs
     * @return void
     */
    public function attachInputFilterDefaults(InputFilterInterface $inputFilter, FieldsetInterface $fieldset)
    {
        $formFactory  = $this->getFormFactory();
        $inputFactory = $formFactory->getInputFilterFactory();
        foreach ($fieldset->getElements() as $element) {
            if (!$element instanceof InputProviderInterface) {
                // only interested in the element if it provides input information
                continue;
            }

            $name = $element->getName();
            if ($inputFilter->has($name)) {
                // if we already have an input by this name, use it
                continue;
            }

            // Create an input based on the specification returned from the element
            $spec  = $element->getInputSpecification();
            $input = $inputFactory->createInput($spec);
            $inputFilter->add($input, $name);
        }

        foreach ($fieldset->getFieldsets() as $fieldset) {
            $name = $fieldset->getName();

            if (!$fieldset instanceof InputFilterProviderInterface) {
                if (!$inputFilter->has($name)) {
                    // Add a new empty input filter if it does not exist, so that elements of nested fieldsets can be
                    // recursively added
                    $inputFilter->add(new InputFilter(), $name);
                }

                $fieldsetFilter = $inputFilter->get($name);

                if (!$fieldsetFilter instanceof InputFilterInterface) {
                    // Input attached for fieldset, not input filter; nothing more to do.
                    continue;
                }

                // Traverse the elements of the fieldset, and attach any
                // defaults to the fieldset's input filter
                $this->attachInputFilterDefaults($fieldsetFilter, $fieldset);
                continue;
            }

            if ($inputFilter->has($name)) {
                // if we already have an input/filter by this name, use it
                continue;
            }

            // Create an input filter based on the specification returned from the fieldset
            $spec   = $fieldset->getInputFilterSpecification();
            $filter = $inputFactory->createInputFilter($spec);
            $inputFilter->add($filter, $name);

            // Recursively attach sub filters
            $this->attachInputFilterDefaults($filter, $fieldset);
        }
    }

    /**
     * Extract values from the bound object and populate
     * the form elements
     * 
     * @return void
     */
    protected function extract()
    {
        if (!is_object($this->object)) {
            return;
        }
        $hydrator = $this->getHydrator();
        if (!$hydrator instanceof Hydrator\HydratorInterface) {
            return;
        }

        $values = $hydrator->extract($this->object);
        if (!is_array($values)) {
            // Do nothing if the hydrator returned a non-array
            return;
        }

        if (null !== $this->baseFieldset) {
            $this->baseFieldset->populateValues($values);
        } else {
            $this->populateValues($values);
        }
    }
}
