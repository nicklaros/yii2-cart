<?php

namespace nicklaros\yii2cart;

use yii\base\Behavior;

/**
 * Class CartDiscountBehavior
 */
class CartDiscountBehavior extends Behavior
{
    public function events()
    {
        return [
            CartEvent::PRICE_CALCULATION => 'onPriceCalculation',
        ];
    }

    /**
     * @param CartEvent $event
     */
    public function onPriceCalculation($event)
    {
        // Add logic here to calculate amount of discount
        $event->discount = 0;
    }
}