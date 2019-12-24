<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 17.10.16
 * Time: 16:56
 */

namespace totum\moduls\Main;


use totum\common\Auth;
use totum\common\CalculateAction;
use totum\common\Controller;
use totum\common\errorException;
use totum\common\interfaceController;
use totum\common\Model;
use totum\common\Settings;
use totum\models\Table;
use totum\models\UserV;
use totum\tableTypes\tableTypes;


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
            case 'getNotificationsTable':

                $Calc=new CalculateAction('=: linkToDataTable(table: \'ttm__manage_notifications\'; title: "Нотификации"; width: 800; height: "80vh"; refresh: false; header: true; footer: true)');
                $Calc->execAction('KOD', [], [], [], [], tableTypes::getTableByName('tables'));

                break;
            case 'notificationUpdate':
                if (!empty($_POST['id'])) {
                    if ($row = Model::init('notifications')->get(['id' => (int)$_POST['id'], 'user_id' => Auth::$aUser->getId()])) {
                        $upd = [];
                        switch ($_POST['type']) {
                            case 'deactivate':
                                $upd = ['active' => false];
                                break;
                            case 'later':

                                $date = date_create();
                                $date->modify('+5 minutes');

                                $upd = ['active_dt_from' => $date->format('Y-m-d H:i')];
                                break;
                        }
                        tableTypes::getTableByName('notifications')->reCalculateFromOvers(['modify' => [$row['id'] => $upd]]);
                    }
                }
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