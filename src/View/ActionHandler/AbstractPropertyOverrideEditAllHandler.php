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
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2013-2018 Contao Community Alliance.
 * @license    https://github.com/contao-community-alliance/dc-general/blob/master/LICENSE LGPL-3.0
 * @filesource
 */

namespace ContaoCommunityAlliance\DcGeneral\View\ActionHandler;

use Contao\Environment;
use ContaoCommunityAlliance\Contao\Bindings\ContaoEvents;
use ContaoCommunityAlliance\Contao\Bindings\Events\Controller\RedirectEvent;
use ContaoCommunityAlliance\Contao\Bindings\Events\System\GetReferrerEvent;
use ContaoCommunityAlliance\DcGeneral\Action;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\BaseView;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ContaoBackendViewTemplate;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\GetBreadcrumbEvent;
use ContaoCommunityAlliance\DcGeneral\Data\CollectionInterface;
use ContaoCommunityAlliance\DcGeneral\Data\ModelId;
use ContaoCommunityAlliance\DcGeneral\Data\ModelIdInterface;
use ContaoCommunityAlliance\DcGeneral\Data\ModelInterface;
use ContaoCommunityAlliance\DcGeneral\Data\PropertyValueBag;
use ContaoCommunityAlliance\DcGeneral\Data\PropertyValueBagInterface;
use ContaoCommunityAlliance\DcGeneral\EnvironmentInterface;
use ContaoCommunityAlliance\DcGeneral\Event\PostPersistModelEvent;
use ContaoCommunityAlliance\DcGeneral\Exception\DcGeneralInvalidArgumentException;
use ContaoCommunityAlliance\DcGeneral\Exception\DcGeneralRuntimeException;

/**
 * This class is the abstract base for override/edit all "overrideAll/editAll" commands.
 */
abstract class AbstractPropertyOverrideEditAllHandler extends AbstractPropertyVisibilityHandler
{
    /**
     * Handle submit triggered button.
     *
     * If the button save and back triggered.
     * Clear the data from session and redirect to list view.
     *
     * @param Action               $action      The action.
     * @param EnvironmentInterface $environment The environment.
     *
     * @return void
     */
    protected function handleSubmit(Action $action, EnvironmentInterface $environment)
    {
        $inputProvider   = $environment->getInputProvider();
        $sessionStorage  = $environment->getSessionStorage();
        $eventDispatcher = $environment->getEventDispatcher();

        if (!$inputProvider->hasValue($this->getMode($action) . '_saveNback')) {
            return;
        }

        $sessionStorage->remove($environment->getDataDefinition()->getName() . '.' . $this->getMode($action));

        $urlEvent = new GetReferrerEvent(false, $environment->getDataDefinition()->getName());

        $eventDispatcher->dispatch(ContaoEvents::SYSTEM_GET_REFERRER, $urlEvent);
        $eventDispatcher->dispatch(ContaoEvents::CONTROLLER_REDIRECT, new RedirectEvent($urlEvent->getReferrerUrl()));
    }

    /**
     * Edit collection of models.
     *
     * If model property isn´t visible revoke this.
     *
     * @param Action                    $action            The action.
     * @param CollectionInterface       $collection        The model collection.
     * @param PropertyValueBagInterface $propertyValueBag  The properties and values for model collection.
     * @param \ArrayObject              $renderInformation The render information.
     * @param EnvironmentInterface      $environment       The environment.
     *
     * @return void
     */
    protected function editCollection(
        Action $action,
        CollectionInterface $collection,
        PropertyValueBagInterface $propertyValueBag,
        \ArrayObject $renderInformation,
        EnvironmentInterface $environment
    ) {
        while ($collection->count() > 0) {
            $model = $collection->shift();

            $persistPropertyValueBag =
                $this->cloneCleanPropertyValueBag($action, $propertyValueBag, $model, $environment);

            $this->resetPropertyValueErrors($persistPropertyValueBag);
            $this->handleEditHandler($action, $model, $persistPropertyValueBag, $environment);
            $this->markPropertyInvalidErrorsByModel($propertyValueBag, $model, $renderInformation, $environment);
            $this->updatePropertyValueBag($action, $model, $persistPropertyValueBag, $propertyValueBag, $environment);
        }
    }

