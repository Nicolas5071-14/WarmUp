// Campaign Form JavaScript - Complete corrected version with Email Sequences
(function () {
    console.log('Campaign form with email sequences initialized');

    // Configuration
    const config = {
        currentStep: 1,
        totalSteps: 5,
        contactSource: 'manual',
        contactsCount: 0,
        segmentCache: null,
        dailyVolumes: [],
        totalEmails: 0,
        csvContacts: null,
        currentSegmentId: null,
        emailSequences: [],
        sequenceType: 'single'
    };

    // ==========================================
    // UTILITY FUNCTIONS
    // ==========================================

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func(...args), wait);
        };
    }

    function showNotification(message, type = 'info') {
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';

        const notification = document.createElement('div');
        notification.className = `alert ${alertClass} alert-dismissible`;
        notification.style.cssText = 'position: fixed; top: 70px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);';
        notification.innerHTML = `
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <strong>${type === 'success' ? 'Success!' : type === 'error' ? 'Error!' : 'Info:'}</strong> ${message}
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    // ==========================================
    // EMAIL SEQUENCES MANAGEMENT
    // ==========================================

    // 1. REMPLACER la fonction setupEmailSequences() complète
    function setupEmailSequences() {
        const sequenceTypeRadios = document.querySelectorAll('input[name="sequence_type_ui"]');
        const singleSection = document.getElementById('single-email-section');
        const multiSection = document.getElementById('multiple-emails-section');
        const addBtn = document.getElementById('add-email-sequence-btn');

        if (sequenceTypeRadios.length > 0) {
            sequenceTypeRadios.forEach(radio => {
                radio.addEventListener('change', function () {
                    config.sequenceType = this.value;

                    if (singleSection) {
                        singleSection.style.display = this.value === 'single' ? 'block' : 'none';
                    }

                    if (multiSection) {
                        multiSection.style.display = this.value === 'multiple' ? 'block' : 'none';
                    }

                    // Mise à jour du champ caché Symfony
                    const typeField = document.querySelector('[name="campaign_form[sequenceType]"]');
                    if (typeField) {
                        typeField.value = this.value;
                    }

                    // Gérer les attributs required
                    const subjectField = document.querySelector('[name="campaign_form[subjectTemplate]"]');
                    const messageField = document.querySelector('[name="campaign_form[customMessage]"]');

                    if (this.value === 'multiple') {
                        if (subjectField) subjectField.removeAttribute('required');
                        if (messageField) messageField.removeAttribute('required');
                    } else {
                        if (subjectField) subjectField.setAttribute('required', 'required');
                        if (messageField) messageField.setAttribute('required', 'required');
                    }
                });
            });
        }

        if (addBtn) {
            addBtn.addEventListener('click', addEmailToSequence);
        }

        loadExistingSequences();
    }

    function addEmailToSequence() {
        const idx = config.emailSequences.length + 1;
        const container = document.getElementById('email-sequences-container');

        const card = document.createElement('div');
        card.className = 'email-sequence-card panel panel-default';
        card.setAttribute('data-index', idx);
        card.innerHTML = `
            <div class="panel-heading">
                <h5 class="panel-title">
                    Email ${idx} (Day ${idx})
                    <button type="button" class="btn btn-xs btn-danger pull-right remove-seq" data-idx="${idx}">
                        <i class="ri-delete-bin-line"></i> Remove
                    </button>
                </h5>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" class="form-control seq-subject" 
                           name="email_sequences[${idx}][subject]" 
                           placeholder="Email ${idx} subject" required>
                </div>
                <div class="form-group">
                    <label>Body</label>
                    <textarea class="form-control seq-body" rows="6"
                              name="email_sequences[${idx}][body]" 
                              placeholder="Email ${idx} content" required></textarea>
                    <p class="help-block">
                        Available variables: 
                        <code>{{first_name}}</code>
                        <code>{{last_name}}</code>
                        <code>{{email}}</code>
                        <code>{{campaign_name}}</code>
                        <code>{{unsubscribe_link}}</code>
                    </p>
                </div>
            </div>
        `;

        if (container) {
            container.appendChild(card);

            // Add remove button event
            const removeBtn = card.querySelector('.remove-seq');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    const idxToRemove = parseInt(this.getAttribute('data-idx'));
                    removeSequence(idxToRemove);
                });
            }

            // Add input event listeners
            const subjectInput = card.querySelector('.seq-subject');
            const bodyInput = card.querySelector('.seq-body');

            if (subjectInput) {
                subjectInput.addEventListener('input', updateSequencesField);
            }

            if (bodyInput) {
                bodyInput.addEventListener('input', updateSequencesField);
            }

            // Add to sequences array
            config.emailSequences.push({
                index: idx,
                subject: '',
                body: ''
            });

            updateSequencesField();
            showNotification(`Email ${idx} added to sequence`, 'success');
        }
    }

    function removeSequence(idx) {
        const card = document.querySelector(`.email-sequence-card[data-index="${idx}"]`);
        if (card) {
            card.remove();
        }

        // Remove from array
        config.emailSequences = config.emailSequences.filter(seq => seq.index !== idx);

        // Reindex remaining sequences
        reindexSequences();
        updateSequencesField();

        showNotification(`Email sequence ${idx} removed`, 'info');
    }

    function reindexSequences() {
        const cards = document.querySelectorAll('.email-sequence-card');

        cards.forEach((card, i) => {
            const newIdx = i + 1;
            card.setAttribute('data-index', newIdx);

            // Update title
            const title = card.querySelector('.panel-title');
            if (title) {
                const removeBtn = title.querySelector('.remove-seq');
                title.innerHTML = `
                    Email ${newIdx} (Day ${newIdx})
                    <button type="button" class="btn btn-xs btn-danger pull-right remove-seq" data-idx="${newIdx}">
                        <i class="ri-delete-bin-line"></i> Remove
                    </button>
                `;

                // Reattach event listener
                const newRemoveBtn = title.querySelector('.remove-seq');
                if (newRemoveBtn) {
                    newRemoveBtn.addEventListener('click', function () {
                        const idxToRemove = parseInt(this.getAttribute('data-idx'));
                        removeSequence(idxToRemove);
                    });
                }
            }

            // Update input names
            card.querySelectorAll('input, textarea').forEach(input => {
                const oldName = input.name;
                if (oldName) {
                    input.name = oldName.replace(/\[\d+\]/, `[${newIdx}]`);
                }
            });
        });

        // Update array indices
        config.emailSequences = Array.from(cards).map((card, i) => {
            const subjectInput = card.querySelector('.seq-subject');
            const bodyInput = card.querySelector('.seq-body');

            return {
                index: i + 1,
                subject: subjectInput ? subjectInput.value : '',
                body: bodyInput ? bodyInput.value : ''
            };
        });
    }

    function updateSequencesField() {
        const cards = document.querySelectorAll('.email-sequence-card');
        const data = [];

        cards.forEach(card => {
            const subjectInput = card.querySelector('.seq-subject');
            const bodyInput = card.querySelector('.seq-body');
            const idx = parseInt(card.getAttribute('data-index'));

            if (subjectInput && bodyInput) {
                data.push({
                    index: idx,
                    day: idx,
                    subject: subjectInput.value || '',
                    body: bodyInput.value || ''
                });
            }
        });

        // Mettre à jour le champ textarea Symfony
        const field = document.querySelector('[name="campaign_form[emailSequences]"]');
        if (field) {
            field.value = JSON.stringify(data);
            console.log('Updated emailSequences field:', field.value);
        }

        // Mettre à jour config
        config.emailSequences = data;
    }


    function loadExistingSequences() {
        const existingField = document.getElementById('email_sequences_data');
        if (!existingField) return;

        const existing = existingField.value;
        if (existing && existing !== '[]' && existing !== '') {
            try {
                const sequences = JSON.parse(existing);

                // CORRECTION: "[]" devient un tableau vide après JSON.parse
                // Donc on vérifie simplement si c'est un tableau
                if (Array.isArray(sequences) && sequences.length > 0) {
                    sequences.forEach(seq => {
                        // Add new sequence card
                        addEmailToSequence();

                        // Fill with existing data
                        const cards = document.querySelectorAll('.email-sequence-card');
                        const lastCard = cards[cards.length - 1];

                        if (lastCard) {
                            const subjectInput = lastCard.querySelector('.seq-subject');
                            const bodyInput = lastCard.querySelector('.seq-body');

                            if (subjectInput) subjectInput.value = seq.subject || '';
                            if (bodyInput) bodyInput.value = seq.body || '';
                        }
                    });

                    // Set sequence type to multiple if we have sequences
                    if (sequences.length > 0) {
                        const multipleRadio = document.querySelector('input[name="sequence_type"][value="multiple"]');
                        const singleSection = document.getElementById('single-email-section');
                        const multiSection = document.getElementById('multiple-emails-section');

                        if (multipleRadio) {
                            multipleRadio.checked = true;
                            config.sequenceType = 'multiple';

                            const typeField = document.getElementById('sequence_type_field');
                            if (typeField) {
                                typeField.value = 'multiple';
                            }

                            if (singleSection) singleSection.style.display = 'none';
                            if (multiSection) multiSection.style.display = 'block';

                            // Remove required attribute from single email fields
                            const subjectField = document.getElementById('campaign_form_subjectTemplate');
                            const messageField = document.getElementById('campaign_form_customMessage');
                            if (subjectField) subjectField.removeAttribute('required');
                            if (messageField) messageField.removeAttribute('required');
                        }
                    }
                }
                // Si sequences est un tableau vide ([]), on ne fait rien
            } catch (e) {
                console.error('Error parsing sequences JSON:', e);
                showNotification('Error loading email sequences', 'error');

                // Reset to empty array
                config.emailSequences = [];
                if (existingField) {
                    existingField.value = '[]';
                }
            }
        }
    }

    // ==========================================
    // CAMPAIGN PLAN CALCULATIONS
    // ==========================================

    function calculateWarmupVolumes(startVolume, dailyIncrement, durationDays, formulaType, totalContacts = null, alpha = 0.1) {
        const dailyVolumes = [];
        let previous = startVolume;

        for (let day = 1; day <= durationDays; day++) {
            let dailyVolume;

            switch (formulaType) {
                case 'arithmetic':
                    dailyVolume = startVolume + ((day - 1) * dailyIncrement);
                    break;
                case 'geometric':
                    const r = 1 + (dailyIncrement / 100);
                    dailyVolume = startVolume * Math.pow(r, day - 1);
                    break;
                case 'progressive':
                    const target = totalContacts ? Math.ceil(totalContacts / durationDays) : startVolume + dailyIncrement * durationDays;
                    dailyVolume = previous + alpha * (target - previous);
                    previous = dailyVolume;
                    break;
                case 'flat':
                    dailyVolume = startVolume;
                    break;
                case 'randomize':
                    const base = startVolume + ((day - 1) * dailyIncrement);
                    const min = base * 0.85;
                    const max = base * 1.15;
                    dailyVolume = min + Math.random() * (max - min);
                    break;
                default:
                    dailyVolume = startVolume + ((day - 1) * dailyIncrement);
            }

            dailyVolume = Math.max(1, Math.round(dailyVolume));
            dailyVolumes.push(dailyVolume);
        }

        if (totalContacts && totalContacts > 0) {
            const totalCalculated = dailyVolumes.reduce((a, b) => a + b, 0);
            if (totalCalculated > totalContacts) {
                const factor = totalContacts / totalCalculated;
                for (let i = 0; i < dailyVolumes.length; i++) {
                    dailyVolumes[i] = Math.max(1, Math.round(dailyVolumes[i] * factor));
                }
            }
        }

        return dailyVolumes;
    }

    function calculateCampaignPlan() {
        const totalContacts = parseInt(config.contactsCount) || 0;
        const ALPHA_OPTIMAL = 0.1;

        const durationDaysInput = document.querySelector('[name="campaign_form[durationDays]"]');
        const durationDays = durationDaysInput && durationDaysInput.value !== '' ?
            parseInt(durationDaysInput.value) : null;

        const startVolumeInput = document.querySelector('[name="campaign_form[startVolume]"]');
        const startVolume = startVolumeInput && startVolumeInput.value !== '' ?
            parseInt(startVolumeInput.value) : null;

        const dailyIncrementInput = document.querySelector('[name="campaign_form[dailyIncrement]"]');
        const dailyIncrement = dailyIncrementInput && dailyIncrementInput.value !== '' ?
            parseInt(dailyIncrementInput.value) : null;

        const warmupTypeSelect = document.querySelector('[name="campaign_form[warmupType]"]');
        const selectedOption = warmupTypeSelect?.options[warmupTypeSelect.selectedIndex];
        const formulaType = selectedOption?.getAttribute('data-formula-type') || 'arithmetic';

        console.log('Calculating campaign plan with:', {
            totalContacts,
            durationDays,
            startVolume,
            dailyIncrement,
            formulaType
        });

        if (!startVolume || !durationDays || !dailyIncrement) {
            console.log('Missing required parameters for calculation');
            hideCampaignPlanDisplay();
            updateWarmupDisplay(0, durationDays || 0);
            return null;
        }

        if (totalContacts > 0 && durationDays > 0) {
            const idealDailyVolume = Math.ceil(totalContacts / durationDays);
            const dailyVolumes = calculateWarmupVolumes(
                startVolume,
                dailyIncrement,
                durationDays,
                formulaType,
                totalContacts,
                ALPHA_OPTIMAL
            );

            let warmupTotalEmails = 0;
            const adjustedVolumes = [];

            for (let i = 0; i < dailyVolumes.length; i++) {
                let volume = dailyVolumes[i];
                if (idealDailyVolume > 0 && volume > idealDailyVolume) {
                    volume = idealDailyVolume;
                }
                volume = Math.max(1, Math.round(volume));
                adjustedVolumes.push(volume);
                warmupTotalEmails += volume;
            }

            if (warmupTotalEmails > totalContacts) {
                const adjustmentFactor = totalContacts / warmupTotalEmails;
                for (let i = 0; i < adjustedVolumes.length; i++) {
                    adjustedVolumes[i] = Math.max(1, Math.round(adjustedVolumes[i] * adjustmentFactor));
                }
                warmupTotalEmails = totalContacts;
            }

            config.dailyVolumes = adjustedVolumes;
            config.totalEmails = warmupTotalEmails;

            updateCampaignPlanDisplay(totalContacts, durationDays, idealDailyVolume, formulaType, adjustedVolumes);
            updateWarmupDisplay(warmupTotalEmails, durationDays);

            return {
                totalContacts,
                durationDays,
                idealDailyVolume,
                startVolume,
                dailyIncrement,
                formulaType,
                dailyVolumes: adjustedVolumes,
                warmupTotalEmails
            };
        } else {
            hideCampaignPlanDisplay();
            updateWarmupDisplay(0, durationDays || 0);
            return null;
        }
    }

    function getFormulaDescription(formulaType) {
        const descriptions = {
            'arithmetic': 'Linear increase: Volume increases by constant amount each day',
            'geometric': 'Exponential growth: Volume multiplies by constant factor each day',
            'progressive': 'Adaptive increase: Gradual acceleration based on performance',
            'flat': 'Constant volume: Same number of emails sent daily',
            'randomize': 'Random variation: Volume varies randomly to mimic natural patterns'
        };
        return descriptions[formulaType] || 'Custom progression formula';
    }

    function updateFormulaDisplay(formulaType) {
        const formulaDescription = getFormulaDescription(formulaType);
        const formulaInfo = document.querySelector('.warmup-formula-info');

        if (formulaInfo && formulaDescription) {
            formulaInfo.style.display = 'block';
            const descriptionEl = formulaInfo.querySelector('.formula-description');
            if (descriptionEl) {
                descriptionEl.textContent = formulaDescription;
            }
        }
    }

    function getWarmupFormulaDescription(formulaType, startVolume, dailyIncrement, alpha = 0.1) {
        const formulas = {
            'arithmetic': `Iₙ = ${startVolume} + (n−1) × ${dailyIncrement}`,
            'geometric': `Iₙ = ${startVolume} × ${(1 + dailyIncrement / 100).toFixed(2)}^(n−1)`,
            'progressive': `Eₙ = Eₙ₋₁ + α(E_target − Eₙ₋₁),  α = ${alpha}`,
            'flat': `I(t) = ${startVolume}`,
            'randomize': `Iₙ ~ U(0.85·I_base , 1.15·I_base)`
        };

        return formulas[formulaType] || formulas.arithmetic;
    }

    function updateCampaignPlanDisplay(totalContacts, durationDays, idealDailyVolume, formulaType, dailyVolumes) {
        const summaryElement = document.getElementById('campaign-plan-summary');
        const contentElement = document.getElementById('campaign-plan-content');
        const detailsElement = document.getElementById('campaign-plan-details');
        const detailsContent = document.getElementById('campaign-details-content');

        const startVolume = parseInt(document.getElementById('campaign_form_startVolume')?.value) || 0;
        const dailyIncrement = parseInt(document.getElementById('campaign_form_dailyIncrement')?.value) || 0;

        const averageDailyVolume = Math.round(dailyVolumes.reduce((a, b) => a + b, 0) / durationDays);
        const formulaDescription = getWarmupFormulaDescription(formulaType, startVolume, dailyIncrement);

        if (summaryElement && contentElement) {
            contentElement.innerHTML = `
                <strong>${totalContacts.toLocaleString()}</strong> contacts over <strong>${durationDays}</strong> days
                <br><small>≈ ${averageDailyVolume.toLocaleString()} emails/day average</small>
                <br><small><i>Formula: ${formulaDescription}</i></small>
            `;
            summaryElement.style.display = 'block';
        }

        if (detailsElement && detailsContent) {
            const firstWeekTotal = dailyVolumes.slice(0, 7).reduce((a, b) => a + b, 0);
            const peakVolume = Math.max(...dailyVolumes);

            detailsContent.innerHTML = `
                <table class="table table-condensed">
                    <tr>
                        <td>Total Contacts:</td>
                        <td><strong>${totalContacts.toLocaleString()}</strong></td>
                    </tr>
                    <tr>
                        <td>Campaign Duration:</td>
                        <td><strong>${durationDays} days</strong></td>
                    </tr>
                    <tr>
                        <td>Daily Average:</td>
                        <td><strong>${averageDailyVolume.toLocaleString()} emails/day</strong></td>
                    </tr>
                    <tr>
                        <td>Peak Daily Volume:</td>
                        <td><strong>${peakVolume.toLocaleString()} emails/day</strong></td>
                    </tr>
                    <tr>
                        <td>First Week Total:</td>
                        <td><strong>${firstWeekTotal.toLocaleString()} emails</strong></td>
                    </tr>
                    <tr>
                        <td>Warmup Formula:</td>
                        <td><strong>${formulaDescription}</strong></td>
                    </tr>
                    ${config.sequenceType === 'multiple' && config.emailSequences.length > 0 ? `
                    <tr>
                        <td>Email Sequence:</td>
                        <td><strong>${config.emailSequences.length} different emails</strong></td>
                    </tr>
                    ` : ''}
                </table>
                <p style="margin-bottom: 0; font-size: 11px;">
                    <i class="ri-information-line"></i> 
                    System calculates: <strong>${totalContacts} ÷ ${durationDays} = ${idealDailyVolume} emails/day (ideal)</strong>
                </p>
            `;
            detailsElement.style.display = 'block';
        }

        updateSummaryCampaignPlan(totalContacts, durationDays, averageDailyVolume, formulaType);
    }

    function hideCampaignPlanDisplay() {
        const summaryElement = document.getElementById('campaign-plan-summary');
        const detailsElement = document.getElementById('campaign-plan-details');

        if (summaryElement) summaryElement.style.display = 'none';
        if (detailsElement) detailsElement.style.display = 'none';
    }

    function updateWarmupDisplay(totalEmails, durationDays) {
        const totalEmailsSpan = document.getElementById('total-emails');
        const totalDaysSpan = document.getElementById('total-days');
        const progressBar = document.getElementById('warmup-progress-bar');
        const summaryTotalEmails = document.getElementById('summary-total-emails');
        const summaryProgressBar = document.getElementById('summary-progress-bar');
        const summaryDuration = document.getElementById('summary-duration');

        if (totalEmailsSpan) {
            totalEmailsSpan.textContent = totalEmails.toLocaleString();
            totalEmailsSpan.style.fontWeight = 'bold';
            totalEmailsSpan.style.color = totalEmails > 0 ? '#5cb85c' : '#d9534f';
        }

        if (totalDaysSpan) {
            totalDaysSpan.textContent = durationDays;
        }

        if (progressBar) {
            const maxEmails = Math.max(totalEmails, 1000);
            const percentage = Math.min(100, (totalEmails / maxEmails) * 100);
            progressBar.style.width = percentage + '%';
            progressBar.textContent = totalEmails.toLocaleString() + ' emails';
            progressBar.className = 'progress-bar progress-bar-success progress-bar-striped';
        }

        if (summaryTotalEmails) {
            summaryTotalEmails.textContent = totalEmails.toLocaleString();
        }

        if (summaryProgressBar) {
            const maxEmails = Math.max(totalEmails, 1000);
            const percentage = Math.min(100, (totalEmails / maxEmails) * 100);
            summaryProgressBar.style.width = percentage + '%';
        }

        if (summaryDuration) {
            summaryDuration.textContent = durationDays + ' days';
        }
    }

    function updateSummaryCampaignPlan(totalContacts, durationDays, averageDailyVolume) {
        const summaryDailyVolume = document.getElementById('summary-daily-volume');
        const summaryCampaignPlan = document.getElementById('summary-campaign-plan');
        const summaryWarmupType = document.getElementById('summary-warmup-type');
        const summaryEmailType = document.getElementById('summary-email-type');
        const warmupTypeSelect = document.getElementById('campaign_form_warmupType');
        const selectedOption = warmupTypeSelect?.options[warmupTypeSelect.selectedIndex];
        const warmupTypeName = selectedOption?.text || '--';

        if (summaryDailyVolume) {
            summaryDailyVolume.textContent = `${averageDailyVolume.toLocaleString()} emails/day`;
        }

        if (summaryWarmupType) {
            summaryWarmupType.textContent = warmupTypeName;
        }

        if (summaryEmailType) {
            summaryEmailType.textContent = config.sequenceType === 'multiple'
                ? `Sequence (${config.emailSequences.length} emails)`
                : 'Single Email';
        }

        if (summaryCampaignPlan) {
            const sequenceInfo = config.sequenceType === 'multiple' && config.emailSequences.length > 0
                ? `<p>Email Sequence: <strong>${config.emailSequences.length} different emails</strong></p>`
                : '';

            summaryCampaignPlan.innerHTML = `
                <p>${totalContacts.toLocaleString()} contacts will receive ${config.sequenceType === 'multiple' ? 'multiple emails' : '1 email each'} over ${durationDays} days.</p>
                ${sequenceInfo}
                <p>Average sending volume: <strong>${averageDailyVolume.toLocaleString()} emails/day</strong></p>
                <p>Review all settings before creating the campaign.</p>
            `;
        }
    }

    // ==========================================
    // STEP VALIDATION
    // ==========================================

    function validateStep(step) {
        let isValid = true;
        let message = '';

        switch (step) {
            case 1:
                const campaignName = document.querySelector('[name="campaign_form[campaignName]"]');
                const domain = document.querySelector('[name="campaign_form[domain]"]');

                if (!campaignName || !campaignName.value.trim()) {
                    isValid = false;
                    message = 'Please enter a campaign name';
                    if (campaignName) campaignName.focus();
                } else if (!domain || !domain.value) {
                    isValid = false;
                    message = 'Please select a sending domain';
                    if (domain) domain.focus();
                }
                break;

            case 2:
                if (config.contactsCount === 0) {
                    isValid = false;
                    message = 'Please add at least one contact';
                }
                break;

            case 3:
                const warmupType = document.querySelector('[name="campaign_form[warmupType]"]');
                if (!warmupType || !warmupType.value) {
                    isValid = false;
                    message = 'Please select a warmup type';
                    if (warmupType) warmupType.focus();
                }
                break;

            case 4:
                if (config.sequenceType === 'single') {
                    const subject = document.querySelector('[name="campaign_form[subjectTemplate]"]');
                    const content = document.querySelector('[name="campaign_form[customMessage]"]');

                    if (!subject || !subject.value.trim()) {
                        isValid = false;
                        message = 'Please enter an email subject';
                        if (subject) subject.focus();
                    } else if (!content || !content.value.trim()) {
                        isValid = false;
                        message = 'Please enter email content';
                        if (content) content.focus();
                    }
                } else {
                    if (config.emailSequences.length === 0) {
                        isValid = false;
                        message = 'Please add at least one email sequence';
                    } else {
                        const allFilled = config.emailSequences.every(seq =>
                            seq.subject && seq.subject.trim() && seq.body && seq.body.trim()
                        );

                        if (!allFilled) {
                            isValid = false;
                            message = 'Please fill all email sequence fields';
                        }
                    }
                }
                break;
        }

        if (!isValid && message) {
            showNotification(message, 'error');
        }

        return isValid;
    }

    // ==========================================
    // FORM VALIDATION HANDLER
    // ==========================================

    function handleFormValidation(event) {
        if (config.sequenceType === 'multiple') {
            const subjectField = document.querySelector('[name="campaign_form[subjectTemplate]"]');
            const messageField = document.querySelector('[name="campaign_form[customMessage]"]');

            if (subjectField) {
                subjectField.removeAttribute('required');
                subjectField.setCustomValidity('');
            }

            if (messageField) {
                messageField.removeAttribute('required');
                messageField.setCustomValidity('');
            }
        }

        // Vérifier que emailSequences est bien rempli en mode multiple
        if (config.sequenceType === 'multiple') {
            const emailSequencesField = document.querySelector('[name="campaign_form[emailSequences]"]');
            if (emailSequencesField) {
                const sequences = JSON.parse(emailSequencesField.value || '[]');
                if (sequences.length === 0) {
                    event.preventDefault();
                    showNotification('Please add at least one email sequence', 'error');
                    return false;
                }
            }
        }

        if (!validateStep(config.currentStep)) {
            event.preventDefault();
            return false;
        }

        return true;
    }

    // ==========================================
    // INITIALIZATION
    // ==========================================


    // ==========================================
    // STEP NAVIGATION
    // ==========================================

    function setupSteps() {
        const steps = document.querySelectorAll('.step');
        const stepContents = document.querySelectorAll('.step-content');
        const prevBtn = document.getElementById('prev-step-btn');
        const nextBtn = document.getElementById('next-step-btn');
        const finalButtons = document.getElementById('final-buttons');

        console.log('=== SETUP STEPS DEBUG ===');
        console.log('finalButtons found:', !!finalButtons);

        steps.forEach(step => {
            step.addEventListener('click', function () {
                const stepNum = parseInt(this.dataset.step);
                if (stepNum < config.currentStep) {
                    goToStep(stepNum);
                }
            });
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', () => goToStep(config.currentStep - 1));
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (validateStep(config.currentStep)) {
                    goToStep(config.currentStep + 1);
                }
            });
        }

        // Fonction pour aller à une étape
        window.goToStep = function (step) {
            if (step < 1 || step > config.totalSteps) return;

            config.currentStep = step;
            console.log('Going to step:', step);

            // Mettre à jour les étapes visuellement
            steps.forEach(s => {
                const stepNum = parseInt(s.dataset.step);
                s.classList.toggle('active', stepNum === config.currentStep);
                s.classList.toggle('completed', stepNum < config.currentStep);
            });

            // Afficher/masquer le contenu
            stepContents.forEach((content, index) => {
                content.style.display = (index + 1 === config.currentStep) ? 'block' : 'none';
            });

            // Gérer les boutons
            if (prevBtn) {
                prevBtn.style.display = config.currentStep > 1 ? 'inline-block' : 'none';
            }

            // CRITIQUE : À l'étape 5, montrer les boutons de soumission
            if (config.currentStep === 5) {
                if (nextBtn) nextBtn.style.display = 'none';

                // FORCER l'affichage des boutons submit
                if (finalButtons) {
                    finalButtons.style.display = 'block';
                    finalButtons.style.opacity = '1';
                    finalButtons.style.visibility = 'visible';

                    // S'assurer que les boutons submit sont cliquables
                    const saveBtn = document.getElementById('save-btn');
                    const saveActivateBtn = document.getElementById('save-activate-btn');

                    if (saveBtn) {
                        saveBtn.style.display = 'inline-block';
                        saveBtn.disabled = false;
                        console.log('✅ Save button enabled');
                    }

                    if (saveActivateBtn) {
                        saveActivateBtn.style.display = 'inline-block';
                        saveActivateBtn.disabled = false;
                        console.log('✅ Save & Activate button enabled');
                    }

                    console.log('✅ Step 5 - Submit buttons should be visible');
                }

                updateSummary();
            } else {
                if (nextBtn) nextBtn.style.display = 'inline-block';
                if (finalButtons) finalButtons.style.display = 'none';
            }

            console.log('Current step:', config.currentStep);
        };

        // Initialiser à l'étape 1
        window.goToStep(1);
    }

    // ==========================================
    // OTHER SETUP FUNCTIONS
    // ==========================================

    function setupContactSection() {
        const contactSourceOptions = document.querySelectorAll('.contact-source-option');
        const manualTextarea = document.querySelector('[name="campaign_form[manualContacts]"]');
        const csvFileInput = document.querySelector('[name="campaign_form[csvFile]"]');
        const browseSegmentsBtn = document.getElementById('browse-segments-btn');

        contactSourceOptions.forEach(option => {
            option.addEventListener('click', function (e) {
                e.preventDefault();

                contactSourceOptions.forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');

                const source = this.dataset.source;
                config.contactSource = source;

                // Afficher/masquer les sections
                document.querySelectorAll('.contacts-section').forEach(section => {
                    section.style.display = 'none';
                });

                if (source === 'manual') {
                    const section = document.getElementById('manual-contacts-section');
                    if (section) section.style.display = 'block';
                } else if (source === 'mautic') {
                    const section = document.getElementById('mautic-segment-section');
                    if (section) section.style.display = 'block';
                } else if (source === 'csv') {
                    const section = document.getElementById('csv-import-section');
                    if (section) section.style.display = 'block';
                }

                // Mettre à jour le champ contactSource
                const contactSourceField = document.querySelector('[name="campaign_form[contactSource]"]');
                if (contactSourceField) {
                    contactSourceField.value = source;
                }

                updateContactsCounter(0);
            });
        });

        // Initialiser la première section
        const activeOption = document.querySelector('.contact-source-option.active');
        if (activeOption) {
            const source = activeOption.dataset.source;
            const section = document.getElementById(`${source}-contacts-section`);
            if (section) section.style.display = 'block';
        }

        // Manual contacts
        if (manualTextarea) {
            manualTextarea.addEventListener('input', debounce(() => {
                const emails = manualTextarea.value.split('\n')
                    .map(email => email.trim())
                    .filter(email => email && email.includes('@'));
                updateContactsCounter(emails.length);
            }, 500));
        }

        // CSV file
        if (csvFileInput) {
            csvFileInput.addEventListener('change', handleCsvFile);
        }

        // Segments
        if (browseSegmentsBtn) {
            browseSegmentsBtn.addEventListener('click', showSegmentsModal);
        }

        // Exposer les fonctions globalement
        window.previewCsvFile = previewCsvFile;
        window.clearCsvPreview = clearCsvPreview;
        window.confirmCsvImport = confirmCsvImport;
        window.clearSegmentPreview = clearSegmentPreview;
        window.confirmSegmentImport = confirmSegmentImport;
    }

    function setupEmailContent() {
        const sendTestEmailBtn = document.getElementById('send-test-email-btn');
        const previewEmailBtn = document.getElementById('preview-email-btn');

        if (sendTestEmailBtn) {
            sendTestEmailBtn.addEventListener('click', sendTestEmail);
        }

        if (previewEmailBtn) {
            previewEmailBtn.addEventListener('click', previewEmail);
        }
    }

    function setupWarmupCalculator() {
        const startVolumeInput = document.querySelector('[name="campaign_form[startVolume]"]');
        const durationDaysInput = document.querySelector('[name="campaign_form[durationDays]"]');
        const dailyIncrementInput = document.querySelector('[name="campaign_form[dailyIncrement]"]');
        const warmupTypeSelect = document.querySelector('[name="campaign_form[warmupType]"]');
        const previewWarmupBtn = document.getElementById('preview-warmup-btn');

        console.log('Setting up warmup calculator...');
        console.log('warmupTypeSelect:', warmupTypeSelect);

        // Charger les données des formules dans les options
        if (warmupTypeSelect && window.warmupFormulas) {
            Array.from(warmupTypeSelect.options).forEach(option => {
                const formula = window.warmupFormulas[option.value];
                if (formula) {
                    option.setAttribute('data-formula-type', formula.formulaType);
                    option.setAttribute('data-default-start', formula.defaultStartVolume);
                    option.setAttribute('data-default-duration', formula.defaultDurationDays);
                    option.setAttribute('data-default-increment', formula.defaultIncrementPercentage);
                }
            });
        }

        // Écouter les changements
        [startVolumeInput, durationDaysInput, dailyIncrementInput].forEach(input => {
            if (input) {
                input.addEventListener('input', debounce(() => {
                    calculateCampaignPlan();
                }, 300));
                input.addEventListener('change', () => {
                    calculateCampaignPlan();
                });
            }
        });

        // GESTION CRITIQUE : Changement de type de warmup
        if (warmupTypeSelect) {
            warmupTypeSelect.addEventListener('change', function () {
                console.log('Warmup type changed to:', this.value);

                const selectedOption = this.options[this.selectedIndex];
                console.log('Selected option:', selectedOption);

                if (selectedOption) {
                    const defaultStart = selectedOption.getAttribute('data-default-start');
                    const defaultDuration = selectedOption.getAttribute('data-default-duration');
                    const defaultIncrement = selectedOption.getAttribute('data-default-increment');
                    const formulaType = selectedOption.getAttribute('data-formula-type');

                    console.log('Defaults:', { defaultStart, defaultDuration, defaultIncrement, formulaType });

                    // Appliquer les valeurs par défaut uniquement si non modifiées par l'utilisateur
                    if (startVolumeInput && !startVolumeInput.dataset.modified && defaultStart) {
                        startVolumeInput.value = defaultStart;
                        console.log('Applied default start volume:', defaultStart);
                    }

                    if (durationDaysInput && !durationDaysInput.dataset.modified && defaultDuration) {
                        durationDaysInput.value = defaultDuration;
                        console.log('Applied default duration:', defaultDuration);
                    }

                    if (dailyIncrementInput && !dailyIncrementInput.dataset.modified && defaultIncrement) {
                        dailyIncrementInput.value = defaultIncrement;
                        console.log('Applied default increment:', defaultIncrement);
                    }

                    // Mettre à jour l'affichage de la formule
                    updateFormulaDisplay(formulaType);

                    calculateCampaignPlan();
                }
            });

            // Déclencher l'événement change au chargement
            setTimeout(() => {
                if (warmupTypeSelect.value) {
                    warmupTypeSelect.dispatchEvent(new Event('change'));
                }
            }, 100);
        }

        // Marquer les champs modifiés par l'utilisateur
        [startVolumeInput, dailyIncrementInput, durationDaysInput].forEach(input => {
            if (input) {
                input.addEventListener('input', function () {
                    this.dataset.modified = 'true';
                });
            }
        });

        if (previewWarmupBtn) {
            previewWarmupBtn.addEventListener('click', showWarmupPlanPreview);
        }
    }



    function setupFormValidation() {
        const form = document.getElementById('campaign-form');
        if (form) {
            // Use our custom validation handler
            form.addEventListener('submit', function (e) {
                if (!handleFormValidation(e)) {
                    return;
                }

                const submitBtn = this.querySelector('button[type="submit"]:not([style*="display: none"])');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = 'Processing...';
                    submitBtn.disabled = true;

                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 5000);
                }
            });
        }
    }

    async function submitCampaignForm(activate = false) {
        console.log('Submitting campaign form, activate:', activate);

        // Valider toutes les étapes d'abord
        for (let step = 1; step <= 5; step++) {
            if (!validateStep(step)) {
                showNotification('Please fix all validation errors before submitting', 'error');
                goToStep(step);
                return;
            }
        }

        // Récupérer le formulaire
        const form = document.getElementById('campaign-form');
        if (!form) {
            showNotification('Form not found', 'error');
            return;
        }

        // Créer FormData à partir du formulaire
        const formData = new FormData(form);

        // Ajouter l'action spécifique (save or saveAndActivate)
        if (activate) {
            formData.append('campaign_form[saveAndActivate]', '1');
        } else {
            formData.append('campaign_form[save]', '1');
        }

        // // Ajouter le token CSRF
        // const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        // if (csrfToken) {
        //     formData.append('campaign_form[_token]', csrfToken);
        // }

        // Désactiver les boutons pendant l'envoi
        const createBtn = document.getElementById('create-campaign-btn');
        const createActivateBtn = document.getElementById('create-activate-campaign-btn');

        if (createBtn) {
            const originalText = createBtn.innerHTML;
            createBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
            createBtn.disabled = true;
        }

        if (createActivateBtn) {
            const originalText = createActivateBtn.innerHTML;
            createActivateBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
            createActivateBtn.disabled = true;
        }

        try {
            console.log('Sending form data to server...');

            const response = await fetch('{{ path("warmup_campaign_save") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            // Vérifier si c'est une redirection (success) ou une réponse JSON (error)
            const contentType = response.headers.get('content-type');

            if (response.redirected || response.url !== window.location.href) {
                // Redirection = succès
                console.log('Success! Redirecting...');
                window.location.href = '{{ path("warmup_campaign_index") }}';
                return;
            }

            if (contentType && contentType.includes('application/json')) {
                // Réponse JSON (probablement une erreur)
                const data = await response.json();
                console.log('Server response:', data);

                if (data.success) {
                    showNotification(data.message || 'Campaign saved successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = '{{ path("warmup_campaign_index") }}';
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Unknown error');
                }
            } else {
                // Réponse HTML (probablement une erreur de validation)
                const html = await response.text();
                console.log('HTML response received');

                // Extraire les messages d'erreur
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const errorMessages = doc.querySelectorAll('.alert-error, .alert-danger, .has-error');

                if (errorMessages.length > 0) {
                    let errorText = '';
                    errorMessages.forEach(msg => {
                        errorText += msg.textContent + '\n';
                    });
                    throw new Error(errorText || 'Form validation failed');
                } else {
                    // Si pas d'erreurs visibles, considérer comme succès
                    showNotification('Campaign saved successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = '{{ path("warmup_campaign_index") }}';
                    }, 1500);
                }
            }

        } catch (error) {
            console.error('Error submitting form:', error);
            showNotification('Error: ' + error.message, 'error');

            // Réactiver les boutons
            if (createBtn) {
                createBtn.innerHTML = 'Create Campaign';
                createBtn.disabled = false;
            }
            if (createActivateBtn) {
                createActivateBtn.innerHTML = 'Create & Activate';
                createActivateBtn.disabled = false;
            }
        }
    }


    function initialize() {
        console.log('=== INITIALIZE CAMPAIGN FORM (DIRECT POST) ===');

        // Set default date
        const startDateInput = document.querySelector('[name="campaign_form[startDate]"]');
        if (startDateInput && !startDateInput.value) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(9, 0, 0, 0);
            startDateInput.value = tomorrow.toISOString().slice(0, 16);
        }

        // Setup components
        setupSteps();
        setupContactSection();
        setupEmailContent();
        setupWarmupCalculator();
        setupEmailSequences();
        setupFormValidation();
        setupFormButtons(); // <-- Utiliser la nouvelle fonction

        // Initial calculation
        calculateCampaignPlan();
        window.CampaignEndDateCalculator.autoCalculateEndDate();

        debugCSRFToken();

        // Debug
        debugDirectPostButtons();
    }

    function debugDirectPostButtons() {
        console.group('=== DIRECT POST BUTTONS DEBUG ===');

        const buttons = [
            { id: 'save-btn', name: 'Save Campaign' },
            { id: 'save-activate-btn', name: 'Save & Activate' }
        ];

        buttons.forEach(button => {
            const element = document.getElementById(button.id);
            if (element) {
                console.log(`${button.name} (${button.id}):`);
                console.log(`  Data-action: ${element.getAttribute('data-action')}`);
                console.log(`  Data-url: ${element.getAttribute('data-url')}`);
                console.log(`  Data-campaign-id: ${element.getAttribute('data-campaign-id')}`);
                console.log(`  Display: ${window.getComputedStyle(element).display}`);
                console.log(`  Disabled: ${element.disabled}`);

                // Tester le clic
                element.addEventListener('click', function () {
                    console.log(`=== ${button.name} CLICK EVENT FIRED ===`);
                });
            } else {
                console.error(`${button.name} (${button.id}) NOT FOUND!`);
            }
        });

        console.groupEnd();
    }


    function debugFormState() {
        console.group('=== FORM DEBUG ===');

        // Vérifier les boutons
        const buttons = {
            'prev-step-btn': document.getElementById('prev-step-btn'),
            'next-step-btn': document.getElementById('next-step-btn'),
            'create-campaign-btn': document.getElementById('create-campaign-btn'),
            'create-activate-campaign-btn': document.getElementById('create-activate-campaign-btn'),
            'final-buttons': document.getElementById('final-buttons')
        };

        Object.entries(buttons).forEach(([name, element]) => {
            console.log(`${name}:`, element ? 'FOUND' : 'NOT FOUND');
            if (element) {
                console.log(`  Display: ${window.getComputedStyle(element).display}`);
                console.log(`  Enabled: ${!element.disabled}`);
            }
        });

        // Vérifier le formulaire
        const form = document.getElementById('campaign-form');
        console.log('Form:', form ? 'FOUND' : 'NOT FOUND');

        console.groupEnd();
    }





    // Fonction de soumission simple (PAS AJAX)
    function submitCampaignFormDirectly(button) {
        console.log('=== SUBMIT CAMPAIGN FORM (NORMAL POST) ===');

        const action = button.getAttribute('data-action');
        const campaignId = button.getAttribute('data-campaign-id');

        const form = document.getElementById('campaign-form');
        if (!form) {
            showNotification('Form not found', 'error');
            return;
        }

        // Créer un champ caché pour l'action (save ou saveAndActivate)
        let actionField = form.querySelector('input[name="campaign_form[' + action + ']"]');

        if (!actionField) {
            actionField = document.createElement('input');
            actionField.type = 'hidden';
            actionField.name = 'campaign_form[' + action + ']';
            actionField.value = '1';
            form.appendChild(actionField);
            console.log('✅ Created action field:', actionField.name);
        }

        // Ajouter l'ID si c'est une édition
        if (campaignId) {
            let idField = form.querySelector('input[name="campaign_form[id]"]');
            if (!idField) {
                idField = document.createElement('input');
                idField.type = 'hidden';
                idField.name = 'campaign_form[id]';
                idField.value = campaignId;
                form.appendChild(idField);
                console.log('✅ Created ID field:', campaignId);
            }
        }

        // Vérifier que le token CSRF existe
        const csrfToken = form.querySelector('[name="campaign_form[_token]"]');
        if (!csrfToken || !csrfToken.value) {
            showNotification('Security token missing. Please refresh the page.', 'error');
            return;
        }

        console.log('✅ CSRF Token present:', csrfToken.value.substring(0, 20) + '...');
        console.log('✅ Form will be submitted normally (not AJAX)');

        // Désactiver le bouton
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
        button.disabled = true;

        // Soumettre le formulaire normalement
        form.submit();
    }

    function setupFormButtons() {
        console.log('=== SETUP FORM BUTTONS ===');

        const form = document.getElementById('campaign-form');
        if (!form) {
            console.error('❌ Form not found!');
            return;
        }

        // SIMPLE: Laisser le formulaire se soumettre normalement
        // Les boutons submit vont déclencher la soumission normale

        console.log('✅ Form buttons setup complete');

        // Assurer que les boutons sont visibles à l'étape 5
        const finalButtons = document.getElementById('final-buttons');
        if (finalButtons) {
            // Forcer l'affichage quand on arrive à l'étape 5
            const observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    if (finalButtons.style.display === 'none') {
                        finalButtons.style.display = 'block';
                    }
                });
            });

            observer.observe(finalButtons, { attributes: true, attributeFilter: ['style'] });
        }
    }

    // Fonction de validation simplifiée
    function validateAllSteps() {
        console.log('=== VALIDATING ALL STEPS ===');

        // Étape 1: Nom de campagne
        const campaignName = document.querySelector('[name="campaign_form[campaignName]"]');
        if (!campaignName || !campaignName.value.trim()) {
            console.error('❌ Campaign name is required');
            updateStep(1); // Retourner à l'étape 1
            return false;
        }
        console.log('✅ Campaign name: OK');

        // Étape 1: Domain
        const domain = document.querySelector('[name="campaign_form[domain]"]');
        if (!domain || !domain.value) {
            console.error('❌ Domain is required');
            updateStep(1);
            return false;
        }
        console.log('✅ Domain: OK');

        // Étape 1: Start date
        const startDate = document.querySelector('[name="campaign_form[startDate]"]');
        if (!startDate || !startDate.value) {
            console.error('❌ Start date is required');
            updateStep(1);
            return false;
        }
        console.log('✅ Start date: OK');

        // Étape 3: Warmup type
        const warmupType = document.querySelector('[name="campaign_form[warmupType]"]');
        if (!warmupType || !warmupType.value) {
            console.error('❌ Warmup type is required');
            updateStep(3);
            return false;
        }
        console.log('✅ Warmup type: OK');

        // Vérifier le CSRF token
        const csrfToken = document.querySelector('[name="campaign_form[_token]"]');
        if (!csrfToken || !csrfToken.value) {
            console.error('❌ CSRF token missing!');
            return false;
        }
        console.log('✅ CSRF token: OK');

        console.log('✅✅✅ ALL VALIDATIONS PASSED ✅✅✅');
        return true;
    }

    // Fonction helper pour aller à une étape spécifique
    function updateStep(step) {
        // Cette fonction devrait déjà exister dans setupSteps()
        // Sinon, ajoutez-la
        if (typeof window.updateStepGlobal === 'function') {
            window.updateStepGlobal(step);
        }
    }
    function updateSummary() {
        const campaignName = document.querySelector('[name="campaign_form[campaignName]"]')?.value || '--';
        const domainSelect = document.querySelector('[name="campaign_form[domain]"]');
        const domain = domainSelect?.options[domainSelect.selectedIndex]?.text || '--';
        const startDate = document.querySelector('[name="campaign_form[startDate]"]')?.value || '--';
        const durationDays = parseInt(document.querySelector('[name="campaign_form[durationDays]"]')?.value) || 0;

        const summaryName = document.getElementById('summary-name');
        const summaryDomain = document.getElementById('summary-domain');
        const summaryStartDate = document.getElementById('summary-start-date');
        const summaryDuration = document.getElementById('summary-duration');
        const summaryContacts = document.getElementById('summary-contacts');

        if (summaryName) summaryName.textContent = campaignName;
        if (summaryDomain) summaryDomain.textContent = domain;
        if (summaryStartDate && startDate !== '--') {
            const date = new Date(startDate);
            summaryStartDate.textContent = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        if (summaryDuration) summaryDuration.textContent = durationDays + ' days';
        if (summaryContacts) summaryContacts.textContent = config.contactsCount.toLocaleString();

        calculateCampaignPlan();
    }


    // ==========================================
    // REST OF THE FUNCTIONS (keep from original)
    // ==========================================

    async function handleCsvFile(event) {
        const file = event.target.files[0];
        if (!file) return;

        if (!file.name.toLowerCase().endsWith('.csv')) {
            showNotification('Please select a CSV file', 'error');
            event.target.value = '';
            return;
        }

        if (file.size > 1024 * 1024) {
            showNotification('File size must be less than 1MB', 'error');
            event.target.value = '';
            return;
        }

        await previewCsvFile(event.target);
    }

    async function previewCsvFile(input) {
        if (!input.files || !input.files[0]) return;

        const file = input.files[0];
        const formData = new FormData();
        formData.append('csvFile', file);

        const progressDiv = document.getElementById('csv-upload-progress');
        const previewDiv = document.getElementById('csv-preview');

        if (progressDiv) progressDiv.style.display = 'block';
        if (previewDiv) previewDiv.style.display = 'none';

        try {
            const response = await fetch('/s/warmup/campaigns/preview-csv', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                const previewBody = document.getElementById('csv-preview-body');
                const totalContacts = document.getElementById('csv-total-contacts');

                if (previewBody && totalContacts) {
                    previewBody.innerHTML = '';

                    if (data.preview && data.preview.length > 0) {
                        data.preview.forEach(row => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${row[0] || ''}</td>
                                <td>${row[1] || ''}</td>
                                <td>${row[2] || ''}</td>
                            `;
                            previewBody.appendChild(tr);
                        });
                    }

                    totalContacts.textContent = `${data.total_contacts} contacts found`;
                    updateContactsCounter(data.total_contacts);
                    config.csvContacts = data.total_contacts;

                    if (progressDiv) progressDiv.style.display = 'none';
                    if (previewDiv) previewDiv.style.display = 'block';

                    showNotification(`CSV file processed. Found ${data.total_contacts} contacts.`, 'success');
                }
            } else {
                throw new Error(data.message || 'Failed to process CSV');
            }
        } catch (error) {
            console.error('Error processing CSV:', error);
            showNotification('Error: ' + error.message, 'error');
            if (progressDiv) progressDiv.style.display = 'none';
        }
    }

    function clearCsvPreview() {
        document.getElementById('campaign_form_csvFile').value = '';
        document.getElementById('csv-preview').style.display = 'none';
        document.getElementById('csv-preview-body').innerHTML = '';
        document.getElementById('csv-total-contacts').textContent = '0 contacts found';
        config.csvContacts = null;
        updateContactsCounter(0);
    }

    function confirmCsvImport() {
        if (config.csvContacts && config.csvContacts > 0) {
            showNotification(`Successfully imported ${config.csvContacts} contacts from CSV`, 'success');
        } else {
            showNotification('No contacts to import', 'warning');
        }
    }

    function clearSegmentPreview() {
        document.getElementById('segment-preview').innerHTML = '';
        document.getElementById('segment-preview').style.display = 'none';
        document.getElementById('campaign_form_segmentId').value = '';
        updateContactsCounter(0);
        config.currentSegmentId = null;
    }

    function confirmSegmentImport(segmentId) {
        showNotification(`Segment import confirmed. ${config.contactsCount} contacts will be added.`, 'success');
    }

    async function showSegmentsModal() {
        try {
            const modalContent = document.getElementById('segments-modal-content');
            if (modalContent) {
                modalContent.innerHTML = '<div class="text-center"><p>Loading segments...</p></div>';
            }

            const response = await fetch('/s/warmup/campaigns/list-segments', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (modalContent) {
                if (data.success && data.segments && data.segments.length > 0) {
                    let html = '<div class="list-group">';

                    data.segments.forEach(segment => {
                        html += `
                            <a href="#" class="list-group-item select-segment" 
                               data-id="${segment.id}" 
                               data-name="${segment.name}">
                                <h5 class="list-group-item-heading">${segment.name}</h5>
                                <p class="list-group-item-text">
                                    ${segment.description || 'No description'}
                                    <br>
                                    <small>${segment.lead_count || 0} contacts</small>
                                </p>
                            </a>
                        `;
                    });

                    html += '</div>';
                    modalContent.innerHTML = html;

                    document.querySelectorAll('.select-segment').forEach(btn => {
                        btn.addEventListener('click', function (e) {
                            e.preventDefault();
                            const segmentId = this.dataset.id;
                            const segmentName = this.dataset.name;

                            const segmentIdInput = document.getElementById('campaign_form_segmentId');
                            if (segmentIdInput) {
                                segmentIdInput.value = segmentId;
                            }

                            const segmentPreview = document.getElementById('segment-preview');
                            if (segmentPreview) {
                                segmentPreview.innerHTML = `
                                    <div class="alert alert-success">
                                        Selected segment: <strong>${segmentName}</strong>
                                    </div>
                                `;
                                segmentPreview.style.display = 'block';
                            }

                            loadSegmentContacts(segmentId);

                            if (typeof jQuery !== 'undefined') {
                                jQuery('#segments-modal').modal('hide');
                            }
                        });
                    });
                } else {
                    modalContent.innerHTML = '<div class="alert alert-warning">No segments found. Please create segments in Mautic first.</div>';
                }
            }

            if (typeof jQuery !== 'undefined') {
                jQuery('#segments-modal').modal('show');
            }

        } catch (error) {
            console.error('Error loading segments:', error);
            showNotification('Failed to load segments: ' + error.message, 'error');
        }
    }

    async function loadSegmentContacts(segmentId) {
        try {
            const formData = new URLSearchParams();
            formData.append('segment_id', segmentId);
            formData.append('limit', '5');

            const response = await fetch('/s/warmup/campaigns/preview-contacts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                const contactsCount = data.total_available || 0;
                updateContactsCounter(contactsCount);
                showNotification(`Loaded ${contactsCount} contacts from segment`, 'success');
            } else {
                throw new Error(data.message || 'Failed to load segment contacts');
            }
        } catch (error) {
            console.error('Error loading segment contacts:', error);
            showNotification(error.message, 'error');
        }
    }

    function updateContactsCounter(count) {
        config.contactsCount = count;

        const contactsCountSpan = document.getElementById('contacts-count');
        const contactsCounter = document.getElementById('contacts-counter');
        const summaryContacts = document.getElementById('summary-contacts');

        if (contactsCountSpan) contactsCountSpan.textContent = count.toLocaleString();
        if (summaryContacts) summaryContacts.textContent = count.toLocaleString();

        if (contactsCounter) {
            contactsCounter.style.display = count > 0 ? 'block' : 'none';
        }

        // IMPORTANT: Mettre à jour le champ totalContacts
        const totalContactsField = document.querySelector('[name="campaign_form[totalContacts]"]');
        if (totalContactsField) {
            totalContactsField.value = count;
            console.log('Updated totalContacts to:', count);
        }

        calculateCampaignPlan();
    }

    async function sendTestEmail() {
        const testEmailInput = document.getElementById('test-email');
        const subjectInput = document.getElementById('campaign_form_subjectTemplate');
        const messageTextarea = document.getElementById('campaign_form_customMessage');
        const domainSelect = document.getElementById('campaign_form_domain');

        if (!testEmailInput || !testEmailInput.value) {
            showNotification('Please enter a test email address', 'warning');
            if (testEmailInput) testEmailInput.focus();
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(testEmailInput.value)) {
            showNotification('Please enter a valid email address', 'warning');
            testEmailInput.focus();
            return;
        }

        if (!domainSelect || !domainSelect.value) {
            showNotification('Please select a domain first', 'warning');
            return;
        }

        const btn = this;
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Sending...';
        btn.disabled = true;

        const token = document.getElementById('csrf-token')?.value || '';

        const bodyParams = new URLSearchParams();
        bodyParams.append('testEmail', testEmailInput.value);
        bodyParams.append('domainId', domainSelect.value);
        bodyParams.append('subject', subjectInput?.value || 'Test Email');
        bodyParams.append('message', messageTextarea?.value || 'This is a test email');
        bodyParams.append('_token', token);

        try {
            const response = await fetch('/s/warmup/campaigns/send-test-email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: bodyParams.toString()
            });

            const responseText = await response.text();
            console.log('Response:', responseText.substring(0, 500));

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (jsonError) {
                console.error('Failed to parse JSON:', jsonError);
                throw new Error('Server returned invalid response');
            }

            if (data.success) {
                showNotification('Test email sent successfully!', 'success');
                testEmailInput.value = '';
            } else {
                throw new Error(data.message || 'Failed to send test email');
            }

        } catch (error) {
            console.error('Error sending test email:', error);
            showNotification('Error: ' + error.message, 'error');
        } finally {
            btn.innerHTML = 'Send';
            btn.disabled = false;
        }
    }

    function previewEmail() {
        const subjectInput = document.getElementById('campaign_form_subjectTemplate');
        const messageTextarea = document.getElementById('campaign_form_customMessage');

        const subject = subjectInput?.value || 'No subject';
        const content = messageTextarea?.value || 'No content';

        const existingModal = document.getElementById('email-preview-modal');
        if (existingModal) {
            existingModal.remove();
        }

        const modal = `
            <div class="modal fade" id="email-preview-modal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title">Email Preview</h4>
                        </div>
                        <div class="modal-body">
                            <h5>Subject: ${subject}</h5>
                            <hr>
                            <div style="border: 1px solid #ddd; padding: 15px; background: white;">
                                ${content.replace(/\n/g, '<br>')}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const container = document.getElementById('email-preview-modal-container');
        if (container) {
            container.innerHTML = modal;

            if (typeof jQuery !== 'undefined') {
                jQuery('#email-preview-modal').modal('show');
                jQuery('#email-preview-modal').on('hidden.bs.modal', function () {
                    container.innerHTML = '';
                });
            }
        }
    }

    function showWarmupPlanPreview() {
        const totalContacts = config.contactsCount || 0;
        const durationDays = parseInt(document.getElementById('campaign_form_durationDays')?.value) || 30;
        const startVolume = parseInt(document.getElementById('campaign_form_startVolume')?.value) || 20;
        const warmupTypeSelect = document.getElementById('campaign_form_warmupType');
        const selectedTypeId = warmupTypeSelect?.value;

        if (totalContacts === 0) {
            showNotification('Please add contacts first to see the warmup plan', 'warning');
            return;
        }

        let warmupTypeName = 'Warmup Plan';
        let warmupTypeDescription = 'Daily email sending plan';

        if (selectedTypeId && window.warmupFormulas && window.warmupFormulas[selectedTypeId]) {
            warmupTypeName = window.warmupFormulas[selectedTypeId].name;
            warmupTypeDescription = window.warmupFormulas[selectedTypeId].description;
        }

        const plan = calculateCampaignPlan();
        if (!plan) return;

        const sequenceInfo = config.sequenceType === 'multiple' && config.emailSequences.length > 0
            ? `<p><strong>Email Sequence:</strong> ${config.emailSequences.length} different emails</p>`
            : '';

        let html = `
            <div class="alert alert-success">
                <h4>Campaign Warmup Plan</h4>
                ${sequenceInfo}
                <p><strong>Objective:</strong> Send ${config.sequenceType === 'multiple' ? 'multiple emails' : '1 email'} to each of ${totalContacts.toLocaleString()} contacts over ${durationDays} days</p>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h5 class="panel-title">Summary</h5>
                        </div>
                        <div class="panel-body">
                            <table class="table table-condensed">
                                <tr>
                                    <td>Total Contacts:</td>
                                    <td><strong>${totalContacts.toLocaleString()}</strong></td>
                                </tr>
                                <tr>
                                    <td>Campaign Duration:</td>
                                    <td><strong>${durationDays} days</strong></td>
                                </tr>
                                <tr>
                                    <td>Email Type:</td>
                                    <td><strong>${config.sequenceType === 'multiple' ? 'Sequence (' + config.emailSequences.length + ' emails)' : 'Single Email'}</strong></td>
                                </tr>
                                <tr>
                                    <td>Warmup Type:</td>
                                    <td><strong>${warmupTypeName}</strong></td>
                                </tr>
                                <tr>
                                    <td>Start Volume:</td>
                                    <td><strong>${startVolume} emails/day</strong></td>
                                </tr>
                                <tr>
                                    <td>Daily Average:</td>
                                    <td><strong>${Math.round(plan.dailyVolumes.reduce((a, b) => a + b, 0) / durationDays).toLocaleString()} emails/day</strong></td>
                                </tr>
                                <tr>
                                    <td>Total Emails:</td>
                                    <td><strong>${plan.warmupTotalEmails.toLocaleString()} emails</strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h5 class="panel-title">Daily Schedule (First 2 Weeks)</h5>
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-condensed">
                                    <thead>
                                        <tr>
                                            <th>Day</th>
                                            <th>Emails</th>
                                            <th>Cumulative</th>
                                            ${config.sequenceType === 'multiple' ? '<th>Email #</th>' : ''}
                                        </tr>
                                    </thead>
                                    <tbody>
        `;

        let cumulative = 0;
        const displayLimit = Math.min(14, plan.dailyVolumes.length);

        for (let day = 1; day <= displayLimit; day++) {
            const dailyVolume = plan.dailyVolumes[day - 1] || 0;
            cumulative += dailyVolume;
            const emailNum = config.sequenceType === 'multiple'
                ? (day <= config.emailSequences.length ? day : config.emailSequences.length)
                : 1;

            html += `
                <tr>
                    <td>Day ${day}</td>
                    <td><span class="badge badge-primary">${dailyVolume}</span></td>
                    <td>${cumulative.toLocaleString()}</td>
                    ${config.sequenceType === 'multiple' ? `<td>Email ${emailNum}</td>` : ''}
                </tr>
            `;
        }

        if (plan.dailyVolumes.length > 14) {
            const remainingDays = plan.dailyVolumes.length - 14;
            const remainingEmails = plan.warmupTotalEmails - cumulative;

            html += `
                <tr>
                    <td colspan="${config.sequenceType === 'multiple' ? '4' : '3'}" class="text-center">
                        <em>... ${remainingDays} more days (${remainingEmails.toLocaleString()} more emails)</em>
                    </td>
                </tr>
            `;
        }

        html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info">
                <p><strong>How it works:</strong></p>
                <ul>
                    <li>${config.sequenceType === 'multiple' ? 'Contacts receive multiple emails in sequence' : 'Each contact receives exactly 1 email'}</li>
                    <li>System calculates daily volume: <code>${totalContacts} ÷ ${durationDays} = ${plan.idealDailyVolume} emails/day</code></li>
                    <li>Warmup progression: ${warmupTypeDescription}</li>
                    <li>Campaign completes when all ${totalContacts.toLocaleString()} contacts have been emailed</li>
                </ul>
            </div>
        `;

        const planContent = document.getElementById('warmup-plan-content');
        if (planContent) {
            planContent.innerHTML = html;

            if (typeof jQuery !== 'undefined') {
                jQuery('#warmup-plan-modal').modal('show');
            }
        }
    }

    // ==========================================
    // INITIALIZE ON DOM READY
    // ==========================================

    document.addEventListener('DOMContentLoaded', function () {
        const token = document.getElementById('csrf-token')?.value;
        console.log('CSRF Token for sendTestEmail:', token ? 'Found (' + token.substring(0, 10) + '...)' : 'NOT FOUND!');

        const sendTestEmailBtn = document.getElementById('send-test-email-btn');
        if (sendTestEmailBtn) {
            sendTestEmailBtn.addEventListener('click', sendTestEmail);
        }

        initialize();
    });

    // Export functions to window object
    window.calculateCampaignPlan = calculateCampaignPlan;
    window.updateContactsCounter = updateContactsCounter;

    document.addEventListener('DOMContentLoaded', function () {
        // Attendre que tout soit chargé
        setTimeout(function () {
            // Exposer des fonctions de test dans la console
            window.testDirectPost = function () {
                console.log('=== TEST DIRECT POST ===');

                const saveBtn = document.getElementById('save-btn');
                const saveActivateBtn = document.getElementById('save-activate-btn');

                if (saveBtn) {
                    console.log('1. Testing save-btn...');
                    saveBtn.click();
                    return true;
                }

                if (saveActivateBtn) {
                    console.log('2. Testing save-activate-btn...');
                    saveActivateBtn.click();
                    return true;
                }

                console.error('No buttons found!');
                return false;
            };

            window.forceShowButtons = function () {
                console.log('=== FORCE SHOW BUTTONS ===');

                const buttons = ['save-btn', 'save-activate-btn'];
                buttons.forEach(id => {
                    const button = document.getElementById(id);
                    if (button) {
                        button.style.display = 'inline-block';
                        button.style.opacity = '1';
                        button.style.visibility = 'visible';
                        button.disabled = false;
                        console.log(`✅ ${id} forced to show`);
                    }
                });

                const finalButtons = document.getElementById('final-buttons');
                if (finalButtons) {
                    finalButtons.style.display = 'block';
                    console.log('✅ final-buttons forced to show');
                }
            };

            console.log('Test functions available:');
            console.log('- testDirectPost()');
            console.log('- forceShowButtons()');

        }, 2000);
    });

    // Ajouter à la fin de campaign_form.js
    function debugCSRFToken() {
        console.group('=== CSRF TOKEN DEBUG ===');

        // Chercher le token de différentes manières
        const tokenSources = [
            { name: 'input[name="campaign_form[_token]"]', element: document.querySelector('[name="campaign_form[_token]"]') },
            { name: 'meta[name="csrf-token"]', element: document.querySelector('meta[name="csrf-token"]') },
            { name: '#campaign_form__token', element: document.getElementById('campaign_form__token') },
            { name: 'input[name="_token"]', element: document.querySelector('[name="_token"]') },
            { name: 'input[name="campaign_form__token"]', element: document.querySelector('[name="campaign_form__token"]') }
        ];

        tokenSources.forEach(source => {
            console.log(source.name + ':');
            if (source.element) {
                const value = source.element.value || source.element.content || 'N/A';
                console.log('  Found: ' + (value ? 'Yes (' + value.substring(0, 20) + '...)' : 'Empty'));
                console.log('  Type: ' + source.element.tagName);
            } else {
                console.log('  Not found');
            }
        });

        // Vérifier le formulaire
        const form = document.getElementById('campaign-form');
        if (form) {
            console.log('Form action:', form.action);
            console.log('Form method:', form.method);

            // Vérifier tous les champs cachés
            const hiddenInputs = form.querySelectorAll('input[type="hidden"]');
            console.log('Hidden inputs:', hiddenInputs.length);
            hiddenInputs.forEach(input => {
                if (input.name.includes('token') || input.name.includes('csrf')) {
                    console.log('  ' + input.name + ': ' + input.value.substring(0, 20) + '...');
                }
            });
        }

        console.groupEnd();
    }

})();