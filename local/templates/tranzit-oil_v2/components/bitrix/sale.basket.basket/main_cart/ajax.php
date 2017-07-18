<?
define("STOP_STATISTICS", true);
define('NO_AGENT_CHECK', true);

use Bitrix\Main\Loader;

if (isset($_REQUEST['site_id']) && is_string($_REQUEST['site_id']))
{
	$siteID = trim($_REQUEST['site_id']);
	if ($siteID !== '' && preg_match('/^[a-z0-9_]{2}$/i', $siteID) === 1)
	{
		define('SITE_ID', $siteID);
	}
}

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if (!check_bitrix_sessid() || $_SERVER["REQUEST_METHOD"] != "POST")
	return;

if (!Loader::includeModule('sale') || !Loader::includeModule('catalog'))
	return;

global $USER, $APPLICATION;

include(dirname(__FILE__)."/functions.php");

CUtil::JSPostUnescape();

$arRes = array();
$newProductId = false;
$newBasketId = false;
$action_var = (isset($_POST["action_var"]) && strlen(trim($_POST["action_var"])) > 0) ? trim($_POST["action_var"]) : "action";
$arErrors = array();

if (isset($_POST[$action_var]) && strlen($_POST[$action_var]) > 0)
{
	if (array_key_exists('COUPON', $_POST) && !array_key_exists('coupon', $_POST))
	{
		$_POST["coupon"] = $_POST["COUPON"];
	}

	$arPropsValues = isset($_POST["props"]) ? $_POST["props"] : array();
	$strColumns = isset($_POST["select_props"]) ? $_POST["select_props"] : "";
	$arColumns = explode(",", $strColumns);
	$strOffersProps = isset($_POST["offers_props"]) ? $_POST["offers_props"] : "";
	$strOffersProps = explode(",", $strOffersProps);

	if ($_POST[$action_var] == "select_item")
	{
		$arSelect = array("ID", "IBLOCK_ID", "PROPERTY_CML2_LINK", "XML_ID");
		foreach ($arColumns as &$columnName)
		{
			if (strpos($columnName, "PROPERTY_", 0) === 0)
			{
				$arSelect[] = str_replace("_VALUE", "", $columnName);
			}
		}
		unset($columnName);

		$arItemSelect = array(
			"ID",
			"XML_ID",
			"PRODUCT_ID",
			"PRICE",
			"CURRENCY",
			"WEIGHT",
			"QUANTITY",
			"MODULE",
			"PRODUCT_PROVIDER_CLASS",
			"CALLBACK_FUNC",
			"NOTES"
		);
		$arItem = false;
		$currentId = 0;
		if (isset($_POST['basketItemId']))
			$currentId = (int)$_POST['basketItemId'];
		if ($currentId > 0)
		{
			$dbItemRes = CSaleBasket::GetList(array(),
				array("ID" => intval($_POST["basketItemId"])),
				false,
				false,
				$arItemSelect
			);
			$arItem = $dbItemRes->Fetch();
		}


		if ($arItem )
		{
			$dbProp = CSaleBasket::GetPropsList(
				array("SORT" => "ASC", "ID" => "ASC"),
				array("BASKET_ID" => $arItem["ID"]),
				false,
				false,
				array('NAME', 'CODE', 'VALUE', 'SORT')
			);
			while ($arProp = $dbProp->Fetch())
			{
				if (!isset($arItem['PROPS']))
					$arItem['PROPS'] = array();
				$arItem['PROPS'][] = $arProp;
			}

			$dbRes = CIBlockElement::GetList(
				array("SORT" => "ASC", "ID" => "ASC"),
				array("ID" => $arItem["PRODUCT_ID"]),
				false,
				false,
				$arSelect
			);

			if ($arElement = $dbRes->Fetch())
			{
				$bBasketUpdate = false;
				$arPropsValues["CML2_LINK"] = $arElement["PROPERTY_CML2_LINK_VALUE"];

				$newProductId = getProductByProps($arElement["IBLOCK_ID"], $arPropsValues);

				if ($newProductId > 0)
				{
					if ($productProvider = CSaleBasket::GetProductProvider($arItem))
					{
						$arFieldsTmp = $productProvider::GetProductData(array(
							"PRODUCT_ID" => $newProductId,
							"QUANTITY"   => $arItem['QUANTITY'],
							"RENEWAL"    => "N",
							"USER_ID"    => $USER->GetID(),
							"SITE_ID"    => SITE_ID,
							"BASKET_ID" => $arItem['ID'],
							"CHECK_QUANTITY" => "Y",
							"CHECK_PRICE" => "Y",
							"NOTES" => $arItem["NOTES"]
						));
					}
					elseif (isset($arItem["CALLBACK_FUNC"]) && !empty($arItem["CALLBACK_FUNC"]))
					{
						$arFieldsTmp = CSaleBasket::ExecuteCallbackFunction(
							$arItem["CALLBACK_FUNC"],
							$arItem["MODULE"],
							$newProductId,
							$arItem['QUANTITY'],
							"N",
							$USER->GetID(),
							SITE_ID
						);
					}

					if (!empty($arFieldsTmp) && is_array($arFieldsTmp))
					{
						$arFields = array(
							'PRODUCT_ID' => $newProductId,
							'PRODUCT_PRICE_ID' => $arFieldsTmp["PRODUCT_PRICE_ID"],
							'PRICE' => $arFieldsTmp["PRICE"],
							'CURRENCY' => $arFieldsTmp["CURRENCY"],
							'QUANTITY' => $arFieldsTmp['QUANTITY'],
							'WEIGHT' => $arFieldsTmp['WEIGHT'],
							);

						$dbProduct = CIBlockElement::GetList(array(), array("ID" => $newProductId), false, false, array('ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID','XML_ID'));
						if ($arProduct = $dbProduct->Fetch())
						{
							$arParentSku = CCatalogSku::GetProductInfo($newProductId, $arElement["IBLOCK_ID"]);
							if ($arParentSku && !empty($arParentSku))
							{
								$arProps = array();

								if (strpos($arProduct["XML_ID"], '#') === false)
								{
									$dbParentProduct = CIBlockElement::GetList(array(), array("ID" => $arParentSku['ID']), false, false, array('ID','XML_ID'));
									if ($arParentProduct = $dbParentProduct->Fetch())
									{
										$arProduct["XML_ID"] = $arParentProduct['XML_ID'].'#'.$arProduct["XML_ID"];
									}
								}
								$arFields["PRODUCT_XML_ID"] = $arProduct["XML_ID"];

								$dbOfferProperties = CIBlock::GetProperties($arProduct["IBLOCK_ID"], array(), array("!XML_ID" => "CML2_LINK"));
								while($arOfferProperties = $dbOfferProperties->Fetch())
								{
									$arPropsSku[] = $arOfferProperties["CODE"];
								}

								$product_properties = CIBlockPriceTools::GetOfferProperties(
									$newProductId,
									$arParentSku["IBLOCK_ID"],
									$arPropsSku
								);

								$newValues = array();
								foreach ($product_properties as $productSkuProp)
								{
									$bFieldExists = false;
									foreach ($strOffersProps as $existingSkuProp)
									{
										if ($existingSkuProp == $productSkuProp["CODE"])
										{
											$bFieldExists = true;
											break;
										}
									}

									if ($bFieldExists === true)
									{
										$newValues[] = array(
											"NAME" => $productSkuProp["NAME"],
											"CODE" => $productSkuProp["CODE"],
											"VALUE" => $productSkuProp["VALUE"],
											"SORT" => $productSkuProp["SORT"]
										);
									}
								}

								$newValues[] = array(
									"NAME" => "Product XML_ID",
									"CODE" => "PRODUCT.XML_ID",
									"VALUE" => $arProduct["XML_ID"]
								);

								$arFields['PROPS'] = (isset($arItem['PROPS']) ? updateBasketOffersProps($arItem['PROPS'], $newValues) : $newValues);
								unset($newValues);
							}
							else
							{
								$arErrors[] = GetMessage('SBB_PRODUCT_PRICE_NOT_FOUND');
							}
							if (empty($arErrors))
							{
								$bBasketUpdate = CSaleBasket::Update($arItem['ID'], $arFields);
							}
						}
					}
					else
					{
						$arErrors[] = GetMessage('SBB_PRODUCT_PRICE_NOT_FOUND');
					}
				}

				if ($bBasketUpdate === true)
				{
					CBitrixComponent::includeComponentClass("bitrix:sale.basket.basket");

					$basket = new CBitrixBasketComponent();

					$basket->weightKoef = htmlspecialcharsbx(COption::GetOptionString('sale', 'weight_koef', 1, SITE_ID));
					$basket->weightUnit = htmlspecialcharsbx(COption::GetOptionString('sale', 'weight_unit', "", SITE_ID));
					$basket->columns = $arColumns;
					$basket->offersProps = $strOffersProps;

					$basket->quantityFloat = (isset($_POST["quantity_float"]) && $_POST["quantity_float"] == "Y") ? "Y" : "N";
					$basket->countDiscount4AllQuantity = (isset($_POST["count_discount_4_all_quantity"]) && $_POST["count_discount_4_all_quantity"] == "Y") ? "Y" : "N";
					$basket->priceVatShowValue = (isset($_POST["price_vat_show_value"]) && $_POST["price_vat_show_value"] == "Y") ? "Y" : "N";
					$basket->hideCoupon = (isset($_POST["hide_coupon"]) && $_POST["hide_coupon"] == "Y") ? "Y" : "N";
					$basket->usePrepayment = (isset($_POST["use_prepayment"]) && $_POST["use_prepayment"] == "Y") ? "Y" : "N";

					$columnsData = $basket->getCustomColumns();
					$basketData  = $basket->getBasketItems();

					foreach ($basketData as $index => $data) {
						if($data['DETAIL_PICTURE'])
						{
							$img = CFile::ResizeImageGet($data['DETAIL_PICTURE'], array('width'=>138, 'height'=>138), BX_RESIZE_IMAGE_PROPORTIONAL, true);
							$basketData[$index]['DETAIL_PICTURE_SRC'] = $img['src'];
						}

					}


					$arRes["DELETE_ORIGINAL"] = "Y";
					$arRes["BASKET_DATA"] = $basketData;
					$arRes["BASKET_DATA"]["GRID"]["HEADERS"] = $columnsData;
					$arRes["COLUMNS"] = $strColumns;

					$arRes["BASKET_ID"] = $arItem['ID'];
				}

				$arRes["CODE"] = ($bBasketUpdate === true) ? "SUCCESS" : "ERROR";
				if ($bBasketUpdate === false && is_array($arErrors) && !empty($arErrors))
				{
					foreach ($arErrors as $error)
					{
						$arRes["MESSAGE"] .= (strlen($arRes["MESSAGE"]) > 0 ? "<br/>" : ""). $error;
					}
				}
			}
		}
	}
	else if ($_POST[$action_var] == "recalculate")
	{
		// todo: extract duplicated code to function

		CBitrixComponent::includeComponentClass("bitrix:sale.basket.basket");

		$basket = new CBitrixBasketComponent();

		$basket->weightKoef = htmlspecialcharsbx(COption::GetOptionString('sale', 'weight_koef', 1, SITE_ID));
		$basket->weightUnit = htmlspecialcharsbx(COption::GetOptionString('sale', 'weight_unit', "", SITE_ID));
		$basket->columns = $arColumns;
		$basket->offersProps = explode(",", $strOffersProps);

		$basket->quantityFloat = (isset($_POST["quantity_float"]) && $_POST["quantity_float"] == "Y") ? "Y" : "N";
		$basket->countDiscount4AllQuantity = (isset($_POST["count_discount_4_all_quantity"]) && $_POST["count_discount_4_all_quantity"] == "Y") ? "Y" : "N";
		$basket->priceVatShowValue = (isset($_POST["price_vat_show_value"]) && $_POST["price_vat_show_value"] == "Y") ? "Y" : "N";
		$basket->hideCoupon = (isset($_POST["hide_coupon"]) && $_POST["hide_coupon"] == "Y") ? "Y" : "N";
		$basket->usePrepayment = (isset($_POST["use_prepayment"]) && $_POST["use_prepayment"] == "Y") ? "Y" : "N";

		$res = $basket->recalculateBasket($_POST);

		foreach ($res as $key => $value)
		{
			$arRes[$key] = $value;
		}



$a=$basket->getBasketItems();
$allSum=0; $AllSum=0;

foreach ($a[ITEMS][AnDelCanBuy] as $kk=>$b)
{
if ($b[AVAILABLE_QUANTITY]<=0) {
$id=$a[ITEMS][AnDelCanBuy][$kk][ID];
 unset($a[ITEMS][AnDelCanBuy][$kk]);
 unset($a[GRID][ROWS][$id]);

} else 
{

$id=$a[ITEMS][AnDelCanBuy][$kk][ID];
$allSum=$allSum+$a[GRID][ROWS][$id][PRICE]*$a[GRID][ROWS][$id][QUANTITY];

$f=fopen($_SERVER["DOCUMENT_ROOT"]."/logajax2.txt",'w');
fwrite($f,print_r($_POST,true));
fclose($f);


}

$a[allSum]=$allSum;
$a[allSum_FORMATED]=  CurrencyFormat($allSum,"RUB");

}
/*
$f=fopen($_SERVER["DOCUMENT_ROOT"]."/log.txt3",'a');
fwrite($f,print_r($a,true));
fclose($f);
*/
		$arRes["BASKET_DATA"] = $a;
		$arRes["BASKET_DATA"]["GRID"]["HEADERS"] = $basket->getCustomColumns();
		$arRes["COLUMNS"] = $strColumns;

		$arRes["CODE"] = "SUCCESS";
	}
}

$arRes["PARAMS"]["QUANTITY_FLOAT"] = (isset($_POST["quantity_float"]) && $_POST["quantity_float"] == "Y") ? "Y" : "N";

$APPLICATION->RestartBuffer();
header('Content-Type: application/json; charset='.LANG_CHARSET);
echo CUtil::PhpToJSObject($arRes);
die();