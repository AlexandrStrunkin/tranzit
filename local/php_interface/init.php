<?
    ini_set('mbstring.func_overload', '2');
    ini_set('mbstring.internal_encoding', 'utf-8');

    #error_reporting(E_ALL | E_STRICT);
    #ini_set('display_errors', 'On');

    define('CATALOG_IBLOCK_ID', 73);                  
    define('EXCHANGE_1C_USER', 4);
    define('BONUS_PROGRAM', 17);

    function arshow($array, $adminCheck = false, $dieAfterArshow = false){
        global $USER;
        $USER = new Cuser;
        if ($adminCheck) {
            if (!$USER->IsAdmin()) {
                return false;
            }
        }
        echo "<pre>";
        print_r($array);
        echo "</pre>";
        if ($dieAfterArshow) {
            die();
        }
    }

    $aURI = Array();
    global $aURI;
    $pureURI = $_SERVER["REQUEST_URI"];
    if (substr_count($pureURI, "?")) {
        $pos = strpos($pureURI, "?");
        $pureURI = substr($pureURI, 0, $pos);
    }
    $aURI = explode("/", $pureURI);

    function pre($obj,$admOnly=true,$d=false){global $USER;if($USER->IsAdmin()||$admOnly===false){echo"<pre>";print_r($obj);echo"</pre>";if($d===true)die();}}

    $GLOBALS['APPLICATION']->AddHeadString('<script>var CATALOG_IBLOCK_ID='.CATALOG_IBLOCK_ID.';</script>',true);

    #log
    include(dirname(__FILE__).'/include/log.php');

    #theme
    include(dirname(__FILE__).'/include/site_controller.php');

    #form
    include(dirname(__FILE__).'/include/form.php');
    include(dirname(__FILE__).'/include/form_validator_email.php');

    #user
    include(dirname(__FILE__).'/include/user.php');

    #iblock
    #include(dirname(__FILE__).'/include/iblock.php');

