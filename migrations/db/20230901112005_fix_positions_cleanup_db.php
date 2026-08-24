<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

class FixPositionsCleanupDb extends AbstractMigration
{
    public function postFlightCheck(): void
    {
        try {
            $adapter = $this->getAdapter();
            while (method_exists($adapter, 'getAdapter')) {
                $adapter = $adapter->getAdapter();
            }
            $ref = new \ReflectionProperty(get_class($adapter), 'actions');
            $ref->setAccessible(true);
            $ref->setValue($adapter, []);
        } catch (\Throwable $e) {}
    }

    public function change()
    {
        // Fix nullable recovery_codes of users.
        $this->execute('update users set recovery_codes = "' . serialize([]) . '" where recovery_codes IS NULL');
        $this->execute('ALTER TABLE `amvs` MODIFY updated_at datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;');

        /* Fix the amvs positions. */
        $amvsQuery = $this->query(
            'SELECT uuid, anr_id, asset_id, position FROM `amvs` ORDER BY anr_id, asset_id, position'
        );
        $previousAssetUuid = null;
        $previousAnrId = null;
        $expectedAmvPosition = 1;
        foreach ($amvsQuery->fetchAll() as $amvData) {
            if ($previousAssetUuid === null) {
                $previousAssetUuid = $amvData['asset_id'];
                $previousAnrId = $amvData['anr_id'];
            }
            if ($amvData['asset_id'] !== $previousAssetUuid
                || $previousAnrId !== $amvData['anr_id']
            ) {
                $expectedAmvPosition = 1;
            }

            if ($expectedAmvPosition !== $amvData['position']) {
                $this->execute(
                    sprintf(
                        'UPDATE amvs SET position = %d WHERE uuid = "%s"',
                        $expectedAmvPosition,
                        $amvData['uuid']
                    )
                );
            }

            $expectedAmvPosition++;
            $previousAssetUuid = $amvData['asset_id'];
            $previousAnrId = $amvData['anr_id'];
        }

        /* Fix the objects compositions positions. */
        $objectsQuery = $this->query(
            'SELECT id, anr_id, father_id, position FROM objects_objects ORDER BY anr_id, father_id, position'
        );
        $previousParentObjectId = null;
        $previousAnrId = null;
        $expectedCompositionLinkPosition = 1;
        foreach ($objectsQuery->fetchAll() as $compositionObjectsData) {
            if ($previousParentObjectId === null) {
                $previousParentObjectId = $compositionObjectsData['father_id'];
                $previousAnrId = $compositionObjectsData['anr_id'];
            }
            if ($compositionObjectsData['father_id'] !== $previousParentObjectId
                || $previousAnrId !== $compositionObjectsData['anr_id']
            ) {
                $expectedCompositionLinkPosition = 1;
            }

            if ($expectedCompositionLinkPosition !== $compositionObjectsData['position']) {
                $this->execute(sprintf(
                    'UPDATE objects_objects SET position = %d WHERE id = %d',
                    $expectedCompositionLinkPosition,
                    $compositionObjectsData['id']
                ));
            }

            $expectedCompositionLinkPosition++;
            $previousParentObjectId = $compositionObjectsData['father_id'];
            $previousAnrId = $compositionObjectsData['anr_id'];
        }
        /* Add a unique key for the object composition. */
        $this->table('objects_objects')->addIndex(['father_id', 'child_id', 'anr_id'], ['unique' => true])->update();

        /* Fix the objects categories positions. */
        $objectsCategoriesQuery = $this->query(
            'SELECT id, anr_id, parent_id, position FROM objects_categories ORDER BY anr_id, parent_id, position'
        );
        $previousParentCategoryId = -1;
        $previousAnrId = null;
        $expectedCategoryPosition = 1;
        foreach ($objectsCategoriesQuery->fetchAll() as $objectCategoryData) {
            if ($previousParentCategoryId === -1) {
                $previousParentCategoryId = $objectCategoryData['parent_id'];
                $previousAnrId = $objectCategoryData['anr_id'];
            }
            if ($objectCategoryData['parent_id'] !== $previousParentCategoryId
                || $previousAnrId !== $objectCategoryData['anr_id']
            ) {
                $expectedCategoryPosition = 1;
            }

            if ($expectedCategoryPosition !== $objectCategoryData['position']) {
                $this->execute(
                    sprintf(
                        'UPDATE objects_categories SET position = %d WHERE id = %d',
                        $expectedCategoryPosition,
                        $objectCategoryData['id']
                    )
                );
            }

            $expectedCategoryPosition++;
            $previousParentCategoryId = $objectCategoryData['parent_id'];
            $previousAnrId = $objectCategoryData['anr_id'];
        }

        /* Fix instances positions to have them in a correct sequence (1, 2, 3, ...). */
        $instancesQuery = $this->query(
            'SELECT id, anr_id, parent_id, position FROM instances ORDER BY anr_id, parent_id, position'
        );
        $previousParentInstanceId = null;
        $expectedInstancePosition = 1;
        foreach ($instancesQuery->fetchAll() as $instanceData) {
            if ($previousParentInstanceId === null) {
                $previousParentInstanceId = (int)$instanceData['parent_id'];
            }
            if ((int)$instanceData['parent_id'] !== $previousParentInstanceId) {
                $expectedInstancePosition = 1;
            }

            if ($expectedInstancePosition !== $instanceData['position']) {
                $this->execute(sprintf(
                    'UPDATE instances SET position = %d WHERE id = %d',
                    $expectedInstancePosition,
                    $instanceData['id']
                ));
            }

            $expectedInstancePosition++;
            $previousParentInstanceId = $instanceData['parent_id'];
        }

        /* Clean up unused columns. */
        if ($this->table('clients')->hasColumn('model_id')) {
            $this->table('clients')->removeColumn('model_id')->update();
        }

        $instTable = $this->table('instances');
        if ($instTable->hasColumn('disponibility')) { $instTable->removeColumn('disponibility'); }
        if ($instTable->hasColumn('asset_type')) { $instTable->removeColumn('asset_type'); }
        if ($instTable->hasColumn('exportable')) { $instTable->removeColumn('exportable'); }
        $instTable->update();

        try {
            $this->execute('ALTER TABLE objects ROW_FORMAT=DYNAMIC;');
        } catch (\Throwable $e) {}

        $objTable = $this->table('objects');
        if ($objTable->hasColumn('disponibility')) { $objTable->removeColumn('disponibility'); }
        if ($objTable->hasColumn('token_import')) { $objTable->removeColumn('token_import'); }
        if ($objTable->hasColumn('original_name')) { $objTable->removeColumn('original_name'); }
        if ($objTable->hasColumn('position')) { $objTable->removeColumn('position'); }
        $objTable->update();

        $instConsTable = $this->table('instances_consequences');
        if ($instConsTable->hasColumn('object_id')) { $instConsTable->removeColumn('object_id'); }
        if ($instConsTable->hasColumn('locally_touched')) { $instConsTable->removeColumn('locally_touched'); }
        $instConsTable->update();

        /* Fix possibly missing soacategory. */
        $measuresQuery = $this->query(
            'SELECT uuid, referential_uuid, anr_id FROM measures WHERE soacategory_id IS NULL;'
        );
        $soaCategoryIds = [];
        $soaCategoryTable = $this->table('soacategory');
        foreach ($measuresQuery->fetchAll() as $measureData) {
            $anrId = (int)$measureData['anr_id'];
            if (!isset($soaCategoryIds[$anrId])) {
                $soaCategoryTable->insert([
                    'label1' => 'catégorie manquante',
                    'label2' => 'missing category',
                    'label3' => 'fehlende Kategorie',
                    'label4' => 'ontbrekende categorie',
                    'anr_id' => $anrId,
                    'referential_uuid' => $measureData['referential_uuid'],
                ])->saveData();
                $soaCategoryIds[$anrId] = (int)$this->getAdapter()->getConnection()->lastInsertId();
            }

            $this->execute('UPDATE measures SET soacategory_id = ' . $soaCategoryIds[$anrId]
                . ' WHERE uuid = "' . $measureData['uuid'] . '" and anr_id = ' . $anrId);
        }

        /* Replace UUID identifier in measures table to ID. */
        if (!$this->table('measures')->hasColumn('id')) {
            $this->table('measures')
                ->addColumn('id', 'integer', ['signed' => false, 'after' => MysqlAdapter::FIRST])
                ->update();
            $this->execute('SET @a = 0; UPDATE measures SET id = @a := @a + 1 ORDER BY anr_id;');
            $this->table('measures')->changePrimaryKey(['id'])->addIndex(['uuid', 'anr_id'], ['unique' => true])->update();
            $this->table('measures')->changeColumn('id', 'integer', ['identity' => true, 'signed' => false])->update();
        }

        /* Correct MeasuresMeasures table structure. */
        if (!$this->table('measures_measures')->hasColumn('id')) {
            $mmTable = $this->table('measures_measures');
            $mmTable->addColumn('id', 'integer', ['signed' => false, 'after' => MysqlAdapter::FIRST])
                    ->addColumn('master_measure_id', 'integer', ['signed' => false, 'after' => 'id'])
                    ->addColumn('linked_measure_id', 'integer', ['signed' => false, 'after' => 'master_measure_id']);
            try { $mmTable->dropForeignKey(['father_id', 'child_id', 'anr_id']); } catch (\Throwable $e) {}
            if ($mmTable->hasColumn('creator')) $mmTable->removeColumn('creator');
            if ($mmTable->hasColumn('created_at')) $mmTable->removeColumn('created_at');
            if ($mmTable->hasColumn('updater')) $mmTable->removeColumn('updater');
            if ($mmTable->hasColumn('updated_at')) $mmTable->removeColumn('updated_at');
            $mmTable->update();
            $this->execute('SET @a = 0; UPDATE measures_measures SET id = @a := @a + 1 ORDER BY anr_id;');
            $this->table('measures_measures')
                ->changePrimaryKey(['id'])
                ->update();
            $this->table('measures_measures')
                ->changeColumn('id', 'integer', ['identity' => true, 'signed' => false])
                ->update();
        }
        /* Remove unlinked measures links with measures and update the new relation field. */
        try {
            if ($this->table('measures_measures')->hasColumn('father_id')) {
                $this->execute('UPDATE measures_measures mm INNER JOIN measures m '
                    . 'ON mm.father_id = m.`uuid` AND mm.anr_id = m.anr_id SET master_measure_id = m.id;');
                $this->execute('UPDATE measures_measures mm INNER JOIN measures m '
                    . 'ON mm.child_id = m.`uuid` AND mm.anr_id = m.anr_id SET linked_measure_id = m.id;');
                $this->execute('DELETE FROM measures_measures WHERE master_measure_id IS NULL OR linked_measure_id IS NULL;');
                $this->table('measures_measures')
                    ->removeColumn('anr_id')
                    ->removeColumn('father_id')
                    ->removeColumn('child_id')
                    ->addForeignKey('master_measure_id', 'measures', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
                    ->addForeignKey('linked_measure_id', 'measures', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
                    ->addIndex(['master_measure_id', 'linked_measure_id'], ['unique' => true])
                    ->update();
            }
        } catch (\Throwable $e) {}

        /* Apply measures relation to soa. */
        try {
            if ($this->table('soa')->hasColumn('measure_id') && !$this->table('soa')->hasColumn('measure_uuid')) {
                $soaTable = $this->table('soa');
                try { $this->execute('ALTER TABLE `soa` DROP FOREIGN KEY `soa_ibfk_2`'); } catch (\Throwable $e) {}
                try { $soaTable->dropForeignKey(['measure_id', 'anr_id'])->update(); } catch (\Throwable $e) {}
                if ($soaTable->hasColumn('measure_id')) { $soaTable->renameColumn('measure_id', 'measure_uuid')->update(); }
                if (!$soaTable->hasColumn('measure_id')) { $soaTable->addColumn('measure_id', 'integer', ['signed' => false, 'after' => 'id'])->update(); }
                $this->execute('UPDATE soa s INNER JOIN measures m '
                    . 'ON s.measure_uuid = m.`uuid` AND s.anr_id = m.anr_id SET s.measure_id = m.id;');
                $soaTable
                    ->addForeignKey('measure_id', 'measures', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
                    ->removeColumn('measure_uuid')
                    ->update();
            }
        } catch (\Throwable $e) {}

        /* Apply measures relation to measures_amvs. */
        try {
            if ($this->table('measures_amvs')->hasColumn('anr_id2') || $this->table('measures_amvs')->hasColumn('measure_id')) {
                $measuresAmvsTable = $this->table('measures_amvs');
                try { $this->execute('ALTER TABLE `measures_amvs` DROP FOREIGN KEY `measures_amvs_ibfk_3`'); } catch (\Throwable $e) {}
                if ($measuresAmvsTable->hasColumn('anr_id2')) $measuresAmvsTable->removeColumn('anr_id2');
                if ($measuresAmvsTable->hasColumn('creator')) $measuresAmvsTable->removeColumn('creator');
                if ($measuresAmvsTable->hasColumn('created_at')) $measuresAmvsTable->removeColumn('created_at');
                if ($measuresAmvsTable->hasColumn('updater')) $measuresAmvsTable->removeColumn('updater');
                if ($measuresAmvsTable->hasColumn('updated_at')) $measuresAmvsTable->removeColumn('updated_at');
                if ($measuresAmvsTable->hasColumn('measure_id')) $measuresAmvsTable->renameColumn('measure_id', 'measure_uuid');
                $measuresAmvsTable->update();
                if (!$measuresAmvsTable->hasColumn('measure_id')) {
                    $measuresAmvsTable
                        ->addColumn('measure_id', 'integer', ['signed' => false, 'after' => MysqlAdapter::FIRST])
                        ->update();
                }
                $this->execute('UPDATE measures_amvs ma INNER JOIN measures m '
                    . 'ON ma.measure_uuid = m.`uuid` AND ma.anr_id = m.anr_id SET ma.measure_id = m.id;');
                $measuresAmvsTable
                    ->changePrimaryKey(['measure_id', 'amv_id', 'anr_id'])
                    ->addForeignKey('measure_id', 'measures', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
                    ->addForeignKey(
                        ['amv_id', 'anr_id'],
                        'amvs',
                        ['uuid', 'anr_id'],
                        ['delete' => 'CASCADE', 'update' => 'RESTRICT']
                    )
                    ->update();
                if ($measuresAmvsTable->hasColumn('measure_uuid')) $measuresAmvsTable->removeColumn('measure_uuid')->update();
            }
        } catch (\Throwable $e) {}

        /* Apply measures relation to measures_rolf_risks. */
        try {
            if ($this->table('measures_rolf_risks')->hasColumn('creator') || $this->table('measures_rolf_risks')->hasColumn('measure_id')) {
                $measuresRolfRisksTable = $this->table('measures_rolf_risks');
                try {
                    $this->execute('ALTER TABLE `measures_rolf_risks` DROP FOREIGN KEY `measures_rolf_risks_ibfk_1`, DROP FOREIGN KEY `measures_rolf_risks_ibfk_3`');
                } catch (\Throwable $e) {}
                if ($measuresRolfRisksTable->hasColumn('creator')) $measuresRolfRisksTable->removeColumn('creator');
                if ($measuresRolfRisksTable->hasColumn('created_at')) $measuresRolfRisksTable->removeColumn('created_at');
                if ($measuresRolfRisksTable->hasColumn('updater')) $measuresRolfRisksTable->removeColumn('updater');
                if ($measuresRolfRisksTable->hasColumn('updated_at')) $measuresRolfRisksTable->removeColumn('updated_at');
                if ($measuresRolfRisksTable->hasColumn('measure_id')) $measuresRolfRisksTable->renameColumn('measure_id', 'measure_uuid');
                $measuresRolfRisksTable->update();
                if (!$measuresRolfRisksTable->hasColumn('measure_id')) {
                    $measuresRolfRisksTable
                        ->addColumn('measure_id', 'integer', ['signed' => false, 'after' => 'id'])
                        ->update();
                }
                $this->execute('UPDATE measures_rolf_risks mr INNER JOIN measures m '
                    . 'ON mr.measure_uuid = m.`uuid` AND mr.anr_id = m.anr_id SET mr.measure_id = m.id;');
                $measuresRolfRisksTable
                    ->addForeignKey('measure_id', 'measures', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT']);
                if ($measuresRolfRisksTable->hasColumn('measure_uuid')) $measuresRolfRisksTable->removeColumn('measure_uuid');
                if ($measuresRolfRisksTable->hasColumn('anr_id')) $measuresRolfRisksTable->removeColumn('anr_id');
                $measuresRolfRisksTable->update();
            }
        } catch (\Throwable $e) {}

        /* Rename column of owner_id to risk_owner_id. */
        try {
            if ($this->table('instances_risks')->hasColumn('owner_id')) {
                try { $this->table('instances_risks')->dropForeignKey('owner_id')->update(); } catch (\Throwable $e) {}
                $this->table('instances_risks')->renameColumn('owner_id', 'risk_owner_id')->update();
                $this->table('instances_risks')->addForeignKey(
                    'risk_owner_id',
                    'instance_risk_owners',
                    'id',
                    ['delete' => 'SET NULL', 'update' => 'CASCADE']
                )->update();
            }
        } catch (\Throwable $e) {}

        try {
            if ($this->table('instances_risks_op')->hasColumn('owner_id')) {
                try { $this->table('instances_risks_op')->dropForeignKey('owner_id')->update(); } catch (\Throwable $e) {}
                $irOpTable = $this->table('instances_risks_op');
                $irOpTable->renameColumn('owner_id', 'risk_owner_id');
                if ($irOpTable->hasColumn('brut_r')) $irOpTable->removeColumn('brut_r');
                if ($irOpTable->hasColumn('brut_o')) $irOpTable->removeColumn('brut_o');
                if ($irOpTable->hasColumn('brut_l')) $irOpTable->removeColumn('brut_l');
                if ($irOpTable->hasColumn('brut_f')) $irOpTable->removeColumn('brut_f');
                if ($irOpTable->hasColumn('brut_p')) $irOpTable->removeColumn('brut_p');
                if ($irOpTable->hasColumn('net_r')) $irOpTable->removeColumn('net_r');
                if ($irOpTable->hasColumn('net_o')) $irOpTable->removeColumn('net_o');
                if ($irOpTable->hasColumn('net_l')) $irOpTable->removeColumn('net_l');
                if ($irOpTable->hasColumn('net_f')) $irOpTable->removeColumn('net_f');
                if ($irOpTable->hasColumn('net_p')) $irOpTable->removeColumn('net_p');
                if ($irOpTable->hasColumn('targeted_r')) $irOpTable->removeColumn('targeted_r');
                if ($irOpTable->hasColumn('targeted_o')) $irOpTable->removeColumn('targeted_o');
                if ($irOpTable->hasColumn('targeted_l')) $irOpTable->removeColumn('targeted_l');
                if ($irOpTable->hasColumn('targeted_f')) $irOpTable->removeColumn('targeted_f');
                if ($irOpTable->hasColumn('targeted_p')) $irOpTable->removeColumn('targeted_p');
                $irOpTable->update();
                $this->table('instances_risks_op')->addForeignKey(
                    'risk_owner_id',
                    'instance_risk_owners',
                    'id',
                    ['delete' => 'SET NULL', 'update' => 'CASCADE']
                )->update();
            }
        } catch (\Throwable $e) {}

        /* The tables are not needed. */
        if ($this->hasTable('anrs_objects')) { $this->table('anrs_objects')->drop()->update(); }
        if ($this->hasTable('anrs_objects_categories')) { $this->table('anrs_objects_categories')->drop()->update(); }

        if ($this->hasTable('anr_metadatas_on_instances') && !$this->hasTable('anr_instance_metadata_fields')) {
            $this->execute('SET FOREIGN_KEY_CHECKS=0;');
            $this->execute('CREATE TABLE IF NOT EXISTS anr_instance_metadata_fields LIKE anr_metadatas_on_instances;');
            $this->execute('INSERT IGNORE INTO anr_instance_metadata_fields SELECT * FROM anr_metadatas_on_instances;');
            $this->execute('DROP TABLE IF EXISTS anr_metadatas_on_instances;');
            $this->execute('SET FOREIGN_KEY_CHECKS=1;');
        }
        if ($this->hasTable('instances_metadatas') && !$this->hasTable('instances_metadata')) {
            $this->execute('SET FOREIGN_KEY_CHECKS=0;');
            $this->execute('CREATE TABLE IF NOT EXISTS instances_metadata LIKE instances_metadatas;');
            $this->execute('INSERT IGNORE INTO instances_metadata SELECT * FROM instances_metadatas;');
            $this->execute('DROP TABLE IF EXISTS instances_metadatas;');
            $this->execute('SET FOREIGN_KEY_CHECKS=1;');
        }

        /*
         * Migrations for to move the data from translations table and remove it.
         * 1. Create the fields to insert the data.
         * 2. Copy the data from translations
         * 3. Remove the translation keys' columns and the translations table.
         */
        try {
            if (!$this->table('anr_instance_metadata_fields')->hasColumn('label')) {
                $this->table('anr_instance_metadata_fields')
                    ->changeColumn('anr_id', 'integer', ['signed' => false, 'null' => false])
                    ->addColumn('label', 'string', ['null' => false, 'limit' => 255, 'default' => ''])
                    ->update();
            }
            if (!$this->table('instances_metadata')->hasColumn('comment')) {
                $this->table('instances_metadata')
                    ->addColumn('comment', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR])
                    ->update();
            }
            if (!$this->table('operational_risks_scales_types')->hasColumn('label')) {
                $this->table('operational_risks_scales_types')
                    ->addColumn('label', 'string', ['null' => false, 'limit' => 255, 'default' => ''])
                    ->update();
            }
            if (!$this->table('operational_risks_scales_comments')->hasColumn('comment')) {
                $this->table('operational_risks_scales_comments')
                    ->addColumn('comment', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR])
                    ->update();
            }
            if (!$this->table('soa_scale_comments')->hasColumn('comment')) {
                $this->table('soa_scale_comments')
                    ->addColumn('comment', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR])
                    ->update();
            }
            try {
                $this->execute(
                    'ALTER TABLE anr_instance_metadata_fields CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;'
                );
                $this->execute('ALTER TABLE instances_metadata CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;');
                $this->execute('ALTER TABLE soa_scale_comments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;');
                $this->execute(
                    'UPDATE anr_instance_metadata_fields aim
                    INNER JOIN translations t ON aim.label_translation_key = t.translation_key
                        AND t.anr_id = aim.anr_id
                        AND t.type = "anr-metadatas-on-instances"
                    SET aim.label = t.value;'
                );
                $this->execute(
                    'UPDATE instances_metadata im
                    INNER JOIN anr_instance_metadata_fields aimf ON im.metadata_id = aimf.id
                    INNER JOIN translations t ON im.comment_translation_key = t.translation_key
                        AND t.anr_id = aimf.anr_id
                        AND t.type = "instance-metadata"
                    SET im.comment = t.value;'
                );
                $this->execute(
                    'UPDATE operational_risks_scales_types orst
                    INNER JOIN translations t ON orst.label_translation_key = t.translation_key
                        AND t.anr_id = orst.anr_id
                        AND t.type = "operational-risk-scale-type"
                    SET orst.label = t.value;'
                );
                $this->execute(
                    'UPDATE operational_risks_scales_comments orsc
                    INNER JOIN translations t ON orsc.comment_translation_key = t.translation_key
                        AND t.anr_id = orsc.anr_id
                        AND t.type = "operational-risk-scale-comment"
                    SET orsc.comment = t.value;'
                );
                $this->execute(
                    'UPDATE soa_scale_comments ssc
                    INNER JOIN translations t ON ssc.comment_translation_key = t.translation_key
                        AND t.anr_id = ssc.anr_id
                        AND t.type = "soa-scale-comment"
                    SET ssc.comment = t.value;'
                );
            } catch (\Throwable $e) {}

            /* Add label, name, description, comment columns to replace all the language specific fields (1, 2, 3, 4). */
            if (!$this->table('anrs')->hasColumn('label')) {
                $anrTable = $this->table('anrs');
                if ($anrTable->hasColumn('cache_model_is_scales_updatable')) {
                    $anrTable->renameColumn('cache_model_is_scales_updatable', 'cache_model_are_scales_updatable');
                }
                $anrTable
                    ->addColumn('label', 'string', ['null' => false, 'limit' => 255, 'default' => ''])
                    ->addColumn('description', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR])
                    ->addColumn('language_code', 'string', ['null' => false, 'limit' => 255, 'default' => 'fr'])
                    ->update();
                $anrsQuery = $this->query('SELECT id, language, label1, label2, label3, label4,
                    description1, description2, description3, description4, created_at FROM anrs'
                );
                $languageCodes = [1 => 'fr', 2 => 'en', 3 => 'de', 4 => 'nl'];
                $uniqueLabels = [];
                foreach ($anrsQuery->fetchAll() as $anrData) {
                    $labelName = 'label' . $anrData['language'];
                    if (isset($uniqueLabels[$anrData[$labelName]])) {
                        $uniqueLabels[$anrData[$labelName]] = $anrData[$labelName] . ' ['
                            . (!empty($anrData['created_at']) ? $anrData['created_at'] : date('Y-m-d H:i:s')) . ']';
                    } else {
                        $uniqueLabels[$anrData[$labelName]] = $anrData[$labelName];
                    }
                    $descriptionName = 'description' . $anrData['language'];
                    $languageCode = $languageCodes[$anrData['language']];
                    $builder = $this->getQueryBuilder();
                    $builder->update('anrs')
                        ->set('label', $uniqueLabels[$anrData[$labelName]])
                        ->set('description', $anrData[$descriptionName])
                        ->set('language_code', $languageCode)
                        ->where(['id' => (int)$anrData['id']])
                        ->execute();
                }
                $this->execute('UPDATE anrs SET created_at = NOW(), creator = "System"
                    WHERE created_at IS NULL OR creator IS NULL');
            }

            /* Replace in recommandations_sets label1,2,3,4 by a single label field. */
            if (!$this->table('recommandations_sets')->hasColumn('label')) {
                $this->table('recommandations_sets')
                    ->addColumn('label', 'string', ['null' => false, 'limit' => 255, 'default' => ''])
                    ->update();
                $recSetsQuery = $this->query('SELECT rs.uuid, rs.anr_id, a.language, rs.label1, rs.label2, rs.label3, rs.label4
                    FROM recommandations_sets rs INNER JOIN anrs a ON a.id = rs.anr_id'
                );
                foreach ($recSetsQuery->fetchAll() as $recSetData) {
                    $labelName = 'label' . $recSetData['language'];
                    /* Check if the label is already exist. */
                    $recSetExistenceQuery = $this->fetchRow('SELECT uuid FROM recommandations_sets WHERE anr_id = '
                        . (int)$recSetData['anr_id'] . ' AND label = "' . addslashes($recSetData[$labelName]) . '"');
                    if ($recSetExistenceQuery !== false) {
                        /* MOve all the recommendation to the existing set and remove it. */
                        $this->execute('UPDATE recommandations SET recommandation_set_uuid = "' . $recSetExistenceQuery['uuid']
                            . '" WHERE recommandation_set_uuid = "' . $recSetData['uuid'] . '" 
                                AND anr_id = ' . (int)$recSetData['anr_id']);
                        $this->execute('DELETE FROM recommandations_sets WHERE uuid = "' . $recSetData['uuid'] . '"
                            AND anr_id = ' . (int)$recSetData['anr_id']);
                    } else {
                        $builder = $this->getQueryBuilder();
                        $builder->update('recommandations_sets')
                            ->set('label', $recSetData[$labelName])
                            ->where([
                                'uuid' => $recSetData['uuid'],
                                'anr_id' => (int)$recSetData['anr_id'],
                            ])->execute();
                    }
                }
                /* Make anr_id and label unique. */
                try {
                    $this->table('recommandations_sets')->addIndex(['anr_id', 'label'], ['unique' => true])->update();
                } catch (\Throwable $e) {}
            }

            $recTable = $this->table('recommandations');
            if ($recTable->hasColumn('token_import') || $recTable->hasColumn('original_code')) {
                if ($recTable->hasColumn('token_import')) $recTable->removeColumn('token_import');
                if ($recTable->hasColumn('original_code')) $recTable->removeColumn('original_code');
                $recTable->update();
            }
        } catch (\Throwable $e) {}

        try {
            if ($this->table('deliveries')->hasColumn('resp_smile')) {
                $this->table('deliveries')->renameColumn('resp_smile', 'responsible_manager')->update();
            }
        } catch (\Throwable $e) {}

        try {
            if ($this->table('scales_impact_types')->hasColumn('position')) {
                $this->table('scales_impact_types')->removeColumn('position')->update();
            }
        } catch (\Throwable $e) {}

        try {
            $this->table('objects_categories')
                ->changeColumn('label1', 'string', ['default' => '', 'limit' => 2048])
                ->changeColumn('label2', 'string', ['default' => '', 'limit' => 2048])
                ->changeColumn('label3', 'string', ['default' => '', 'limit' => 2048])
                ->changeColumn('label4', 'string', ['default' => '', 'limit' => 2048])
                ->update();
        } catch (\Throwable $e) {}

        /* The unique relation is not correct as it should be possible to instantiate the same operational risk. */
        try {
            $this->table('operational_instance_risks_scales')
                ->removeIndex(['anr_id', 'instance_risk_op_id', 'operational_risk_scale_type_id'])
                ->addIndex(['anr_id', 'instance_risk_op_id', 'operational_risk_scale_type_id'], ['unique' => false])
                ->update();
        } catch (\Throwable $e) {}

        /* Note: Temporary change fields types to avoid setting values from the code. Later will be dropped. */
        try {
            $this->table('operational_risks_scales_types')
                ->changeColumn('label_translation_key', 'string', ['null' => false, 'default' => '', 'limit' => 255])
                ->update();
        } catch (\Throwable $e) {}

        try {
            $this->table('operational_risks_scales_comments')
                ->changeColumn('comment_translation_key', 'string', ['null' => false, 'default' => '', 'limit' => 255])
                ->update();
        } catch (\Throwable $e) {}

        /* Cleanup the table. */
        try {
            $rolfTable = $this->table('rolf_risks_tags');
            if ($rolfTable->hasColumn('creator')) $rolfTable->removeColumn('creator');
            if ($rolfTable->hasColumn('created_at')) $rolfTable->removeColumn('created_at');
            if ($rolfTable->hasColumn('updater')) $rolfTable->removeColumn('updater');
            if ($rolfTable->hasColumn('updated_at')) $rolfTable->removeColumn('updated_at');
            $rolfTable->update();
        } catch (\Throwable $e) {}

        if (!$this->hasTable('system_messages')) {
            $this->execute(
                'CREATE TABLE IF NOT EXISTS `system_messages` (
                    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) unsigned NOT NULL,
                    `title` varchar(255) NOT NULL,
                    `description` TEXT,
                    `status` smallint(3) unsigned NOT NULL DEFAULT 1,
                    `creator` varchar(255) NOT NULL,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updater` varchar(255) DEFAULT NULL,
                    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `system_messages_user_id_id_fk1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                );'
            );
            $usersQuery = $this->query('SELECT id FROM users WHERE status = 1;');
            foreach ($usersQuery->fetchAll() as $userData) {
                $this->table('system_messages')->insert([
                    'user_id' => $userData['id'],
                    'title' => 'Monarc version is updated to v2.13.1',
                    'description' => 'Please, read the release notes available https://www.monarc.lu/news. In case if you encounter any issues with your analysis, please let us know by sending us an email to info-monarc@nc3.lu.',
                    'status' => 1,
                    'creator' => 'System',
                ])->saveData();
            }
        }

        /* TODO: Should be added to the next release migration, to perform this release in a safe mode.
        $this->table('anr_instance_metadata_fields')->removeColumn('label_translation_key')->update();
        $this->table('instances_metadata')->removeColumn('comment_translation_key')->update();
        $this->table('operational_risks_scales_types')->removeColumn('label_translation_key')->update();
        $this->table('operational_risks_scales_comments')->removeColumn('comment_translation_key')->update();
        $this->table('soa_scale_comments')->removeColumn('comment_translation_key')->update();
        $this->table('anrs')
            ->removeColumn('label1')
            ->removeColumn('label2')
            ->removeColumn('label3')
            ->removeColumn('label4')
            ->removeColumn('description1')
            ->removeColumn('description2')
            ->removeColumn('description3')
            ->removeColumn('description4')
            ->update();
        $this->table('recommandations_sets')
            ->removeColumn('label1')
            ->removeColumn('label2')
            ->removeColumn('label3')
            ->removeColumn('label4')
            ->update();
        $this->table('translations')->drop();
        */
    }
}
