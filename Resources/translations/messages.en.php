<?php

return [
    // Menu
    'mautic.warmup.menu' => 'Email WarmUp',
    'mautic.warmup.menu.domains' => 'Domains',
    'mautic.warmup.menu.campaigns' => 'Campaigns',
    'mautic.warmup.menu.templates' => 'Templates',
    'mautic.warmup.menu.contacts' => 'Contacts',
    'mautic.warmup.menu.reports' => 'Reports',


    'warmup.domains' => 'Domains',
    'warmup.campaigns' => 'Campaign warmuped',
    'warmup.campaigns.new' => 'Add campaign',
    'warmup.templates' => 'Templates',
    'warmup.contacts' => 'Contacts',
    'warmup.reports' => 'Reports',

    // Domain
    'mautic.warmup.domain' => 'Domain',
    'mautic.warmup.domains' => 'Domains',
    'mautic.warmup.domain.name' => 'Domain Name',
    'mautic.warmup.domain.email_prefix' => 'Email Prefix',
    'mautic.warmup.domain.email_prefix.help' => 'Prefix for email addresses (e.g., "noreply" for noreply@domain.com)',
    'mautic.warmup.domain.daily_limit' => 'Daily Limit',
    'mautic.warmup.domain.smtp_host' => 'SMTP Host',
    'mautic.warmup.domain.smtp_port' => 'SMTP Port',
    'mautic.warmup.domain.smtp_username' => 'SMTP Username',
    'mautic.warmup.domain.smtp_password' => 'SMTP Password',
    'mautic.warmup.domain.smtp_encryption' => 'SMTP Encryption',
    'mautic.warmup.domain.warmup_phase' => 'Warm-up Phase',
    'mautic.warmup.domain.is_active' => 'Is Active',
    'mautic.warmup.domain.is_verified' => 'Is Verified',
    'mautic.warmup.domain.warmup_start_date' => 'Warm-up Start Date',
    'mautic.warmup.domain.warmup_end_date' => 'Warm-up End Date',
    'mautic.warmup.domain.verification_date' => 'Verification Date',
    'mautic.warmup.domain.total_sent_today' => 'Sent Today',
    'mautic.warmup.domain.remaining_today' => 'Remaining Today',

    // Campaign
    'mautic.warmup.campaign' => 'Campaign',
    'mautic.warmup.campaigns' => 'Campaigns',
    'mautic.warmup.campaign.name' => 'Campaign Name',
    'mautic.warmup.campaign.description' => 'Description',
    'mautic.warmup.campaign.domain' => 'Domain',
    'mautic.warmup.campaign.warmup_type' => 'Warm-up Type',
    'mautic.warmup.campaign.start_date' => 'Start Date',
    'mautic.warmup.campaign.status' => 'Status',
    'mautic.warmup.campaign.total_contacts' => 'Total Contacts',
    'mautic.warmup.campaign.emails_sent' => 'Emails Sent',
    'mautic.warmup.campaign.sequences' => 'Email Sequences',



    'mautic.core.status' => 'Status',
    'warmup.campaign.contacts' => 'Contacts',
    'warmup.campaign.emails_sent' => 'Emails Sent',
    'warmup.campaign.progress' => 'Progress',
    'warmup.campaign.rates' => 'Rates',
    'mautic.core.new' => 'Add new',
    'warmup.campaign.status.draft' => 'Draft',
    'warmup.campaign.status.active' => 'Active',
    'warmup.campaign.status.completed' => 'Completed',
    'warmup.campaign.status.paused' => 'Paused',
    'mautic.core.filter.all' => 'All',


    // Campaign Statuses
    'mautic.warmup.campaign.status.draft' => 'Draft',
    'mautic.warmup.campaign.status.active' => 'Active',
    'mautic.warmup.campaign.status.paused' => 'Paused',
    'mautic.warmup.campaign.status.completed' => 'Completed',
    'mautic.warmup.campaign.status.failed' => 'Failed',

    // Sequence
    'mautic.warmup.sequence' => 'Sequence',
    'mautic.warmup.sequence.name' => 'Sequence Name',
    'mautic.warmup.sequence.order' => 'Order',
    'mautic.warmup.sequence.days_after' => 'Days After Previous',
    'mautic.warmup.sequence.subject' => 'Subject',
    'mautic.warmup.sequence.body' => 'Body Content',

    // Template
    'mautic.warmup.template' => 'Template',
    'mautic.warmup.templates' => 'Templates',
    'mautic.warmup.template.name' => 'Template Name',
    'mautic.warmup.template.type' => 'Template Type',
    'mautic.warmup.template.subject' => 'Email Subject',
    'mautic.warmup.template.html_content' => 'HTML Content',
    'mautic.warmup.template.text_content' => 'Text Content',
    'mautic.warmup.template.text_content.help' => 'Plain text version (optional, will be generated from HTML if not provided)',
    'mautic.warmup.template.is_active' => 'Is Active',

    // Contact
    'mautic.warmup.contact' => 'Contact',
    'mautic.warmup.contacts' => 'Contacts',
    'mautic.warmup.contact.email' => 'Email Address',
    'mautic.warmup.contact.first_name' => 'First Name',
    'mautic.warmup.contact.last_name' => 'Last Name',
    'mautic.warmup.contact.campaign' => 'Campaign',
    'mautic.warmup.contact.sequence_day' => 'Sequence Day',
    'mautic.warmup.contact.days_between' => 'Days Between Emails',
    'mautic.warmup.contact.last_sent' => 'Last Sent',
    'mautic.warmup.contact.next_send' => 'Next Send',
    'mautic.warmup.contact.sent_count' => 'Sent Count',
    'mautic.warmup.contact.is_active' => 'Is Active',

    // Actions
    'mautic.warmup.action.new' => 'New',
    'mautic.warmup.action.edit' => 'Edit',
    'mautic.warmup.action.delete' => 'Delete',
    'mautic.warmup.action.start' => 'Start',
    'mautic.warmup.action.pause' => 'Pause',
    'mautic.warmup.action.resume' => 'Resume',
    'mautic.warmup.action.verify' => 'Verify',
    'mautic.warmup.action.import' => 'Import',
    'mautic.warmup.action.export' => 'Export',
    'mautic.warmup.action.preview' => 'Preview',
    'mautic.warmup.action.duplicate' => 'Duplicate',

    // Status Messages
    'mautic.warmup.status.success' => 'Success',
    'mautic.warmup.status.error' => 'Error',
    'mautic.warmup.status.warning' => 'Warning',
    'mautic.warmup.status.info' => 'Info',

    // Validation Messages
    'mautic.warmup.validation.required' => 'This field is required',
    'mautic.warmup.validation.email' => 'Please enter a valid email address',
    'mautic.warmup.validation.domain' => 'Please enter a valid domain name',
    'mautic.warmup.validation.number' => 'Please enter a valid number',

    // Tooltips
    'mautic.warmup.tooltip.daily_limit' => 'Maximum number of emails that can be sent from this domain per day',
    'mautic.warmup.tooltip.warmup_phase' => 'Current phase of the warm-up process (1-30 days)',
    'mautic.warmup.tooltip.sequences' => 'Sequence of emails to be sent to each contact',

    // Statistics
    'mautic.warmup.stats.total_domains' => 'Total Domains',
    'mautic.warmup.stats.active_domains' => 'Active Domains',
    'mautic.warmup.stats.verified_domains' => 'Verified Domains',
    'mautic.warmup.stats.total_campaigns' => 'Total Campaigns',
    'mautic.warmup.stats.active_campaigns' => 'Active Campaigns',
    'mautic.warmup.stats.total_contacts' => 'Total Contacts',
    'mautic.warmup.stats.emails_sent' => 'Emails Sent',
    'mautic.warmup.stats.delivery_rate' => 'Delivery Rate',
    'mautic.warmup.stats.open_rate' => 'Open Rate',
    'mautic.warmup.stats.click_rate' => 'Click Rate',
    'mautic.warmup.stats.bounce_rate' => 'Bounce Rate',

    // Reports
    'mautic.warmup.report.performance' => 'Performance Report',
    'mautic.warmup.report.deliverability' => 'Deliverability Report',
    'mautic.warmup.report.engagement' => 'Engagement Report',
    'mautic.warmup.report.daily_summary' => 'Daily Summary',
    'mautic.warmup.report.weekly_summary' => 'Weekly Summary',
    'mautic.warmup.report.monthly_summary' => 'Monthly Summary',

    // Buttons
    'mautic.warmup.button.save' => 'Save',
    'mautic.warmup.button.cancel' => 'Cancel',
    'mautic.warmup.button.close' => 'Close',
    'mautic.warmup.button.back' => 'Back',
    'mautic.warmup.button.next' => 'Next',
    'mautic.warmup.button.send_test' => 'Send Test',
    'mautic.warmup.button.process_now' => 'Process Now',

    // Messages
    'mautic.warmup.message.saved' => '%name% has been saved',
    'mautic.warmup.message.deleted' => '%name% has been deleted',
    'mautic.warmup.message.started' => 'Campaign has been started',
    'mautic.warmup.message.paused' => 'Campaign has been paused',
    'mautic.warmup.message.verified' => 'Domain has been verified',
    'mautic.warmup.message.imported' => '%count% contacts have been imported',
    'mautic.warmup.message.exported' => 'Data has been exported',

    // Errors
    'mautic.warmup.error.not_found' => 'Item not found',
    'mautic.warmup.error.invalid_data' => 'Invalid data provided',
    'mautic.warmup.error.smtp_failed' => 'SMTP verification failed',
    'mautic.warmup.error.domain_inactive' => 'Domain is not active',
    'mautic.warmup.error.domain_limit_reached' => 'Daily sending limit reached for this domain',
    'mautic.warmup.error.campaign_no_contacts' => 'Campaign has no contacts',
    'mautic.warmup.error.campaign_no_sequences' => 'Campaign has no email sequences',

    // Success
    'mautic.warmup.success.saved' => 'Changes saved successfully',
    'mautic.warmup.success.deleted' => 'Item deleted successfully',
    'mautic.warmup.success.verified' => 'Domain verified successfully',
    'mautic.warmup.success.imported' => 'Contacts imported successfully',
    'mautic.warmup.success.exported' => 'Data exported successfully',

    // Wizard translations
    'warmup.wizard.title' => 'Create Campaign',
    'warmup.wizard.steps' => 'Campaign Wizard',
    'warmup.wizard.progress' => 'Progress',
    'warmup.wizard.confirm_cancel' => 'Are you sure you want to cancel campaign creation? All progress will be lost.',
    'warmup.wizard.available_domains' => 'Available Domains',
    'warmup.wizard.subject_variables' => 'Subject Variables',
    'warmup.wizard.body_variables' => 'Body Variables',
    'warmup.wizard.add_variable' => 'Add Variable',
    'warmup.wizard.preview' => 'Email Preview',
    'warmup.wizard.review_title' => 'Review Campaign Settings',
    'warmup.wizard.campaign_summary' => 'Campaign Summary',
    'warmup.wizard.contacts_summary' => 'Contacts Summary',
    'warmup.wizard.domain_summary' => 'Domain Summary',
    'warmup.wizard.settings_summary' => 'Settings Summary',
    'warmup.wizard.sequences_summary' => 'Sequences Summary',
    'warmup.wizard.source' => 'Source',
    'warmup.wizard.total_contacts' => 'Total Contacts',
    'warmup.wizard.sample_contacts' => 'Sample Contacts',
    'warmup.wizard.total_sequences' => 'Total Sequences',
    'warmup.wizard.total_emails' => 'Total Emails',
    'warmup.wizard.estimated_duration' => 'Estimated Completion',
    'warmup.wizard.sequence_details' => 'Sequence Details',
    'warmup.wizard.timeline_preview' => 'Timeline Preview',
    'warmup.wizard.day' => 'Day',
    'warmup.wizard.date' => 'Date',
    'warmup.wizard.send_time' => 'Send Time',
    'warmup.wizard.pause_weekends' => 'Pause on Weekends',
    'warmup.wizard.validate_sequences' => 'Please fill all required fields for each sequence.',
    'warmup.wizard.create_campaign' => 'Create Campaign',

    // Wizard
    'warmup.wizard.contact_source' => 'Contact Source',
    'warmup.wizard.manual_contacts' => 'Manual Contacts',
    'warmup.wizard.domain_selection' => 'Domain Selection',
    'warmup.wizard.enable_throttling' => 'Enable Throttling',
    'warmup.wizard.throttling_start' => 'Start Rate (emails/day)',
    'warmup.wizard.throttling_end' => 'End Rate (emails/day)',

    // Campaign
    'warmup.campaign.stats' => 'Campaign Statistics',
    'warmup.campaign.details' => 'Campaign Details',
    'warmup.campaign.progress_report' => 'Progress Report',
    'warmup.campaign.import_contacts' => 'Import Contacts',
    'warmup.campaign.no_contacts' => 'No contacts found',
    'warmup.campaign.overview' => 'Overview',
    'warmup.campaign.performance_metrics' => 'Performance Metrics',
    'warmup.campaign.quick_stats' => 'Quick Stats',
    'warmup.campaign.recent_activity' => 'Recent Activity',
    'warmup.campaign.chart_placeholder' => 'Timeline chart will appear here',

    // Contact
    'warmup.contact.confirm_activate' => 'Are you sure you want to activate this contact?',
    'warmup.contact.confirm_deactivate' => 'Are you sure you want to deactivate this contact?',
    'warmup.contact.confirm_delete' => 'Are you sure you want to delete this contact?',

    // Template
    'warmup.template.name' => 'Template Name',
    'warmup.template.content' => 'Template Content',

    // Variable
    'warmup.variable.key' => 'Variable Name',
    'warmup.variable.value' => 'Variable Value',

    // Campaign wizard translations
    'warmup.campaign.name' => 'Campaign Name',
    'warmup.campaign.description' => 'Description',
    'warmup.campaign.warmup_type' => 'Warm-up Type',
    'warmup.campaign.start_date' => 'Start Date',
    'warmup.campaign.daily_limit' => 'Daily Limit',
    'warmup.campaign.warmup_duration' => 'Warm-up Duration (days)',



    // Sequences
    'warmup.sequence.singular' => 'Sequence',
    'warmup.sequence.name' => 'Sequence Name',
    'warmup.sequence.subject' => 'Subject',
    'warmup.sequence.days_after' => 'Days After Previous',
    'warmup.sequence.description' => 'Description',
    'warmup.sequence.body' => 'Body',
    'warmup.sequence.add' => 'Add Sequence',

    // Content



    // Navigation
    'warmup.wizard.back' => 'Back',
    'warmup.wizard.next' => 'Next',
    'warmup.wizard.cancel' => 'Cancel',

    // Warmup type options (dans les forms)
    'Arithmetic (Linear)' => 'Arithmetic (Linear)',
    'Geometric (Exponential)' => 'Geometric (Exponential)',
    'Flat (Constant)' => 'Flat (Constant)',
    'Progressive (Based on success)' => 'Progressive (Based on success)',
    'Randomize (Varied)' => 'Randomize (Varied)',

    // Contact source options
    'Select from Segment' => 'Select from Segment',
    'Upload CSV File' => 'Upload CSV File',
    'Manual Entry' => 'Manual Entry',

    // Domain selection options
    'Use Existing Domain' => 'Use Existing Domain',
    'Add New Domain' => 'Add New Domain',

    // Domain translations
    'warmup.domain.name' => 'Domain Name',
    'warmup.domain.status' => 'Status',
    'warmup.domain.daily_limit' => 'Daily Limit',
    'warmup.domain.remaining' => 'Remaining',
    'warmup.domain.verified' => 'Verified',
    'warmup.domain.unverified' => 'Unverified',

];
