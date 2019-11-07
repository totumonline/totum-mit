<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 17.10.16
 * Time: 16:56
 */

namespace totum\moduls\Main;


use totum\common\Auth;
use totum\common\Controller;
use totum\common\errorException;
use totum\common\interfaceController;
use totum\common\Model;
use totum\common\Settings;
use totum\models\Table;
use totum\models\TablesFields;
use totum\models\UserV;


class MainController extends interfaceController
{

    function actionMain()
    {
        foreach (Model::init('tables')->getAll(['id' => Auth::$aUser->getFavoriteTables()], 'id, top, title, type', 'sort') as $t) {

            $tree[] = [
                'id' => 'table' . $t['id']
                , 'href' => '/Table/' . $t['top'] . '/' . $t['id']
                , 'text' => $t['title']
                , 'type' => 'table_' . $t['type']
                , 'parent' => '#'
            ];
        }
        $this->__addAnswerVar('treeData', $tree);
        $SettingsTableRow = Table::getTableRowByName('settings');
        $this->__addAnswerVar('mainHtml', Settings::init()->getParam('main_page'));
    }

    function actionAjaxMain()
    {
        $result = [];
        switch ($_POST['method'] ?? null) {
            case 'reuser':

                if (!Auth::isCreator() && !Auth::isCreatorOnShadow()) throw new errorException('Функция доступна только Создателю');
                $user = UserV::init()->get(['id' => $_POST['userId'], 'is_del' => false]);
                if (!$user) throw new errorException('Пользователь не найден');
                Auth::reUserFromCreator($user['id']);

                Controller::addLinkLocation($_SERVER['REQUEST_URI'], 'self', 'reload');

                $result = ['ok' => 1];
                break;

        }

        if ($links = Controller::getLinks()) {
            $result['links'] = $links;
        }
        if ($panels = Controller::getPanels()) {
            $result['panels'] = $panels;
        }
        if ($links = Controller::getInterfaceDatas()) {
            $result['interfaceDatas'] = $links;
        }

        return $result;
    }


}