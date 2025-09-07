(function() {
    'use strict';

    function activateTab(tabs, tab, setFocus, dashboard) {
        tabs.forEach(function(t) {
            var controls = t.getAttribute('aria-controls');
            t.classList.remove('active');
            t.setAttribute('aria-selected', 'false');
            t.setAttribute('tabindex', '-1');
            if (controls) {
                var panel = dashboard.querySelector('#' + controls);
                if (panel) {
                    panel.classList.remove('active');
                    panel.setAttribute('hidden', '');
                }
            }
        });

        var controls = tab.getAttribute('aria-controls');
        tab.classList.add('active');
        tab.setAttribute('aria-selected', 'true');
        tab.setAttribute('tabindex', '0');
        if (setFocus) {
            tab.focus();
        }
        if (controls) {
            var panel = dashboard.querySelector('#' + controls);
            if (panel) {
                panel.classList.add('active');
                panel.removeAttribute('hidden');
            }
        }

        var section = tab.dataset.section;
        if (section) {
            var url = new URL(window.location);
            url.searchParams.set('tab', section);
            url.hash = 'tab=' + section;
            window.history.replaceState(null, '', url);
        }
    }

    function setupDashboard(dashboard) {
        var tabs = dashboard.querySelectorAll('.ufsc-nav-btn');
        if (!tabs.length) {
            return;
        }

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                activateTab(Array.from(tabs), tab, true, dashboard);
            });

            tab.addEventListener('keydown', function(e) {
                var key = e.key;
                var tabsArray = Array.from(tabs);
                var index = tabsArray.indexOf(tab);
                var newTab;
                switch (key) {
                    case 'ArrowLeft':
                    case 'ArrowUp':
                        newTab = tabsArray[index - 1] || tabsArray[tabsArray.length - 1];
                        e.preventDefault();
                        activateTab(tabsArray, newTab, true, dashboard);
                        break;
                    case 'ArrowRight':
                    case 'ArrowDown':
                        newTab = tabsArray[index + 1] || tabsArray[0];
                        e.preventDefault();
                        activateTab(tabsArray, newTab, true, dashboard);
                        break;
                    case 'Home':
                        e.preventDefault();
                        activateTab(tabsArray, tabsArray[0], true, dashboard);
                        break;
                    case 'End':
                        e.preventDefault();
                        activateTab(tabsArray, tabsArray[tabsArray.length - 1], true, dashboard);
                        break;
                }
            });
        });

        // Activate from URL
        var url = new URL(window.location);
        var section = url.searchParams.get('tab') || new URLSearchParams(location.hash.slice(1)).get('tab');
        var target = Array.from(tabs).find(function(t) { return t.dataset.section === section; });
        if (target) {
            activateTab(Array.from(tabs), target, false, dashboard);
        } else {
            activateTab(Array.from(tabs), tabs[0], false, dashboard);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.ufsc-club-dashboard').forEach(function(dashboard) {
            setupDashboard(dashboard);
        });
    });
})();
