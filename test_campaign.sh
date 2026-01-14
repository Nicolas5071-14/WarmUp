#!/bin/bash

# Script de test pour créer une campagne et lancer le process

echo "=== TEST CAMPAGNE WARMUP ==="
echo "Date: $(date)"
echo ""

# 1. Exécutez le SQL pour créer une campagne
echo "1. Création de la campagne via SQL..."
mysql -h mautic-db -u root -p mautic << EOF
$(cat << 'SQLSCRIPT'
-- Insérez une campagne simple
INSERT INTO warmup_campaigns (
    campaignName,
    domain_id,
    warmup_type_id,
    startDate,
    status,
    totalContacts,
    emailsSent,
    createdAt,
    updatedAt,
    send_time,
    send_frequency,
    start_volume,
    duration_days,
    contact_source,
    subject_template,
    custom_message
) VALUES (
    'Test Auto ' || DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s'),
    1,
    1,
    DATE_ADD(NOW(), INTERVAL 5 MINUTE),
    'active',
    2,
    0,
    NOW(),
    NOW(),
    DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 5 MINUTE), '%H:%i:00'),
    'daily',
    2,
    3,
    'manual',
    'Test Auto',
    'Email de test auto'
);

SET @camp_id = LAST_INSERT_ID();

-- Ajoutez 2 contacts
INSERT INTO warmup_contacts (campaign_id, emailAddress, firstName, sequenceDay, isActive, unsubscribeToken, createdAt)
VALUES 
(@camp_id, 'auto1@test.com', 'Auto1', 1, 1, SHA2(RAND(), 256), NOW()),
(@camp_id, 'auto2@test.com', 'Auto2', 1, 1, SHA2(RAND(), 256), NOW());

SELECT 'Campagne créée avec ID:' as info, @camp_id;
SQLSCRIPT
)

echo ""
echo "2. Lancement de la commande de process..."
echo ""

# 2. Lancez la commande de process
php /var/www/html/bin/console mautic:warmup:process --force -vv

echo ""
echo "3. Vérification des résultats..."
echo ""

# 3. Vérifiez ce qui a été envoyé
mysql -u mautic -pmautic mautic << 'EOF'
SELECT 
    '=== CAMPAGNES ACTIVES ===' as info;
    
SELECT 
    c.id,
    c.campaignName,
    c.status,
    c.totalContacts,
    c.emailsSent,
    c.startDate,
    c.send_time,
    (SELECT COUNT(*) FROM warmup_contacts WHERE campaign_id = c.id AND isActive = 1) as active_contacts,
    (SELECT COUNT(*) FROM warmup_sent_logs WHERE campaign_id = c.id) as emails_sent_count
FROM warmup_campaigns c
WHERE c.status = 'active'
ORDER BY c.id DESC
LIMIT 5;

SELECT 
    '=== DERNIERS EMAILS ENVOYÉS ===' as info;
    
SELECT 
    sl.id,
    sl.campaign_id,
    c.campaignName,
    sl.sendTime,
    sl.status,
    sl.emailSubject,
    d.domainName
FROM warmup_sent_logs sl
LEFT JOIN warmup_campaigns c ON sl.campaign_id = c.id
LEFT JOIN warmup_domains d ON sl.domain_id = d.id
ORDER BY sl.sendTime DESC
LIMIT 10;
EOF

echo ""
echo "=== TEST TERMINÉ ==="
