<?php

namespace nicklaros\yii2cart;

use Yii;
use yii\base\Component;
use yii\base\Model;
use yii\di\Instance;
use yii\web\Session;

/**
 * Class Cart
 *
 * @property integer $count Total count of items in the cart
 * @property integer $cost Total cost of items in the cart
 * @property string $hash Unique hash value of the cart instance
 * @property Model $info Cart information
 * @property boolean $isEmpty Whether cart is empty
 * @property string $serialized Storable representation of the cart data
 */
class Cart extends Component implements CartInterface
{
    /**
     * Model that represent cart information
     * @var string|Model
     */
    protected $model = '';

    /**
     * @var CartItemInterface[]
     */
    protected $items = [];

    /**
     * Shopping cart ID to support multiple carts
     * @var string
     */
    public $cartId = __CLASS__;

    /**
     * Session component
     * @var string|Session
     */
    public $session = 'session';

    /**
     * If true, cart will be automatically stored in and loaded from session.
     * If false, you should do this manually with saveToSession and loadFromSession methods
     * @var bool
     */
    public $storeInSession = true;

    public function init()
    {
        if ($this->storeInSession) {
            $this->loadFromSession();
        }
    }

    /**
     * Add item to the cart
     *
     * @param CartItemInterface $item
     * @param int $quantity
     */
    public function add($item, $quantity = 1)
    {
        $this->trigger(CartEvent::BEFORE_CART_CHANGE, new CartEvent([
            'item' => $item,
        ]));

        $this->trigger(CartEvent::BEFORE_ITEM_ADD, new CartEvent([
            'item' => $item,
        ]));

        if (isset($this->items[$item->getId()])) {
            $this->items[$item->getId()]->setQuantity(
                $this->items[$item->getId()]->getQuantity() + $quantity
            );
        } else {
            $item->setQuantity($quantity);
            $this->items[$item->getId()] = $item;
        }

        $this->trigger(CartEvent::AFTER_ITEM_ADD, new CartEvent([
            'item' => $item,
        ]));

        $this->trigger(CartEvent::AFTER_CART_CHANGE, new CartEvent([
            'item' => $item,
        ]));

        if ($this->storeInSession) {
            $this->saveToSession();
        }
    }

    /**
     * Returns items count in the cart
     *
     * @return integer
     */
    public function getCount()
    {
        $count = 0;

        foreach ($this->items as $item) {
            $count += $item->getQuantity();
        }

        return $count;
    }

    /**
     * Returns total price of all items in the cart
     *
     * @param boolean $withDiscount Whether to calculate discounted price
     * @return integer
     */
    public function getPrice($withDiscount = false)
    {
        $price = 0;

        foreach ($this->items as $item) {
            $price += $item->getPrice($withDiscount);
        }

        $event = new CartEvent([
            'price' => $price,
        ]);

        $this->trigger(CartEvent::PRICE_CALCULATION, $event);

        if ($withDiscount) {
            $price = max(0, $price - $event->discount);
        }

        return $price;
    }

    /**
     * Returns md5 hash of the current cart instance that is unique to the current combination of information,
     * items, quantities and costs.
     * The hash can be used to compare whether two carts are the same, or not. We can also detect if cart was changed 
     * by comparing current hash to the old one
     *
     * @return string
     */
    public function getHash()
    {
        $data = [];

        foreach ($this->items as $item) {
            $data[] = [$item->getId(), $item->getQuantity(), $item->getPrice()];
        }

        return md5(serialize($data));
    }

    /**
     * Returns cart information
     */
    public function getInfo()
    {
        return $this->model;
    }

    /**
     * Returns true if cart is empty
     *
     * @return boolean
     */
    public function getIsEmpty()
    {
        return count($this->items) == 0;
    }

    /**
     * Returns all items in the cart
     *
     * @return CartItemInterface[]
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Returns item by it's id. Null is returned if item was not found
     *
     * @param string $id
     * @return CartItemInterface|null
     */
    public function getItemById($id)
    {
        if ($this->hasItem($id)) {
            return $this->items[$id];
        } else {
            return null;
        }
    }

