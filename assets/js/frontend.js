/**
 * GuidePost Frontend JavaScript
 * Booking form functionality
 */

(function($) {
    'use strict';

    const GuidePostBooking = {
        // State
        state: {
            currentStep: 1,
            selectedService: null,
            selectedProvider: null,
            selectedDate: null,
            selectedTime: null,
            currentMonth: new Date(),
            availability: {}
        },

        // Elements
        $container: null,

        /**
         * Build REST API URL
         */
        apiUrl: function(endpoint) {
            return guidepost.rest_base + '?rest_route=/guidepost/v1/' + endpoint;
        },

        /**
         * Initialize
         */
        init: function() {
            this.$container = $('.guidepost-booking');

            if (!this.$container.length) {
                return;
            }

            this.bindEvents();
            this.initCalendar();

            // Check for pre-selected service/provider from shortcode attributes
            const preselectedService = this.$container.data('service');
            const preselectedProvider = this.$container.data('provider');

            if (preselectedService) {
                this.selectService(preselectedService);
            }

            if (preselectedProvider) {
                this.selectProvider(preselectedProvider);
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Service selection
            this.$container.on('click', '.guidepost-service-card', function() {
                const serviceId = $(this).data('service-id');
                self.selectService(serviceId);
            });

            // Provider selection
            this.$container.on('click', '.guidepost-provider-card', function() {
                const providerId = $(this).data('provider-id');
                self.selectProvider(providerId);
            });

            // Calendar navigation
            this.$container.on('click', '.guidepost-calendar-prev', function() {
                self.navigateMonth(-1);
            });

            this.$container.on('click', '.guidepost-calendar-next', function() {
                self.navigateMonth(1);
            });

            // Date selection
            this.$container.on('click', '.guidepost-calendar-day:not(.disabled):not(.empty)', function() {
                self.selectDate($(this).data('date'));
            });

            // Time slot selection
            this.$container.on('click', '.guidepost-time-slot', function() {
                self.selectTimeSlot($(this).data('time'), $(this).text());
            });

            // Back button
            this.$container.on('click', '.guidepost-btn-back', function() {
                self.goToStep(self.state.currentStep - 1);
            });

            // Book button
            this.$container.on('click', '.guidepost-btn-book', function() {
                self.submitBooking();
            });

            // Book another button
            this.$container.on('click', '.guidepost-btn-new', function() {
                self.reset();
            });
        },

        /**
         * Select service
         */
        selectService: function(serviceId) {
            this.state.selectedService = serviceId;

            // Update UI
            this.$container.find('.guidepost-service-card').removeClass('selected');
            this.$container.find('.guidepost-service-card[data-service-id="' + serviceId + '"]').addClass('selected');

            // Load providers for this service
            this.loadProviders(serviceId);
        },

        /**
         * Load providers for service
         */
        loadProviders: function(serviceId) {
            const self = this;

            this.setLoading(true);

            $.ajax({
                url: self.apiUrl('providers'),
                method: 'GET',
                data: {
                    service_id: serviceId
                },
                success: function(providers) {
                    self.renderProviders(providers);
                    self.setLoading(false);

                    // If only one provider, auto-select
                    if (providers.length === 1) {
                        self.selectProvider(providers[0].id);
                    } else {
                        self.goToStep(2);
                    }
                },
                error: function() {
                    self.setLoading(false);
                    self.showError('Failed to load providers');
                }
            });
        },

        /**
         * Render providers
         */
        renderProviders: function(providers) {
            const $grid = this.$container.find('.guidepost-providers-grid');
            $grid.empty();

            if (!providers.length) {
                $grid.html('<p class="guidepost-no-providers">No providers available for this service.</p>');
                return;
            }

            providers.forEach(function(provider) {
                const photoUrl = provider.photo_url || 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MCIgaGVpZ2h0PSI4MCIgdmlld0JveD0iMCAwIDgwIDgwIj48cmVjdCBmaWxsPSIjZGRkIiB3aWR0aD0iODAiIGhlaWdodD0iODAiLz48dGV4dCB4PSI1MCUiIHk9IjUwJSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk5OSIgZm9udC1zaXplPSIzMCI+PwYPL3RleHQ+PC9zdmc+';

                $grid.append(
                    '<div class="guidepost-provider-card" data-provider-id="' + provider.id + '">' +
                        '<img src="' + photoUrl + '" alt="' + provider.name + '" class="guidepost-provider-photo">' +
                        '<h4 class="guidepost-provider-name">' + provider.name + '</h4>' +
                    '</div>'
                );
            });
        },

        /**
         * Select provider
         */
        selectProvider: function(providerId) {
            this.state.selectedProvider = providerId;

            // Update UI
            this.$container.find('.guidepost-provider-card').removeClass('selected');
            this.$container.find('.guidepost-provider-card[data-provider-id="' + providerId + '"]').addClass('selected');

            // Go to date/time selection
            this.goToStep(3);
            this.renderCalendar();
        },

        /**
         * Initialize calendar
         */
        initCalendar: function() {
            this.state.currentMonth = new Date();
            this.state.currentMonth.setDate(1);
        },

        /**
         * Navigate month
         */
        navigateMonth: function(direction) {
            this.state.currentMonth.setMonth(this.state.currentMonth.getMonth() + direction);
            this.renderCalendar();
        },

        /**
         * Render calendar
         */
        renderCalendar: function() {
            const $days = this.$container.find('.guidepost-calendar-days');
            const $month = this.$container.find('.guidepost-calendar-month');

            const year = this.state.currentMonth.getFullYear();
            const month = this.state.currentMonth.getMonth();

            // Update header
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                               'July', 'August', 'September', 'October', 'November', 'December'];
            $month.text(monthNames[month] + ' ' + year);

            // Get first day and total days
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            $days.empty();

            // Empty cells for days before first day
            for (let i = 0; i < firstDay; i++) {
                $days.append('<div class="guidepost-calendar-day empty"></div>');
            }

            // Day cells
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                const dateStr = this.formatDate(date);
                let classes = 'guidepost-calendar-day';

                // Check if past
                if (date < today) {
                    classes += ' disabled';
                }

                // Check if today
                if (date.getTime() === today.getTime()) {
                    classes += ' today';
                }

                // Check if selected
                if (this.state.selectedDate === dateStr) {
                    classes += ' selected';
                }

                $days.append(
                    '<div class="' + classes + '" data-date="' + dateStr + '">' + day + '</div>'
                );
            }
        },

        /**
         * Select date
         */
        selectDate: function(dateStr) {
            this.state.selectedDate = dateStr;

            // Update UI
            this.$container.find('.guidepost-calendar-day').removeClass('selected');
            this.$container.find('.guidepost-calendar-day[data-date="' + dateStr + '"]').addClass('selected');

            // Load time slots
            this.loadTimeSlots(dateStr);
        },

        /**
         * Load time slots
         */
        loadTimeSlots: function(dateStr) {
            const self = this;
            const $container = this.$container.find('.guidepost-slots-container');

            $container.html('<p class="guidepost-loading-slots">Loading available times...</p>');

            $.ajax({
                url: self.apiUrl('availability'),
                method: 'GET',
                data: {
                    service_id: this.state.selectedService,
                    provider_id: this.state.selectedProvider,
                    date: dateStr
                },
                success: function(response) {
                    self.renderTimeSlots(response.slots);
                },
                error: function() {
                    $container.html('<p class="guidepost-no-slots">Failed to load available times.</p>');
                }
            });
        },

        /**
         * Render time slots
         */
        renderTimeSlots: function(slots) {
            const $container = this.$container.find('.guidepost-slots-container');
            $container.empty();

            if (!slots || !slots.length) {
                $container.html('<p class="guidepost-no-slots">No available times for this date.</p>');
                return;
            }

            slots.forEach(function(slot) {
                $container.append(
                    '<button type="button" class="guidepost-time-slot" data-time="' + slot.time + '">' +
                        slot.display +
                    '</button>'
                );
            });
        },

        /**
         * Select time slot
         */
        selectTimeSlot: function(time, display) {
            this.state.selectedTime = time;
            this.state.selectedTimeDisplay = display;

            // Update UI
            this.$container.find('.guidepost-time-slot').removeClass('selected');
            this.$container.find('.guidepost-time-slot[data-time="' + time + '"]').addClass('selected');

            // Go to customer info
            this.goToStep(4);
            this.updateSummary();
        },

        /**
         * Update booking summary
         */
        updateSummary: function() {
            const $summary = this.$container.find('.guidepost-summary-details');
            const selectedServiceCard = this.$container.find('.guidepost-service-card[data-service-id="' + this.state.selectedService + '"]');
            const serviceName = selectedServiceCard.find('.guidepost-service-name').text();
            const serviceDuration = selectedServiceCard.find('.guidepost-service-duration').text();
            const servicePrice = selectedServiceCard.find('.guidepost-service-price').text();

            const selectedProviderCard = this.$container.find('.guidepost-provider-card[data-provider-id="' + this.state.selectedProvider + '"]');
            const providerName = selectedProviderCard.find('.guidepost-provider-name').text();

            const dateDisplay = this.formatDateDisplay(this.state.selectedDate);

            let html = '<div class="guidepost-summary-row">' +
                           '<span class="guidepost-summary-label">Service:</span>' +
                           '<span class="guidepost-summary-value">' + serviceName + '</span>' +
                       '</div>' +
                       '<div class="guidepost-summary-row">' +
                           '<span class="guidepost-summary-label">Provider:</span>' +
                           '<span class="guidepost-summary-value">' + providerName + '</span>' +
                       '</div>' +
                       '<div class="guidepost-summary-row">' +
                           '<span class="guidepost-summary-label">Date:</span>' +
                           '<span class="guidepost-summary-value">' + dateDisplay + '</span>' +
                       '</div>' +
                       '<div class="guidepost-summary-row">' +
                           '<span class="guidepost-summary-label">Time:</span>' +
                           '<span class="guidepost-summary-value">' + this.state.selectedTimeDisplay + '</span>' +
                       '</div>' +
                       '<div class="guidepost-summary-row">' +
                           '<span class="guidepost-summary-label">Duration:</span>' +
                           '<span class="guidepost-summary-value">' + serviceDuration + '</span>' +
                       '</div>';

            if (servicePrice) {
                html += '<div class="guidepost-summary-row guidepost-summary-total">' +
                            '<span class="guidepost-summary-label">Total:</span>' +
                            '<span class="guidepost-summary-value">' + servicePrice + '</span>' +
                        '</div>';
            }

            $summary.html(html);
        },

        /**
         * Submit booking
         */
        submitBooking: function() {
            const self = this;
            const $form = this.$container.find('.guidepost-customer-form');

            // Validate form
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            this.setLoading(true);

            const data = {
                service_id: this.state.selectedService,
                provider_id: this.state.selectedProvider,
                date: this.state.selectedDate,
                time: this.state.selectedTime,
                first_name: $form.find('[name="first_name"]').val(),
                last_name: $form.find('[name="last_name"]').val(),
                email: $form.find('[name="email"]').val(),
                phone: $form.find('[name="phone"]').val(),
                notes: $form.find('[name="notes"]').val()
            };

            $.ajax({
                url: self.apiUrl('bookings'),
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', guidepost.nonce);
                },
                success: function(response) {
                    self.setLoading(false);
                    if (response.success) {
                        // Check if payment is required (WooCommerce)
                        if (response.requires_payment && response.checkout_url) {
                            // Redirect to WooCommerce checkout
                            window.location.href = response.checkout_url;
                        } else {
                            self.showConfirmation(response);
                        }
                    } else {
                        self.showError(response.message || 'Booking failed');
                    }
                },
                error: function(xhr) {
                    self.setLoading(false);
                    const message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Booking failed. Please try again.';
                    self.showError(message);
                }
            });
        },

        /**
         * Show confirmation
         */
        showConfirmation: function(response) {
            // Copy summary to confirmation
            const summaryHtml = this.$container.find('.guidepost-summary-details').html();
            this.$container.find('.guidepost-confirmation-details').html(summaryHtml);

            this.goToStep(5);
        },

        /**
         * Go to step
         */
        goToStep: function(step) {
            this.state.currentStep = step;

            this.$container.find('.guidepost-step').removeClass('active');
            this.$container.find('.guidepost-step[data-step="' + step + '"]').addClass('active');
        },

        /**
         * Reset form
         */
        reset: function() {
            this.state = {
                currentStep: 1,
                selectedService: null,
                selectedProvider: null,
                selectedDate: null,
                selectedTime: null,
                currentMonth: new Date(),
                availability: {}
            };

            // Reset UI
            this.$container.find('.selected').removeClass('selected');
            this.$container.find('.guidepost-customer-form')[0].reset();

            this.goToStep(1);
        },

        /**
         * Set loading state
         */
        setLoading: function(loading) {
            if (loading) {
                this.$container.addClass('loading');
            } else {
                this.$container.removeClass('loading');
            }
        },

        /**
         * Show error
         */
        showError: function(message) {
            alert(message); // TODO: Replace with better UI
        },

        /**
         * Format date to YYYY-MM-DD
         */
        formatDate: function(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        },

        /**
         * Format date for display
         */
        formatDateDisplay: function(dateStr) {
            const date = new Date(dateStr + 'T00:00:00');
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        GuidePostBooking.init();
    });

})(jQuery);
