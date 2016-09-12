<?php
/**
 * Tine 2.0
 *
 * @package     Projects
 * @subpackage  Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @copyright   Copyright (c) 2012-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Stefanie Stamer <s.stamer@metaways.de>
 */
class Projects_Setup_Update_Release9 extends Setup_Update_Abstract
{
    /**
     * update to 10.0
     *
     * @return void
     */
    public function update_0()
    {
        $this->setApplicationVersion('Projects', '10.0');
    }
}
