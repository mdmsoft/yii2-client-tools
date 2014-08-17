<?php

namespace mdm\clienttools;

use yii\web\Cookie;
use Yii;
use yii\base\InvalidCallException;

/**
 * Description of ClientBehavior
 *
 * @property string $clientId
 *
 * @author MDMunir <misbahuldmunir@gmail.com>
 */
class ClientBehavior extends \yii\base\Behavior
{
    /**
     *
     * @var integer
     */
    public $expire = 31536000; // default 1 year
    /**
     *
     * @var string
     */
    public $cookieKey = '_client_identity';
    private $_clientId;

    public function init()
    {
        parent::init();
        $cookie = Yii::$app->getRequest()->cookies->get($this->cookieKey);
        if ($cookie) {
            $this->_clientId = $cookie->value;
        } else {
            $str = microtime(true);
            if (($session = Yii::$app->getSession()) !== null) {
                $str .= $session->id;
            }
            $this->_clientId = md5($str . ':' . microtime(true));
            $cookie = new Cookie();
            $cookie->name = $this->cookieKey;
            $cookie->value = $this->_clientId;
        }
        $cookie->expire = time() + $this->expire;
        Yii::$app->getResponse()->cookies->add($cookie);
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        return stripos($name, 'client') === 0 || parent::canGetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        return (stripos($name, 'client') === 0 && strcasecmp($name, 'clientId') != 0) || parent::canSetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        if (stripos($name, 'client') === 0) {
            return strcasecmp($name, 'clientId') == 0 ? $this->_clientId : $this->getClientProperty(strtolower($name));
        } else {
            return parent::__get($name);
        }
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if (stripos($name, 'client') === 0) {
            if (strcasecmp($name, 'clientId') == 0) {
                throw new InvalidCallException('Setting read-only property: ' . get_class($this) . '::' . $name);
            }
            $this->setClientProperty(strtolower($name), $value);
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function __isset($name)
    {
        if (stripos($name, 'client') === 0) {
            return strcasecmp($name, 'clientId') == 0 || $this->getClientProperty(strtolower($name)) !== null;
        } else {
            return parent::__isset($name);
        }
    }

    /**
     * @inheritdoc
     */
    public function __unset($name)
    {
        if (stripos($name, 'client') === 0) {
            if (strcasecmp($name, 'clientId') == 0) {
                throw new InvalidCallException('Setting read-only property: ' . get_class($this) . '::' . $name);
            }
            $this->setClientProperty(strtolower($name), null);
        } else {
            return parent::__unset($name);
        }
    }

    private function buildKey($key)
    {
        return [
            __CLASS__,
            $this->_clientId,
            $key
        ];
    }

    private function setClientProperty($key, $value)
    {
        if (($cache = Yii::$app->cache) !== null) {
            $cache->set($this->buildKey($key), $value);
        }
    }

    private function getClientProperty($key)
    {
        if (($cache = Yii::$app->cache) !== null) {
            $result = $cache->get($this->buildKey($key));

            return $result === false ? null : $result;
        }

        return null;
    }
}
