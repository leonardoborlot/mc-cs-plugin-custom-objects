<?php

declare(strict_types=1);

/*
 * @copyright   2018 Mautic, Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\CustomObjectsBundle\Controller\CustomObject;

use MauticPlugin\CustomObjectsBundle\Entity\CustomField;
use MauticPlugin\CustomObjectsBundle\Form\DataTransformer\OptionsToStringTransformer;
use MauticPlugin\CustomObjectsBundle\Form\DataTransformer\ParamsToStringTransformer;
use MauticPlugin\CustomObjectsBundle\Model\CustomFieldModel;
use MauticPlugin\CustomObjectsBundle\Provider\CustomFieldTypeProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use MauticPlugin\CustomObjectsBundle\Entity\CustomObject;
use MauticPlugin\CustomObjectsBundle\Form\Type\CustomObjectType;
use MauticPlugin\CustomObjectsBundle\Model\CustomObjectModel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\CustomObjectsBundle\Exception\NotFoundException;
use MauticPlugin\CustomObjectsBundle\Provider\CustomObjectPermissionProvider;
use MauticPlugin\CustomObjectsBundle\Exception\ForbiddenException;
use MauticPlugin\CustomObjectsBundle\Provider\CustomObjectRouteProvider;
use Mautic\CoreBundle\Service\FlashBag;
use Symfony\Component\Form\FormFactoryInterface;

class SaveController extends CommonController
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var FlashBag
     */
    private $flashBag;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var CustomObjectModel
     */
    private $customObjectModel;

    /**
     * @var CustomFieldModel
     */
    private $customFieldModel;

    /**
     * @var CustomObjectPermissionProvider
     */
    private $permissionProvider;

    /**
     * @var CustomObjectRouteProvider
     */
    private $routeProvider;

    /**
     * @var CustomFieldTypeProvider
     */
    private $customFieldTypeProvider;

    /**
     * @var ParamsToStringTransformer
     */
    private $paramsToStringTransformer;

    /**
     * @var OptionsToStringTransformer
     */
    private $optionsToStringTransformer;

    /**
     * @param RequestStack                   $requestStack
     * @param FlashBag                       $flashBag
     * @param FormFactoryInterface           $formFactory
     * @param CustomObjectModel              $customObjectModel
     * @param CustomFieldModel               $customFieldModel
     * @param CustomObjectPermissionProvider $permissionProvider
     * @param CustomObjectRouteProvider      $routeProvider
     * @param CustomFieldTypeProvider        $customFieldTypeProvider
     * @param ParamsToStringTransformer      $paramsToStringTransformer
     * @param OptionsToStringTransformer     $optionsToStringTransformer
     */
    public function __construct(
        RequestStack $requestStack,
        FlashBag $flashBag,
        FormFactoryInterface $formFactory,
        CustomObjectModel $customObjectModel,
        CustomFieldModel $customFieldModel,
        CustomObjectPermissionProvider $permissionProvider,
        CustomObjectRouteProvider $routeProvider,
        CustomFieldTypeProvider $customFieldTypeProvider,
        ParamsToStringTransformer $paramsToStringTransformer,
        OptionsToStringTransformer $optionsToStringTransformer
    ) {
        $this->requestStack               = $requestStack;
        $this->flashBag                   = $flashBag;
        $this->formFactory                = $formFactory;
        $this->customObjectModel          = $customObjectModel;
        $this->customFieldModel           = $customFieldModel;
        $this->permissionProvider         = $permissionProvider;
        $this->routeProvider              = $routeProvider;
        $this->customFieldTypeProvider    = $customFieldTypeProvider;
        $this->paramsToStringTransformer  = $paramsToStringTransformer;
        $this->optionsToStringTransformer = $optionsToStringTransformer;
    }

    /**
     * @param int|null $objectId
     *
     * @return Response
     */
    public function saveAction(?int $objectId = null): Response
    {
        try {
            $customObject = $objectId ? $this->customObjectModel->fetchEntity($objectId) : new CustomObject();
            if ($customObject->isNew()) {
                $this->permissionProvider->canCreate();
            } else {
                $this->permissionProvider->canEdit($customObject);
            }
        } catch (NotFoundException $e) {
            return $this->notFound($e->getMessage());
        } catch (ForbiddenException $e) {
            return $this->accessDenied(false, $e->getMessage());
        }

        $request = $this->requestStack->getCurrentRequest();
        $action  = $this->routeProvider->buildSaveRoute($objectId);
        $form    = $this->formFactory->create(
            CustomObjectType::class,
            $customObject,
            ['action' => $action]
        );
        $form->handleRequest($request);

        if ($form->isValid()) {
            $this->handleRawPost($customObject, $request->get('custom_object'));

            $this->customObjectModel->save($customObject);

            $this->flashBag->add(
                $objectId ? 'mautic.core.notice.updated' : 'mautic.core.notice.created',
                [
                    '%name%' => $customObject->getName(),
                    '%url%'  => $this->routeProvider->buildEditRoute($objectId),
                ]
            );

            if ($form->get('buttons')->get('save')->isClicked()) {
                if (!$this->customObjectModel->isLocked($customObject)) {
                    // New entity should be locked after save
                    $this->customObjectModel->lockEntity($customObject);
                }

                return $this->forwardToDetail($request, $customObject);
            }

            return $this->redirect($this->routeProvider->buildEditRoute($customObject->getId()));
        }

        return $this->delegateView(
            [
                'returnUrl'      => $this->routeProvider->buildListRoute(),
                'viewParameters' => [
                    'customObject'        => $customObject,
                    'availableFieldTypes' => $this->customFieldTypeProvider->getTypes(),
                    'customFields'        => $this->customFieldModel->fetchCustomFieldsForObject($customObject),
                    'deletedFields'       => [],
                    'form'                => $form->createView(),
                ],
                'contentTemplate' => 'CustomObjectsBundle:CustomObject:form.html.php',
                'passthroughVars' => [
                    'mauticContent' => 'customObject',
                    'route'         => $objectId ? $this->routeProvider->buildEditRoute($customObject->getId()) : $this->routeProvider->buildNewRoute(),
                ],
            ]
        );
    }

    /**
     * @param CustomObject $customObject
     * @param string[]     $rawCustomObject
     */
    private function handleRawPost(CustomObject $customObject, array $rawCustomObject): void
    {
        if (empty($rawCustomObject['customFields'])) {
            return;
        }

        foreach ($rawCustomObject['customFields'] as $key => $rawCustomField) {
            if ($rawCustomField['deleted'] && $rawCustomField['id']) {
                // Remove deleted custom fields
                $this->customObjectModel->removeCustomFieldById($customObject, (int) $rawCustomField['id']);
            } else {
                // Should be resolved better in form/transformer, but here it is more clear
                $params = $rawCustomField['params'];
                $params = $this->paramsToStringTransformer->reverseTransform($params);

                $options = $rawCustomField['options'];
                $options = $this->optionsToStringTransformer->reverseTransform($options);

                /** @var CustomField $customField */
                $customField = $customObject->getCustomFields()->get($key);
                $customField->setParams($params);
                $customField->setOptions($options);
            }
        }
    }

    /**
     * @param Request      $request
     * @param CustomObject $customObject
     *
     * @return Response
     */
    private function forwardToDetail(Request $request, CustomObject $customObject): Response
    {
        $request->setMethod('GET');
        $params = ['objectId' => $customObject->getId()];

        $this->customObjectModel->unlockEntity($customObject);

        return $this->forward('CustomObjectsBundle:CustomObject\View:view', $params);
    }
}
