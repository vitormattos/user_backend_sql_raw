<?php
/**
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserBackendSqlRaw\Backend;

use OCA\UserBackendSqlRaw\Config;
use OCA\UserBackendSqlRaw\Db;
use OCP\Group\Backend\ABackend;
use OCP\Group\Backend\IGroupDetailsBackend;
use Psr\Log\LoggerInterface;

class GroupBackend extends ABackend implements IGroupDetailsBackend
{
    public function __construct(
        private LoggerInterface $logger,
        private Config $config,
        private Db $db,
    ) {
    }

    public function inGroup($uid, $gid): bool
    {
        $queryFromConfig = $this->config->getQueryInGroup();
        if (empty($queryFromConfig)) {
            return false;
        }
        $statement = $this->db->getDbHandle()->prepare($queryFromConfig);
        $statement->execute(['username' => $uid, 'group' => $gid]);
        $inGroup = $statement->fetchColumn();
        return (bool) $inGroup;
    }

    public function getUserGroups($uid)
    {
        $queryFromConfig = $this->config->getQueryUserGroups();
        if (empty($queryFromConfig)) {
            return [];
        }
        $statement = $this->db->getDbHandle()->prepare($queryFromConfig);
        $statement->execute(['username' => $uid]);
        $groups = $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
        return $groups;
    }

    public function getGroups($search = '', $limit = -1, $offset = 0)
    {
        $queryFromConfig = $this->config->getQueryGroups();
        if (empty($queryFromConfig)) {
            return [];
        }
        isset($limit) && $limit > 0 ? $limitSegment = ' LIMIT :limit' : $limitSegment = '';
        isset($offset) && $offset > 0? $offsetSegment = ' OFFSET :offset' : $offsetSegment = '';
        $finalQuery = $queryFromConfig . $limitSegment . $offsetSegment;
        $statement = $this->db->getDbHandle()->prepare($finalQuery);
        // Because MariaDB can not handle string parameters for LIMIT/OFFSET we have to bind the
        // values "manually" instead of passing an array to execute(). This is another instance of
        // MariaDB making the code "uglier".
        $statement->bindValue(':search', '%' . $search . '%', \PDO::PARAM_STR);
        if (isset($limit) && $limit > 0) {
            $statement->bindValue(':limit', intval($limit), \PDO::PARAM_INT);
        }
        if (isset($offset) && $offset > 0) {
            $statement->bindValue(':offset', intval($offset), \PDO::PARAM_INT);
        }
        $statement->execute();
        $groups = $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
        return $groups;
    }

    public function groupExists($gid)
    {
        $queryFromConfig = $this->config->getQueryGroupExists();
        if (empty($queryFromConfig)) {
            return false;
        }
        $statement = $this->db->getDbHandle()->prepare($queryFromConfig);
        $statement->execute(['group' => $gid]);
        $groupExists = $statement->fetchColumn();
        return (bool) $groupExists;
    }

    public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0)
    {
        $queryFromConfig = $this->config->getQueryUsersInGroup();
        if (empty($queryFromConfig)) {
            return [];
        }
        isset($limit) && $limit > 0 ? $limitSegment = ' LIMIT :limit' : $limitSegment = '';
        isset($offset) && $offset > 0? $offsetSegment = ' OFFSET :offset' : $offsetSegment = '';
        $finalQuery = $queryFromConfig . $limitSegment . $offsetSegment;
        $statement = $this->db->getDbHandle()->prepare($finalQuery);
        // Because MariaDB can not handle string parameters for LIMIT/OFFSET we have to bind the
        // values "manually" instead of passing an array to execute(). This is another instance of
        // MariaDB making the code "uglier".
        $statement->bindValue(':search', '%' . $search . '%', \PDO::PARAM_STR);
        $statement->bindValue(':group', $gid, \PDO::PARAM_STR);
        if (isset($limit) && $limit > 0) {
            $statement->bindValue(':limit', intval($limit), \PDO::PARAM_INT);
        }
        if (isset($offset) && $offset > 0) {
            $statement->bindValue(':offset', intval($offset), \PDO::PARAM_INT);
        }
        $statement->execute();
        $groups = $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
        return $groups;
    }

    public function getGroupDetails(string $gid): array
    {
        $queryFromConfig = $this->config->getQueryGroupDetails();
        if (empty($queryFromConfig)) {
            return [];
        }
        $statement = $this->db->getDbHandle()->prepare($queryFromConfig);
        $statement->execute(['group' => $gid]);
        $groupDetails = $statement->fetchColumn();
        if (!$groupDetails) {
            return [];
        }
        return ['displayName' => $groupDetails];
    }
}
