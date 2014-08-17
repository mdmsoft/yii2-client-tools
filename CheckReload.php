<?php

namespace mdm\clienttools;

use yii\helpers\Html;

/**
 * Description of CheckReload
 *
 * @author Misbahul D Munir (mdmunir) <misbahuldmunir@gmail.com>
 */
class CheckReload
{
    const TOKEN_NAME = '_MDM_CHECK_RELOAD';

    public static function generateToken()
    {
        $session = \Yii::$app->getSession();
        $session[static::TOKEN_NAME] = $value = md5(microtime(true) . mt_rand(0, 1000));

        return Html::tag('div', Html::hiddenInput(static::TOKEN_NAME, $value, ['id' => false]), ['style' => 'display:none;']);
    }

    public static function check()
    {
        $session = \Yii::$app->getSession();
        $token = \Yii::$app->getRequest()->post(static::TOKEN_NAME);
        $value = $session->get(static::TOKEN_NAME);
        $session->set(static::TOKEN_NAME, md5(time() . static::TOKEN_NAME));

        return $token === $value;
    }
}
