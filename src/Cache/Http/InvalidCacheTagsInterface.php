<?php

/**
 * This file is part of contao-community-alliance/dc-general.
 *
 * (c) 2013-2020 Contao Community Alliance.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    contao-community-alliance/dc-general
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2013-2020 Contao Community Alliance.
 * @license    https://github.com/contao-community-alliance/dc-general/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

declare(strict_types=1);

namespace ContaoCommunityAlliance\DcGeneral\Cache\Http;

use ContaoCommunityAlliance\DcGeneral\Data\ModelInterface;
use ContaoCommunityAlliance\DcGeneral\EnvironmentInterface;

/**
 * This is interface for purge the invalid http cache tags.
 */
interface InvalidCacheTagsInterface
{
    /**
     * Set the environment.
     *
     * @param EnvironmentInterface $environment The dc general environment.
     *
     * @return InvalidCacheTags
     */
    public function setEnvironment(EnvironmentInterface $environment): InvalidCacheTags;

    /**
     * Purge the http cache tags.
     *
     * @param ModelInterface $model The current model.
     *
     * @return void
     */
    public function purgeCacheTags(ModelInterface $model): void;
}