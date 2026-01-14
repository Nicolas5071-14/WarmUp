/**
 * Campaign End Date Calculator
 * Calcule automatiquement la date de fin de campagne basée sur le plan de warmup
 */

(function () {
    'use strict';

    /**
     * Calcule la date de fin de la campagne
     * @param {Date} startDate - Date de début de la campagne
     * @param {number} durationDays - Durée en jours
     * @param {string} sendFrequency - Fréquence d'envoi (daily, weekdays, weekly)
     * @param {boolean} enableWeekends - Envoyer pendant les week-ends
     * @returns {Date} Date de fin calculée
     */
    function calculateEndDate(startDate, durationDays, sendFrequency, enableWeekends) {
        if (!startDate || !durationDays || durationDays <= 0) {
            console.warn('Invalid parameters for end date calculation');
            return null;
        }

        const endDate = new Date(startDate);
        let daysAdded = 0;
        let totalDays = 0;

        // Calculer la date de fin en fonction de la fréquence
        while (daysAdded < durationDays) {
            totalDays++;
            endDate.setDate(endDate.getDate() + 1);

            const dayOfWeek = endDate.getDay(); // 0 = Sunday, 6 = Saturday
            const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

            switch (sendFrequency) {
                case 'daily':
                    // Envoi tous les jours
                    if (enableWeekends || !isWeekend) {
                        daysAdded++;
                    }
                    break;

                case 'weekdays':
                    // Envoi seulement en semaine
                    if (!isWeekend) {
                        daysAdded++;
                    }
                    break;

                case 'weekly':
                    // Envoi une fois par semaine (lundi)
                    if (dayOfWeek === 1) { // Monday
                        daysAdded++;
                    }
                    break;

                default:
                    // Par défaut : tous les jours
                    if (enableWeekends || !isWeekend) {
                        daysAdded++;
                    }
            }

            // Sécurité : limiter à 365 jours maximum
            if (totalDays > 365) {
                console.error('End date calculation exceeded 365 days');
                break;
            }
        }

        console.log(`End date calculated: ${endDate.toISOString()}`);
        console.log(`Total calendar days: ${totalDays}`);
        console.log(`Actual sending days: ${daysAdded}`);

        return endDate;
    }

    /**
     * Calcule la date de fin avec le plan de warmup
     * @param {Object} warmupPlan - Plan de warmup calculé
     * @returns {Date} Date de fin
     */
    function calculateEndDateFromWarmupPlan(warmupPlan) {
        if (!warmupPlan || !warmupPlan.startDate || !warmupPlan.durationDays) {
            console.warn('Invalid warmup plan for end date calculation');
            return null;
        }

        const startDate = new Date(warmupPlan.startDate);
        const durationDays = warmupPlan.durationDays;
        const sendFrequency = warmupPlan.sendFrequency || 'daily';
        const enableWeekends = warmupPlan.enableWeekends !== false;

        return calculateEndDate(startDate, durationDays, sendFrequency, enableWeekends);
    }

    /**
     * Met à jour le champ end_date dans le formulaire
     * @param {Date} endDate - Date de fin calculée
     */
    /**
  * Met à jour le champ end_date dans le formulaire
  * @param {Date} endDate - Date de fin calculée
  */
    function updateEndDateField(endDate) {
        if (!endDate) {
            console.warn('No end date to update');
            return;
        }

        let endDateField = document.querySelector('[name="campaign_form[endDate]"]');

        if (!endDateField) {
            endDateField = document.createElement('input');
            endDateField.type = 'hidden';
            endDateField.name = 'campaign_form[endDate]';
            endDateField.id = 'campaign_form_endDate';

            const form = document.getElementById('campaign-form');
            if (form) {
                form.appendChild(endDateField);
                console.log('Created endDate hidden field');
            }
        }

        // 🔥 FORMAT ISO 8601 pour Symfony DateTimeType
        // Format: "YYYY-MM-DDTHH:mm"
        const formattedDate = endDate.toISOString().slice(0, 16);
        endDateField.value = formattedDate;

        console.log(`✅ End date field updated to ISO format: ${formattedDate}`);
    }

    /**
     * Calcule et met à jour la date de fin automatiquement
     */
    /**
  * Calcule et met à jour la date de fin automatiquement
  */
    function autoCalculateEndDate() {
        // Récupérer les données du formulaire
        const startDateInput = document.querySelector('[name="campaign_form[startDate]"]');
        const durationDaysInput = document.querySelector('[name="campaign_form[durationDays]"]');
        const sendFrequencyInput = document.querySelector('[name="campaign_form[sendFrequency]"]');
        const enableWeekendsInput = document.querySelector('[name="campaign_form[enableWeekends]"]');

        if (!startDateInput || !durationDaysInput) {
            console.warn('Required fields not found for end date calculation');
            return;
        }

        const startDateValue = startDateInput.value;
        const durationDays = parseInt(durationDaysInput.value);
        const sendFrequency = sendFrequencyInput?.value || 'daily';
        const enableWeekends = enableWeekendsInput?.checked !== false;

        if (!startDateValue || isNaN(durationDays) || durationDays <= 0) {
            console.warn('Invalid values for end date calculation');
            return;
        }

        // Convertir la date de début
        const startDate = new Date(startDateValue);

        // Calculer la date de fin
        const endDate = calculateEndDate(startDate, durationDays, sendFrequency, enableWeekends);

        if (endDate) {
            updateEndDateField(endDate);

            // Afficher dans la console pour debug
            console.group('📅 End Date Calculation');
            console.log('Start Date:', startDate.toISOString());
            console.log('Duration Days:', durationDays);
            console.log('Send Frequency:', sendFrequency);
            console.log('Enable Weekends:', enableWeekends);
            console.log('Calculated End Date:', endDate.toISOString());
            console.log('Formatted for Form (ISO 8601):', endDate.toISOString().slice(0, 16));
            console.groupEnd();
        }
    }

    /**
     * Crée le bouton pour ouvrir le popup
     */
    function createScheduleButton() {
        // Vérifier si le bouton existe déjà
        let button = document.getElementById('view-schedule-btn');
        if (button) return;

        button = document.createElement('button');
        button.id = 'view-schedule-btn';
        button.type = 'button';
        button.className = 'btn btn-info btn-sm';
        button.innerHTML = '<i class="ri-calendar-line"></i> View Campaign Schedule';
        button.style.cssText = 'margin-top: 10px; width: 100%;';

        button.addEventListener('click', showSchedulePopup);

        // Insérer après le champ durationDays
        const durationDaysInput = document.querySelector('[name="campaign_form[durationDays]"]');
        const durationField = durationDaysInput?.closest('.form-group');
        if (durationField) {
            durationField.appendChild(button);
        }
    }

    /**
     * Affiche le popup avec le résumé de la campagne
     */
    function showSchedulePopup() {
        const startDateInput = document.querySelector('[name="campaign_form[startDate]"]');
        const durationDaysInput = document.querySelector('[name="campaign_form[durationDays]"]');
        const sendFrequencyInput = document.querySelector('[name="campaign_form[sendFrequency]"]');
        const enableWeekendsInput = document.querySelector('[name="campaign_form[enableWeekends]"]');
        const endDateField = document.querySelector('[name="campaign_form[endDate]"]');

        if (!startDateInput?.value || !durationDaysInput?.value) {
            alert('Please fill in Start Date and Duration first');
            return;
        }

        const startDate = new Date(startDateInput.value);
        const endDate = endDateField?.value ? new Date(endDateField.value) : null;
        const durationDays = parseInt(durationDaysInput.value);
        const sendFrequency = sendFrequencyInput?.value || 'daily';
        const enableWeekends = enableWeekendsInput?.checked !== false;

        // Formater les dates
        const formatDate = (date) => {
            return date.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        };

        const formatDateTime = (date) => {
            return date.toLocaleString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        };

        // Calculer les jours calendaires
        const calendarDays = endDate ? Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) : 0;

        // Créer ou récupérer le modal
        let modal = document.getElementById('campaign-schedule-modal');

        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'campaign-schedule-modal';
            modal.className = 'modal fade';
            modal.setAttribute('tabindex', '-1');
            modal.setAttribute('role', 'dialog');
            document.body.appendChild(modal);
        }

        // Générer le contenu du modal
        const modalHTML = `
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content" style="border-radius: 10px; overflow: hidden;">
                    <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 25px;">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white; opacity: 1; text-shadow: none;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" style="margin: 0; font-size: 24px; font-weight: 600;">
                            <i class="ri-calendar-line" style="margin-right: 10px;"></i>
                            Campaign Schedule
                        </h4>
                        <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 14px;">
                            Complete timeline and dates for your campaign
                        </p>
                    </div>
                    
                    <div class="modal-body" style="padding: 30px; background: #f8f9fa;">
                        <!-- Timeline Visual -->
                        <div style="background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                            <div style="text-align: center; margin-bottom: 20px;">
                                <div style="display: inline-block; position: relative; width: 100%; max-width: 600px;">
                                    <div style="height: 4px; background: linear-gradient(90deg, #5cb85c 0%, #f0ad4e 50%, #d9534f 100%); border-radius: 2px; position: relative;">
                                        <div style="position: absolute; left: 0; top: -8px; width: 20px; height: 20px; background: #5cb85c; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.2);"></div>
                                        <div style="position: absolute; right: 0; top: -8px; width: 20px; height: 20px; background: #d9534f; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.2);"></div>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-top: 15px;">
                                        <div style="text-align: left;">
                                            <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px;">Start</div>
                                            <div style="font-size: 13px; font-weight: 600; color: #5cb85c;">${formatDate(startDate).split(',')[0]}</div>
                                        </div>
                                        <div style="text-align: center;">
                                            <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px;">Duration</div>
                                            <div style="font-size: 13px; font-weight: 600; color: #f0ad4e;">${durationDays} days</div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px;">End</div>
                                            <div style="font-size: 13px; font-weight: 600; color: #d9534f;">${endDate ? formatDate(endDate).split(',')[0] : 'TBD'}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cards Grid -->
                        <div class="row" style="margin: 0 -10px;">
                            <div class="col-md-6" style="padding: 0 10px; margin-bottom: 20px;">
                                <div style="background: white; padding: 20px; border-radius: 8px; height: 100%; border-left: 5px solid #5cb85c; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s;">
                                    <div style="display: flex; align-items: center; margin-bottom: 12px;">
                                        <div style="width: 40px; height: 40px; background: rgba(92, 184, 92, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                            <i class="ri-play-circle-fill" style="font-size: 22px; color: #5cb85c;"></i>
                                        </div>
                                        <div>
                                            <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">Start Date</div>
                                        </div>
                                    </div>
                                    <div style="font-size: 18px; font-weight: 700; color: #333; line-height: 1.4; padding-left: 52px;">
                                        ${formatDateTime(startDate)}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6" style="padding: 0 10px; margin-bottom: 20px;">
                                <div style="background: white; padding: 20px; border-radius: 8px; height: 100%; border-left: 5px solid #f0ad4e; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s;">
                                    <div style="display: flex; align-items: center; margin-bottom: 12px;">
                                        <div style="width: 40px; height: 40px; background: rgba(240, 173, 78, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                            <i class="ri-time-fill" style="font-size: 22px; color: #f0ad4e;"></i>
                                        </div>
                                        <div>
                                            <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">Duration</div>
                                        </div>
                                    </div>
                                    <div style="font-size: 18px; font-weight: 700; color: #333; line-height: 1.4; padding-left: 52px;">
                                        ${durationDays} sending days
                                    </div>
                                </div>
                            </div>
                            
                            ${endDate ? `
                            <div class="col-md-6" style="padding: 0 10px; margin-bottom: 20px;">
                                <div style="background: white; padding: 20px; border-radius: 8px; height: 100%; border-left: 5px solid #d9534f; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s;">
                                    <div style="display: flex; align-items: center; margin-bottom: 12px;">
                                        <div style="width: 40px; height: 40px; background: rgba(217, 83, 79, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                            <i class="ri-stop-circle-fill" style="font-size: 22px; color: #d9534f;"></i>
                                        </div>
                                        <div>
                                            <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">End Date</div>
                                        </div>
                                    </div>
                                    <div style="font-size: 18px; font-weight: 700; color: #333; line-height: 1.4; padding-left: 52px;">
                                        ${formatDate(endDate)}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6" style="padding: 0 10px; margin-bottom: 20px;">
                                <div style="background: white; padding: 20px; border-radius: 8px; height: 100%; border-left: 5px solid #5bc0de; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s;">
                                    <div style="display: flex; align-items: center; margin-bottom: 12px;">
                                        <div style="width: 40px; height: 40px; background: rgba(91, 192, 222, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                            <i class="ri-calendar-check-fill" style="font-size: 22px; color: #5bc0de;"></i>
                                        </div>
                                        <div>
                                            <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">Total Days</div>
                                        </div>
                                    </div>
                                    <div style="font-size: 18px; font-weight: 700; color: #333; line-height: 1.4; padding-left: 52px;">
                                        ${calendarDays} calendar days
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                        </div>

                        <!-- Additional Info -->
                        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                            <h5 style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">
                                <i class="ri-settings-3-line" style="margin-right: 8px;"></i>Campaign Settings
                            </h5>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                <div>
                                    <div style="font-size: 12px; color: #999; margin-bottom: 5px;">Send Frequency</div>
                                    <div style="font-size: 14px; font-weight: 600; color: #333;">
                                        <i class="ri-refresh-line" style="margin-right: 5px; color: #5bc0de;"></i>
                                        ${sendFrequency.charAt(0).toUpperCase() + sendFrequency.slice(1)}
                                    </div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: #999; margin-bottom: 5px;">Weekend Sending</div>
                                    <div style="font-size: 14px; font-weight: 600; color: #333;">
                                        <i class="ri-${enableWeekends ? 'check' : 'close'}-circle-line" style="margin-right: 5px; color: ${enableWeekends ? '#5cb85c' : '#d9534f'};"></i>
                                        ${enableWeekends ? 'Enabled' : 'Disabled'}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Footer -->
                        <div style="margin-top: 20px; padding: 15px; background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%); border-radius: 8px; text-align: center;">
                            <div style="font-size: 14px; color: #666; line-height: 1.6;">
                                <i class="ri-information-line" style="margin-right: 5px; color: #667eea;"></i>
                                Your campaign will run from <strong style="color: #667eea;">${formatDate(startDate)}</strong>
                                ${endDate ? ` to <strong style="color: #764ba2;">${formatDate(endDate)}</strong>` : ''}
                                ${endDate ? `, spanning <strong>${calendarDays}</strong> calendar days with <strong>${durationDays}</strong> sending days.` : '.'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 15px 30px; background: white;">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="window.print()">
                            <i class="ri-printer-line"></i> Print Schedule
                        </button>
                    </div>
                </div>
            </div>
        `;

        modal.innerHTML = modalHTML;

        // Afficher le modal
        if (typeof jQuery !== 'undefined') {
            jQuery(modal).modal('show');
        }
    }

    /**
     * Affiche un résumé compact sous forme de badge
     */
    function displayCampaignSummary() {
        const startDateInput = document.querySelector('[name="campaign_form[startDate]"]');
        const durationDaysInput = document.querySelector('[name="campaign_form[durationDays]"]');
        const endDateField = document.querySelector('[name="campaign_form[endDate]"]');

        if (!startDateInput?.value || !durationDaysInput?.value) {
            return;
        }

        const endDate = endDateField?.value ? new Date(endDateField.value) : null;
        const durationDays = parseInt(durationDaysInput.value);

        // Mettre à jour ou créer le badge
        let badge = document.getElementById('schedule-summary-badge');

        if (!badge) {
            badge = document.createElement('div');
            badge.id = 'schedule-summary-badge';
            badge.style.cssText = 'margin-top: 8px; padding: 8px 12px; background: #d9edf7; border-left: 4px solid #31708f; border-radius: 4px; font-size: 13px; color: #31708f;';

            const durationField = durationDaysInput.closest('.form-group');
            if (durationField) {
                const button = document.getElementById('view-schedule-btn');
                if (button) {
                    button.parentNode.insertBefore(badge, button);
                }
            }
        }

        const formatDate = (date) => date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

        badge.innerHTML = `
            <i class="ri-calendar-check-line" style="margin-right: 5px;"></i>
            <strong>${durationDays} days</strong> campaign
            ${endDate ? ` • Ends ${formatDate(endDate)}` : ''}
        `;
    }

    /**
     * Initialisation
     */
    function initialize() {
        console.log('🚀 Campaign End Date Calculator initialized');

        // Créer le bouton pour afficher le popup
        createScheduleButton();

        // Écouter les changements sur les champs pertinents
        const fieldsToWatch = [
            '[name="campaign_form[startDate]"]',
            '[name="campaign_form[durationDays]"]',
            '[name="campaign_form[sendFrequency]"]',
            '[name="campaign_form[enableWeekends]"]'
        ];

        fieldsToWatch.forEach(selector => {
            const field = document.querySelector(selector);
            if (field) {
                field.addEventListener('change', () => {
                    autoCalculateEndDate();
                    displayCampaignSummary();
                });

                field.addEventListener('input', () => {
                    // Debounce pour les inputs
                    clearTimeout(field._debounceTimer);
                    field._debounceTimer = setTimeout(() => {
                        autoCalculateEndDate();
                        displayCampaignSummary();
                    }, 500);
                });
            }
        });

        // Calculer au chargement si les champs sont remplis
        setTimeout(() => {
            autoCalculateEndDate();
            displayCampaignSummary();
        }, 500);

        // Écouter l'événement de calcul du warmup
        document.addEventListener('warmupPlanCalculated', (e) => {
            console.log('Warmup plan calculated, updating end date...');
            autoCalculateEndDate();
            displayCampaignSummary();
        });

        // Avant la soumission du formulaire, s'assurer que end_date est à jour
        const form = document.getElementById('campaign-form');
        if (form) {
            form.addEventListener('submit', (e) => {
                autoCalculateEndDate();
                console.log('✅ End date updated before form submission');
            });
        }
    }

    // Exposer les fonctions pour utilisation externe
    window.CampaignEndDateCalculator = {
        calculateEndDate,
        calculateEndDateFromWarmupPlan,
        updateEndDateField,
        autoCalculateEndDate,
        displayCampaignSummary,
        showSchedulePopup,
        createScheduleButton
    };

    // Initialiser au chargement du DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

})();