?><?

    CModule::IncludeModule("catalog");
    CModule::IncludeModule("iblock");
    CModule::IncludeModule("sale");


    //AddEventHandler("sale", "OnSaleStatusOrder", Array("MyClass", "OnStatus")); // После добавления

    AddEventHandler('main', 'OnBeforeEventSend', "my_OnBeforeEventSend");

    AddEventHandler('main', 'OnAfterUserAdd', "my_OnAfterUserAdd"); // Добавление пользователя

    AddEventHandler("main", "OnAfterUserUpdate", "my_OnAfterUserUpdate");

    function my_OnAfterUserUpdate(&$arFields)
    {
        if($arFields['ACTIVE']=='Y'){
            $arEventFields = array(
                "EMAIL"       => $arFields['EMAIL'],
            );
            $arrSITE =  CAdvContract::GetSiteArray($CONTRACT_ID);

            CEvent::Send("ACTIVATE_UR",'s1', $arEventFields);
        }
    }

    function my_OnAfterUserAdd(&$arFields)
    {
        $arFields2 = Array("USER_ID" => $arFields[ID], "CURRENCY" => "RUB", "CURRENT_BUDGET" => 0);
        $accountID = CSaleUserAccount::Add($arFields2);
    }

    AddEventHandler('main', 'OnBeforeEventAdd', Array("MyMail", "my_OnBeforeEventAdd"));
    class MyMail
    {
        function my_OnBeforeEventAdd(&$event, &$lid, &$arFields, &$message_id)
        { 
            if($event=='SALE_NEW_ORDER'){

                if(CSite::InGroup( array(9))){
                    $event = 'SALE_NEW_ORDER_UR';
                }
            }
        }
    }
    AddEventHandler("sale", "OnSaleComponentOrderOneStepPersonType", "selectSavedPersonType");
    function selectSavedPersonType(&$arResult, &$arUserResult, $arParams)
    {
        if ( CSite::InGroup( array(9) )){
            $arResult['PERSON_TYPE'][1]['CHECKED'] = 'N';
            $arUserResult['PERSON_TYPE_ID'] = 3;
            $arResult['PERSON_TYPE'][3]['CHECKED'] = 'Y'; 	
        }else{
            $arResult['PERSON_TYPE'][1]['CHECKED'] = 'Y';
            $arUserResult['PERSON_TYPE_ID'] = 1;
            $arResult['PERSON_TYPE'][3]['CHECKED'] = 'N'; 	
        } 
    }


    function my_OnBeforeEventSend(&$arFields, $arTemplate)
    {


        if ( ($arFields[ORDER_ID]>0) && ($arFields[ORDER_DATE]!='') )
        {
            $arOrder = CSaleOrder::GetByID($arFields[ORDER_ID]);
            $arFields[PRICE]=CurrencyFormat($arOrder[PRICE], "RUB");

            /*		if ($arOrder[PRICE]<=0) 
            {
            // Удаляем нулевой заказ
            CSaleOrder::Delete($arFields[ORDER_ID]);		
            }
            */		
            if ($arOrder[PRICE]<=0) return false;



            $dbBasketItems = CSaleBasket::GetList(
                array(
                    "NAME" => "ASC",
                    "ID" => "ASC"
                ),
                array(
                    "LID" => SITE_ID,
                    "ORDER_ID" => $arFields[ORDER_ID]
                ),
                false,
                false,
                array("ID", "CALLBACK_FUNC", "MODULE", 
                    "PRODUCT_ID", "QUANTITY", "DELAY", 
                    "CAN_BUY", "PRICE", "WEIGHT","NOTES")
            );

            $OrderList='';

            while ($arItems = $dbBasketItems->Fetch())
            {	

                $res = CIBlockElement::GetByID($arItems[PRODUCT_ID]);
                $ar_res = $res->GetNext();

                $OrderList=$OrderList.$ar_res[NAME]." - ".$arItems[QUANTITY]." шт.: ".CurrencyFormat($arItems[PRICE]*$arItems[QUANTITY], "RUB")." \r\n";


            }
            $arFields[ORDER_LIST]=$OrderList;


        }

        // Изменим содержимое для письма



        /////////


    }



    //AddEventHandler("sale", "OnBeforeOrderAdd", Array("MyClass", "OrderTo")); // Разбить заказ на 2 части, те что есть и тех что нету



    AddEventHandler("catalog", "onBeforePriceUpdate", Array("MyClass", "OnPrice")); // После добавления

    class MyClass
    {

        function OrderTo(&$arFields)
        {


            $BasketSave=array(); // Массив с корзиной товаров с отрицательным колличеством

            //$f=fopen($_SERVER["DOCUMENT_ROOT"].'/log25.txt','a');
            //fwrite($f,print_r($arFields,true));
            //fclose($f);

            ////////

            // Выведем актуальную корзину для текущего пользователя

            $arBasketItems = array();

            $dbBasketItems = CSaleBasket::GetList(
                array(
                    "NAME" => "ASC",
                    "ID" => "ASC"
                ),
                array(
                    "FUSER_ID" => CSaleBasket::GetBasketUserID(),
                    "LID" => SITE_ID,
                    "ORDER_ID" => "NULL"
                ),
                false,
                false,
                array("ID", "CALLBACK_FUNC", "MODULE", 
                    "PRODUCT_ID", "QUANTITY", "DELAY", 
                    "CAN_BUY", "PRICE", "WEIGHT","NOTES")
            );
            	

            $allSum=$arFields[PRICE];

            while ($arItems = $dbBasketItems->Fetch())
            {
                if (strlen($arItems["CALLBACK_FUNC"]) > 0)
                {
                    CSaleBasket::UpdatePrice($arItems["ID"], 
                        $arItems["CALLBACK_FUNC"], 
                        $arItems["MODULE"], 
                        $arItems["PRODUCT_ID"], 
                        $arItems["QUANTITY"]);
                    $arItems = CSaleBasket::GetByID($arItems["ID"]);
                }
                
                $arBasketItems[] = $arItems;

                //$f=fopen($_SERVER["DOCUMENT_ROOT"].'/log28.txt','a');
                //fwrite($f,"Корзина ".print_r($arItems,true)."\r\n");
                //fclose($f);

                // Определяем для продукта колличеством

                $ar_res = CCatalogProduct::GetByID($arItems[PRODUCT_ID]); 

                $price = CPrice::GetBasePrice($arItems[PRODUCT_ID]);
                
                // Сохранить в файло $arItems[NOTES] 
                
                if ( ($ar_res["QUANTITY"]<=0) && ($arItems[NOTES]=='Интернет Магазин') )
                {


                    $arFields[PRICE]=$arFields[PRICE]-$price[PRICE]*$arItems["QUANTITY"];
                    $BasketSave[]=$arItems;	

                    $price[PRICE]=round($price[PRICE]);
                    $arItems[QUANTITY]=round($arItems[QUANTITY]);

                    global $USER;
                    global $DB;
                    $iu=$USER->GetID();

                    if ($iu<=0) $iu=$arFields[USER_ID];

                    $SQL="INSERT INTO order_agent (user, status, product, QUANTITY, PRICE, PAY_SYSTEM_ID, DELIVERY_ID) VALUES ($iu, 0, $arItems[PRODUCT_ID], $arItems[QUANTITY], $price[PRICE],$arFields[PAY_SYSTEM_ID],$arFields[DELIVERY_ID]);";

                    $res=CSaleBasket::Delete($arItems[ID]);

                    //$f=fopen($_SERVER["DOCUMENT_ROOT"].'/log26SQL.txt','a');
                    //fwrite($f,"SQL запрос".$SQL."\r\n");
                    //fclose($f);

                    $DB->Query($SQL);




                    $allSum=$allSum-$arItems["QUANTITY"]*$price[PRICE];

                } 

            }

            // Изменить в заказе стоимость [PRICE] и налог [TAX_VALUE] 

            $arFields[PRICE]=$allSum;

            //$f=fopen($_SERVER["DOCUMENT_ROOT"].'/log27.txt','a');
            //fwrite($f,print_r($arFields,true));
            //fclose($f);

            global $USER;
            // if ($USER->IsAdmin()) return false;

            if ($arFields[USER_ID]=='') return false; 
        }


        /////////

        /*function  OnStatus($ID, $val)
        {            
            if ($val=='F')
            {
                $arOrder = CSaleOrder::GetByID($ID);

                $bonus=floor(2*($arOrder["PRICE"]-$arOrder[PRICE_DELIVERY])/100);
                $vbonus=$arOrder[CURRENCY];
                $user=$arOrder[USER_ID]; // Номер пользователя для счета

                $d=explode(' ',$arOrder[DATE_STATUS]);
                $dt=explode('-',$d[0]);
                $dt=$dt[2].'.'.$dt[1].'.'.$dt[0].' '.$d[1];

                $ar = CSaleUserAccount::GetByUserID($user, "RUB");
                $bp=$ar["CURRENT_BUDGET"]+$bonus;

                $arFields=array("CURRENT_BUDGET"=>$bp);
                if ($ar[ID]>0) CSaleUserAccount::Update($ar[ID],$arFields);
                if ($ar[ID]<=0) {  $arFields["USER_ID"]=$user; CSaleUserAccount::Add($arFields); }

                $arFields=array(
                    "USER_ID"=>$user,
                    "AMOUNT"=>$bonus,
                    "CURRENCY"=>$vbonus,
                    "DEBIT"=>'Y',
                    "EMPLOYEE_ID"=>1,
                    "DESCRIPTION"=>'Оплата по бонусной программе',
                    "TRANSACT_DATE"=>$dt
                );

                $res=CSaleUserTransact::Add($arFields); // Добавление тразанкции
            } 
        } */ 

        ///////// 

        function OnPrice($ID,&$arFields3)
        {
            $iu=CUser::GetID();

            $aiu=4;  // Индификатор пользователя 1С


            if (($arFields3[CATALOG_GROUP_ID]==2) && ($iu==$aiu)) {




                // Сохраняем цену при экспорте из 1С.
                CIBlockElement::SetPropertyValueCode($arFields3[PRODUCT_ID], "DONACENKI", $arFields3[PRICE]);

                $arSelect = Array("ID", "NAME", "DATE_ACTIVE_FROM","PROPERTY_NACENKA","PROPERTY_DONACENKI");
                $arFilter = Array("IBLOCK_ID"=>73, "ID"=>$arFields3[PRODUCT_ID]);
                $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>1), $arSelect);
                $n='';
                while($ob = $res->GetNextElement())
                {
                    $arFields = $ob->GetFields();
                    $n=$arFields[PROPERTY_NACENKA_VALUE];
                }

                // Обработка строки с наценкой
                if ($n!='')
                {

                    if ($n[0]=='+') { $p=explode('+',$n); $p=explode('%',$p[1]); $p=$p[0]/100;}
                    if ($n[0]=='-') { $p=explode('-',$n); $p=explode('%',$p[1]); $p=-$p[0]/100;}

                    if (($n[0]=='+') || ($n[0]=='-') ) $arFields3[PRICE]=round((1+$p)*$arFields[PROPERTY_DONACENKI_VALUE]);

                }


                //////////



            } // условия

        } // Функция

    } //класс

