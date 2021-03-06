<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

/**
 * Class Commerce_SetVariantValuesElementAction
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.elementactions
 * @since     1.0
 */
class Commerce_SetVariantValuesElementAction extends BaseElementAction
{

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Set Variant Values…');
    }

    /**
     * @inheritDoc IElementAction::isDestructive()
     *
     * @return bool
     */
    public function isDestructive()
    {
        return true;
    }

    /**
     * @inheritDoc IElementAction::getTriggerHtml()
     *
     * @return string|null
     */
    public function getTriggerHtml()
    {
        $js = <<<EOT
(function()
{
	var trigger = new Craft.ElementActionTrigger({
		handle: 'Commerce_SetVariantValues',
		batch: true,
		activate: function(\$selectedItems)
		{
			var modal = new Craft.SetVariantValuesModal(Craft.elementIndex.getSelectedElementIds(), {
				onSubmit: function()
				{
					Craft.elementIndex.submitAction('Commerce_SetVariantValues', Garnish.getPostData(modal.\$container));
					modal.hide();

					return false;
				}
			});
		}
	});
})();
EOT;

        craft()->templates->includeJs($js);
    }


    /**
     * @inheritDoc IElementAction::performAction()
     *
     * @param ElementCriteriaModel $criteria
     *
     * @return bool
     */
    public function performAction(ElementCriteriaModel $criteria)
    {
        $variants = $criteria->find();
        $attributes = ['price', 'minQty', 'maxQty', 'width', 'height', 'length', 'weight'];

        CommerceDbHelper::beginStackedTransaction();
        try {
            foreach ($variants as $variant) {
                foreach ($attributes as $attribute) {
                    $set = $this->getParams()->{'set' . ucfirst($attribute)};
                    if ($set) {
                        $variant->$attribute = $this->getParams()->$attribute;
                    }
                }
                craft()->commerce_variants->saveVariant($variant);
            }
        } catch (Exception $e) {
            CommerceDbHelper::rollbackStackedTransaction();
            $this->setMessage(Craft::t('Error'));

            return false;
        }

        CommerceDbHelper::commitStackedTransaction();
        $this->setMessage(Craft::t('Variants updated.'));

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc BaseElementAction::defineParams()
     *
     * @return array
     */
    protected function defineParams()
    {
        return [
            'setPrice' => AttributeType::Bool,
            'price' => [AttributeType::Number, 'decimals' => 4],
            'setMinQty' => AttributeType::Bool,
            'minQty' => [AttributeType::Number, 'decimals' => 4],
            'setMaxQty' => AttributeType::Bool,
            'maxQty' => [AttributeType::Number, 'decimals' => 4],
            'setWidth' => AttributeType::Bool,
            'width' => [AttributeType::Number, 'decimals' => 4],
            'setHeight' => AttributeType::Bool,
            'height' => [AttributeType::Number, 'decimals' => 4],
            'setLength' => AttributeType::Bool,
            'length' => [AttributeType::Number, 'decimals' => 4],
            'setWeight' => AttributeType::Bool,
            'weight' => [AttributeType::Number, 'decimals' => 4],
        ];
    }
}
