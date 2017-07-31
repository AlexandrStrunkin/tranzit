<?

AddEventHandler('form', 'onBeforeResultAdd',     array("TranziOilForm", "onBeforeResultAdd"), 10);
#AddEventHandler('form', 'onAfterResultAdd',     array("TranziOilForm", "onAfterResultAdd"), 10);
#AddEventHandler('main', 'OnBeforeEventAdd',     array("TranziOilForm", "OnBeforeEventAdd"), 1); //отправка почты - добавляем инфо


class TranziOilForm 
{
    function onBeforeResultAdd($WEB_FORM_ID, $arFields, $arrVALUES)
    {
        #=============================================================================
        # проверка на бота - не было входа или отправка формы через 10 сек
        # в форме необходимо сохранить время в куках
        # $APPLICATION->set_cookie("FORM_OPENED", time());
        #=============================================================================
        
        global $APPLICATION;
        $is_bot = false;
        
        $last_opened = $APPLICATION->get_cookie("FORM_OPENED_".$WEB_FORM_ID);
        $last_opened = intval($last_opened);

        $time = time() - $last_opened;

        if($last_opened == 0 || $time < 7)
            $is_bot = true;

        if($is_bot || !empty($_REQUEST['YOUR_EMAIL_ADRES']))
            $APPLICATION->ThrowException('Данные не отправлены. Попробуйте еще раз...');
        #=============================================================================
    }
    
    function onAfterResultAdd($WEB_FORM_ID, $RESULT_ID)
    {
    
    }
    
    function OnBeforeEventAdd(&$event, &$lid, &$arFields)
    {
    
    }
}
