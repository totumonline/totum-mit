<?php


namespace totum\moduls\Table;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\Auth;
use totum\common\calculates\CalculateAction;
use totum\common\errorException;
use totum\common\Model;
use totum\common\Totum;
use totum\common\User;
use totum\tableTypes\aTable;

class Actions
{
    /**
     * @var aTable
     */
    protected $Table;
    /**
     * @var Totum
     */
    protected $Totum;
    /**
     * @var User|null
     */
    protected $User;
    /**
     * @var array|object|null
     */
    protected $post;
    /**
     * @var ServerRequestInterface
     */
    protected $Request;

    protected $modulePath;

    public function __construct(ServerRequestInterface $Request, string $modulePath, aTable $Table = null, Totum $Totum = null)
    {
        if ($this->Table = $Table) {
            $this->Totum = $this->Table->getTotum();
        } else {
            $this->Totum = $Totum;
        }
        $this->User = $this->Totum->getUser();
        $this->Request = $Request;
        $this->post = $Request->getParsedBody();

        $this->modulePath = $modulePath;
    }

    public function reuser()
    {
        if (!$this->User->isCreator() && !Auth::isCreatorOnShadow()) {
            throw new errorException('Функция доступна только Создателю');
        }
        $user = $this->Totum->getModel('users')->get(['id' => $this->post['userId'], 'is_del' => false]);
        if (!$user) {
            throw new errorException('Пользователь не найден');
        }
        Auth::reUserFromCreator($this->Totum->getConfig(), $user['id'], $this->User->getId());

        $this->Totum->addToInterfaceLink($this->Request->getParsedBody()['location'], 'self', 'reload');

        return ['ok' => 1];
    }

    public function getNotificationsTable()
    {
        $Calc = new CalculateAction('=: linkToDataTable(table: \'ttm__manage_notifications\'; title: "Нотификации"; width: 800; height: "80vh"; refresh: false; header: true; footer: true)');
        $Calc->execAction('KOD', [], [], [], [], $this->Totum->getTable('tables'));
    }

    public function notificationUpdate()
    {
        if (!empty($this->post['id'])) {
            if ($rows = $this->Totum->getModel('notifications')->getAll(['id' => $this->post['id'], 'user_id' => $this->User->getId()])) {
                $upd = [];
                switch ($this->post['type']) {
                    case 'deactivate':
                        $upd = ['active' => false];
                        break;
                    case 'later':

                        $date = date_create();
                        if (empty($this->post['num']) || empty($this->post['item'])) {
                            $date->modify('+5 minutes');
                        } else {
                            $items = [1 => 'minutes', 'hours', 'days'];
                            $date->modify('+' . $this->post['num'] . ' ' . ($items[$this->post['item']] ?? 'minutes'));
                        }

                        $upd = ['active_dt_from' => $date->format('Y-m-d H:i')];
                        break;
                }

                $md = [];
                foreach ($rows as $row) {
                    $md[$row['id']] = $upd;
                }
                $this->Totum->getTable('notifications')->reCalculateFromOvers(['modify' => $md]);
            }
        }
        return ['ok' => 1];
    }

    public function checkForNotifications()
    {
        /*TODO FOR MY TEST SERVER */
        if ($_SERVER['HTTP_HOST'] === 'localhost:8080') {
            die('test');
        }

        $actived = $this->post['activeIds'] ?? [];
        $model = $this->Totum->getModel('notifications');
        $codes = $this->Totum->getModel('notification_codes');
        $getNotification = function () use ($actived, $model, $codes) {
            if (!$actived) {
                $actived = [0];
            }
            $result = [];

            if ($row = $model->getPrepared(
                ['!id' => $actived,
                    '<=active_dt_from' => date('Y-m-d H:i:s'),
                    'user_id' => $this->User->getId(),
                    'active' => 'true'],
                '*',
                '(prioritet->>\'v\')::int, id'
            )) {
                array_walk(
                    $row,
                    function (&$v, $k) {
                        if (!Model::isServiceField($k)) {
                            $v = json_decode($v, true);
                        }
                    }
                );
                $kod = $codes->getField(
                    'code',
                    ['name' => $row['code']['v']]
                );
                $calc = new CalculateAction($kod);
                $table = $this->Totum->getTable('notifications');
                $calc->execAction(
                    'code',
                    [],
                    $row,
                    [],
                    $table->getTbl(),
                    $table,
                    $row['vars']['v']
                );

                $result['notification_id'] = $row['id'];
            }
            if ($actived) {
                $result['deactivated'] = [];
                if ($ids = ($model->getColumn(
                    'id',
                    ['id' => $actived, 'user_id' => $this->User->getId(), 'active' => 'false']
                ) ?? [])) {
                    $result['deactivated'] = array_merge($result['deactivated'], $ids);
                }
                if ($ids = ($model->getColumn(
                    'id',
                    ['id' => $actived, 'user_id' => $this->User->getId(), 'active' => 'true', '>active_dt_from' => date('Y-m-d H:i')]
                ) ?? [])) {
                    $result['deactivated'] = array_merge($result['deactivated'], $ids);
                }
                if (empty($result['deactivated'])) {
                    unset($result['deactivated']);
                }
            }
            return $result;
        };

        if (!empty($this->post['periodicity']) && ($this->post['periodicity'] > 1)) {
            $i = 0;

            $count = ceil(20 / $this->post['periodicity']);

            do {
                echo "\n";
                flush();

                if (connection_status() !== CONNECTION_NORMAL) {
                    die;
                }
                if ($result = $getNotification()) {
                    break;
                }

                sleep($this->post['periodicity']);
            } while (($i++) < $count);
        } else {
            $result = $getNotification();
        }
        echo json_encode($result + ['notifications' => array_map(
            function ($n) {
                $n[0] = 'notification';
                return $n;
            },
            $this->Totum->getInterfaceDatas()
        )]);
        die;
    }
}
