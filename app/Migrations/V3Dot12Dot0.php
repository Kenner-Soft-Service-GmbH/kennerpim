<?php
/**
 * Pim
 * Free Extension
 * Copyright (c) TreoLabs GmbH
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Pim\Migrations;

use DamCommon\Services\MigrationPimImage;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\File\Manager;
use PDO;
use Treo\Core\FilePathBuilder;
use Treo\Core\FileStorage\Storages\UploadDir;
use Treo\Core\Migration\AbstractMigration;
use Treo\Core\Utils\Auth;

/**
 * Migration class for version 3.12.0
 *
 * @author m.kokhanskyi@treolabs.com
 */
class V3Dot12Dot0 extends AbstractMigration
{
    /**
     * Max execute queries at a time
     */
    const MAX_QUERY = 3000;

    /**
     * @var array
     */
    protected $sqlUpdate = [];

    /**
     * @inheritdoc
     * @throws Error
     */
    public function up(): void
    {
        (new Auth($this->getContainer()))->useNoAuth();

        $config = $this->getContainer()->get('config');
        //set flag about installed Pim and Image
        $config->set('pimAndDamInstalled', false);

        if (!$this->getContainer()->get('metadata')->isModuleInstalled('Dam')) {
            $this->sendNotification();
        } elseif(!empty($this->getContainer()->get('metadata')->get('entityDefs.Product.links.assets'))) {
            //migration pimImage
            $migrationPimImage = new MigrationPimImage();
            $migrationPimImage->setContainer($this->getContainer());
            $migrationPimImage->run();

            //set flag about installed Pim and Image
            $config->set('pimAndDamInstalled', true);
        }
        $config->save();
    }

    /**
     * Install module Dam
     */
    protected function sendNotification(): void
    {
        $em = $this
            ->getContainer()
            ->get('entityManager');
        $users = $em->getRepository('User')->getAdminUsers();
        if (!empty($users)) {
            foreach ($users as $user) {
                $message = 'In the new <a href="https://treopim.com">TreoPIM </a> version, the PimImage entity is replaced with the <a href="https://treodam.com/">TreoDAM module</a>. 
                So to continue work with the images, please, install the latest version of the <a href="https://treodam.com/">TreoDAM module</a>.';
                // create notification
                $notification = $em->getEntity('Notification');
                $notification->set('type', 'Message');
                $notification->set('message', $message);
                $notification->set('userId', $user['id']);
                // save notification
                $em->saveEntity($notification);
            }
        }
    }

