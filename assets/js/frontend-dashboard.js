/**
 * UFSC Club Dashboard Frontend JavaScript
 * Enhanced interactive functionality for club dashboard
 */

(function($) {
    'use strict';

    var UfscDashboard = {
        // Configuration
        config: {
            club_id: null,
            ajax_url: '',
            nonce: '',
            cache_duration: 300000, // 5 minutes
            refresh_interval: 60000,  // 1 minute
        },

        // Cache for API responses
        cache: {},

        // Charts instances
        charts: {},

        // Initialize dashboard
        init: function() {
            // // UFSC: Use frontend vars and get club ID from current user
            this.config = $.extend(this.config, window.ufsc_frontend_vars || {});
            
            // Get club ID from dashboard configuration
            var dashboardConfig = window.ufsc_dashboard_vars || {};
            this.config.club_id = dashboardConfig.club_id || this.config.club_id;
            this.config.rest_url = dashboardConfig.rest_url || this.config.rest_url;
            
            if (!this.config.club_id) {
                console.warn('UFSC Dashboard: No club ID provided');
                return;
            }

            this.setupEventHandlers();
            this.loadInitialData();
            this.initializeCharts();
            this.startRefreshTimer();
        },

        // Setup event handlers
        setupEventHandlers: function() {
            var self = this;

            // // UFSC: Enhanced Action buttons
            $('#btn-ajouter-licence').on('click', function(e) {
                e.preventDefault();
                self.handleAddLicence();
            });

            $('#btn-mettre-a-jour-club').on('click', function(e) {
                e.preventDefault();
                self.handleUpdateClub();
            });

            $('#btn-televerser-document').on('click', function(e) {
                e.preventDefault();
                self.handleUploadDocument();
            });

            // // UFSC: Filters
            $('.ufsc-filter, .ufsc-filter-checkbox').on('change', function() {
                self.applyFilters();
            });

            // // UFSC: CSV Export
            $('#btn-export-csv').on('click', function(e) {
                e.preventDefault();
                self.handleExportCSV();
            });

            // Confirm deletion before submitting
            $(document).on('submit', '.ufsc-delete-licence-form', function(e) {
                if (!confirm('Êtes-vous sûr de vouloir supprimer cette licence ?')) {
                    e.preventDefault();
                }
            });

            $('#btn-importer-csv').on('click', function(e) {
                e.preventDefault();
                self.handleImportCSV();
            });

            $('#btn-exporter-selection').on('click', function(e) {
                e.preventDefault();
                self.handleExportSelection();
            });

            $(document).on('change', '#ufsc-select-all', function() {
                $('.ufsc-licence-select').prop('checked', $(this).is(':checked'));
            });

            $('#btn-generer-attestation').on('click', function(e) {
                e.preventDefault();
                self.handleGenerateAttestation();
            });

            $('#btn-configurer-club').on('click', function(e) {
                e.preventDefault();
                self.handleConfigureClub();
            });

            // Toast close buttons
            $(document).on('click', '.ufsc-toast-close', function() {
                $(this).closest('.ufsc-toast').fadeOut(300, function() {
                    $(this).remove();
                });
            });

            // Auto-refresh on page visibility change
            $(document).on('visibilitychange', function() {
                if (!document.hidden) {
                    self.refreshData();
                }
            });
        },

        // Load initial dashboard data
        loadInitialData: function() {
            this.loadKPIs();
            this.loadRecentLicences();
            this.loadDocumentsStatus();
            this.loadStatistics();
            this.loadNotifications();
            this.loadAuditLog();
            this.loadChartsData();
        },

        // Load KPI data with enhanced status tracking
        loadKPIs: function() {
            var self = this;
            var filters = this.getCurrentFilters();
            var cacheKey = 'kpis_' + this.config.club_id + '_' + JSON.stringify(filters);

            if (this.isCacheValid(cacheKey)) {
                this.updateKPIs(this.cache[cacheKey].data);
                return;
            }

            // // UFSC: Use REST API endpoint
            this.restRequest('dashboard/kpis', {
                filters: filters
            }, function(data) {
                self.cache[cacheKey] = {
                    data: data,
                    timestamp: Date.now()
                };
                self.updateKPIs(data);
            }, function() {
                self.showError('Erreur lors du chargement des KPI');
            });
        },

        // // UFSC: Update KPI displays according to new status structure
        updateKPIs: function(data) {
            $('#kpi-licences-validees').text(data.licences_validees || '-');
            $('#kpi-licences-payees').text(data.licences_payees || '-');
            $('#kpi-licences-attente').text(data.licences_attente || '-');
            $('#kpi-licences-rejected').text(data.licences_rejected || '-');

            var kpiGrid = $('#ufsc-kpi-grid');
            // Remove previously generated KPI cards for sex and age
            kpiGrid.find('.ufsc-kpi-card.-sexe, .ufsc-kpi-card.-age').remove();

            // Add KPI cards by sex
            if (data.sexe) {
                Object.keys(data.sexe).forEach(function(sex) {
                    var card = $('<div/>', { 'class': 'ufsc-card ufsc-kpi-card -sexe' });
                    card.append($('<div/>', { 'class': 'ufsc-kpi-value', text: data.sexe[sex] }));
                    card.append($('<div/>', { 'class': 'ufsc-kpi-label', text: sex }));
                    kpiGrid.append(card);
                });
            }

            // Add KPI cards by age range
            if (data.age) {
                Object.keys(data.age).forEach(function(range) {
                    var card = $('<div/>', { 'class': 'ufsc-card ufsc-kpi-card -age' });
                    card.append($('<div/>', { 'class': 'ufsc-kpi-value', text: data.age[range] }));
                    card.append($('<div/>', { 'class': 'ufsc-kpi-label', text: range }));
                    kpiGrid.append(card);
                });
            }
        },

        // // UFSC: Get current filter values
        getCurrentFilters: function() {
            return {
                periode: $('#filter-periode').val(),
                genre: $('#filter-genre').val(),
                role: $('#filter-role').val(),
                competition: $('#filter-competition').val()
            };
        },

        // // UFSC: Apply filters and refresh data
        applyFilters: function() {
            // Clear relevant cache entries
            var self = this;
            Object.keys(this.cache).forEach(function(key) {
                if (key.includes('kpis_') || key.includes('recent_licences_') || key.includes('stats_')) {
                    delete self.cache[key];
                }
            });
            
            // Reload data
            this.loadKPIs();
            this.loadRecentLicences();
            this.loadStatistics();
        },

        // // UFSC: Load recent licenses with actions
        loadRecentLicences: function() {
            var self = this;
            var filters = this.getCurrentFilters();
            filters.drafts_only = $('#filter-drafts').is(':checked') ? 1 : 0;
            var cacheKey = 'recent_licences_' + this.config.club_id + '_' + JSON.stringify(filters);

            if (this.isCacheValid(cacheKey)) {
                this.updateRecentLicences(this.cache[cacheKey].data);
                return;
            }

            this.restRequest('dashboard/recent-licences', {
                limit: 5,
                filters: filters
            }, function(data) {
                self.cache[cacheKey] = {
                    data: data,
                    timestamp: Date.now()
                };
                self.updateRecentLicences(data);
            }, function() {
                $('#ufsc-recent-licences').html('<div class="ufsc-error">Erreur lors du chargement</div>');
            });
        },

        // // UFSC: Update recent licenses display
        updateRecentLicences: function(licences) {
            var self = this;
            var container = $('#ufsc-recent-licences');
            
            if (!licences || licences.length === 0) {
                container.html('<p>Aucune licence récente</p>');
                return;
            }

            var html = '<table class="ufsc-table">';
            html += '<thead><tr><th>Nom</th><th>Rôle</th><th>Saison</th><th>Statut</th><th>Actions</th></tr></thead>';
            html += '<tbody>';
            
            licences.forEach(function(licence) {
                html += '<tr>';
                html += '<td>' + licence.prenom + ' ' + licence.nom + '</td>';
                html += '<td>' + (licence.role || 'Adhérent') + '</td>';
                var seasonLabel = licence.season_label || licence.saison || '';
                html += '<td>' + seasonLabel + '</td>';
                html += '<td>' + self.renderStatusBadge(licence.statut) + '</td>';
                html += '<td>' + self.renderLicenceActions(licence) + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            html += '<p class="ufsc-table-note">Utilisez le filtre « Afficher seulement les brouillons » pour ne voir que ces licences.</p>';
            container.html(html);
        },

        // // UFSC: Render status badge
        renderStatusBadge: function(statut) {
            var badges = {
                'brouillon': '<span class="ufsc-badge -draft">Brouillon</span>',
                'non_payee': '<span class="ufsc-badge -pending">En attente</span>',
                'payee': '<span class="ufsc-badge -pending">⏳ Payée (en cours)</span>',
                'validee': '<span class="ufsc-badge -ok">✅ Validée</span>',
                'rejected': '<span class="ufsc-badge -rejected">Refusée</span>'
            };
            return badges[statut] || '<span class="ufsc-badge">' + statut + '</span>';
        },

        // // UFSC: Render license actions based on status
        renderLicenceActions: function(licence) {
            var actions = [];
            var editableStatuses = ['brouillon', 'non_payee', 'rejected'];
            var deletableStatuses = ['brouillon', 'non_payee'];
            var adminPostUrl = this.config.ajax_url ? this.config.ajax_url.replace('admin-ajax.php', 'admin-post.php') : 'admin-post.php';

            // View action (always available)
            actions.push('<a href="?view_licence=' + licence.id + '" class="ufsc-btn ufsc-btn-small">Consulter</a>');

            // Edit action (conditionally available)
            if (editableStatuses.includes(licence.statut)) {
                actions.push('<a href="?edit_licence=' + licence.id + '" class="ufsc-btn ufsc-btn-small">Modifier</a>');
            }

            // Delete action (conditionally available)
            if (deletableStatuses.includes(licence.statut)) {
                actions.push(
                    '<form method="post" action="' + adminPostUrl + '" class="ufsc-delete-licence-form" style="display:inline">' +
                    '<input type="hidden" name="action" value="ufsc_delete_licence">' +
                    '<input type="hidden" name="licence_id" value="' + licence.id + '">' +
                    '<input type="hidden" name="_wpnonce" value="' + this.config.nonce + '">' +
                    '<button type="submit" class="ufsc-btn ufsc-btn-small ufsc-btn-danger">Supprimer</button>' +
                    '</form>'
                );
            }

            return '<div class="ufsc-row-actions">' + actions.join(' ') + '</div>';
        },

        // // UFSC: Load documents status
        loadDocumentsStatus: function() {
            var self = this;
            var cacheKey = 'documents_' + this.config.club_id;

            if (this.isCacheValid(cacheKey)) {
                this.updateDocumentsStatus(this.cache[cacheKey].data);
                return;
            }

            this.restRequest('dashboard/documents', {}, function(data) {
                self.cache[cacheKey] = {
                    data: data,
                    timestamp: Date.now()
                };
                self.updateDocumentsStatus(data);
            });
        },

        // // UFSC: Update documents status display
        updateDocumentsStatus: function(documents) {
            var docTypes = ['statuts', 'recepisse', 'jo', 'pv_ag', 'cer', 'attestation_cer'];
            
            docTypes.forEach(function(docType) {
                var item = $('.ufsc-document-item[data-doc="' + docType + '"]');
                var status = item.children('.ufsc-document-status');

                if (documents && documents[docType]) {
                    status.removeClass('-pending').addClass('-transmitted').text('✅');
                    item.addClass('-transmitted');
                } else {
                    status.removeClass('-transmitted').addClass('-pending').text('⏳');
                    item.removeClass('-transmitted');
                }
            });
        },

        // Load notifications
        loadNotifications: function() {
            var self = this;
            var cacheKey = 'notifications_' + this.config.club_id;

            if (this.isCacheValid(cacheKey)) {
                this.updateNotifications(this.cache[cacheKey].data);
                return;
            }

            this.apiRequest('get_club_notifications', {
                club_id: this.config.club_id
            }, function(data) {
                self.cache[cacheKey] = {
                    data: data,
                    timestamp: Date.now()
                };
                self.updateNotifications(data);
            }, function() {
                $('#ufsc-notifications').html('<div class="ufsc-loading">Erreur lors du chargement</div>');
            });
        },

        // Update notifications display
        updateNotifications: function(notifications) {
            var container = $('#ufsc-notifications');
            
            if (!notifications || notifications.length === 0) {
                container.html('<p>Aucune notification</p>');
                return;
            }

            var html = '';
            notifications.forEach(function(notification) {
                html += '<div class="ufsc-notification ' + notification.type + '">';
                html += '<span class="ufsc-notification-text">' + notification.message + '</span>';
                html += '</div>';
            });

            container.html(html);
        },

        // Load audit log
        loadAuditLog: function() {
            var self = this;
            var cacheKey = 'audit_' + this.config.club_id;

            if (this.isCacheValid(cacheKey)) {
                this.updateAuditLog(this.cache[cacheKey].data);
                return;
            }

            this.apiRequest('get_club_audit_log', {
                club_id: this.config.club_id,
                limit: 10
            }, function(data) {
                self.cache[cacheKey] = {
                    data: data,
                    timestamp: Date.now()
                };
                self.updateAuditLog(data);
            }, function() {
                $('#ufsc-audit-log').html('<div class="ufsc-loading">Erreur lors du chargement</div>');
            });
        },

        // Update audit log display
        updateAuditLog: function(entries) {
            var container = $('#ufsc-audit-log');
            
            if (!entries || entries.length === 0) {
                container.html('<p>Aucune activité récente</p>');
                return;
            }

            var html = '';
            entries.forEach(function(entry) {
                html += '<div class="ufsc-audit-entry">';
                html += '<span class="ufsc-audit-time">' + entry.time + '</span>';
                html += '<span class="ufsc-audit-action">' + entry.action + '</span>';
                html += '</div>';
            });

            container.html(html);
        },

        // Initialize charts
        initializeCharts: function() {
            this.initSexChart();
            this.initAgeChart();
            this.initPaymentsChart();
        },

        // Load charts data
        loadChartsData: function() {
            var self = this;
            
            this.apiRequest('get_club_stats', {
                club_id: this.config.club_id
            }, function(data) {
                self.updateCharts(data);
            }, function() {
                self.showError('Erreur lors du chargement des statistiques');
            });
        },

        // Initialize sex distribution chart
        initSexChart: function() {
            var ctx = document.getElementById('chart-sexe');
            if (!ctx) return;

            this.charts.sex = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Masculin', 'Féminin'],
                    datasets: [{
                        data: [0, 0],
                        backgroundColor: ['#3b82f6', '#ec4899'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },

        // Initialize age distribution chart
        initAgeChart: function() {
            var ctx = document.getElementById('chart-age');
            if (!ctx) return;

            this.charts.age = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['18-25', '26-35', '36-45', '46-60', '60+'],
                    datasets: [{
                        label: 'Licenciés',
                        data: [0, 0, 0, 0, 0],
                        backgroundColor: '#2271b1',
                        borderColor: '#135e96',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },

        // Initialize payments chart
        initPaymentsChart: function() {
            var ctx = document.getElementById('chart-paiements');
            if (!ctx) return;

            this.charts.payments = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Paiements',
                        data: [],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },

        // Update charts with new data
        updateCharts: function(data) {
            // Update sex chart
            if (this.charts.sex && data.sex_distribution) {
                this.charts.sex.data.datasets[0].data = [
                    data.sex_distribution.M || 0,
                    data.sex_distribution.F || 0
                ];
                this.charts.sex.update();
            }

            // Update age chart
            if (this.charts.age && data.age_distribution) {
                this.charts.age.data.datasets[0].data = [
                    data.age_distribution['18-25'] || 0,
                    data.age_distribution['26-35'] || 0,
                    data.age_distribution['36-45'] || 0,
                    data.age_distribution['46-60'] || 0,
                    data.age_distribution['60+'] || 0
                ];
                this.charts.age.update();
            }

            // Update payments chart
            if (this.charts.payments && data.payments_by_month) {
                this.charts.payments.data.labels = data.payments_by_month.labels || [];
                this.charts.payments.data.datasets[0].data = data.payments_by_month.data || [];
                this.charts.payments.update();
            }
        },

        // Action handlers
        handleNewLicence: function() {
            // Placeholder - integrate with licence creation
            this.showToast('Redirection vers la création de licence...', 'info');
        },

        handleImportCSV: function() {
            // Placeholder - integrate with CSV import
            this.showToast('Import CSV - fonctionnalité à venir', 'info');
        },

        handleExportSelection: function() {
            var selectedIds = [];
            $('.ufsc-licence-select:checked').each(function() {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length === 0) {
                this.showToast('Veuillez sélectionner au moins une licence.', 'warning');
                return;
            }

            var actionUrl = window.location.href.split('?')[0];
            var form = $('<form method="get" action="' + actionUrl + '">');
            form.append('<input type="hidden" name="ufsc_export" value="csv">');
            selectedIds.forEach(function(id) {
                form.append('<input type="hidden" name="ids[]" value="' + id + '">');
            });

            $('body').append(form);
            form.submit();
            form.remove();
        },

        handleGenerateAttestation: function() {
            // Placeholder - integrate with PDF generation
            this.showToast('Génération d\'attestation - fonctionnalité à venir', 'info');
        },

        handleConfigureClub: function() {
            // Placeholder - redirect to club configuration
            this.showToast('Redirection vers la configuration...', 'info');
        },

        // // UFSC: New action handlers
        handleAddLicence: function() {
            window.location.href = $('#btn-ajouter-licence').attr('href');
        },

        handleUpdateClub: function() {
            window.location.href = $('#btn-mettre-a-jour-club').attr('href');
        },

        handleUploadDocument: function() {
            window.location.href = $('#btn-televerser-document').attr('href');
        },

        // // UFSC: CSV Export with filters
        handleExportCSV: function() {
            var self = this;
            var filters = this.getCurrentFilters();
            
            this.showToast('Export en cours...', 'info');
            
            var form = $('<form/>', { method: 'post', action: this.config.ajax_url });
            form.append($('<input/>', { type: 'hidden', name: 'action', value: 'ufsc_export_stats' }));
            form.append($('<input/>', { type: 'hidden', name: 'nonce', value: this.config.nonce }));
            form.append($('<input/>', { type: 'hidden', name: 'club_id', value: this.config.club_id }));
            form.append($('<input/>', { type: 'hidden', name: 'filters', value: JSON.stringify(filters) }));
            
            $('body').append(form);
            form.submit();
            form.remove();
        },

        // // UFSC: Load statistics for detailed view
        loadStatistics: function() {
            var self = this;
            var filters = this.getCurrentFilters();
            var cacheKey = 'stats_' + this.config.club_id + '_' + JSON.stringify(filters);

            if (this.isCacheValid(cacheKey)) {
                this.updateStatistics(this.cache[cacheKey].data);
                return;
            }

            this.restRequest('dashboard/detailed-stats', {
                filters: filters
            }, function(data) {
                self.cache[cacheKey] = {
                    data: data,
                    timestamp: Date.now()
                };
                self.updateStatistics(data);
            });
        },

        // // UFSC: Update statistics displays
        updateStatistics: function(stats) {
            // Sex statistics
            if (stats.sexe) {
                var sexeHtml = '';
                Object.keys(stats.sexe).forEach(function(sex) {
                    var percentage = stats.sexe[sex].percentage || 0;
                    sexeHtml += '<div class="ufsc-stat-item">';
                    sexeHtml += '<span class="ufsc-stat-label">' + sex + '</span>';
                    sexeHtml += '<span class="ufsc-stat-value">' + percentage.toFixed(1) + '%</span>';
                    sexeHtml += '</div>';
                });
                $('#stats-sexe').html(sexeHtml);
            }

            // Age statistics
            if (stats.age) {
                var ageHtml = '';
                Object.keys(stats.age).forEach(function(tranche) {
                    var count = stats.age[tranche] || 0;
                    ageHtml += '<div class="ufsc-stat-item">';
                    ageHtml += '<span class="ufsc-stat-label">' + tranche + '</span>';
                    ageHtml += '<span class="ufsc-stat-value">' + count + '</span>';
                    ageHtml += '</div>';
                });
                $('#stats-age').html(ageHtml);
            }

            // Competition vs Leisure
            if (stats.competition) {
                var compHtml = '';
                compHtml += '<div class="ufsc-stat-item">';
                compHtml += '<span class="ufsc-stat-label">Compétition</span>';
                compHtml += '<span class="ufsc-stat-value">' + (stats.competition.competition || 0) + '</span>';
                compHtml += '</div>';
                compHtml += '<div class="ufsc-stat-item">';
                compHtml += '<span class="ufsc-stat-label">Loisir</span>';
                compHtml += '<span class="ufsc-stat-value">' + (stats.competition.loisir || 0) + '</span>';
                compHtml += '</div>';
                $('#stats-competition').html(compHtml);
            }

            // Roles
            if (stats.roles) {
                var rolesHtml = '';
                Object.keys(stats.roles).forEach(function(role) {
                    var count = stats.roles[role] || 0;
                    rolesHtml += '<div class="ufsc-stat-item">';
                    rolesHtml += '<span class="ufsc-stat-label">' + role + '</span>';
                    rolesHtml += '<span class="ufsc-stat-value">' + count + '</span>';
                    rolesHtml += '</div>';
                });
                $('#stats-roles').html(rolesHtml);
            }

            // Evolution
            if (stats.evolution) {
                var evolHtml = '';
                evolHtml += '<div class="ufsc-evolution-item">';
                evolHtml += '<span class="ufsc-evolution-label">Nouveaux brouillons</span>';
                evolHtml += '<span class="ufsc-evolution-value">' + (stats.evolution.nouveaux_brouillons || 0) + '</span>';
                evolHtml += '</div>';
                evolHtml += '<div class="ufsc-evolution-item">';
                evolHtml += '<span class="ufsc-evolution-label">Nouveaux payés</span>';
                evolHtml += '<span class="ufsc-evolution-value">' + (stats.evolution.nouveaux_payes || 0) + '</span>';
                evolHtml += '</div>';
                evolHtml += '<div class="ufsc-evolution-item">';
                evolHtml += '<span class="ufsc-evolution-label">Nouveaux validés</span>';
                evolHtml += '<span class="ufsc-evolution-value">' + (stats.evolution.nouveaux_valides || 0) + '</span>';
                evolHtml += '</div>';
                $('#stats-evolution').html(evolHtml);
            }

            // Alerts
            if (stats.alerts) {
                var alertsHtml = '';
                stats.alerts.forEach(function(alert) {
                    alertsHtml += '<div class="ufsc-alert ufsc-alert-' + alert.type + '">';
                    alertsHtml += '<span class="ufsc-alert-icon">⚠️</span>';
                    alertsHtml += '<span class="ufsc-alert-message">' + alert.message + '</span>';
                    alertsHtml += '</div>';
                });
                $('#stats-alerts').html(alertsHtml || '<p>Aucune alerte</p>');
            }
        },

        // Utility functions
        apiRequest: function(action, data, successCallback, errorCallback) {
            var requestData = $.extend({
                action: 'ufsc_dashboard_' + action,
                nonce: this.config.nonce
            }, data);

            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: requestData,
                success: function(response) {
                    if (response.success) {
                        successCallback(response.data);
                    } else {
                        errorCallback(response.data || 'Erreur inconnue');
                    }
                },
                error: function() {
                    errorCallback('Erreur de communication');
                }
            });
        },

        // // UFSC: REST API request method
        restRequest: function(endpoint, data, successCallback, errorCallback) {
            var url = this.config.rest_url + endpoint;
            var params = $.param(data || {});
            if (params) {
                url += '?' + params;
            }

            $.ajax({
                url: url,
                type: 'GET',
                beforeSend: function(xhr) {
                    var nonce = window.ufsc_frontend_vars && window.ufsc_frontend_vars.rest_nonce ? window.ufsc_frontend_vars.rest_nonce : '';
                    xhr.setRequestHeader('X-WP-Nonce', nonce);
                },
                success: function(response) {
                    successCallback(response);
                },
                error: function(xhr) {
                    var message = 'Erreur de communication';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    errorCallback(message);
                }
            });
        },

        // // UFSC: DELETE request for license deletion
        restDelete: function(endpoint, successCallback, errorCallback) {
            $.ajax({
                url: this.config.rest_url + endpoint,
                type: 'DELETE',
                beforeSend: function(xhr) {
                    var nonce = window.ufsc_frontend_vars && window.ufsc_frontend_vars.rest_nonce ? window.ufsc_frontend_vars.rest_nonce : '';
                    xhr.setRequestHeader('X-WP-Nonce', nonce);
                },
                success: function(response) {
                    successCallback(response);
                },
                error: function(xhr) {
                    var message = 'Erreur de communication';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    errorCallback(message);
                }
            });
        },

        isCacheValid: function(key) {
            if (!this.cache[key]) return false;
            return (Date.now() - this.cache[key].timestamp) < this.config.cache_duration;
        },

        refreshData: function() {
            // Clear cache to force refresh
            this.cache = {};
            this.loadInitialData();
        },

        startRefreshTimer: function() {
            var self = this;
            setInterval(function() {
                self.refreshData();
            }, this.config.refresh_interval);
        },

        showToast: function(message, type) {
            type = type || 'info';
            var toast = $('<div class="ufsc-toast ' + type + '">' + message + '</div>');
            $('#ufsc-toast-container').append(toast);

            // Auto remove after 5 seconds
            setTimeout(function() {
                toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        showError: function(message) {
            this.showToast(message, 'error');
        },

        showSuccess: function(message) {
            this.showToast(message, 'success');
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        UfscDashboard.init();
        var requestedSection = new URLSearchParams(window.location.search).get('ufsc_section');
        if (requestedSection === 'licences-archives') {
            var archives = document.getElementById('ufsc-licences-archives');
            var title = document.getElementById('ufsc-licences-archives-title');
            if (archives) {
                archives.scrollIntoView({behavior: 'smooth', block: 'start'});
                if (title) { title.setAttribute('tabindex', '-1'); title.focus({preventScroll: true}); }
            }
        }
        $('[data-ufsc-select-all]').on('click', function() { $('#ufsc-renewal-assistant-form .ufsc-renewal-checkbox:not(:disabled)').prop('checked', true).trigger('change'); });
        $('[data-ufsc-select-none]').on('click', function() { $('#ufsc-renewal-assistant-form .ufsc-renewal-checkbox').prop('checked', false).trigger('change'); });
        var renewalForm = $('#ufsc-renewal-assistant-form');
        if (renewalForm.length) {
            if (renewalForm.data('ufsc-renewal-initialized')) { return; }
            renewalForm.data('ufsc-renewal-initialized', true);
            renewalForm.addClass('ufsc-renewal-enhanced');
            var renewalError = function(message) {
                var notice = renewalForm.find('.ufsc-renewal-client-notice');
                if (!notice.length) { notice = $('<div class="ufsc-message ufsc-error ufsc-renewal-client-notice" role="alert" tabindex="-1"></div>').prependTo(renewalForm); }
                notice.text(message).focus();
            };
            var showRenewalStep = function(step, focusId) {
                renewalForm.attr('data-current-step', step);
                $('[data-ufsc-step-indicator]').removeAttr('aria-current').filter('[data-ufsc-step-indicator="' + step + '"]').attr('aria-current', 'step');
                $('[data-ufsc-step-actions], [data-ufsc-step-review]').prop('hidden', true).addClass('ufsc-is-hidden');
                $('[data-ufsc-step-actions="' + step + '"], [data-ufsc-step-review="' + step + '"]').prop('hidden', false).removeClass('ufsc-is-hidden');
                renewalForm.find('.ufsc-renewal-source-row').prop('hidden', step !== 1).toggleClass('ufsc-is-hidden', step !== 1);
                renewalForm.find('.ufsc-renewal-profile-row').each(function() {
                    var selected = renewalForm.find('.ufsc-renewal-checkbox[value="' + $(this).data('profile-id') + '"]').prop('checked');
                    $(this).prop('hidden', !(step === 2 && selected)).toggleClass('ufsc-is-hidden', !(step === 2 && selected));
                    if (step === 2 && selected) { $(this).find('details').prop('open', true).attr('aria-expanded', 'true'); }
                });
                renewalForm.find('.ufsc-renewal-table thead').prop('hidden', step !== 1).toggleClass('ufsc-is-hidden', step !== 1);
                renewalForm.find('.ufsc-front-table-scroll').prop('hidden', step === 3).toggleClass('ufsc-is-hidden', step === 3);
                if (step === 3) {
                    var review = renewalForm.find('[data-ufsc-step-review="3"] ul').empty();
                    renewalForm.find('.ufsc-renewal-checkbox:checked').each(function() {
                        var source = renewalForm.find('.ufsc-renewal-source-row[data-source-id="' + this.value + '"]');
                        $('<li>').text(source.find('td').eq(1).text().trim() + ' — quantité 1').appendTo(review);
                    });
                }
                var focus = focusId ? document.getElementById(focusId) : renewalForm.find('[data-ufsc-step-indicator="' + step + '"]')[0];
                if (focus) { focus.setAttribute('tabindex', '-1'); focus.focus(); focus.scrollIntoView({behavior: 'smooth', block: 'start'}); }
            };
            var updateRenewalCount = function() {
                var selected = renewalForm.find('.ufsc-renewal-checkbox:checked').length;
                var incomplete = renewalForm.find('.ufsc-renewal-source-row .ufsc-badge-warning').length;
                var blocked = renewalForm.find('.ufsc-renewal-checkbox:disabled').length;
                renewalForm.find('[data-ufsc-selection-count]').text(selected + ' sélectionnée(s), ' + incomplete + ' à compléter, ' + blocked + ' bloquée(s)');
                renewalForm.find('[data-ufsc-next-step="2"]').prop('disabled', selected === 0).attr('aria-disabled', selected === 0 ? 'true' : 'false');
            };
            renewalForm.on('change', '.ufsc-renewal-checkbox', updateRenewalCount);
            renewalForm.on('click', '[data-ufsc-renew-one]', function(event) {
                // JavaScript enhances the real fallback URL; without JS the link
                // reloads the assistant with the requested source selected.
                event.preventDefault();
                var id = String($(this).data('ufsc-renew-one'));
                renewalForm.find('.ufsc-renewal-checkbox[value="' + id + '"]').prop('checked', true);
                updateRenewalCount(); showRenewalStep(2, 'ufsc-renewal-profile-title-' + id);
            });
            renewalForm.on('click', '[data-ufsc-next-step]', function() {
                var step = Number($(this).data('ufsc-next-step'));
                if (step > 1 && !renewalForm.find('.ufsc-renewal-checkbox:checked').length) { renewalError('Sélectionnez au moins une licence.'); return; }
                if (step === 3) {
                    var firstInvalid = null;
                    renewalForm.find('.ufsc-renewal-checkbox:checked').each(function() {
                        var profile = renewalForm.find('.ufsc-renewal-profile-row[data-profile-id="' + this.value + '"]');
                        ['nom','prenom','email','date_naissance','sexe','adresse','ville','code_postal','fighter_level','poids'].forEach(function(field) {
                            var input = profile.find('[name$="[' + field + ']"]');
                            input.next('.ufsc-renewal-field-error').remove(); input.removeAttr('aria-invalid');
                            if (!String(input.val() || '').trim()) { input.attr('aria-invalid', 'true').after('<span class="ufsc-renewal-field-error" role="alert">Champ obligatoire.</span>'); firstInvalid = firstInvalid || input[0]; }
                            if (field === 'poids' && String(input.val() || '').trim()) {
                                var weight = Number(String(input.val()).replace(',', '.'));
                                if (!Number.isFinite(weight) || weight < 20 || weight > 300) { input.attr('aria-invalid', 'true').after('<span class="ufsc-renewal-field-error" role="alert">Le poids doit être compris entre 20 et 300 kg.</span>'); firstInvalid = firstInvalid || input[0]; }
                            }
                        });
                    });
                    if (firstInvalid) { firstInvalid.focus(); renewalError('Complétez les informations obligatoires avant de continuer.'); return; }
                }
                showRenewalStep(step);
            });
            renewalForm.on('toggle', 'details', function() { $(this).attr('aria-expanded', this.open ? 'true' : 'false'); });
            updateRenewalCount(); showRenewalStep(Number(renewalForm.attr('data-initial-step')) === 2 ? 2 : 1);
        }
        $('.ufsc-renewal-profile').each(function() {
            var profile = this;
            $(profile).find(':input').each(function() { $(this).data('ufsc-initial', this.type === 'checkbox' ? this.checked : this.value); });
            $(profile).on('input change', ':input', function() {
                var list = $(profile).find('.ufsc-renewal-change-summary ul').empty();
                $(profile).find(':input').each(function() {
                    var current = this.type === 'checkbox' ? this.checked : this.value;
                    if ( current !== $(this).data('ufsc-initial') && this.name.indexOf('confirm_identity_change') === -1 ) {
                        var label = $(this).closest('label').clone().children().remove().end().text().trim() || this.name;
                        $('<li>').text(label + ' — nouvelle valeur : ' + (this.type === 'checkbox' ? (current ? 'Oui' : 'Non') : current)).appendTo(list);
                    }
                });
                $(profile).find('.ufsc-renewal-change-summary').toggle(list.children().length > 0);
            });
            $(profile).find('.ufsc-renewal-change-summary').hide();
        });
    });

    // Expose to global scope for external access
    window.UfscDashboard = UfscDashboard;

})(jQuery);

// Club-logo preview is scoped to the profile upload controls only.
(function($){
    'use strict';
    $(function(){
        $('.ufsc-change-photo-form input[name="profile_photo"], .ufsc-upload-photo-form input[name="profile_photo"]').on('change', function(){
            var file=this.files&&this.files[0]; if(!file || !/^image\/(jpeg|png|webp)$/.test(file.type)) return;
            var form=$(this).closest('form'); var preview=form.siblings('.ufsc-club-logo').find('img');
            if(!preview.length) preview=$('<figure class="ufsc-club-logo ufsc-logo-preview"><img alt="Aperçu du nouveau logo"></figure>').insertBefore(form).find('img');
            var previous=preview.attr('src'); if(previous&&previous.indexOf('blob:')===0) URL.revokeObjectURL(previous);
            preview.attr('src',URL.createObjectURL(file));
        });
    });
})(jQuery);

// Accessible Compte Club tabs: inactive panels are removed from layout after enhancement.
(function($){
    'use strict';
    $(function(){
        var account=$('.ufsc-club-profile').first(); if(!account.length) return;
        var nav=account.find('.ufsc-club-account__nav').first();
        var form=account.find('form').filter(function(){return $(this).find('.ufsc-club-account__savebar').length;}).first();
        var panels={overview:account.find('.ufsc-profile-header, .ufsc-club-hero, .ufsc-profile-insight-band'),information:form.find('.ufsc-club-profile-main > .ufsc-card, #ufsc-club-information').not('#ufsc-club-officers, #ufsc-club-documents'),officers:form.find('#ufsc-club-officers'),documents:account.find('#ufsc-club-documents')};
        var links=nav.find('a'); account.addClass('ufsc-account-tabs-enhanced'); nav.attr('role','tablist');
        links.each(function(index){var key=['overview','information','officers','documents','archives','rules'][index]||'overview';$(this).attr({'role':'tab','data-account-tab':key,'aria-selected':'false'});});
        function show(key){
            if(!panels[key]||!panels[key].length) return false;
            $.each(panels,function(name,panel){panel.prop('hidden',name!==key);});
            links.attr({'aria-selected':'false'}).removeAttr('aria-current').filter('[data-account-tab="'+key+'"]').attr({'aria-selected':'true','aria-current':'page'});
            return true;
        }
        nav.on('click','[data-account-tab]',function(e){var key=$(this).data('account-tab');if(show(key)){e.preventDefault();history.replaceState(null,'',location.pathname+location.search+'#ufsc-account-'+key);}});
        var initial=(location.hash.match(/^#ufsc-account-(overview|information|officers|documents)$/)||[])[1]||'overview'; show(initial);
        var savebar=form.find('.ufsc-club-account__savebar').prop('hidden',true); var dirty=false;
        form.on('input change',':input',function(){dirty=true;savebar.prop('hidden',false);});
        form.on('submit',function(){if(!dirty)return;savebar.find(':submit').prop('disabled',true).attr('aria-disabled','true');});
        savebar.find('[data-ufsc-cancel]').on('click',function(){form[0].reset();dirty=false;savebar.prop('hidden',true);});
    });
})(jQuery);
