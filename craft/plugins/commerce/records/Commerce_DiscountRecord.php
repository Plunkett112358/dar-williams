<?php

namespace Craft;

/**
 * Discount record.
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $code
 * @property int $perUserLimit
 * @property int $totalUseLimit
 * @property int $totalUses
 * @property DateTime $dateFrom
 * @property DateTime $dateTo
 * @property int $purchaseTotal
 * @property int $purchaseQty
 * @property float $baseDiscount
 * @property float $perItemDiscount
 * @property float $percentDiscount
 * @property bool $excludeOnSale
 * @property bool $freeShipping
 * @property bool $allGroups
 * @property bool $allProducts
 * @property bool $allProductTypes
 * @property bool $enabled
 *
 * @property Commerce_ProductRecord[] $products
 * @property Commerce_ProductTypeRecord[] $productTypes
 * @property UserGroupRecord[] $groups
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_DiscountRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_discounts';
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'groups' => [
                static::MANY_MANY,
                'UserGroupRecord',
                'commerce_discount_usergroups(discountId, userGroupId)'
            ],
            'products' => [
                static::MANY_MANY,
                'Commerce_ProductRecord',
                'commerce_discount_products(discountId, productId)'
            ],
            'productTypes' => [
                static::MANY_MANY,
                'Commerce_ProductTypeRecord',
                'commerce_discount_producttypes(discountId, productTypeId)'
            ],
        ];
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['code'], 'unique' => true],
            ['columns' => ['dateFrom']],
            ['columns' => ['dateTo']],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'name' => [AttributeType::Name, 'required' => true],
            'description' => AttributeType::Mixed,
            'code' => AttributeType::String,
            'perUserLimit' => [
                AttributeType::Number,
                'required' => true,
                'min' => 0,
                'default' => 0
            ],
            'totalUseLimit' => [
                AttributeType::Number,
                'required' => true,
                'min' => 0,
                'default' => 0
            ],
            'totalUses' => [
                AttributeType::Number,
                'required' => true,
                'min' => 0,
                'default' => 0
            ],
            'dateFrom' => AttributeType::DateTime,
            'dateTo' => AttributeType::DateTime,
            'purchaseTotal' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0
            ],
            'purchaseQty' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0
            ],
            'baseDiscount' => [
                AttributeType::Number,
                'decimals' => 5,
                'required' => true,
                'default' => 0
            ],
            'perItemDiscount' => [
                AttributeType::Number,
                'decimals' => 5,
                'required' => true,
                'default' => 0
            ],
            'percentDiscount' => [
                AttributeType::Number,
                'decimals' => 5,
                'required' => true,
                'default' => 0
            ],
            'excludeOnSale' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 0
            ],
            'freeShipping' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 0
            ],
            'allGroups' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 0
            ],
            'allProducts' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 0
            ],
            'allProductTypes' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 0
            ],
            'enabled' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 1
            ],
        ];
    }

}
