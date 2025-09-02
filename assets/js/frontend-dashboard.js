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
            this.config = $.extend(this.config, window.ufsc_dashboard_vars || {});
            
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

            // Action buttons
            $('#btn-nouvelle-licence').on('click', function(e) {
                e.preventDefault();
                self.handleNewLicence();
            });

            $('#btn-importer-csv').on('click', function(e) {
                e.preventDefault();
                self.handleImportCSV();
            });

            $('#btn-exporter-selection').on('click', function(e) {
                e.preventDefault();
                self.handleExportSelection();
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
            this.loadNotifications();
            this.loadAuditLog();
            this.loadChartsData();
        },

        // Load KPI data
        loadKPIs: function() {
            var self = this;
            var cacheKey = 'kpis_' + this.config.club_id;

            if (this.isCacheValid(cacheKey)) {
                this.updateKPIs(this.cache[cacheKey]);
                return;
            }

            this.apiRequest('get_club_kpis', {
                club_id: this.config.club_id
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

        // Update KPI displays
        updateKPIs: function(data) {
            $('#kpi-licences-total').text(data.licences_total || '-');
            $('#kpi-licences-validees').text(data.licences_validees || '-');
            $('#kpi-licences-attente').text(data.licences_attente || '-');
            $('#kpi-licences-expirees').text(data.licences_expirees || '-');
            $('#kpi-paiements-a-payer').text(data.paiements_a_payer || '-');
            $('#kpi-paiements-payes').text(data.paiements_payes || '-');
            $('#kpi-documents').text(data.documents_complets || '-');
        },

        // Load notifications
        loadNotifications: function() {
            var self = this;
            var cacheKey = 'notifications_' + this.config.club_id;

            if (this.isCacheValid(cacheKey)) {
                this.updateNotifications(this.cache[cacheKey]);
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
                this.updateAuditLog(this.cache[cacheKey]);
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
            // Placeholder - integrate with export
            this.showToast('Export - fonctionnalité à venir', 'info');
        },

        handleGenerateAttestation: function() {
            // Placeholder - integrate with PDF generation
            this.showToast('Génération d\'attestation - fonctionnalité à venir', 'info');
        },

        handleConfigureClub: function() {
            // Placeholder - redirect to club configuration
            this.showToast('Redirection vers la configuration...', 'info');
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
    });

    // Expose to global scope for external access
    window.UfscDashboard = UfscDashboard;

})(jQuery);