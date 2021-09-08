<?php

$newsIblockId = 1;

/**
 * Подключаем зависимости композера
 */
require_once($_SERVER['DOCUMENT_ROOT'] . "/vendor/autoload.php");

/**
 * Подключаем стандартный пролог
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\ORM\Data\DataManager,
    \Bitrix\Main\ORM\Fields\IntegerField,
    \Bitrix\Main\ORM\Fields\TextField,
    \Bitrix\Iblock\ORM\PropertyValue;

/**
 * Сгенерированный ORM Класс HL инфоблока категорий
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> UF_TITLE text optional
 * <li> UF_XML_ID text optional
 * </ul>
 *
 * @package Bitrix\
 **/
class CategoriesHlTable extends DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'categories';
    }

    /**
     * Returns entity map definition.
     *
     * @return array
     */
    public static function getMap()
    {
        return [
            new IntegerField(
                'ID',
                [
                    'primary' => true,
                    'autocomplete' => true,
                    'title' => Loc::getMessage('_ENTITY_ID_FIELD')
                ]
            ),
            new TextField(
                'UF_TITLE',
                [
                    'title' => Loc::getMessage('_ENTITY_UF_TITLE_FIELD')
                ]
            ),
            new TextField(
                'UF_XML_ID',
                [
                    'title' => Loc::getMessage('_ENTITY_UF_XML_ID_FIELD')
                ]
            ),
        ];
    }
}

class ImporterFeeds
{

    private $iblock;
    private $url = "https://lenta.ru/rss/";

    /**
     * Вместо описания ORM класса вручную используем наиболее Api битрикса из документации
     * для работы с ORM классом инфоблока
     * @param $iblockId
     */
    public function __construct($iblockId)
    {
        $this->iblock = \Bitrix\Iblock\Iblock::wakeUp($iblockId);
    }

    /**
     * Данный метод запускает импорт в инфоблок и hl-блок
     */
    public function run(): void
    {
        $items = $this->parseItems($this->url);
        foreach ($items as $item) {
            if (!$this->checkItemExist($item)) {
                $this->addItem($item);
            }
        }
    }

    /**
     * @param $categoryName
     * @return array|void
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function getByCategory($categoryName)
    {
        $categoryResult = CategoriesHlTable::getList([
            'filter' => ['UF_TITLE' => $categoryName]
        ])->fetch();
        if ($categoryResult['UF_XML_ID']) {
            $result =
                $this->iblock->getEntityDataClass()::getList([
                    'filter' => ['CATEGORY.VALUE' => $categoryResult['UF_XML_ID']]
                ])->fetchAll();
            return $result;
        }

    }

    private function checkItemExist($item)
    {
        $itemObject = $this->iblock->getEntityDataClass()::query()
            ->where('CODE', $item['guid'])->setSelect(['NAME'])
            ->fetchObject();
        return (bool)$itemObject;
    }

    private function parseItems($url)
    {
        $rss = Feed::loadRss($url);
        $rssAsArray = $rss->toArray();
        if ($rssAsArray['item']) {
            return $rssAsArray['item'];
        }
    }

    private function addItem($item)
    {
        $newElement = $this->iblock->getEntityDataClass()::createObject();
        $newElement->setName($item['title']);
        $newElement->setCode($item['guid']);
        $newElement->setUrl($item['link']);
        $newElement->setPreviewText($item['description']);
        $newElement->setPublicationDate(ConvertTimeStamp($item['timestamp'], 'FULL'));
        $this->checkCategory($item['category']);
        $newElement->setCategory(new PropertyValue($item['category']));
        $newElement->save();
    }

    private function checkCategory($categoryName)
    {
        $categoryResult = CategoriesHlTable::getList([
            'filter' => ['UF_XML_ID' => $categoryName]
        ])->fetch();
        if (!$categoryResult) {
            CategoriesHlTable::add(['UF_XML_ID' => $categoryName, 'UF_TITLE' => $categoryName]);
        }
    }

}


require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("1С-Битрикс: Управление сайтом");


\Bitrix\Main\Loader::includeModule('iblock');


$importer = new ImporterFeeds($newsIblockId);

//Выплняем импорт
$importer->run();

//Получаем элементы по названию категории
$res = $importer->getByCategory('Экономика');


dd($res);


