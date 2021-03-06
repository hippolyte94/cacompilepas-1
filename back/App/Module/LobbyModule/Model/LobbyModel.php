<?php


namespace App\Module\LobbyModule\Model;

use App\Connection\Connection;
use App\Exception\JSONException;
use App\Model\AbstractModel;
use App\Module\ConnectionModule\Model\ConnectionModel;
use Firebase\JWT\JWT;


class LobbyModel extends AbstractModel
{
    public function isAdmin(int $idUser, int $idLobby): bool
    {
        $this->send_query('
                SELECT id_user
                FROM ccp_is_admin
                WHERE id_user = ?
                AND id_lobby = ?
            ',
            [(int)$idUser, $idLobby]);
        if ($this->getQuery()->fetch()) {
            return true;
        } else {
            return false;
        }
    }
    public function checkRights(int $idLobby, string $token): string
    {
        $decoded = $this->getUserFromToken($token);

        if ($result = (new ConnectionModel($this->getConnection()))->checkIfUserExists($decoded['email'], $decoded['password'])) {
            $isAdmin = $this->isAdmin((int)$result['id_user'], $idLobby);

            if ($isAdmin) {
                return 'admin';
            } else {
                $this->send_query('
                    SELECT read_right, id_lobby
                    FROM ccp_rights
                    RIGHT OUTER JOIN ccp_lobby cl ON ccp_rights.id_lobby_Protect = cl.id_lobby
                    WHERE 
                    private = 0 OR
                    id_user = ?
                    AND id_lobby_protect = ?
                ',
                    [(int)$result['id_user'], $idLobby]);
                if ($result = $this->getQuery()->fetch()) {
                    return 'user';
                } else {
                    return 'none';
                }
            }
        } else {
            return 'none';
        }
    }

    public function getLobbyById(int $idLobby): array
    {
        $this->send_query('SELECT id_lobby, label_lobby, description, logo
                        FROM ccp_lobby
                        WHERE id_lobby = ?
                        ',
            [$idLobby]);
        return $this->fetchData(['message' => 'Lobby ' . $idLobby . ' doesn\'t exist']);
    }

    public function getCourseSheets(int $idLobby): array
    {
        $this->send_query('
            SELECT ccp_coursesheet.id_course_sheet, title, publication_date, file_name, description
            FROM ccp_coursesheet
            WHERE id_lobby_Contain = ?
        ',
            [$idLobby]);
        return $this->fetchData(['message' => 'Lobby ' . $idLobby . ' doesn\'t contain any course sheet']);
    }

    public function getMessages(int $idLobby): array
    {
        $this->send_query('SELECT id_message, content, send_date, pseudo
                        FROM ccp_message
                        INNER JOIN ccp_user
                        USING(id_user)
                        WHERE id_lobby = ?
                        ',
            [$idLobby]);
        return $this->fetchData(['message' => 'Lobby ' . $idLobby . ' doesn\'t contain any message']);
    }

    public function getLogo(int $idLobby): string
    {
        $this->send_query(
            'SELECT logo FROM ccp_lobby
                        WHERE id_lobby = ?
                        ',
            [$idLobby]);

        if ($result = $this->getQuery()->fetch()) {
            return $result['logo'];
        } else {
            return '';
        }
    }

    public function backUpAndUpdateLogo(int $idLobby, string $fileName): string
    {
        // Update logo in database
        // But make a backup of old logo before to be able to update logo on ftp server
        $oldLogo = $this->getLogo($idLobby);
        $this->updateLobby($idLobby, ['logo' => $fileName]);
        return $oldLogo;
    }

    public function updateLogo(int $idLobby, string $fileName, string $tmpName): array
    {
        $oldLogo = $this->backUpAndUpdateLogo($idLobby, $fileName);
        return $this->updateOnFTP($idLobby, $fileName, $tmpName, AbstractModel::$IMG_EXTENSIONS, '/logo/', $oldLogo);
    }

    public function updateLobby(int $idLobby, array $newData): array
    {
        return $this->update($idLobby, 'Lobby', 'ccp_lobby', 'id_lobby', $newData);
    }

