<?php

namespace App\Module\LobbyModule\Controller;

use App\Controller\AbstractController;
use App\Exception\JSONException;
use App\Http\JSONResponse;
use App\Model\AbstractModel;
use App\Module\ConnectionModule\Model\ConnectionModel;
use App\Module\CourseSheetModule\Model\CourseSheetModel;
use App\Module\LobbyModule\Model\LobbyModel;

class LobbyController extends AbstractController
{
    public function __construct(LobbyModel $model)
    {
        parent::__construct($model);
        $this->setActions([
            'coursesheets',
            'messages',
            'consult',
            'newCourseSheet',
            'deleteCourseSheet',
            'addUser',
            'removeUser',
            'addWriteRight',
            'removeWriteRight',
            'makePrivate',
            'makePublic',
            'update',
            'checkIfAdmin',
            'users',
            'visibility',
            'coursesheet',
            'getByHashtag',
            'search',
            'getLobbies',
            'getLogo',
            'addMessage',
            'delete',
            'create',
        ]);
    }

    public function run(): void
    {
        $this->checkAction();
        $idLobby = (int)$this->getRequest()->getParam();
        $result = [];
        if (0 !== $idLobby) {
            $this->checkToken();

            if (-1 == $idLobby) {
                $result = $this->getModel()->create(
                    $this->getModel()->idUserFromToken($this->getRequest()->getToken()),
                    $this->getRequest()->getLabel(),
                    $this->getRequest()->getDescription(),
                    $this->getRequest()->getPrivate(),
                    $this->getRequest()->getFile()['name'],
                    $this->getRequest()->getFile()['tmp_name'],
                    );

                $idLobby = $result['id_lobby'];
            }

            $rightsOnLobby = $this->getModel()->checkRights($idLobby, $this->getRequest()->getToken());
        } else {
            $rightsOnLobby = 'none';
        }
        if ('none' !== $rightsOnLobby) {
            switch ($this->getRequest()->getAction()) {
                case 'coursesheets':
                    $result = $this->getModel()->getCourseSheets($idLobby);
                    break;

                case 'messages':
                    $result = $this->getModel()->getMessages($idLobby);
                    break;

                case 'consult':
                    $result = $this->getModel()->getLobbyById($idLobby);
                    break;

                case 'coursesheet':
                    $file = $this->getModel()->getFile($idLobby, $this->getRequest()->getPath(), '/coursesheets/');
                    $this->downloadFile($file);
                    break;

                case 'addMessage':
                    $result = $this->getModel()->addMessage($idLobby, $this->getModel()->idUserFromToken($this->getRequest()->getToken()), $this->getRequest()->getContent());
                    break;

                default:
                    if ('admin' === $rightsOnLobby) {
                        switch ($this->getRequest()->getAction()) {
                            case 'checkIfAdmin':
                                $result = ['isAdmin' => true];
                                break;

                            case 'newCourseSheet':
                                $requestHashtags = explode('&quot;', $this->getRequest()->getHashtags());
                                $hashtags = [];
                                foreach ($requestHashtags as $key => $value) {
                                    if (ctype_alnum($value)) {
                                        array_push($hashtags, $value);
                                    }
                                }
                                $result = (new CourseSheetModel($this->getModel()->getConnection()))->addCourseSheet(
                                    $idLobby,
                                    $this->getRequest()->getTitle(),
                                    $this->getRequest()->getFile()['name'],
                                    $this->getRequest()->getFile()['tmp_name'],
                                    $this->getRequest()->getDescription(),
                                    $hashtags,
                                    );
                                break;

                            case 'deleteCourseSheet':
                                $result = (new CourseSheetModel($this->getModel()->getConnection()))->deleteCourseSheet($idLobby, (int)$this->getRequest()->getId());
                                break;

                            case 'addUser':
                                $result = $this->getModel()->addUser($idLobby, $this->getRequest()->getEmail());
                                break;

                            case 'removeUser':
                                $result = $this->getModel()->removeUser($idLobby, $this->getRequest()->getId());
                                break;

                            case 'addWriteRight':
                                $result = $this->getModel()->addWriteRight($idLobby, $this->getRequest()->getId());
                                break;

                            case 'removeWriteRight':
                                $result = $this->getModel()->removeWriteRight($idLobby, $this->getRequest()->getId());
                                break;

                            case 'makePrivate':
                                $result = $this->getModel()->makePrivate($idLobby);
                                break;

                            case 'makePublic':
                                $result = $this->getModel()->makePublic($idLobby);
                                break;

                            case 'update':
                                if (isset($this->getRequest()->label)) {
                                    $result['message_label'] = $this->getModel()->updateLobby($idLobby, ['label_lobby' => $this->getRequest()->getLabel()])['message'];
                                }
                                if (isset($this->getRequest()->description)) {
                                    $result['message_description'] = $this->getModel()->updateLobby($idLobby, ['description' => $this->getRequest()->getDescription()])['message'];
                                }
                                if (isset($this->getRequest()->file)) {
                                    $result['message_logo'] = $this->getModel()->updateLogo(
                                        $idLobby,
                                        $this->getRequest()->getFile()['name'],
                                        $this->getRequest()->getFile()['tmp_name'],
                                        )['message'];
                                    $result['path'] = $this->getRequest()->getFile()['name'];
                                }
                                break;

                            case 'users':
                                $result = $this->getModel()->getUsers($idLobby);
                                break;

                            case 'visibility':
                                $result = $this->getModel()->getVisibility($idLobby);
                                break;

                            case 'getByHashtag':
                                $result = $this->getModel()->getByHashtags($this->getRequest()->getHashtags());
                                break;

                            case 'delete':
                                $result = $this->getModel()->delete((int)$this->getRequest()->getParam());
                                break;
                        }
                    }
                    break;
            }
        } else {
            switch ($this->getRequest()->getAction()) {
                case 'getLobbies':
                    $result = $this->getModel()->getLobbies();
                    break;

                case 'getLogo':
                    $logo = $this->getModel()->getFile($this->getRequest()->getIdLobby(), $this->getRequest()->getPath(), '/logo/');
                    $this->downloadFile($logo);
                    break;

                case 'search':
                    $result = $this->getModel()->searchLobbies($this->getRequest()->getSearch(), $this->getRequest()->getHashtags());
                    break;

                default:
                    new JSONException('You do not have the right to access this lobby');
                    break;
            }
        }
        new JSONResponse($result);
    }
}
