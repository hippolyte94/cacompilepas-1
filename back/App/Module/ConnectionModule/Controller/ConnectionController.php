<?php

namespace App\Module\ConnectionModule\Controller;

use App\Controller\AbstractController;
use App\Http\JSONResponse;

class ConnectionController extends AbstractController
{
    public function run(): void
    {
        $result = [];
        switch ($this->getRequest()->getAction()) {
            case 'login':
                $email = $this->getRequest()->getEmail();
                $password = $this->getRequest()->getPassword();
                if (0 !== count($this->getModel()->checkIfUserExists($email, $password))) {
                    $idUser = $this->getModel()->checkIfUserExists($email, $password)['id_user'];
                    $result = [
                        'token' => $this->getModel()->generateToken($email, $password, $idUser),
                        'connected' => true,
                    ];
                } else {
                    $result = [
                        'connected' => false,
                    ];
                }
                break;

            // Check if a token is valid
            case 'verification':
                $token_exists = $this->getModel()->checkToken($this->getRequest()->getToken());
                $result = [
                    'token_exists' => $token_exists ? true : false,
                ];
                break;

            case 'personal':
                $this->checkToken();
                $email = $this->getModel()->getUserFromToken($this->getRequest()->getToken())['email'];
                $result = $this->getModel()->getPersonalInformation($email);
                array_push($result, $this->getModel()->getPersonalLobbies($email));
                break;

            case 'getIcon':
                $this->checkToken();
                $icon = $this->getModel()->getIcon($this->getRequest()->getParam(), $this->getRequest()->getPath(), '/icon/');
                $this->downloadFile($icon);
                break;

            case 'disconnect':
                $this->checkToken();
                $email = $this->getModel()->getUserFromToken($this->getRequest()->getToken())['email'];
                $result = $this->getModel()->disconnect($email);
                break;

            case 'register':
                $result = $this->getModel()->register(
                    $this->getRequest()->getPseudo(),
                    $this->getRequest()->getFirstName(),
                    $this->getRequest()->getLastName(),
                    $this->getRequest()->getFile()['name'],
                    $this->getRequest()->getFile()['tmp_name'],
                    $this->getRequest()->getPassword(),
                    $this->getRequest()->getConfirmPassword(),
                    $this->getRequest()->getEmail(),
                );
        }
        new JSONResponse($result);
    }
}
