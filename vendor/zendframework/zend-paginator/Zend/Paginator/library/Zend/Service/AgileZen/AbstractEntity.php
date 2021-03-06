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
 * @package    Zend_Service
 * @subpackage AgileZen
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\Service\AgileZen;

/**
 * @category   Zend
 * @package    Zend_Service
 * @subpackage AgileZen
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class AbstractEntity
{
    /**
     * Id of the entity
     * 
     * @var string 
     */
    protected $id;

    /**
     * Get the Id
     * 
     * @return string 
     */
    public function getId() 
    {
        return $this->id;
    }

    /**
     * Constructor
     * 
     * @param string $id
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($id) 
    {
        if (empty($id)) {
            throw new Exception\InvalidArgumentException('The id is required for the entity');
        }
        $this->id = $id;
    }
}