    /**
     * @inheritdoc
     * @throws Error
     */
    public function down(): void
    {
        (new Auth($this->getContainer()))->useNoAuth();

        $attachments = $this
            ->getEntityManager()
            ->nativeQuery(
                "SELECT att.id, att.name, a.private, att.storage_file_path, a.id as asset_id 
                                FROM asset AS a
                                    RIGHT JOIN asset_relation AS ar 
                                        ON a.id = ar.asset_id 
                                            AND ar.deleted = 0 
                                            AND ar.entity_name IN ('Product', 'Category')
                                    RIGHT JOIN attachment AS att 
                                        ON a.file_id = att.id AND att.deleted = 0
                                WHERE a.type = 'Gallery Image' AND a.deleted = 0 AND att.storage = 'DAMUploadDir'"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
        $assetIds = [];

        foreach ($attachments as $k => $attachment) {
            $oldPath = ($attachment['private'] == '1' ? 'data/dam/private/' : 'data/dam/public/')
                . "master/" . $attachment['storage_file_path'] . "/" . $attachment['name'];

            if (file_exists($oldPath)) {
                $attachmentUpdate = [];
                $storagePath = $this->getFilePath(FilePathBuilder::UPLOAD);
                $newPath = UploadDir::BASE_PATH . $storagePath . "/" . $attachment['name'];

                if ($this->getFileManager()->move($oldPath, $newPath, false)) {
                    $attachmentUpdate['storage_file_path'] = $storagePath;
                    $attachmentUpdate['storage'] = 'UploadDir';
                    $attachmentUpdate['related_type'] = 'PimImage';
                    $attachmentUpdate['related_id'] = null;
                    $this->updateById('attachment', $attachmentUpdate, $attachment['id']);
                    $assetIds[] = $attachment['asset_id'];
                }
            }
            if (empty($attachmentUpdate)) {
                unset($attachments[$k]);
            }
        }
        $this->executeUpdate($this->sqlUpdate);
        if (!empty($assetIds)) {
            $assetIds = "'" . implode("','", $assetIds) . "'";
            //insert pimImages
            $this->insertPimImage('Product', $assetIds);
            $this->insertPimImage('Category', $assetIds);

            //insert pim_image_channel
            $this->insertPimImageChannel('Product', $assetIds);
            $this->insertPimImageChannel('Category', $assetIds);

            $this->updateMainImageDown('Product');
            $this->updateMainImageDown('Category');

            $this
                ->getEntityManager()
                ->nativeQuery("DELETE FROM asset WHERE id IN ({$assetIds});");

            $this
                ->getEntityManager()
                ->nativeQuery(
                    "DELETE 
                                    FROM asset_relation_channel 
                                    WHERE asset_relation_id 
                                            IN (SELECT id FROM asset_relation WHERE asset_id IN ({$assetIds}))"
                );
            $this
                ->getEntityManager()
                ->nativeQuery("DELETE FROM asset_relation WHERE asset_id IN ({$assetIds})");

            $renditions = $this
                ->getEntityManager()
                ->nativeQuery("SELECT id FROM rendition WHERE asset_id IN ({$assetIds})")
                ->fetchAll(PDO::FETCH_COLUMN);

            $renditions = "'" . implode("','", $renditions) . "'";

            $this
                ->getEntityManager()
                ->nativeQuery("DELETE FROM rendition WHERE asset_id IN ({$assetIds})");

            $sql = "DELETE FROM asset_meta_data WHERE asset_id IN ({$assetIds})";
            if (!empty($renditions)) {
                $sql .= " OR rendition_id IN ({$renditions})";
            }
            $this
                ->getEntityManager()
                ->nativeQuery($sql);

            //set flag about installed Pim and Image

            $this->getContainer()->get('config')->remove('pimAndDamInstalled');
            $this->getContainer()->get('config')->save();
        }
    }

    /**
     * @param string $entityName
     * @param string $assetIds
     */
    protected function insertPimImage(string $entityName, string $assetIds)
    {
        if ($entityName == 'Product') {
            $select = " ar.entity_id AS product_id,
                        null AS category_id";
        } elseif ($entityName == 'Category') {
            $select = " null AS product_id,
                        ar.entity_id AS category_id";
        } else {
            return;
        }
        $sql = "                
                INSERT INTO pim_image
                (id, name, image_id, deleted, sort_order, scope, assigned_user_id, product_id, category_id)
                SELECT
                    SUBSTR(MD5(CONCAT(ar.id, RAND())), 16) as id,
                    a.name,
                    a.file_id AS image_id,
                    a.deleted,
                    CASE
                       WHEN ar.sort_order IS NOT NULL THEN ar.sort_order
                       ELSE (SELECT @n := @n + CASE 
                                                WHEN max(ar1.sort_order) is not null 
                                                THEN max(ar1.sort_order) 
                                                ELSE 1 END
                             FROM asset_relation AS ar1,
                                  (SELECT @n := 1) s
                             WHERE ar1.entity_id = ar.entity_id)
                       END AS sort_order,
                    ar.scope,
                    ar.assigned_user_id,
                    {$select}
                FROM asset AS a
                         RIGHT JOIN asset_relation AS ar
                                    ON a.id = ar.asset_id
                                        AND ar.deleted = 0
                                        AND ar.entity_name = '{$entityName}'
                         RIGHT JOIN attachment AS att ON a.file_id = att.id AND att.deleted = 0
                WHERE a.type = 'Gallery Image'
                    AND a.deleted = 0
                  AND a.id IN ({$assetIds});";

        $this->getEntityManager()->nativeQuery($sql);
    }

    /**
     * @param string $entityName
     */
    protected function updateMainImageDown(string $entityName)
    {
        if ($entityName == 'Product') {
            $where = ' AND pi.product_id IS NOT NULL AND pi.product_id != \'\'';
            $wherePimImage = ' AND pim_image.product_id IS NOT NULL AND pim_image.product_id != \'\'';
            $fieldLink = 'product_id';
        } elseif ($entityName == 'Category') {
            $where = ' AND pi.category_id IS NOT NULL AND pi.category_id != \'\'';
            $wherePimImage = ' AND pim_image.category_id IS NOT NULL AND pim_image.category_id != \'\'';
            $fieldLink = 'category_id';
        } else {
            return;
        }

        $table = lcfirst($entityName);
        $sql = "UPDATE {$table} p
                RIGHT JOIN (SELECT pim_image.$fieldLink, min(pim_image.sort_order) as sort
                        FROM pim_image
                        WHERE pim_image.deleted = 0 {$wherePimImage}
                        GROUP BY pim_image.$fieldLink
                    ) as sort ON sort.$fieldLink = p.id
                LEFT JOIN pim_image pi ON pi.$fieldLink = sort.$fieldLink
                              AND pi.sort_order = sort.sort
                              {$where}
                SET p.image_id = pi.image_id
                WHERE p.deleted = 0;";

        $this->getEntityManager()->nativeQuery($sql);
    }

    /**
     * @param string $entityName
     * @param string $assetIds
     */
    protected function insertPimImageChannel(string $entityName, string $assetIds)
    {
        $where = '';
        if ($entityName == 'Product') {
            $where = ' pi.product_id IS NOT NULL AND pi.product_id != \'\'';
        } elseif ($entityName == 'Category') {
            $where = ' pi.category_id IS NOT NULL AND pi.category_id != \'\'';
        } else {
            return;
        }
        $this->getEntityManager()
            ->nativeQuery(
                "INSERT INTO pim_image_channel (channel_id, pim_image_id)
                                    SELECT arc.channel_id, pi.id AS pim_image_id
                                    FROM asset AS a
                                             RIGHT JOIN asset_relation ar
                                                        ON ar.asset_id = a.id
                                                            AND ar.deleted = 0
                                                            AND ar.scope = 'Channel'
                                                            AND ar.entity_name = '{$entityName}'
                                             RIGHT JOIN asset_relation_channel arc
                                                        ON ar.id = arc.asset_relation_id
                                                            AND arc.deleted = 0
                                             LEFT JOIN pim_image pi
                                                       ON pi.image_id = a.file_id
                                                           AND pi.deleted = 0
                                                           AND pi.scope = 'Channel'
                                    WHERE {$where} 
                                        AND a.deleted = 0 
                                        AND a.type = 'Gallery Image' 
                                        AND a.id IN ({$assetIds});"
            );
    }

    /**
     * @param string $table
     * @param array  $values
     * @param string $id
     */
    protected function updateById(string $table, array $values, string $id): void
    {
        $setValues = [];
        foreach ($values as $field => $value) {
            $setValues[] = "{$field} = '{$value}'";
        }
        if (!empty($setValues) && !empty($id)) {
            $this->sqlUpdate[] = 'UPDATE ' . $table . ' SET ' . implode(',', $setValues) . " WHERE id = '{$id}'";
        }
        if (count($this->sqlUpdate) >= self::MAX_QUERY) {
            $this->executeUpdate($this->sqlUpdate);
        }
    }

    /**
     * Execute Sql-Update for Attachments
     *
     * @param array $queries
     */
    protected function executeUpdate(array $queries): void
    {
        if (!empty($queries)) {
            $this->getEntityManager()->nativeQuery(implode(';', $queries));
        }
        $this->sqlUpdate = [];
    }

    /**
     * @param $type
     *
     * @return string
     */
    protected function getFilePath($type): string
    {
        return $this->getContainer()->get('filePathBuilder')->createPath($type);
    }

    /**
     * Get file manager
     *
     * @return Manager
     */
    protected function getFileManager()
    {
        return $this->getContainer()->get('fileManager');
    }
}
