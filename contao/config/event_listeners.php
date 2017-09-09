<?php

/**
 * This file is part of contao-community-alliance/dc-general.
 *
 * (c) 2013-2018 Contao Community Alliance.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    contao-community-alliance/dc-general
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2013-2018 Contao Community Alliance.
 * @license    https://github.com/contao-community-alliance/dc-general/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

use ContaoCommunityAlliance\DcGeneral\Contao\Dca\Builder\Legacy\ExtendedLegacyDcaDataDefinitionBuilder;
use ContaoCommunityAlliance\DcGeneral\Contao\Dca\Builder\Legacy\LegacyDcaDataDefinitionBuilder;
use ContaoCommunityAlliance\DcGeneral\Contao\Dca\Populator\BackendViewPopulator;
use ContaoCommunityAlliance\DcGeneral\Contao\Dca\Populator\DataProviderPopulator;
use ContaoCommunityAlliance\DcGeneral\Contao\Dca\Populator\ExtendedLegacyDcaPopulator;
use ContaoCommunityAlliance\DcGeneral\Contao\Dca\Populator\HardCodedPopulator;
use ContaoCommunityAlliance\DcGeneral\Contao\Dca\Populator\ParentDefinitionPopulator;
use ContaoCommunityAlliance\DcGeneral\Contao\Dca\Populator\PickerCompatPopulator;
use ContaoCommunityAlliance\DcGeneral\Contao\Subscriber\FormatModelLabelSubscriber;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ActionHandler\CopyHandler;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ActionHandler\CreateHandler;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ActionHandler\DeleteHandler;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ActionHandler\EditAllHandler;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ActionHandler\EditHandler;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ActionHandler\ListViewShowAllHandler;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ActionHandler\ListViewShowAllPropertiesHandler;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ActionHandler\OverrideAllHandler;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ActionHandler\ParentedListViewShowAllHandler;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ActionHandler\PasteAllHandler;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ActionHandler\PasteHandler;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ActionHandler\SelectHandler;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ActionHandler\ToggleHandler;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\BuildWidgetEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\GetGlobalButtonEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\GetGroupHeaderEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\EventListener\BackButtonListener;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\EventListener\CreateModelButtonListener;
use ContaoCommunityAlliance\DcGeneral\DcGeneralEvents;
use ContaoCommunityAlliance\DcGeneral\EventListener\ModelRelationship\ParentEnforcingListener;
use ContaoCommunityAlliance\DcGeneral\EventListener\ModelRelationship\TreeEnforcingListener;
use ContaoCommunityAlliance\DcGeneral\Factory\DcGeneralFactory;
use ContaoCommunityAlliance\DcGeneral\Factory\Event\BuildDataDefinitionEvent;
use ContaoCommunityAlliance\DcGeneral\Factory\Event\PopulateEnvironmentEvent;

$result = array(
    BuildDataDefinitionEvent::NAME => array(
        array(
            array(new LegacyDcaDataDefinitionBuilder(), 'process'),
            LegacyDcaDataDefinitionBuilder::PRIORITY
        ),
        array(
            array(new ExtendedLegacyDcaDataDefinitionBuilder(), 'process'),
            ExtendedLegacyDcaDataDefinitionBuilder::PRIORITY
        ),
    ),
    PopulateEnvironmentEvent::NAME => array(
        array(
            array(new ParentDefinitionPopulator(), 'process'),
            ParentDefinitionPopulator::PRIORITY
        ),
        array(
            array(new DataProviderPopulator(), 'process'),
            DataProviderPopulator::PRIORITY
        ),
        array(
            array(new HardCodedPopulator(), 'process'),
            HardCodedPopulator::PRIORITY
        ),
    ),
    DcGeneralEvents::ENFORCE_MODEL_RELATIONSHIP => array(
        array(new TreeEnforcingListener(), 'process'),
        array(new ParentEnforcingListener(), 'process'),
    )
);

if ('BE' === TL_MODE) {
    $result[PopulateEnvironmentEvent::NAME] = array_merge(
        $result[PopulateEnvironmentEvent::NAME],
        array(
            array(
                array(new ExtendedLegacyDcaPopulator(), 'process'),
                ExtendedLegacyDcaPopulator::PRIORITY
            ),
            array(
                array(new BackendViewPopulator(), 'process'),
                BackendViewPopulator::PRIORITY
            ),
            array(
                array(new PickerCompatPopulator(), 'process'),
                PickerCompatPopulator::PRIORITY
            ),
        )
    );

    $result[DcGeneralEvents::FORMAT_MODEL_LABEL] = array(
        array(new FormatModelLabelSubscriber(), 'handleFormatModelLabel'),
    );

    $result[GetGroupHeaderEvent::NAME] = array(
        'ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Subscriber\GetGroupHeaderSubscriber::handle'
    );

    $result[BuildWidgetEvent::NAME] = array(
        'ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Subscriber\WidgetBuilder::handleEvent',
        'ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Subscriber\ColorPickerWizardSubscriber' .
        '::handleEvent'
    );

    $result[DcGeneralEvents::ACTION] = array(
        array(new ListViewShowAllPropertiesHandler(), 'handleEvent'),
        array(new EditAllHandler(), 'handleEvent'),
        array(new OverrideAllHandler(), 'handleEvent'),
        array(new PasteAllHandler(), 'handleEvent'),
        array(new SelectHandler(), 'handleEvent'),
        array(new PasteHandler(), 'handleEvent'),
        array(new CreateHandler(), 'handleEvent'),
        array(new EditHandler(), 'handleEvent'),
        array(new CopyHandler(), 'handleEvent'),
        array(new DeleteHandler(), 'handleEvent'),
        array(new ToggleHandler(), 'handleEvent'),
        array(new ListViewShowAllHandler(), 'handleEvent'),
        array(new ParentedListViewShowAllHandler(), 'handleEvent'),
    );
    $result[GetGlobalButtonEvent::NAME] = array(
        array(new BackButtonListener(), 'handle'),
        array(new CreateModelButtonListener(), 'handle'),
    );
}

return $result;
