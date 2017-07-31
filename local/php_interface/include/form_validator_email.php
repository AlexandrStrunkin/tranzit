<?
AddEventHandler("form", "onFormValidatorBuildList", array("CFormCustomValidatorEmail", "GetDescription"));

CModule::IncludeModule("form");

class CFormCustomValidatorEmail
{
    function GetDescription()
    {
        return array(
            "NAME"            => "check_email",
            "DESCRIPTION"     => "Проверка Email",
            "TYPES"           => array("email"),
            #"SETTINGS"        => array("CFormCustomValidatorEmail", "GetSettings"), // �����, ������������ ������ ��������
            #"CONVERT_TO_DB"   => array("CFormCustomValidatorEmail", "ToDB"),        // �����, �������������� ������ �������� � ������
            #"CONVERT_FROM_DB" => array("CFormCustomValidatorEmail", "FromDB"),      // �����, �������������� ������ �������� � ������
            "HANDLER"         => array("CFormCustomValidatorEmail", "DoValidate")   // ���������
        );
    }

    function GetSettings()
    {
        return array(
            
            );
    }

    function ToDB($arParams)
    {
        
    }

    function FromDB($strParams)
    {
        
    }

    function DoValidate($arParams, $arQuestion, $arAnswers, $arValues)
    {
        global $APPLICATION;
        
        foreach ($arValues as $email)
        {
              if (!check_email($email)) 
            {
                $APPLICATION->ThrowException("#FIELD_NAME#: Некорректный email");
                return false;
            }
            
            return true;
        }
    }
}
