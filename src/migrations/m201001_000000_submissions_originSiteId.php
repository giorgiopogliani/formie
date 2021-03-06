<?php
namespace verbb\formie\migrations;

use craft\db\Migration;

class m201001_000000_submissions_originSiteId extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%formie_submissions}}', 'originSiteId')) {
            $this->addColumn('{{%formie_submissions}}', 'originSiteId', $this->string()->after('formId'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201001_000000_submissions_originSiteId cannot be reverted.\n";
        return false;
    }
}