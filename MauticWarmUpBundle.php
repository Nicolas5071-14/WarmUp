<?php
declare(strict_types=1);

namespace MauticPlugin\MauticWarmUpBundle;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\PluginBundle\Bundle\PluginBundleBase;

class MauticWarmUpBundle extends PluginBundleBase
{
    /**
     * Called when the plugin is installed
     */
    public function install(Schema $schema, MauticFactory $factory): void
    {
        $this->createTables($schema);
        $this->insertDefaultData($factory);
    }

    /**
     * Create all necessary tables
     */
    private function createTables(Schema $schema): void
    {
        if (!$schema->hasTable('warmup_domains')) {
            $table = $schema->createTable('warmup_domains');

            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('domain_name', 'string', ['length' => 255]);
            $table->addColumn('email_prefix', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('daily_limit', 'integer', ['default' => 100]);
            $table->addColumn('warmup_phase', 'integer', ['default' => 1]);
            $table->addColumn('current_phase_day', 'integer', ['default' => 1]);
            $table->addColumn('total_sent_today', 'integer', ['default' => 0]);
            $table->addColumn('smtp_host', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('smtp_port', 'integer', ['default' => 587]);
            $table->addColumn('smtp_username', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('smtp_password', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('smtp_encryption', 'string', ['length' => 10, 'default' => 'tls']);
            $table->addColumn('imap_host', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('imap_port', 'integer', ['default' => 993]);
            $table->addColumn('imap_username', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('imap_password', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('imap_encryption', 'string', ['length' => 10, 'default' => 'ssl']);
            $table->addColumn('is_active', 'boolean', ['default' => false]);
            $table->addColumn('is_verified', 'boolean', ['default' => false]);
            $table->addColumn('warmup_start_date', 'datetime', ['notnull' => false]);
            $table->addColumn('warmup_end_date', 'datetime', ['notnull' => false]);
            $table->addColumn('verification_date', 'datetime', ['notnull' => false]);
            $table->addColumn('createdAt', 'datetime');
            $table->addColumn('updatedAt', 'datetime');

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['domain_name']);
        }

        if (!$schema->hasTable('warmup_campaigns')) {
            $table = $schema->createTable('warmup_campaigns');

            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('campaign_name', 'string', ['length' => 255]);
            $table->addColumn('description', 'text', ['notnull' => false]);
            $table->addColumn('warmup_type_id', 'integer', ['default' => 1]);
            $table->addColumn('domain_id', 'integer', ['notnull' => false]);
            $table->addColumn('start_date', 'datetime', ['notnull' => false]);
            $table->addColumn('status', 'string', ['length' => 20, 'default' => 'draft']);
            $table->addColumn('total_contacts', 'integer', ['default' => 0]);
            $table->addColumn('emails_sent', 'integer', ['default' => 0]);
            $table->addColumn('createdAt', 'datetime');
            $table->addColumn('updatedAt', 'datetime');

            $table->addColumn('emails_delivered', 'integer', ['default' => 0]);
            $table->addColumn('emails_opened', 'integer', ['default' => 0]);
            $table->addColumn('emails_clicked', 'integer', ['default' => 0]);
            $table->addColumn('emails_bounced', 'integer', ['default' => 0]);

            $table->addColumn('delivery_rate', 'float', ['default' => 0]);
            $table->addColumn('open_rate', 'float', ['default' => 0]);
            $table->addColumn('click_rate', 'float', ['default' => 0]);
            $table->addColumn('bounce_rate', 'float', ['default' => 0]);

            $table->addColumn('daily_limit', 'integer', ['notnull' => false]);
            $table->addColumn('warmup_duration', 'integer', ['notnull' => false]);
            $table->addColumn('completed_at', 'datetime', ['notnull' => false]);

            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint('warmup_domains', ['domain_id'], ['id']);
            $table->addForeignKeyConstraint('warmup_types', ['warmup_type_id'], ['id']);
        }

        if (!$schema->hasTable('warmup_templates')) {
            $table = $schema->createTable('warmup_templates');

            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('template_name', 'string', ['length' => 255]);
            $table->addColumn('template_type', 'string', ['length' => 50, 'default' => 'email']);
            $table->addColumn('subject', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('content', 'text', ['notnull' => false]);
            $table->addColumn('html_content', 'text', ['notnull' => false]);
            $table->addColumn('is_active', 'boolean', ['default' => true]);
            $table->addColumn('createdAt', 'datetime');
            $table->addColumn('updatedAt', 'datetime');

            $table->setPrimaryKey(['id']);
        }

        if (!$schema->hasTable('warmup_sent_logs')) {
            $table = $schema->createTable('warmup_sent_logs');

            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('domain_id', 'integer', ['notnull' => false]);
            $table->addColumn('campaign_id', 'integer', ['notnull' => false]);
            $table->addColumn('contact_id', 'integer', ['notnull' => false]);
            $table->addColumn('sequence_day', 'integer');
            $table->addColumn('email_subject', 'string', ['length' => 255]);
            $table->addColumn('email_content', 'text');
            $table->addColumn('send_time', 'datetime');
            $table->addColumn('status', 'string', ['length' => 50]);
            $table->addColumn('message_id', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('error_message', 'text', ['notnull' => false]);
            $table->addColumn('createdAt', 'datetime');

            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint('warmup_domains', ['domain_id'], ['id']);
            $table->addForeignKeyConstraint('warmup_campaigns', ['campaign_id'], ['id']);
            $table->addIndex(['campaign_id'], 'idx_campaign');
            $table->addIndex(['domain_id'], 'idx_domain');
            $table->addIndex(['send_time'], 'idx_send_time');
        }

        if (!$schema->hasTable('warmup_sequences')) {
            $table = $schema->createTable('warmup_sequences');

            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('campaign_id', 'integer');
            $table->addColumn('sequence_name', 'string', ['length' => 255]);
            $table->addColumn('sequence_order', 'integer', ['default' => 1]);
            $table->addColumn('days_after_previous', 'integer', ['default' => 1]);
            $table->addColumn('subject_template', 'text');
            $table->addColumn('body_template', 'text');
            $table->addColumn('is_active', 'boolean', ['default' => true]);
            $table->addColumn('createdAt', 'datetime');

            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint('warmup_campaigns', ['campaign_id'], ['id']);
        }

        if (!$schema->hasTable('warmup_contacts')) {
            $table = $schema->createTable('warmup_contacts');

            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('campaign_id', 'integer');
            $table->addColumn('email_address', 'string', ['length' => 255]);
            $table->addColumn('first_name', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('last_name', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('sequence_day', 'integer', ['default' => 1]);
            $table->addColumn('days_between_emails', 'integer', ['default' => 2]);
            $table->addColumn('last_sent', 'datetime', ['notnull' => false]);
            $table->addColumn('next_send_date', 'datetime', ['notnull' => false]);
            $table->addColumn('sent_count', 'integer', ['default' => 0]);
            $table->addColumn('is_active', 'boolean', ['default' => true]);
            $table->addColumn('unsubscribe_token', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('createdAt', 'datetime');

            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint('warmup_campaigns', ['campaign_id'], ['id']);
            $table->addIndex(['campaign_id'], 'idx_campaign');
            $table->addIndex(['email_address'], 'idx_email');
            $table->addIndex(['next_send_date'], 'idx_next_send');
        }

        if (!$schema->hasTable('warmup_types')) {
            $table = $schema->createTable('warmup_types');

            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('type_name', 'string', ['length' => 100]);
            $table->addColumn('description', 'text', ['notnull' => false]);
            $table->addColumn('createdAt', 'datetime');

            $table->setPrimaryKey(['id']);
        }
    }

    /**
     * Insert default data
     */
    private function insertDefaultData(MauticFactory $factory): void
    {
        $connection = $factory->getDatabase();

        $types = [
            ['Arithmetic', 'Linear increase each day'],
            ['Geometric', 'Exponential growth pattern'],
            ['Flat', 'Constant volume then ramp up'],
            ['Progressive', 'Volume based on success rate'],
            ['Randomize', 'Random variation within limits']
        ];

        foreach ($types as $type) {
            $connection->insert('warmup_types', [
                'type_name' => $type[0],
                'description' => $type[1],
                'createdAt' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        }
    }
}
