<?php

namespace nicklaros\yii2cart;

use Yii;
use yii\base\Component;
use yii\base\Model;
use yii\di\Instance;
use yii\web\Session;

/**
 * Class ShoppingCart
 *
 * @property integer $count Total count of items in the cart
 * @property integer $cost Total cost of items in the cart
 * @property string $hash
 * @property boolean $isEmpty
 * @property boolean $isSaved Returns true if cart already saved to db, otherwise return false
 * @property string $serialized
 */
class ShoppingCart extends Component
{
    /**
     * Model that represent cart data after saved to database
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
            'item' => $this->items[$item->getId()],
        ]));

        if (isset($this->items[$item->getId()])) {
            $this->items[$item->getId()]->setQuantity(
                $this->items[$item->getId()]->getQuantity() + $quantity
            );
        } else {
            $item->setQuantity($quantity);
            $this->items[$item->getId()] = $item;
        }

        $this->trigger(CartEvent::ITEM_ADD, new CartEvent([
            'item' => $this->items[$item->getId()],
        ]));

        $this->trigger(CartEvent::AFTER_CART_CHANGE, new CartEvent([
            'item' => $this->items[$item->getId()],
        ]));

        if ($this->storeInSession) {
            $this->saveToSession();
        }
    }

    /**
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
     * Returns full cart cost as a sum of the individual items costs
     *
     * @param $withDiscount
     * @return integer
     */
    public function getCost($withDiscount = false)
    {
        $cost = 0;

        foreach ($this->items as $item) {
            $cost += $item->getCost($withDiscount);
        }

        $costEvent = new CostCalculationEvent([
            'baseCost' => $cost,
        ]);

        $this->trigger(CartEvent::COST_CALCULATION, $costEvent);

        if ($withDiscount) {
            $cost = max(0, $cost - $costEvent->discountValue);
        }

        return $cost;
    }

    /**
     * Returns hash (md5) of the current cart, that is unique to the current combination of items,
     * quantities and costs. This helps us fast compare if two carts are the same, or not, also
     * we can detect if cart is changed (comparing hash to the one's saved somewhere)
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
     * Returns true if cart already saved to db
     *
     * @return boolean
     */
    public function getIsSaved()
    {
        if (!$this->info->id) {
            return false;
        }

        $count = Order::find()
            ->where(['id' => $this->info->id])
            ->count();

        return $count != 0;
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
     * Returns cart items as a storable representation of a value
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
     * @param string $id
     * @return bool
     */
    public function hasItem($id)
    {
        return isset($this->items[$id]);
    }

    /**
     * Loads cart from session
     */
    public function loadFromSession()
    {
        $this->session = Instance::ensure($this->session, Session::className());
        $this->model = Instance::ensure($this->model, Order::className());

        if (isset($this->session[$this->cartId])) {
            $this->setSerialized($this->session[$this->cartId]);
        }
    }

    /**
     * Process payment
     *
     * @return boolean
     */
    public function pay()
    {
        return true;
    }

    /**
     * Removes item from the cart
     *
     * @param CartItemInterface $item
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
        $this->items = [];

        $this->trigger(CartEvent::REMOVE_ALL, new CartEvent);

        if ($this->storeInSession) {
            $this->saveToSession();
        }
    }

    /**
     * Removes item from the cart by ID
     *
     * @param string $id
     */
    public function removeById($id)
    {
        $this->trigger(CartEvent::BEFORE_ITEM_REMOVE, new CartEvent([
            'item' => $this->items[$id],
        ]));

        $this->trigger(CartEvent::AFTER_CART_CHANGE, new CartEvent([
            'item' => $this->items[$id],
        ]));

        unset($this->items[$id]);

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
     * Sends notification about new transaction checkout
     */
    public function sendCheckoutNotification()
    {
        $info = $this->info;

        // Send notification to user
        $from = Yii::$app->params['adminEmail'];
        $to = $info->email;
        $subject = '[Golden Rama] Detail Pesanan Anda dengan No ' . $info->no;

        Yii::$app->mailer->compose('user-notification-new-checkout', [
            'order' => $info,
            'paymentType' => $this->paymentType,
        ])
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject)
            ->send();

        // Send notification to admin
        $from = Yii::$app->params['adminEmail'];
        $to = Yii::$app->params['adminEmail'];
        $subject = '[New Order] No. Pesanan ' . $info->no;

        Yii::$app->mailer->compose('admin-notification-new-checkout', [
            'order' => $info,
            'paymentType' => $this->paymentType,
        ])
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject)
            ->send();
    }

    /**
     * Sends notification about successful transaction payment
     */
    public function sendPaidNotification()
    {
        $info = $this->info;

        // Send notification to user
        $from = Yii::$app->params['adminEmail'];
        $to = $info->email;
        $subject = '[Golden Rama] Pembayaran Berhasil Untuk No. Pesanan ' . $info->no;

        Yii::$app->mailer->compose('user-notification-payment', [
            'order' => $info,
            'paymentType' => $this->paymentType,
        ])
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject)
            ->send();

        // Send notification to admin
        $from = Yii::$app->params['adminEmail'];
        $to = Yii::$app->params['adminEmail'];
        $subject = '[New Order] No. Pesanan ' . $info->no;

        Yii::$app->mailer->compose('admin-notification-payment', [
            'order' => $info,
            'paymentType' => $this->paymentType,
        ])
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject)
            ->send();
    }

    /**
     * Sets cart information. The information provided here is represent model's data for this cart.
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
     * Sets items in the cart
     *
     * @param CartItemInterface[] $items
     */
    public function setItems($items)
    {
        $this->items = array_filter($items, function (CartItemInterface $item) {
            return $item->quantity > 0;
        });

        $this->trigger(CartEvent::AFTER_CART_CHANGE, new CartEvent);

        if ($this->storeInSession) {
            $this->saveToSession();
        }
    }

    /**
     * Sets cart from serialized string
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
     * Updates item in the cart
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

        if (isset($this->items[$item->getId()])) {
            $this->items[$item->getId()]->setQuantity($quantity);
        } else {
            $item->setQuantity($quantity);
            $this->items[$item->getId()] = $item;
        }

        $this->trigger(CartEvent::ITEM_UPDATE, new CartEvent([
            'item' => $this->items[$item->getId()],
        ]));

        $this->trigger(CartEvent::AFTER_CART_CHANGE, new CartEvent([
            'item' => $this->items[$item->getId()],
        ]));

        if ($this->storeInSession) {
            $this->saveToSession();
        }
    }

    /**
     * Saves cart to database
     *
     * @return boolean whether save operation is successful or not
     */
    private function saveToDb()
    {
        $amount = $this->getCost(true);
        $adminFee = $this->paymentType->getFee($amount);

        $this->setInfo([
            'payment_type_id' => $this->paymentType->id,
            'amount' => $amount,
            'admin_fee' => $adminFee,
            'final_amount' => $amount + $adminFee,
            'no' => Order::generateOrderNumber(),
        ]);

        $success = $this->info->save();

        foreach ($this->items as $item) {
            $item->order_id = $this->info->id;

            $success = $item->save();
        }

        return $success;
    }

}
