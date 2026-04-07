jQuery(document).ready(function($) {
    var refreshTimer = null;
    var currentRefreshTime = parseInt($('.hsq-weather-container').data('refresh-time'));
    
    // Initialize
    initWeatherWidget();
    
    function initWeatherWidget() {
        // Set initial refresh timer
        if (currentRefreshTime > 0) {
            startRefreshTimer();
        }
        
        // Handle unit toggle
        $('.hsq-unit-btn').on('click', function() {
            var $btn = $(this);
            var unit = $btn.data('unit');
            
            if ($btn.hasClass('active')) {
                return;
            }
            
            // Update active state
            $('.hsq-unit-btn').removeClass('active');
            $btn.addClass('active');
            
            // Save preference in cookie (30 days)
            setCookie('hsq_weather_unit', unit, 30);
            
            // Refresh weather data with new unit
            refreshWeatherData(unit, null);
        });
        
        // Handle theme toggle
        $('.hsq-theme-btn').on('click', function() {
            var $btn = $(this);
            var theme = $btn.data('theme');
            
            if ($btn.hasClass('active')) {
                return;
            }
            
            // Update active state
            $('.hsq-theme-btn').removeClass('active');
            $btn.addClass('active');
            
            // Save preference in cookie (30 days)
            setCookie('hsq_weather_theme', theme, 30);
            
            // Update theme class
            $('.hsq-weather-container').removeClass('hsq-theme-light hsq-theme-dark').addClass('hsq-theme-' + theme);
            
            // AJAX call to update theme
            $.ajax({
                url: hsq_weather_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'hsq_weather_toggle_theme',
                    nonce: hsq_weather_ajax.nonce,
                    theme: theme
                }
            });
        });
        
        // Handle manual refresh
        $('.hsq-refresh-btn').on('click', function() {
            manualRefresh();
        });
    }
    
    function refreshWeatherData(unit, callback) {
        var $grid = $('.hsq-weather-grid');
        
        // Add loading animation to cards
        $('.hsq-weather-card').addClass('hsq-loading-card');
        
        $.ajax({
            url: hsq_weather_ajax.ajax_url,
            type: 'POST',
            data: {
                action: unit ? 'hsq_weather_toggle_unit' : 'hsq_weather_refresh',
                nonce: hsq_weather_ajax.nonce,
                unit: unit || ''
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.html) {
                        $grid.fadeOut(200, function() {
                            $grid.html(response.data.html).fadeIn(200);
                            if (callback) callback();
                        });
                    }
                    
                    // Reset timer on manual refresh
                    if (!unit && currentRefreshTime > 0) {
                        resetRefreshTimer();
                    }
                } else {
                    showNotification('Error refreshing weather data', 'error');
                }
            },
            error: function() {
                showNotification('Network error. Please try again.', 'error');
            },
            complete: function() {
                $('.hsq-weather-card').removeClass('hsq-loading-card');
            }
        });
    }
    
    function manualRefresh() {
        var $refreshBtn = $('.hsq-refresh-btn');
        $refreshBtn.css('transform', 'rotate(180deg)');
        
        refreshWeatherData(null, function() {
            setTimeout(function() {
                $refreshBtn.css('transform', '');
            }, 300);
        });
    }
    
    function startRefreshTimer() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }
        
        refreshTimer = setInterval(function() {
            // Only refresh if page is visible
            if (!document.hidden) {
                refreshWeatherData(null, null);
            }
        }, currentRefreshTime * 1000);
    }
    
    function resetRefreshTimer() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            startRefreshTimer();
        }
    }
    
    function setCookie(name, value, days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }
    
    function getCookie(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for(var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
    
    function showNotification(message, type) {
        var $notification = $('<div class="hsq-notification hsq-notification-' + type + '">' + message + '</div>');
        $('.hsq-weather-container').prepend($notification);
        
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Page visibility API - stop timer when page is hidden
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (refreshTimer) {
                clearInterval(refreshTimer);
                refreshTimer = null;
            }
        } else {
            if (currentRefreshTime > 0 && !refreshTimer) {
                startRefreshTimer();
            }
        }
    });
    
    // Add notification styles
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .hsq-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                z-index: 9999;
                animation: slideIn 0.3s ease;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .hsq-notification-success {
                background: #4caf50;
                color: white;
            }
            .hsq-notification-error {
                background: #f44336;
                color: white;
            }
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `)
        .appendTo('head');
});