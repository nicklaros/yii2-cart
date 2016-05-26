<?php

namespace nicklaros\yii2cart;

/**
 * Interface CartItemInterface
 * @property int $price
 * @property int $cost
 * @property string $id
 * @property int $quantity
 */
interface CartItemInterface
{
    /**
     * @return integer
     */
    public function getPrice();

    /**
     * @param bool $withDiscount
     * @return integer
     */
    public function getCost($withDiscount = true);

    /**
     * @return string
     */
    public function getId();

    /**
     * @param int $quantity
     */
    public function setQuantity($quantity);

    /**
     * @return int
     */
    public function getQuantity();

    /**
     * Save to db
     */
    public function save();
}