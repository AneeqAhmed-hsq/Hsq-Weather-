jQuery(document).ready(function($) {
    var searchTimeout;
    var currentResults = [];
    
    // Initialize sortable
    if ($('#hsq-cities-sortable').length) {
        $('#hsq-cities-sortable').sortable({
            handle: '.drag-handle',
            update: function() {
                saveOrder();
            }
        });
        $('#hsq-cities-sortable').disableSelection();
    }
    
    // Save order function
    function saveOrder() {
        var order = [];
        $('#hsq-cities-sortable li').each(function(index) {
            order.push($(this).data('index'));
        });
        
        $.ajax({
            url: hsq_weather_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'hsq_weather_reorder_cities',
                nonce: hsq_weather_admin.nonce,
                order: order
            },
            success: function(response) {
                if (response.success) {
                    // Update data-index attributes
                    $('#hsq-cities-sortable li').each(function(newIndex) {
                        $(this).attr('data-index', newIndex);
                        $(this).find('input[type="hidden"]').each(function() {
                            var name = $(this).attr('name');
                            var newName = name.replace(/cities\[\d+\]/, 'cities[' + newIndex + ']');
                            $(this).attr('name', newName);
                        });
                    });
                    showMessage('Cities reordered successfully!', 'success');
                }
            }
        });
    }
    
    // City search with autocomplete
    $('#hsq-city-search').on('input', function() {
        var searchTerm = $(this).val();
        
        clearTimeout(searchTimeout);
        
        if (searchTerm.length < 2) {
            $('#hsq-search-results').hide().empty();
            return;
        }
        
        searchTimeout = setTimeout(function() {
            $.ajax({
                url: hsq_weather_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hsq_weather_search_city',
                    nonce: hsq_weather_admin.nonce,
                    search: searchTerm
                },
                beforeSend: function() {
                    $('#hsq-city-search').addClass('hsq-loading');
                },
                success: function(response) {
                    $('#hsq-city-search').removeClass('hsq-loading');
                    
                    if (response.success && response.data.length > 0) {
                        currentResults = response.data;
                        var html = '';
                        $.each(response.data, function(index, city) {
                            html += '<div class="hsq-search-result" data-lat="' + city.lat + '" data-lon="' + city.lon + '" data-name="' + city.name + '">';
                            html += '<span class="city-name">' + city.name + '</span>';
                            html += '</div>';
                        });
                        $('#hsq-search-results').html(html).show();
                    } else {
                        $('#hsq-search-results').html('<div class="hsq-search-result">No cities found</div>').show();
                    }
                },
                error: function() {
                    $('#hsq-city-search').removeClass('hsq-loading');
                    showMessage('Error searching cities', 'error');
                }
            });
        }, 500);
    });
    
    // Select search result
    $(document).on('click', '.hsq-search-result', function() {
        var cityName = $(this).data('name');
        var lat = $(this).data('lat');
        var lon = $(this).data('lon');
        
        $('#hsq-city-search').val(cityName);
        $('#hsq-search-results').hide();
        
        // Store selected city data
        $('#hsq-city-search').data('selected-city', {
            name: cityName,
            lat: lat,
            lon: lon
        });
    });
    
    // Hide search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#hsq-city-search, #hsq-search-results').length) {
            $('#hsq-search-results').hide();
        }
    });
    
    // Add city
    $('#hsq-add-city-btn').on('click', function() {
        var cityData = $('#hsq-city-search').data('selected-city');
        var cityName = $('#hsq-city-search').val();
        
        if (!cityData && cityName) {
            // If no selected city from search, try to add directly
            cityData = { name: cityName };
        }
        
        if (!cityData || !cityData.name) {
            showMessage('Please search and select a city first', 'error');
            return;
        }
        
        $.ajax({
            url: hsq_weather_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'hsq_weather_add_city',
                nonce: hsq_weather_admin.nonce,
                city_name: cityData.name
            },
            beforeSend: function() {
                $('#hsq-add-city-btn').prop('disabled', true).text('Adding...');
            },
            success: function(response) {
                $('#hsq-add-city-btn').prop('disabled', false).text('Add City');
                
                if (response.success) {
                    // Reload page to show new city
                    location.reload();
                } else {
                    showMessage(response.data, 'error');
                }
            },
            error: function() {
                $('#hsq-add-city-btn').prop('disabled', false).text('Add City');
                showMessage('Error adding city', 'error');
            }
        });
    });
    
    // Delete city
    $(document).on('click', '.hsq-delete-city', function() {
        if (!confirm(hsq_weather_admin.confirm_delete)) {
            return;
        }
        
        var $li = $(this).closest('li');
        var index = $li.data('index');
        
        $.ajax({
            url: hsq_weather_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'hsq_weather_delete_city',
                nonce: hsq_weather_admin.nonce,
                index: index
            },
            beforeSend: function() {
                $li.css('opacity', '0.5');
            },
            success: function(response) {
                if (response.success) {
                    $li.fadeOut(300, function() {
                        $(this).remove();
                        saveOrder();
                        showMessage('City deleted successfully!', 'success');
                    });
                } else {
                    $li.css('opacity', '1');
                    showMessage(response.data, 'error');
                }
            },
            error: function() {
                $li.css('opacity', '1');
                showMessage('Error deleting city', 'error');
            }
        });
    });
    
    // Show message function
    function showMessage(message, type) {
        var $existingMessage = $('.hsq-message');
        if ($existingMessage.length) {
            $existingMessage.remove();
        }
        
        var $message = $('<div class="hsq-message hsq-message-' + type + '">' + message + '</div>');
        $('.hsq-section:first').before($message);
        
        setTimeout(function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Theme preview (optional)
    $('#hsq_theme_preview').on('change', function() {
        var theme = $(this).val();
        $('.hsq-preview-card').removeClass('light dark').addClass(theme);
    });
});