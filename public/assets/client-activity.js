// Client Activity Tracker
// Updates user activity every 5 seconds to track online status

(function() {
    'use strict';
    
    let activityInterval;
    let isPageVisible = true;
    
    // Function to update activity
    function updateActivity() {
        if (!isPageVisible) {
            return; // Don't update if page is not visible
        }
        
        fetch('/actions/update_client_activity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Activity updated:', data.timestamp);
            } else {
                console.error('Failed to update activity:', data.error);
            }
        })
        .catch(error => {
            console.error('Error updating activity:', error);
        });
    }
    
    // Function to start activity tracking
    function startActivityTracking() {
        // Update immediately
        updateActivity();
        
        // Then update every 5 seconds
        activityInterval = setInterval(updateActivity, 5000);
    }
    
    // Function to stop activity tracking
    function stopActivityTracking() {
        if (activityInterval) {
            clearInterval(activityInterval);
            activityInterval = null;
        }
    }
    
    // Handle page visibility changes
    document.addEventListener('visibilitychange', function() {
        isPageVisible = !document.hidden;
        
        if (isPageVisible) {
            // Page became visible, start tracking
            startActivityTracking();
        } else {
            // Page became hidden, stop tracking
            stopActivityTracking();
        }
    });
    
    // Handle page unload (logout, close, navigate away)
    window.addEventListener('beforeunload', function() {
        stopActivityTracking();
    });
    
    // Start tracking when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startActivityTracking);
    } else {
        startActivityTracking();
    }
    
    // Make functions available globally for manual control
    window.clientActivity = {
        start: startActivityTracking,
        stop: stopActivityTracking,
        update: updateActivity
    };
    
})();