    /**
     * Update the error information.
     *
     * @param \ArrayObject $renderInformation The render information.
     *
     * @return void
     */
    protected function updateErrorInformation(
        \ArrayObject $renderInformation
    ) {
        $modelError = $renderInformation->offsetGet('modelError');

        if (null === $modelError) {
            return;
        }

        $error = $renderInformation->offsetGet('error');

        foreach (\array_keys($modelError) as $modelId) {
            $newEditError = [
                \sprintf(
                    '<strong><a href="%s#pal_%s">%s</a></strong>',
                    Environment::get('request'),
                    \str_replace('::', '____', $modelId),
                    $modelId
                )
            ];

            foreach ($modelError[$modelId] as $modelIdError) {
                $newEditError = \array_merge($newEditError, $modelIdError);
            }

            if (\count($error) > 0) {
                $error = \array_merge($error, ['']);
            }

            $error = \array_merge($error, $newEditError);
        }

        $renderInformation->offsetSet('error', $error);
    }

    /**
     * Clone and clean property value bag.
     *
     * @param Action                    $action           The action.
     * @param PropertyValueBagInterface $propertyValueBag The property value bag.
     * @param ModelInterface            $model            The model.
     * @param EnvironmentInterface      $environment      The environment.
     *
     * @return PropertyValueBagInterface
     */
    private function cloneCleanPropertyValueBag(
        Action $action,
        PropertyValueBagInterface $propertyValueBag,
        ModelInterface $model,
        EnvironmentInterface $environment
    ) {
        $sessionProperties = $this->getPropertiesFromSession($action, $environment);

        $clonePropertyValueBag = clone $propertyValueBag;
        foreach (\array_keys($propertyValueBag->getArrayCopy()) as $propertyName) {
            $clonePropertyValueBag->resetPropertyValueErrors($propertyName);

            if ($this->ensurePropertyVisibleInModel(action, $propertyName, $model, $environment)) {
                if (!\array_key_exists($propertyName, $sessionProperties)
                ) {
                    $clonePropertyValueBag->setPropertyValue($propertyName, $model->getProperty($propertyName));
                }

                continue;
            }

            $clonePropertyValueBag->removePropertyValue($propertyName);
        }

        return $clonePropertyValueBag;
    }

    /**
     * Reset property value errors.
     *
     * @param PropertyValueBagInterface $propertyValueBag The property value bag.
     *
     * @return void
     */
    private function resetPropertyValueErrors(PropertyValueBagInterface $propertyValueBag)
    {
        foreach (\array_keys($propertyValueBag->getInvalidPropertyErrors()) as $errorProperty) {
            $propertyValueBag->resetPropertyValueErrors($errorProperty);
        }
    }

