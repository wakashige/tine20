<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Filter
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009-2018 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 */

/**
 *  alarm filter class
 * 
 * @package     Tinebase
 * @subpackage  Filter 
 */
class Tinebase_Model_ConfigFilter extends Tinebase_Model_Filter_FilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Tinebase';
    
    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = Tinebase_Model_Config::class;
    
    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'name'              => array('filter' => 'Tinebase_Model_Filter_Text'),
        'application_id'    => array('filter' => 'Tinebase_Model_Filter_Id'),
    );
}