    public function verifyIfRightExists(int $idLobby, int $idUser): bool
    {
        $isAdmin = $this->isAdmin($idUser, $idLobby);
        if (!$isAdmin) {
            $this->send_query('
            SELECT id_right FROM ccp_rights
            WHERE id_lobby_protect = ?
            AND id_user = ?
        ',
                [$idLobby, $idUser]);

            if ($result = $this->getQuery()->fetch()) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    public function findUser(string $email): int
    {
        $user = (new ConnectionModel($this->getConnection()))->getUserByEmail($email);
        if ($user) {
            return $user['id_user'];
        } else {
            new JSONException("No user was found with address $email");
        }
    }

    public function addUser(int $idLobby, string $email): array
    {
        $idUser = $this->findUser($email);

        if (!$this->verifyIfRightExists($idLobby, $idUser)) {
            $successfulRightCreation = $this->send_query('
                INSERT INTO ccp_rights
                (read_right, write_right, id_lobby_protect, id_user)
                VALUES
                (?, ?, ?, ?)
            ',
                [1, 0, $idLobby, (int)$idUser]);

            if ($successfulRightCreation) {
                return ['message' => "Read right was successfully added for $email"];
            } else {
                return ['message' => "Read right could not be added for $email"];
            }
        } else {
            return ['message' => "$email already has access to the lobby"];
        }
    }

    public function removeUser(int $idLobby, int $idUser): array
    {
        if ($this->verifyIfRightExists($idLobby, $idUser)) {
            $successfulRightDeletion = $this->send_query('
                DELETE FROM ccp_rights
                WHERE id_lobby_protect = ?
                AND id_user = ?
            ',
                [$idLobby, $idUser]);

            if ($successfulRightDeletion) {
                return ['message' => 'Read right was successfully removed '];
            } else {
                return ['message' => 'Read right could not be removed'];
            }
        } else {
            return ['message' => 'User is already out of the lobby'];
        }
    }

    public function addWriteRight(int $idLobby, int $idUser): array
    {
        if ($this->verifyIfRightExists($idLobby, $idUser)) {
            $successfulWriteRightUpdate = $this->send_query('
                UPDATE ccp_rights
                SET write_right = 1
                WHERE id_lobby_protect = ?
                AND id_user = ?
            ',
                [$idLobby, $idUser]);

            if ($successfulWriteRightUpdate) {
                return ['message' => 'Write right was successfully added'];
            } else {
                return ['message' => 'Write right could not be added'];
            }
        } else {
            return ['message' => 'User doesn\'t have access to the lobby'];
        }
    }

    public function removeWriteRight(int $idLobby, int $idUser): array
    {
        if ($this->verifyIfRightExists($idLobby, $idUser)) {
            $successfulWriteRightRemove = $this->send_query('
                UPDATE ccp_rights
                SET write_right = 0
                WHERE id_lobby_protect = ?
                AND id_user = ?
            ',
                [$idLobby, $idUser]);

            if ($successfulWriteRightRemove) {
                return ['message' => 'Write right was successfully removed'];
            } else {
                return ['message' => 'Write right could not be removed from'];
            }
        } else {
            return ['message' => 'User doesn\'t have access to the lobby'];
        }
    }

    public function makePrivate(int $idLobby): array
    {
        $successfullyMadePublic = $this->send_query('
            UPDATE ccp_lobby
            SET private = 1
            WHERE id_lobby = ?
        ',
            [$idLobby]);

        if ($successfullyMadePublic) {
            return ['message' => 'Lobby was successfully made private'];
        } else {
            return ['message' => 'Lobby could not be made private'];
        }
    }

    public function makePublic(int $idLobby): array
    {
        $successfullyMadePublic = $this->send_query('
            UPDATE ccp_lobby
            SET private = 0
            WHERE id_lobby = ?
        ',
            [$idLobby]);

        if ($successfullyMadePublic) {
            return ['message' => 'Lobby was successfully made public'];
        } else {
            return ['message' => 'Lobby could not be made public'];
        }
    }

    public function getUsers(int $idLobby): array
    {
        $this->send_query('
            SELECT DISTINCT id_user, pseudo, icon, write_right
            FROM ccp_user
            INNER JOIN ccp_rights cr ON ccp_user.id_user = cr.id_user
            LEFT OUTER JOIN ccp_is_admin cia ON ccp_user.id_user = cia.id_user
            WHERE id_lobby_protect = ?
            AND read_right = 1
        ',
            [$idLobby]);

        return $this->fetchData(['message' => 'Lobby ' . $idLobby . ' does not contain any user']);
    }

    public function getVisibility(int $idLobby): array
    {
        $this->send_query('
            SELECT private
            FROM ccp_lobby
            WHERE id_lobby = ?
        ',
            [$idLobby]);

        return $this->fetchData(['message' => 'Lobby ' . $idLobby . 'does not exist']);
    }
  
    public function getByHashtags(array $hashtags): array
    {
        $this->send_query('
            SELECT id_lobby, label_lobby, ccp_lobby.description, logo FROM 
            ccp_lobby INNER JOIN ccp_coursesheet cc ON ccp_lobby.id_lobby = cc.id_lobby_contain
            INNER JOIN ccp_hashtag ch ON cc.id_course_sheet = ch.id_course_sheet
            WHERE label_hashtag IN (?)
        ',
            [$this->arrayToIN($hashtags)]);
        return $this->fetchData([]);
    }
      
    public function getFile(int $idLobby, string $path, string $uploadDirectory): string
    {
        return $this->getOnFTP($idLobby, $path, $uploadDirectory);
    }

    public function getLobbies(): array
    {
        $this->send_query('
            SELECT id_lobby, label_lobby, description, logo, pseudo
            FROM ccp_lobby
            LEFT OUTER JOIN ccp_is_admin USING(id_lobby)
            LEFT OUTER JOIN ccp_user USING(id_user)
            WHERE private = 0
        ');
        return $this->fetchData(['message' => 'There is no public lobby']);
    }

    public function addMessage(int $idLobby, int $idUser, string $content): array
    {
        $successfulInsert = $this->send_query('
            INSERT INTO ccp_message
            (content, send_date, id_user, id_lobby)
            VALUES 
            (?, NOW(), ?, ?)
        ',
            [$content, $idUser, $idLobby]);

        if ($successfulInsert) {
            return ['message' => 'Message was successfully added'];
        } else {
            return ['message' => 'Message could not be added'];
        }
    }
  
    public function searchLobbies(array $search, array $hashtags): array
    {
        $count = 0;
        $lengthSearch = count($search);
        $searchParams = '';
        $hashtagsParams = '';
        $lengthHashtags = count($hashtags);

        foreach ($search as $key => $value) {
            $searchParams .= " UPPER(label_lobby) LIKE UPPER('%" . $value . "%')";
            if ($count !== $lengthSearch - 1) {
                $searchParams .= ' AND';
            }
            $count++;
        }

        $count = 0;

        foreach ($hashtags as $key => $value) {
            $hashtagsParams .= " label_hashtag = '" . $value . "'";
            if ($count !== $lengthHashtags - 1) {
                $hashtagsParams .= ' OR';
            }
            $count++;
        }

        $this->send_query('
            SELECT DISTINCT id_lobby, label_lobby, ccp_lobby.description, logo 
            FROM ccp_lobby 
            LEFT OUTER JOIN ccp_coursesheet on ccp_lobby.id_lobby = ccp_coursesheet.id_lobby_contain
            NATURAL JOIN ccp_hashtag
            WHERE
            ' . (0 !== $lengthSearch ? '(' . $searchParams . ') ' : '') .
            (0 !== $lengthHashtags ? ' AND (' . $hashtagsParams . ')' : '') . '
            AND private = 0
            ',
            []);
        return $this->fetchData([]);
    }

    public function delete(int $idLobby): array
    {
        $successfullDelete = $this->send_query('
            DELETE FROM ccp_lobby
            WHERE id_lobby = ?
        ',
            [$idLobby]);

        if ($successfullDelete) {
            return ['message' => 'Lobby was successfully deleted'];
        } else {
            return ['message' => 'Lobby could not be deleted'];
        }
    }

    public function create(
        string $idAdmin,
        string $label,
        string $description,
        string $private,
        string $logoName,
        string $logoTmpName
    ): array
    {
        $successfulInsert = $this->send_query('
            INSERT INTO ccp_lobby
            (label_lobby, description, logo, private)
            VALUES
            (?, ?, ?, ?)
        ',
            [$label, $description, $logoName, 'true' === $private ? 1 : 0]);

        if ($successfulInsert) {
            $this->send_query('
                SELECT id_lobby
                FROM ccp_lobby
                ORDER BY id_lobby DESC 
                LIMIT 1
            ',
                []);

            $idLobby = (int)$this->fetchData([])[0]['id_lobby'];

            $successfulUpload = $this->uploadOnFTP($idLobby, $logoName, $logoTmpName, '/logo/', ['jpg', 'jpeg', 'ico', 'png', 'svg', 'bmp']);

            $this->send_query('
                INSERT INTO ccp_is_admin
                (id_user, id_lobby) VALUES (?, ?)
            ',
                [$idAdmin, $idLobby]);

            return ['message' => 'Lobby was successfully uploaded',
                    'id_lobby' => $idLobby,
                    'logoPath' => $logoName,
                ];
        } else {
            return ['message' => 'Lobby could not be created'];
        }
    }

    public function idUserFromToken(string $token) {
        $decoded = $this->getUserFromToken($token);
        return $this->findUser($decoded['email']);
    }
}