    /**
     * Mark property in invalid errors by model.
     *
     * @param PropertyValueBagInterface $propertyValueBag  The property values.
     * @param ModelInterface            $model             The Model.
     * @param \ArrayObject              $renderInformation The render information.
     * @param EnvironmentInterface      $environment       The environment.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private function markPropertyInvalidErrorsByModel(
        PropertyValueBagInterface $propertyValueBag,
        ModelInterface $model,
        \ArrayObject $renderInformation,
        EnvironmentInterface $environment
    ) {
        $editInformation  = $GLOBALS['container']['dc-general.edit-information'];
        $errorInformation = $editInformation->getModelError($model);
        if (null === $errorInformation) {
            return;
        }

        foreach (\array_keys($errorInformation) as $errorPropertyName) {
            \array_map(
                function ($error) use (
                    $errorPropertyName,
                    $model,
                    $propertyValueBag,
                    $renderInformation,
                    $editInformation,
                    $environment
                ) {
                    $modelError = (array) $renderInformation->offsetGet('modelError');

                    $inputField = \sprintf(
                        'ctrl_%s_%s',
                        \str_replace('::', '____', ModelId::fromModel($model)->getSerialized()),
                        $errorPropertyName
                    );

                    $modelError[ModelId::fromModel($model)->getSerialized()][$errorPropertyName][] = \sprintf(
                        '<a href="%s#%s">No saved model[%s]. %s</a>',
                        Environment::get('request'),
                        $inputField,
                        $model->getId(),
                        $error
                    );

                    $renderInformation->offsetSet('modelError', $modelError);

                    $modelEditError = $editInformation->getModelError($model);
                    if ($modelEditError && isset($modelEditError[$errorPropertyName])) {
                        $propertyValueBag->setPropertyValue(
                            $errorPropertyName,
                            $environment->getInputProvider()->getValue($errorPropertyName)
                        );

                        $propertyValueBag->markPropertyValueAsInvalid(
                            $errorPropertyName,
                            $modelEditError[$errorPropertyName]
                        );
                    }
                },
                $errorInformation[$errorPropertyName]
            );
        }
    }

    /**
     * Update property value bag.
     *
     * @param Action                    $action                 The action.
     * @param ModelInterface            $model                  The model.
     * @param PropertyValueBagInterface $sourcePropertyValueBag The source property value bag.
     * @param PropertyValueBagInterface $updatePropertyValueBag The update property value bag.
     * @param EnvironmentInterface      $environment            The environment.
     *
     * @return void
     */
    private function updatePropertyValueBag(
        Action $action,
        ModelInterface $model,
        PropertyValueBagInterface $sourcePropertyValueBag,
        PropertyValueBagInterface $updatePropertyValueBag,
        EnvironmentInterface $environment
    ) {
        $dataProvider      = $environment->getDataProvider();
        $sessionProperties = $this->getPropertiesFromSession($action, $environment);

        foreach (\array_keys($sessionProperties) as $sessionPropertyName) {
            if (!$sourcePropertyValueBag->hasPropertyValue($sessionPropertyName)) {
                continue;
            }

            if (!$updatePropertyValueBag->isPropertyValueInvalid($sessionPropertyName)) {
                $editModel = $dataProvider->fetch($dataProvider->getEmptyConfig()->setId($model->getId()));
                $updatePropertyValueBag->setPropertyValue(
                    $sessionPropertyName,
                    $editModel->getProperty($sessionPropertyName)
                );
            }

            if ($updatePropertyValueBag->isPropertyValueInvalid($sessionPropertyName)) {
                continue;
            }

            $updatePropertyValueBag->markPropertyValueAsInvalid(
                $sessionPropertyName,
                $sourcePropertyValueBag->getPropertyValueErrors(
                    $sessionPropertyName
                )
            );
        }
    }

    /**
     * Handle the edit handler.
     *
     * @param Action                    $action           The action.
     * @param ModelInterface            $model            The model.
     * @param PropertyValueBagInterface $propertyValueBag The property value.
     * @param EnvironmentInterface      $environment      The environment.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private function handleEditHandler(
        Action $action,
        ModelInterface $model,
        PropertyValueBagInterface $propertyValueBag,
        EnvironmentInterface $environment
    ) {
        $inputProvider   = $environment->getInputProvider();
        $editInformation = $GLOBALS['container']['dc-general.edit-information'];

        $this->handleUnsetInputValueSubmitType($action, $model, $environment);

        $inputValues = $this->handleInputValues($action, $model, $environment);

        $view = $environment->getView();
        if (!$view instanceof BaseView) {
            return;
        }

        // FIXME If restore version works by dcg, then must implement here as well.
        $clone = clone $model;
        $clone->setId($model->getId());

        $inputProvider->setParameter('id', ModelId::fromModel($model)->getSerialized());

        // TODO implement call action trait.
        $this->callAction('edit');

        $inputProvider->unsetParameter('id');

        $this->restoreInputValues($action, $model, $propertyValueBag, $inputValues, $environment);

        $errorInformation = $editInformation->getModelError($model);
        if (null !== $errorInformation) {
            foreach (\array_keys($errorInformation) as $errorPropertyName) {
                if (false === $propertyValueBag->hasPropertyValue($errorPropertyName)) {
                    continue;
                }

                if ($propertyValueBag->isPropertyValueInvalid($errorPropertyName)) {
                    continue;
                }

                foreach ($errorInformation[$errorPropertyName] as $error) {
                    $propertyValueBag->markPropertyValueAsInvalid($errorPropertyName, $error);
                }
            }
        }
    }

    /**
     * Handle unset the input value submit type.
     *
     * @param Action               $action      The action.
     * @param ModelInterface       $model       The model.
     * @param EnvironmentInterface $environment The environment.
     *
     * @return void
     */
    private function handleUnsetInputValueSubmitType(
        Action $action,
        ModelInterface $model,
        EnvironmentInterface $environment
    ) {
        $inputProvider  = $environment->getInputProvider();
        $editProperties = $this->getEditPropertiesByModelId($action, ModelId::fromModel($model), $environment);

        if ($editProperties
            && !$inputProvider->hasValue('SUBMIT_TYPE')
        ) {
            return;
        }

        $inputProvider->unsetValue('SUBMIT_TYPE');
    }

