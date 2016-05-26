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

    /** Triggered after item removed from cart */
    const AFTER_ITEM_REMOVE = 'afterItemRemove';

    /** Triggered before item added, updated, or removed from cart */
    const BEFORE_CART_CHANGE = 'beforeCartChange';

    /** Triggered before item removed from cart */
    const BEFORE_ITEM_REMOVE = 'beforeItemRemove';

    /** Triggered after calculating cost */
    const COST_CALCULATION = 'costCalculation';

    /** Triggered when item added to cart */
    const ITEM_ADD = 'itemAdd';

    /** Triggered when item updated */
    const ITEM_UPDATE = 'itemUpdate';

    /** Triggered when all items removed */
    const REMOVE_ALL = 'removeAll';
    
    const ACTION_SET_ITEMS = 'setItems';
    const ACTION_UPDATE = 'update';

    /**
     * Item on the cart that was affected. Could be null if action deals with all items.
     * @var CartItemInterface
     */
    public $item;
}