    /**
     * Returns cart data as a storable representation of a value
     *
     * @return string
     */
    public function getSerialized()
    {
        return serialize([
            'items' => $this->items,
            'model' => $this->model,
        ]);
    }

    /**
     * Checks whether cart item exists or not
     *
     * @param string $id Id of an item to check
     * @return boolean
     */
    public function hasItem($id)
    {
        return isset($this->items[$id]);
    }

    /**
     * Loads cart data from session
     */
    public function loadFromSession()
    {
        $this->session = Instance::ensure($this->session, Session::className());

        if (isset($this->session[$this->cartId])) {
            $this->setSerialized($this->session[$this->cartId]);
        }
    }

    /**
     * Process payment. Returns true if payment operation is successful
     *
     * @return boolean
     */
    public function pay()
    {
        // Add logic here to process payment
        return true;
    }

    /**
     * Removes an item from the cart
     *
     * @param CartItemInterface $item Item to remove
     */
    public function remove($item)
    {
        $this->removeById($item->getId());
    }

    /**
     * Removes all items from the cart
     */
    public function removeAll()
    {
        $event = new CartEvent;

        $this->trigger(CartEvent::BEFORE_REMOVE_ALL, $event);

        $this->items = [];

        $this->trigger(CartEvent::AFTER_REMOVE_ALL, $event);

        if ($this->storeInSession) {
            $this->saveToSession();
        }
    }

    /**
     * Removes an item from the cart by it's id
     *
     * @param integer $id
     */
    public function removeById($id)
    {
        if (!isset($this->items[$id])) {
            return;
        }

        $item = $this->items[$id];
        $event = new CartEvent([
            'item' => $item,
        ]);

        $this->trigger(CartEvent::BEFORE_CART_CHANGE, $event);
        $this->trigger(CartEvent::BEFORE_ITEM_REMOVE, $event);

        unset($this->items[$id]);

        $this->trigger(CartEvent::AFTER_ITEM_REMOVE, $event);
        $this->trigger(CartEvent::AFTER_CART_CHANGE, $event);

        if ($this->storeInSession) {
            $this->saveToSession();
        }
    }

    /**
     * Saves cart to the session
     */
    public function saveToSession()
    {
        $this->session = Instance::ensure($this->session, Session::className());
        $this->session[$this->cartId] = $this->getSerialized();
    }

    /**
     * Sets cart information. The information provided here represents data of model for cart instance
     *
     * @param array $data
     */
    public function setInfo($data)
    {
        $this->model->attributes = $data;

        if ($this->storeInSession) {
            $this->saveToSession();
        }
    }

    /**
     * Sets items in the cart. All items previously saved in the cart will be replaced by passed items
     *
     * @param CartItemInterface[] $items
     */
    public function setItems($items)
    {
        $this->trigger(CartEvent::BEFORE_CART_CHANGE, new CartEvent);

        $this->items = array_filter($items, function (CartItemInterface $item) {
            return $item->quantity > 0;
        });

        $this->trigger(CartEvent::AFTER_CART_CHANGE, new CartEvent);

        if ($this->storeInSession) {
            $this->saveToSession();
        }
    }

    /**
     * Sets cart data from serialized string
     *
     * @param string $serialized
     */
    public function setSerialized($serialized)
    {
        $data = unserialize($serialized);

        $this->items = $data['items'];
        $this->model = $data['model'];
    }

    /**
     * Updates an item in the cart
     *
     * @param CartItemInterface $item
     * @param int $quantity
     */
    public function update($item, $quantity)
    {
        if ($quantity <= 0) {
            $this->remove($item);
            return;
        }

        $event = new CartEvent([
            'item' => $item,
        ]);

        $this->trigger(CartEvent::BEFORE_CART_CHANGE, $event);

        if (isset($this->items[$item->getId()])) {
            $this->items[$item->getId()]->setQuantity($quantity);
        } else {
            $item->setQuantity($quantity);
            $this->items[$item->getId()] = $item;
        }

        $this->trigger(CartEvent::ITEM_UPDATE, $event);
        $this->trigger(CartEvent::AFTER_CART_CHANGE, $event);

        if ($this->storeInSession) {
            $this->saveToSession();
        }
    }

}