    /**
     * Handle input values and return it.
     *
     * @param Action               $action      The action.
     * @param ModelInterface       $model       The model.
     * @param EnvironmentInterface $environment The environment.
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private function handleInputValues(Action $action, ModelInterface $model, EnvironmentInterface $environment)
    {
        $editProperties = $this->getEditPropertiesByModelId($action, ModelId::fromModel($model), $environment);

        if (!$editProperties) {
            return [];
        }

        $inputProvider = $environment->getInputProvider();

        $inputValues = [];
        foreach (\array_keys($_POST) as $valueName) {
            $inputValues[$valueName] = $inputProvider->getValue($valueName, true);
            $inputProvider->unsetValue($valueName);

            switch ($valueName) {
                case 'FORM_SUBMIT':
                case 'REQUEST_TOKEN':
                    $inputProvider->setValue($valueName, $inputValues[$valueName]);

                    break;

                case 'FORM_INPUTS':
                    $inputProvider->setValue($valueName, \array_keys($editProperties));

                    foreach (\array_keys($editProperties) as $editPropertyName) {
                        $inputProvider->setValue($editPropertyName, $editProperties[$editPropertyName]);
                    }

                    break;

                default:
            }
        }

        return $inputValues;
    }

    /**
     * Restore input values.
     *
     * @param Action                    $action           The action.
     * @param ModelInterface            $model            The model.
     * @param PropertyValueBagInterface $propertyValueBag The property value bag.
     * @param array                     $inputValues      The input values.
     * @param EnvironmentInterface      $environment      The environment.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function restoreInputValues(
        Action $action,
        ModelInterface $model,
        PropertyValueBagInterface $propertyValueBag,
        array $inputValues,
        EnvironmentInterface $environment
    ) {
        $editProperties = $this->getEditPropertiesByModelId($action, ModelId::fromModel($model), $environment);
        if (!$editProperties) {
            return;
        }

        $inputProvider = $environment->getInputProvider();

        unset($_POST);
        foreach (\array_keys($inputValues) as $postName) {
            $inputProvider->setValue($postName, $inputValues[$postName]);
        }

        foreach (\array_keys($editProperties) as $editedPropertyName) {
            $propertyValueBag->setPropertyValue($editedPropertyName, $model->getProperty($editedPropertyName));
        }
    }

    /**
     * Retrieve buttons to use in the bottom panel.
     *
     * @param Action               $action      The action.
     * @param EnvironmentInterface $environment The environment.
     *
     * @return array
     */
    protected function getEditButtons(Action $action, EnvironmentInterface $environment)
    {
        $translator = $environment->getTranslator();

        $mode = $this->getMode($action);

        $buttons = [];

        $buttons['save'] = \sprintf(
            '<input type="submit" name="%s_save" id="%s_save" class="tl_submit" accesskey="s" value="%s" />',
            $mode,
            $mode,
            $translator->translate('MSC.save')
        );

        $buttons['saveNclose'] = \sprintf(
            '<input type="submit" name="%s_saveNback" id="%s_saveNback" class="tl_submit" accesskey="c" value="%s" />',
            $mode,
            $mode,
            $translator->translate('MSC.saveNback')
        );

        return $buttons;
    }

