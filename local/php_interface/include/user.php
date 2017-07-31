<?php

AddEventHandler("main", "OnBeforeUserRegister", Array("TranzitUsers", "OnBeforeUserRegisterHandler"));
AddEventHandler("main", "OnBeforeUserUpdate", Array("TranzitUsers", "OnBeforeUserUpdateHandler"));

class TranzitUsers
{
    // создаем обработчик события "OnBeforeUserRegister"
    function OnBeforeUserRegisterHandler(&$arFields)
    {
        #print_r($arFields);

        $arFields['CONFIRM_PASSWORD'] = $arFields['PASSWORD'];
    }
    
    function OnBeforeUserUpdateHandler(&$arFields)
    {
        global $APPLICATION, $USER;

        if (!is_object($USER)) 
            $USER = new CUser;
        
        $RIGHT = $APPLICATION->GetGroupRight("main");
        //проверка - если есть права , то не запрашивать стрый пароль (для работы через адинку)

        //проверка старого пароля
        if(strlen($arFields['PASSWORD']) > 0 && strlen($arFields['CONFIRM_PASSWORD']) > 0 && $RIGHT < 'P')
        {
            if(strlen($_REQUEST['LAST_PASSWORD']) == 0)
            {    
                $APPLICATION->throwException('Вы не ввели старый пароль.');
                return false;
            }
            else
            {    
                $arLogin = $USER->Login($USER->GetLogin(), $_REQUEST['LAST_PASSWORD'], "Y" );
                
                if($arLogin['TYPE'] == 'ERROR') 
                {
                    $APPLICATION->throwException('Вы ввели неверный старый пароль.');
                    return false;
                }
            }
        }
    }
}