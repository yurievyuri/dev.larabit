<?php

namespace Dev\Larabit;

use Bitrix\Rest\APAuth\PasswordTable;
use Bitrix\Rest\APAuth\PermissionTable;
use Bitrix\Rest\Preset\IntegrationTable;
use \Bitrix\Main\Localization\Loc;

\Bitrix\Main\Localization\Loc::loadMessages(__FILE__);

class Hooks
{
    public static function install(): bool
    {
        $title = Loc::getMessage('DEV_LARABIT_INBOUND_HOOK_NAME');
        $comment = Loc::getMessage('DEV_LARABIT_INBOUND_HOOK_DESCRIPTION');
        $password = \Bitrix\Main\Security\Random::getString(16);
        $arScope = \Dev\Larabit\Scope::get();

        $res = PasswordTable::add([
            'USER_ID' => \Dev\Larabit\Option::CONF_USER_ID,
            'PASSWORD' => $password,
            'ACTIVE' => 'Y',
            'TITLE' => $title,
            'COMMENT' => $comment,
            'DATE_CREATE' => new \Bitrix\Main\Type\DateTime(),
            //'DATE_LOGIN' => '',
            //'LAST_IP' => ''
        ]);

        if (!$res->isSuccess()) {
            throw new \Exception(implode(', ', $res->getErrorMessages()));
        }

        $passwordId = $res->getId();

        // make arscope array with data
        foreach ($arScope as $item) {
            $arScopeNew[] = array(
                'PASSWORD_ID' => $passwordId,
                'PERM' => $item
            );
        }
        if ($arScopeNew) {
            $res = PermissionTable::addMulti($arScopeNew)->isSuccess();
        }

        $res = IntegrationTable::update(
            (int) IntegrationTable::getList(['filter' => ['=PASSWORD_ID' => $passwordId], 'select' => ['ID']])->fetch()['ID'],
            [
                'USER_ID' => \Dev\Larabit\Option::CONF_USER_ID,
                'ELEMENT_CODE' => \Bitrix\Rest\Preset\Data\Element::DEFAULT_IN_WEBHOOK,
                'TITLE' => $title,
                //'PASSWORD_ID' => $passwordId,
                //'APP_ID' => null,
                'SCOPE' => $arScope,
                //'QUERY' => [],
                //'OUTGOING_EVENTS' => ,
                'OUTGOING_NEEDED' => 'N',
                //'OUTGOING_HANDLER_URL',
                'WIDGET_NEEDED' => 'N',
                //'WIDGET_HANDLER_URL',
                //'WIDGET_LIST',
                'APPLICATION_TOKEN' => \Bitrix\Main\Security\Random::getString(32),
                'APPLICATION_NEEDED' => 'N',
                'APPLICATION_ONLY_API' => 'N',
                //'BOT_ID',
                //'BOT_HANDLER_URL'
            ]);

        if (!$res->isSuccess()) {
            throw new \Exception(implode(', ', $res->getErrorMessages()));
        }
        \Bitrix\Main\Config\Option::set(\Dev\Larabit\Option::CONF_MODULE_ID, \Dev\Larabit\Option::CONF_INBOUND_HOOK_ID, $passwordId);
        \Bitrix\Main\Config\Option::set(\Dev\Larabit\Option::CONF_MODULE_ID, \Dev\Larabit\Option::CONF_INBOUND_HOOK_PASSWORD, $password);

        return true;
    }

    public static function uninstall(): bool
    {
        $passwordId = (int) \Bitrix\Main\Config\Option::get(\Dev\Larabit\Option::CONF_MODULE_ID, \Dev\Larabit\Option::CONF_INBOUND_HOOK_ID);
        if ( !$passwordId ) return false;

        PasswordTable::delete($passwordId);

        $db = PermissionTable::getList(['filter' => ['=PASSWORD_ID' => $passwordId], 'select' => ['ID']]);
        while( $list = $db->fetch() )
        {
            PermissionTable::add($list['ID']);
        }

        $integrId = (int) IntegrationTable::getList(['filter' => ['=PASSWORD_ID' => $passwordId], 'select' => ['ID']])->fetch()['ID'];
        if ( !$integrId ) return false;
        return IntegrationTable::delete($integrId)->isSuccess();
    }
}