    /**
     * Render the template for the edit mask.
     *
     * @param Action $action The action.
     * @param array  $config The template config data.
     *
     * @return string
     */
    protected function renderTemplate(Action $action, array $config)
    {
        $template = new ContaoBackendViewTemplate('dcbe_general_edit');
        $template->setData($config);

        $template->set('mode', $this->getMode($action));

        return $template->parse();
    }

    /**
     * Get property value bag from the model.
     *
     * @param Action               $action      The action.
     * @param ModelInterface       $model       The model.
     * @param EnvironmentInterface $environment The environment.
     *
     * @return PropertyValueBag
     *
     * @throws DcGeneralInvalidArgumentException If create property value bug, the construct argument isn´t right.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function getPropertyValueBagFromModel(
        Action $action,
        ModelInterface $model,
        EnvironmentInterface $environment
    ) {
        $propertiesDefinition = $environment->getDataDefinition()->getPropertiesDefinition();
        $editInformation      = $GLOBALS['container']['dc-general.edit-information'];

        $propertyValueBag = new PropertyValueBag();

        foreach ($model->getPropertiesAsArray() as $propertyName => $propertyValue) {
            if (!$propertiesDefinition->hasProperty($propertyName)) {
                continue;
            }

            $property = $propertiesDefinition->getProperty($propertyName);
            if (!$property->getWidgetType()) {
                continue;
            }

            $modelError = $editInformation->getModelError($model);
            if ($modelError && isset($modelError[$propertyName])) {
                $sessionValues = $this->getEditPropertiesByModelId($action, ModelId::fromModel($model), $environment);

                $propertyValueBag->setPropertyValue($propertyName, $sessionValues[$propertyName]);
                $propertyValueBag->markPropertyValueAsInvalid($propertyName, $modelError[$propertyName]);

                continue;
            }

            $propertyValueBag->setPropertyValue($propertyName, $propertyValue);
        }

        return $propertyValueBag;
    }

    /**
     * Return select model collection from the session.
     *
     * @param Action               $action      The action.
     * @param EnvironmentInterface $environment The environment.
     *
     * @return CollectionInterface
     *
     * @throws DcGeneralRuntimeException When the model id can´t parse.
     */
    protected function getCollectionFromSession(Action $action, EnvironmentInterface $environment)
    {
        $inputProvider  = $environment->getInputProvider();
        $sessionStorage = $environment->getSessionStorage();
        $dataDefinition = $environment->getDataDefinition();
        $dataProvider   = $environment->getDataProvider($dataDefinition->getName());

        $addEditProperties =
            $inputProvider->hasValue('FORM_INPUTS')
            && ($dataDefinition->getName() === $inputProvider->getValue('FORM_SUBMIT'));

        $session = $this->getSession($action, $environment);

        $editProperties = [];

        $modelIds = [];
        foreach ($session['models'] as $modelId) {
            $modelIds[] = ModelId::fromSerialized($modelId)->getId();

            if ($addEditProperties) {
                $modelEditProperties = $inputProvider->getValue(\str_replace('::', '____', $modelId) . '_', true);
                $inputProvider->unsetValue(\str_replace('::', '____', $modelId) . '_');

                $editProperties[$modelId] = $modelEditProperties;
            }
        }

        $idProperty = \method_exists($dataProvider, 'getIdProperty') ? $dataProvider->getIdProperty() : 'id';
        $collection = $dataProvider->fetchAll(
            $dataProvider->getEmptyConfig()->setFilter(
                [['operation' => 'IN', 'property' => $idProperty, 'values' => $modelIds]]
            )
        );

        $session['editProperties'] = $editProperties;

        $sessionStorage->set($dataDefinition->getName() . '.' . $this->getMode($action), $session);

        return $collection;
    }

    /**
     * Get the edit properties by model id.
     *
     * @param Action               $action      The action.
     * @param ModelIdInterface     $modelId     The model id.
     * @param EnvironmentInterface $environment The environment.
     *
     * @return array
     */
    protected function getEditPropertiesByModelId(
        Action $action,
        ModelIdInterface $modelId,
        EnvironmentInterface $environment
    ) {
        $session = $this->getSession($action, $environment);

        return $session['editProperties'][$modelId->getSerialized()];
    }

