<?php

namespace nicklaros\yii2cart;

/**
 * Interface CartInterface
 */
interface CartInterface
{
    /**
     * Process payment. Returns true if payment operation is successful
     *
     * @return boolean
     */
    public function pay();

}