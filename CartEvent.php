<?php

namespace nicklaros\yii2cart;

use yii\base\Event;

/**
 * Class CartActionEvent
 */
class CartEvent extends Event
{
    /** Triggered after item added, updated, or removed from cart */
    const AFTER_CART_CHANGE = 'afterCartChange';

    /** Triggered after item added to cart */
    const AFTER_ITEM_ADD = 'afterItemAdd';

    /** Triggered after item removed from cart */
    const AFTER_ITEM_REMOVE = 'afterItemRemove';

    /** Triggered after all items removed */
    const AFTER_REMOVE_ALL = 'removeAll';

    /** Triggered before item added, updated, or removed from cart */
    const BEFORE_CART_CHANGE = 'beforeCartChange';

    /** Triggered before item added to cart */
    const BEFORE_ITEM_ADD = 'beforeItemAdd';

    /** Triggered before item removed from cart */
    const BEFORE_ITEM_REMOVE = 'beforeItemRemove';

    /** Triggered before all items removed */
    const BEFORE_REMOVE_ALL = 'removeAll';

    /** Triggered when item updated */
    const ITEM_UPDATE = 'itemUpdate';

    /** Triggered after calculating price */
    const PRICE_CALCULATION = 'priceCalculation';

    /**
     * Amount of discount for the cart or an item. You can set it by applying DiscountBehavior
     * @var int
     */
    public $discount = 0;
    
    /**
     * Item on the cart that was affected. Could be null if action deals with all items.
     * @var CartItemInterface
     */
    public $item;
    
    /**
     * Original price of the cart or an item that was calculated without discount
     * @var int
     */
    public $price;

}