    /**
     * Render the breadcrumb.
     *
     * @param EnvironmentInterface $environment The environment.
     *
     * @return null|string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    protected function renderBreadcrumb(EnvironmentInterface $environment)
    {
        $event = new GetBreadcrumbEvent($environment);
        $environment->getEventDispatcher()->dispatch($event::NAME, $event);
        $elements = $event->getElements();
        if (empty($elements)) {
            return null;
        }

        $GLOBALS['TL_CSS'][] = 'system/modules/dc-general/html/css/generalBreadcrumb.css';

        $template = new ContaoBackendViewTemplate('dcbe_general_breadcrumb');
        $template->set('elements', $elements);

        return $template->parse();
    }

    /**
     * Revert model values if their have errors.
     *
     * @param Action               $action      The action.
     * @param CollectionInterface  $collection  The collection of Models.
     * @param EnvironmentInterface $environment The environment.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function revertValuesByErrors(
        Action $action,
        CollectionInterface $collection,
        EnvironmentInterface $environment
    ) {
        $editInformation = $GLOBALS['container']['dc-general.edit-information'];
        if (!$editInformation->hasAnyModelError()) {
            return;
        }

        $dataProvider = $environment->getDataProvider();
        $properties   = $this->getPropertiesFromSession($action, $environment);

        while ($collection->count() > 0) {
            $model = $collection->shift();

            $modelErrors = $editInformation->getModelError($model);
            if (!$modelErrors && ('edit' === $this->getMode($action))) {
                continue;
            }

            $revertModel = $dataProvider->fetch($dataProvider->getEmptyConfig()->setId($model->getId()));

            $originalModel = clone $revertModel;
            $revertModel->setId($revertModel->getId());

            foreach ($properties as $property) {
                if (('edit' === $this->getMode($action))
                    && !\in_array($property->getName(), $modelErrors)) {
                    continue;
                }

                $revertModel->setProperty($property->getName(), $model->getProperty($property->getName()));
            }

            $dataProvider->save($revertModel);

            $this->handlePostPersist($revertModel, $originalModel, $environment);
        }
    }

    /**
     * Trigger the post persist event if available.
     *
     * @param ModelInterface       $model         The edit model.
     * @param ModelInterface       $originalModel The original model.
     * @param EnvironmentInterface $environment   The environment.
     *
     * @return void
     */
    private function handlePostPersist(
        ModelInterface $model,
        ModelInterface $originalModel,
        EnvironmentInterface $environment
    ) {
        $event = new PostPersistModelEvent($environment, $model, $originalModel);
        $environment->getEventDispatcher()->dispatch($event::NAME, $event);
    }

    /**
     * Return the override/edit mode.
     *
     * @param Action $action The action.
     *
     * @return string
     */
    private function getMode(Action $action)
    {
        $arguments = $action->getArguments();

        return $arguments['mode'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getSession(Action $action, EnvironmentInterface $environment)
    {
        $dataDefinition = $environment->getDataDefinition();
        $sessionStorage = $environment->getSessionStorage();

        $session = $sessionStorage->get($dataDefinition->getName() . '.' . $this->getMode($action));

        return (array) $session;
    }

    /**
     * {@inheritDoc}
     */
    protected function getPropertiesFromSession(Action $action, EnvironmentInterface $environment)
    {
        $dataDefinition = $environment->getDataDefinition();

        $session = $this->getSession($action, $environment);

        $selectPropertyNames = [];
        foreach ($session['properties'] as $modelId) {
            $selectPropertyNames[] = ModelId::fromSerialized($modelId)->getId();
        }

        $properties = [];
        foreach ($dataDefinition->getPropertiesDefinition()->getPropertyNames() as $propertyName) {
            if (!\in_array($propertyName, $selectPropertyNames)) {
                continue;
            }

            $properties[$propertyName] = $dataDefinition->getPropertiesDefinition()->getProperty($propertyName);
        }

        return $properties;
    }
}
