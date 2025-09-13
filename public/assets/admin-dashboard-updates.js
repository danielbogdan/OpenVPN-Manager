// Admin Dashboard Auto-Updates
// Automatically updates client user status without page refresh

(function() {
    'use strict';
    
    let updateInterval;
    let isPageVisible = true;
    let lastUpdateTime = null;
    
    // Function to update client user status and session data
    function updateClientStatus() {
        if (!isPageVisible) {
            return; // Don't update if page is not visible
        }
        
        
        // Update client status
        fetch('/actions/get_client_status.php', {
            method: 'GET',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateClientUserElements(data.client_users);
                lastUpdateTime = data.timestamp;
                console.log('Client status updated:', data.timestamp);
            } else {
                console.error('Failed to update client status:', data.error);
            }
        })
        .catch(error => {
            console.error('Error updating client status:', error);
        });
        
        // Update session data for all tenants
        updateAllTenantSessions();
        
        // Update global map
        if (typeof updateGlobalMap === 'function') {
            updateGlobalMap();
        }
    }
    
    // Function to update session data for all tenants
    function updateAllTenantSessions() {
        // Get all tenant IDs from the page
        const tenantElements = document.querySelectorAll('[data-tenant-id]');
        const tenantIds = Array.from(tenantElements).map(el => el.getAttribute('data-tenant-id')).filter((id, index, arr) => arr.indexOf(id) === index);
        
        console.log(`Updating sessions for ${tenantIds.length} tenants:`, tenantIds);
        
        tenantIds.forEach(tenantId => {
            fetch(`/actions/get_live_sessions.php?tenant_id=${tenantId}`, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-cache'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    updateTenantSessionElements(tenantId, data.sessions, data.stats);
                    console.log(`Updated sessions for tenant ${tenantId}:`, data.sessions.length, 'sessions');
                } else {
                    console.error(`Failed to update sessions for tenant ${tenantId}:`, data.error);
                }
            })
            .catch(error => {
                console.error(`Error updating sessions for tenant ${tenantId}:`, error);
            });
        });
    }
    
    // Function to update tenant session elements
    function updateTenantSessionElements(tenantId, sessions, stats) {
        // Update session count in tenant cards
        const tenantCard = document.querySelector(`[data-tenant-id="${tenantId}"]`);
        if (tenantCard) {
            const sessionCountElement = tenantCard.querySelector('.session-count');
            if (sessionCountElement) {
                sessionCountElement.textContent = `${sessions.length} active`;
            }
            
            // Update session list
            const sessionList = tenantCard.querySelector('.session-list');
            if (sessionList) {
                if (sessions.length === 0) {
                    sessionList.innerHTML = '<div class="no-sessions">No active sessions</div>';
                } else {
                    sessionList.innerHTML = sessions.map(session => `
                        <div class="session-item">
                            <span class="session-user">${session.common_name}</span>
                            <span class="session-ip">${session.virtual_address || 'N/A'}</span>
                            <span class="session-location">${session.geo_country || 'Unknown'}</span>
                        </div>
                    `).join('');
                }
            }
        }
    }
    
    
    // Function to update the DOM elements with new status
    function updateClientUserElements(clientUsers) {
        clientUsers.forEach(user => {
            // Find the client user element by username and tenant
            const clientUserElement = document.querySelector(
                `[data-client-username="${user.username}"][data-tenant-id="${user.tenant_id}"]`
            );
            
            if (clientUserElement) {
                const statusBadge = clientUserElement.querySelector('.client-status-badge');
                if (statusBadge) {
                    // Update status badge
                    if (user.is_active) {
                        statusBadge.className = 'client-status-badge status-active';
                        statusBadge.textContent = '🟢 Online';
                    } else {
                        statusBadge.className = 'client-status-badge status-inactive';
                        statusBadge.textContent = '⚪ Offline';
                    }
                    
                    // Add a subtle animation to show the update
                    statusBadge.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        statusBadge.style.transform = 'scale(1)';
                    }, 200);
                }
                
                // Update login information
                const loginTimeElement = clientUserElement.querySelector('.login-time');
                const loginIpElement = clientUserElement.querySelector('.login-ip');
                
                if (loginTimeElement && user.last_login_formatted) {
                    loginTimeElement.textContent = `Last login: ${user.last_login_formatted}`;
                }
                
                if (loginIpElement && user.last_login_ip) {
                    loginIpElement.textContent = `from ${user.last_login_ip}`;
                }
            }
        });
    }
    
    // Function to start auto-updates
    function startAutoUpdates() {
        // Update immediately
        updateClientStatus();
        
        // Then update every 2 seconds for more responsive updates
        updateInterval = setInterval(updateClientStatus, 2000);
    }
    
    // Function to stop auto-updates
    function stopAutoUpdates() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }
    
    // Handle page visibility changes
    document.addEventListener('visibilitychange', function() {
        isPageVisible = !document.hidden;
        
        if (isPageVisible) {
            // Page became visible, start updates
            startAutoUpdates();
        } else {
            // Page became hidden, stop updates
            stopAutoUpdates();
        }
    });
    
    // Handle page unload
    window.addEventListener('beforeunload', function() {
        stopAutoUpdates();
    });
    
    // Start updates when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startAutoUpdates);
    } else {
        startAutoUpdates();
    }
    
    // Make functions available globally for manual control
    window.adminDashboardUpdates = {
        start: startAutoUpdates,
        stop: stopAutoUpdates,
        update: updateClientStatus,
        getLastUpdateTime: () => lastUpdateTime
    };
    
})();
