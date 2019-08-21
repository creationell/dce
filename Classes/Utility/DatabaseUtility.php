<?php
namespace T3\Dce\Utility;

/*  | This extension is made with love for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2012-2019 Armin Vieweg <armin@v.ieweg.de>
 *  |     2019 Stefan Froemken <froemken@gmail.com>
 */
use T3\Dce\Domain\Model\Dce;
use T3\Dce\Domain\Repository\DceRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class DatabaseUtility
{
    /**
     * Get TYPO3s Connection Pool
     *
     * @return ConnectionPool
     */
    public static function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * Give it a non executed QueryBuilder to fetch result as array
     * You have the possibility to add a col as ArrayKey
     *
     * @param QueryBuilder $queryBuilder
     * @param string $columnAsKey
     * @return array
     */
    public static function getRowsFromQueryBuilder(QueryBuilder $queryBuilder, string $columnAsKey = ''): array
    {
        $statement = $queryBuilder->execute();
        $rows = [];
        while ($row = $statement->fetch()) {
            if (!empty($columnAsKey)) {
                $rows[$row[$columnAsKey]] = $row;
            } else {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * Get all tables and table configuration of all configured databases
     *
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function adminGetTables() : array
    {
        $tables = [];
        foreach (DatabaseUtility::getConnectionPool()->getConnectionNames() as $connectionName) {
            $connection = DatabaseUtility::getConnectionPool()->getConnectionByName($connectionName);
            $statement = $connection->query('SHOW TABLE STATUS FROM `' . $connection->getDatabase() . '`');
            while ($tableRow = $statement->fetch()) {
                $tables[$tableRow['Name']] = $tableRow;
            }
        }

        return $tables;
    }

    /**
     * Get all fields and field configuration of given $tableName
     *
     * @param string $tableName
     * @return \Doctrine\DBAL\Schema\AbstractSchemaManager|\Doctrine\DBAL\Schema\Column[]|\TYPO3\CMS\Core\Database\Connection
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function adminGetFields(string $tableName) : array
    {
        $fields = [];
        foreach (DatabaseUtility::getConnectionPool()->getConnectionNames() as $connectionName) {
            $connection = DatabaseUtility::getConnectionPool()->getConnectionByName($connectionName);
            $statement = $connection->query('SHOW FULL COLUMNS FROM `' . $tableName . '`');
            while ($fieldRow = $statement->fetch()) {
                $fields[$fieldRow['Field']] = $fieldRow;
            }
        }

        return $fields;
    }

    /**
     * Gets dce uid by content element uid
     *
     * @param array $row of tt_content record
     * @return int uid of DCE used for this content element
     */
    public static function getDceUidByContentElementRow(array $row) : int
    {
        return DceRepository::extractUidFromCTypeOrIdentifier($row['CType']) ?? 0;
    }

    /**
     * Creates DCE domain object for a given content element
     *
     * @param array|int|string|null $contentElement The content element database record (or UID)
     * @return Dce|null The constructed DCE object or null
     */
    public static function getDceObjectForContentElement($contentElement = null) : ?Dce
    {
        if ($contentElement === null || (\is_string($contentElement) && strpos($contentElement, 'NEW') === 0)) {
            throw new \InvalidArgumentException('This is a new content element, can\'t create DCE instance from it.');
        }
        // Make this method more comfortable:
        // Retrieve content element record if only UID is given.
        if (is_numeric($contentElement)) {
            $contentElement = BackendUtility::getRecordWSOL(
                'tt_content',
                $contentElement,
                '*',
                '',
                false
            );
        }

        // If "pi_flexform" field is not set in the passed contenet element record
        // retrieve the whole tt_content record
        if (!isset($contentElement['pi_flexform'])) {
            $contentElement = BackendUtility::getRecordWSOL(
                'tt_content',
                $contentElement['uid'],
                '*',
                '',
                false
            );
        }

        // Make instance of "DceRepository" and "FlexFormService"
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var DceRepository $dceRepository */
        $dceRepository = $objectManager->get(DceRepository::class);

        // Convert flexform XML to array
        $flexData = FlexformService::get()
                        ->convertFlexFormContentToArray($contentElement['pi_flexform'], 'lDEF', 'vDEF');

        // Retrieve DCE domain model object
        $dceUid = self::getDceUidByContentElementRow($contentElement);
        $dce = $dceRepository->findAndBuildOneByUid(
            $dceUid,
            $flexData['settings'] ?? [],
            $contentElement
        );
        return $dce;
    }
}
