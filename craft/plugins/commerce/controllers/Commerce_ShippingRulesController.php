<?php
namespace Craft;

/**
 * Class Commerce_ShippingRulesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_ShippingRulesController extends Commerce_BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        if (!craft()->userSession->getUser()->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
        }

        $methodsExist = craft()->commerce_shippingMethods->ShippingMethodExists();
        $shippingRules = craft()->commerce_shippingRules->getAllShippingRules([
            'order' => 't.methodId, t.name',
            'with' => ['method', 'country', 'state'],
        ]);
        $this->renderTemplate('commerce/settings/shippingrules/index', compact('shippingRules', 'methodsExist'));
    }

    /**
     * Create/Edit Shipping Rule
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (!craft()->userSession->getUser()->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
        }

        if (empty($variables['methodId'])) {
            throw new HttpException(404);
        }

        $variables['shippingMethod'] = craft()->commerce_shippingMethods->getShippingMethodById($variables['methodId']);

        if (empty($variables['shippingMethod'])) {
            throw new HttpException(404);
        }

        if (empty($variables['shippingRule'])) {
            if (!empty($variables['ruleId'])) {
                $id = $variables['ruleId'];
                $variables['shippingRule'] = craft()->commerce_shippingRules->getShippingRuleById($id);

                if (!$variables['shippingRule']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingRule'] = new Commerce_ShippingRuleModel();
            }
        }

        $variables['countries'] = ['' => ''] + craft()->commerce_countries->getAllCountriesListData();
        $variables['states'] = craft()->commerce_states->getStatesGroupedByCountries();

        if (!empty($variables['ruleId'])) {
            $variables['title'] = $variables['shippingRule']->name;
        } else {
            $variables['title'] = Craft::t('Create a new shipping rule');
        }

        $this->renderTemplate('commerce/settings/shippingrules/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        if (!craft()->userSession->getUser()->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
        }

        $this->requirePostRequest();

        $shippingRule = new Commerce_ShippingRuleModel();

        // Shared attributes
        $fields = ['id', 'name', 'description', 'countryId', 'stateId', 'methodId', 'enabled', 'minQty', 'maxQty', 'minTotal', 'maxTotal',
            'minWeight', 'maxWeight', 'baseRate', 'perItemRate', 'weightRate', 'percentageRate', 'minRate', 'maxRate'];
        foreach ($fields as $field) {
            $shippingRule->$field = craft()->request->getPost($field);
        }

        // Save it
        if (craft()->commerce_shippingRules->saveShippingRule($shippingRule)) {
            craft()->userSession->setNotice(Craft::t('Shipping rule saved.'));
            $this->redirectToPostedUrl($shippingRule);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save shipping rule.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(['shippingRule' => $shippingRule]);
    }

    /**
     * @return null
     * @throws HttpException
     */
    public function actionReorder()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $ids = JsonHelper::decode(craft()->request->getRequiredPost('ids'));
        $success = craft()->commerce_shippingRules->reorderShippingRules($ids);

        return $this->returnJson(['success' => $success]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->commerce_shippingRules->deleteShippingRuleById($id);
        $this->returnJson(['success' => true]);
    }

}