?><? 
    AddEventHandler('main', 'OnBeforeEventSend', Array("MyForm", "my_OnBeforeEventSend"));
    class MyForm
    {
        function my_OnBeforeEventSend(&$arFields, $arTemplate)
        {

            //получим сообщение
            $mess = $arTemplate["MESSAGE"];
            if ($arTemplate[EVENT_NAME]=='SALE_ORDER_DELIVERY')
            {	
                $rsProps = CSaleOrderPropsValue::GetOrderProps($arFields[ORDER_ID]);
                while ($arProps = $rsProps->Fetch())
                    $arOrderProps[$arProps["ORDER_PROPS_ID"]] = $arProps;
                if ($arOrderProps[24]["VALUE"] == "") {$arFields[DELIVERY_DATE] = $arOrderProps[25]["VALUE"];}
                else {$arFields[DELIVERY_DATE] = $arOrderProps[24]["VALUE"];}
            }
            foreach($arFields as $keyField => $arField)
                $mess = str_replace('#'.$keyField.'#', $arField, $mess); //подставляем значения в шаблон

            if ($arTemplate[EVENT_NAME]=='SALE_NEW_ORDER')
            {
                $arFields[BPRICE]='';			
                $arOrder = CSaleOrder::GetByID($arFields[ORDER_ID]);
                $price2=$arOrder[PRICE]-$arOrder[SUM_PAID];
                if ($arOrder[SUM_PAID]>0) $arFields[PRICE]=$arFields[PRICE].'<br>Стоимость заказа с учетом бонусов '.$price2.' руб.<br>';



            }


        }
    }

    $e=explode('?',$_SERVER[REQUEST_URI]);
    $e=$e[0];


    //$f=fopen($_SERVER["DOCUMENT_ROOT"].'/loge.txt','w');
    //fwrite($f,$e);
    //fclose($f);


    function custom_mail($to, $subject, $message, $additionalHeaders = '')
    {
        /*
        $mail="free-vel@mail.ru"; // ваша почта
        $subject ="Test" ; // тема письма
        $text= "Line 1\nLine 2\nLine 4"; // текст письма
        if( mail($mail, $subject, $text) )
        { echo 'Успешно отправлено!'; }
        else{ echo 'Отправка не удалась!'; }
        return true;
        */
        require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/PHPMailer-master/PHPMailerAutoload.php';

        //Create a new PHPMailer instance
        $mail = new PHPMailer;
        //Tell PHPMailer to use SMTP
        $mail->isSMTP();
        //Enable SMTP debugging
        // 0 = off (for production use)
        // 1 = client messages
        // 2 = client and server messages
        $mail->SMTPDebug = 0;
        //Ask for HTML-friendly debug output
        $mail->Debugoutput = 'html';
        //Set the hostname of the mail server

        $mail->Host = "mail.tranzit-oil.ru";
        //Set the SMTP port number - likely to be 25, 465 or 587
        $mail->Port = 465;
        //Whether to use SMTP authentication
        $mail->SMTPAuth = true;
        //Username to use for SMTP authentication
        $mail->Username = "tranzit-oil\\noreply";
        //Password to use for SMTP authentication
        $mail->Password = "6Ty3^v)f";
        //Set the encryption system to use - ssl (deprecated) or tls
        $mail->SMTPSecure = 'tls';
        //Set who the message is to be sent from
        $mail->setFrom('noreply@tranzit-oil.ru', 'tranzit-oil');


        /*
        $mail->Host = "smtp.mail.ru";
        //Set the SMTP port number - likely to be 25, 465 or 587
        $mail->Port = 465;
        //Whether to use SMTP authentication
        $mail->SMTPAuth = true;
        //Username to use for SMTP authentication
        $mail->Username = "tranzit-oilru@mail.ru";
        //Password to use for SMTP authentication
        $mail->Password = "6Ty3^v)f";
        //Set the encryption system to use - ssl (deprecated) or tls
        $mail->SMTPSecure = 'ssl';
        //Set who the message is to be sent from
        $mail->setFrom('tranzit-oilru@mail.ru', 'noreplay');
        */

        //$to = 'free-vel@mail.ru';
        $pieces = explode(',', $to);
        foreach($pieces as $piece){
            //Set who the message is to be sent to
            $mail->addAddress(trim($piece), 'Client');
        }
        //Set the subject line
        $mail->Subject = $subject;
        //Read an HTML message body from an external file, convert referenced images to embedded,
        //convert HTML into a basic plain-text alternative body
        $mail->msgHTML($message);
        //Replace the plain text body with one created manually
        $mail->AltBody = 'This is a plain-text message body';

        //send the message, check for errors
        if (!$mail->send()) {
            //echo "Mailer Error: " . $mail->ErrorInfo;
        } else {
            //echo "Message sent!";
        }

        return true;
    }                       


    AddEventHandler("iblock", "OnBeforeIBlockElementUpdate", "CancelActiveChange");

    function CancelActiveChange(&$arFields) {
        if($arFields['MODIFIED_BY'] == EXCHANGE_1C_USER) {
            unset($arFields['ACTIVE']);    
        }       
    }  
                                                                                                                                 


?>