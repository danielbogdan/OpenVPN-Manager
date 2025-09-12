// Admin Dashboard Auto-Updates
// Automatically updates client user status without page refresh

(function() {
    'use strict';
    
    let updateInterval;
    let isPageVisible = true;
    let lastUpdateTime = null;
    
    // Function to update client user status
    function updateClientStatus() {
        if (!isPageVisible) {
            return; // Don't update if page is not visible
        }
        
        // Show updating indicator
        showUpdateIndicator();
        
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
        })
        .finally(() => {
            // Hide updating indicator
            hideUpdateIndicator();
        });
    }
    
    // Function to show update indicator
    function showUpdateIndicator() {
        let indicator = document.getElementById('update-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'update-indicator';
            indicator.innerHTML = '🔄';
            indicator.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: rgba(59, 130, 246, 0.9);
                color: white;
                padding: 8px 12px;
                border-radius: 20px;
                font-size: 14px;
                z-index: 1000;
                opacity: 0;
                transition: opacity 0.3s ease;
            `;
            document.body.appendChild(indicator);
        }
        indicator.style.opacity = '1';
    }
    
    // Function to hide update indicator
    function hideUpdateIndicator() {
        const indicator = document.getElementById('update-indicator');
        if (indicator) {
            indicator.style.opacity = '0';
            setTimeout(() => {
                if (indicator.parentNode) {
                    indicator.parentNode.removeChild(indicator);
                }
            }, 300);
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
        
        // Then update every 3 seconds
        updateInterval = setInterval(updateClientStatus, 3000);
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
