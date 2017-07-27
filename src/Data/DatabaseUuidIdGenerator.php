<?php

/**
 * This file is part of contao-community-alliance/dc-general.
 *
 * (c) 2013-2017 Contao Community Alliance.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    contao-community-alliance/dc-general
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Christopher Boelter <christopher@boelter.eu>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2013-2017 Contao Community Alliance.
 * @license    https://github.com/contao-community-alliance/dc-general/blob/master/LICENSE LGPL-3.0
 * @filesource
 */

namespace ContaoCommunityAlliance\DcGeneral\Data;

/**
 * Uuid generating by querying it from the Contao database class.
 */
class DatabaseUuidIdGenerator implements IdGeneratorInterface
{
    /**
     * The database to use.
     *
     * @var \Database
     */
    protected $database;

    /**
     * Create a new instance.
     *
     * @param \Database $database The database to use for uuid generating.
     */
    public function __construct(\Database $database)
    {
        $this->database = $database;
    }

    /**
     * Generate an id.
     *
     * @return string
     */
    public function generate()
    {
        return $this->database->query('SELECT UUID() as id')->first()->id;
    }

    /**
     * The amount of storage space an id of this type needs.
     *
     * @return int
     */
    public function getSize()
    {
        return 36;
    }
}